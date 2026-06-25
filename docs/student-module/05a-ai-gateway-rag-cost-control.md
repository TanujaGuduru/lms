# Delivery Phase 5a — AI Workflows: Gateway Architecture, RAG Retrieval, Cost Control

Covers the cross-cutting AI infrastructure every feature from Phases 3e/3i/3f/3g/3h relies on: the AI Gateway abstraction, RAG retrieval mechanics, model selection/tiering, quota/cost enforcement, and the safety/moderation pipeline. Feature-specific prompt design (Doubt Solver modes, Coding Assistant, Notebook AI, risk-narrative generation, parent-report generation, course recommendations) is deliberately deferred to `05b` onward — this document is the shared plumbing every one of those calls through, written once rather than re-specified per feature.

---

## 1. AI Gateway

**It's a Laravel service class, not a separately deployed microservice** — `AiGateway::complete(...)` / `AiGateway::stream(...)`, called in-process by whichever feature needs it. A standalone network service would buy nothing here and would cost a deploy/scaling unit for no benefit, which is exactly the "without paying the full distributed-systems tax everywhere else" tradeoff `01`'s System Landscape section already commits to for the parts of this platform that *don't* need their own isolation boundary (unlike the sandbox or Agora, which genuinely do).

**Responsibilities, all centralized here rather than scattered per-feature** (the actual point of having a Gateway at all, per `01`'s explicit reasoning):
- Provider/model selection per call (§3 below)
- Prompt template loading + versioning (so a prompt change is a deploy of a template, not a code change scattered across features)
- The moderation pipeline (§5)
- Quota check-and-reserve (§4)
- Streaming (SSE) plumbing
- Cost/token accounting written onto `ai_messages` in the same operation that persists the response
- Provider failover

### Hosting duality (ties back to `01`/`01b`)
On AWS, the Gateway's primary transport is **Bedrock** (keeps inference inside the AWS network boundary — the compliance reasoning `01` §8 already states, given the user base includes minors). On a GoDaddy-only deployment, the **same Gateway interface** instead calls the Anthropic/OpenAI HTTPS APIs directly (`01b`'s documented path) — a single config flag selects the transport; no feature code ever branches on which hosting environment it's running in. This is the concrete mechanism behind `01`'s "a model/provider change is a config change, not a rewrite" claim — it has to be true at the *transport* level too, not just the model-name level.

### Failover
A call failing or rate-limited against the configured primary provider/model retries **once** against that route's configured fallback before surfacing failure to the calling feature — invisible to the feature code (`03e`'s explicit requirement). Both attempts are logged (the failed one included) so a pattern of primary-provider degradation is visible in ops monitoring, not just silently absorbed.

### Prompt versioning
Every system-prompt template is stored with a version identifier (an `ai_prompt_versions` config/table — name, version, template text, active-from date). `ai_messages.model_used` records a composite like `claude-sonnet@doubt_solver_hint_v4` rather than just the bare model name — so "did response quality change?" can be answered against *which prompt version* was active, not just which model, since both move independently over a product's life.

---

## 2. RAG Retrieval Mechanics

**Embedding pipeline runs at publish-time, not per-query.** When a lesson/material is published or a class transcript becomes ready (`04c`), a queue job chunks and embeds the content once, written into Pinecone under that course's namespace (`01` §5's namespacing decision — retrieval never crosses a course boundary, so a Python course question can't surface Java curriculum content). Editing a lesson re-embeds **only that lesson's chunks**, not the whole course — keeps re-embedding cost bounded to what actually changed.

**At query time** (Doubt Solver / Coding Assistant / "AI summarize"): the incoming question is embedded once, top-K chunks are retrieved filtered to `linked_course_id`'s namespace (and further narrowed to `linked_lesson_id` when set, for a tighter, more relevant context window), and those chunks are injected into the prompt as context — **never shown verbatim to the student as "the answer."** `ai_messages.rag_sources` logs which chunks were retrieved (already in the `02d` schema) for audit/debugging and for teacher-review tooling; the student-facing surface, at most, gets a lesson-level citation ("this references Lesson 4.2"), never the raw retrieved text — showing the actual source paragraph would partly defeat the teach-don't-spoon-feed intent for hint mode specifically, so the rule is applied uniformly rather than mode-by-mode.

**Backend swap stays interface-stable**: a `VectorStore` abstraction (`embed()`/`query()`/`upsert()`) sits between the Gateway and Pinecone, so the documented pgvector-on-Aurora cost-optimization path (`01`/`02e`) is a config swap behind that interface, not a rewrite of every feature that does retrieval.

---

## 3. Model Selection / Tiering

A config-driven routing table — `{feature, mode} → {provider, model, fallback_model, max_tokens}` — realizes `01`'s "smaller/faster for hint, larger for deeper explain" decision concretely, rather than leaving it as a stated intention with no enforcement mechanism:

| Feature.mode | Tier | Why | Latency target |
|---|---|---|---|
| `doubt_solver.hint` | Fast/small | Short, templated nudge — speed matters more than depth | <3s first token (`01`'s stated SLA) |
| `doubt_solver.explain` | Deep/large | Needs real conceptual reasoning | First token still fast (streamed); full response budget looser |
| `doubt_solver.practice` | Fast/small | Generating an analogous problem from a template, not deep reasoning | <3s first token |
| `coding_assistant.debug` / `.review` | Deep/large | Code reasoning + the sandbox-verification loop (`04d`) needs a model that gets it right more often, not just fast | <8s for full review (`01`'s stated SLA) |
| `notebook.summarize` / `.flashcards` / `.quiz_generation` | Deep/large | Quality of the extracted content matters more than speed; not on a synchronous user-waiting path the same way chat is | No hard SLA — async-tolerant |
| `risk_narrative` / `parent_report.summary` | Deep/large | Written for a parent to read; this is the one place quality of prose matters most | Generated by a nightly/monthly batch job — no latency SLA at all |
| `moderation.precheck` | Fast/small (or rules-based) | Runs before every other call; has to be cheap enough to run on every single message without becoming the actual bottleneck | Sub-second |

This table is config, not a fact baked into application code — a tier can be repointed at a different model without touching a single feature's code, which is the entire reason the Gateway owns routing instead of each feature picking its own model.

---

## 4. Cost Control & Quota Enforcement

`ai_usage_quotas` (`02d`) is checked **before** any provider call is made — already established at the API contract level in `04d`; this section specifies the actual mechanism, since "check before calling" alone isn't enough to be correct under concurrency.

**Atomic check-and-reserve, not check-then-call:** a burst of near-simultaneous requests from the same student (e.g., a flaky client retrying) must not all pass a quota check against the same stale "messages_used" read and then all proceed. The Gateway does this as a single atomic increment against the period row (`UPDATE ai_usage_quotas SET messages_used = messages_used + 1 WHERE ... AND messages_used < quota_limit_messages`, checking affected-row-count) — a request that loses the race gets the same `429` `quota_exhausted` response `04d` already specifies, before any tokens are spent calling a provider. `tokens_used`/`cost_usd_used` reconcile to the *actual* provider response after the call completes (the reservation only ever protects the message-count gate up front; token/cost accounting is necessarily after-the-fact since the real usage isn't known until the response exists).

**Platform-wide circuit breaker**, beyond any individual student's quota: a configured daily total-spend cap that pages ops if approached — protects against a runaway bug (an infinite retry loop, a prompt that somehow generates a 50k-token response repeatedly) rather than just individual overuse, which per-student quotas alone don't catch.

**Cost attribution**: `ai_messages.cost_usd` is computed from the provider response's actual reported token usage, multiplied by that model's per-token rate from a small rate-table config (updated as provider pricing changes) — never a hardcoded estimate baked into application logic, since pricing is exactly the kind of thing that changes on a schedule the app's release cycle doesn't control.

---

## 5. Safety, Moderation, and Age-Awareness

**Two moderation passes, not one**, because a single check point can't satisfy both "block obviously bad input before spending tokens" and "don't let streaming begin on something that turns out problematic":
1. **Input precheck** (fast/cheap model or rules) runs on the student's message before the main model is ever called — catches blatant misuse cheaply, before any real generation cost is incurred.
2. **Output moderation runs in parallel with streaming**, against the rolling output buffer, not after the full response is assembled — waiting for the complete response before checking it would defeat the whole point of streaming and blow past the `<3s` first-token SLA. A trigger mid-stream **aborts the stream** with a short, age-appropriate closing message rather than the response just stopping inexplicably; the full attempted generation is still logged for review, since "the model started saying something and got cut off" is itself a useful signal, not noise to discard.

This is the concrete mechanism behind `03e`'s "a content-moderation pass... runs before the response is shown" — "before it's shown" means before the *triggering portion* reaches the student, which for a streamed response is enforced chunk-by-chunk, not as a single gate at the start.

**Age/persona-aware system prompts**: `ai_profiles.persona_settings` (tone, reading-level band) and `ai_conversations.language` are resolved into the system prompt **once, at conversation start** — not re-resolved per message, since neither value changes mid-conversation and re-resolving on every turn would just be wasted work for an identical result.

**Near-duplicate detection, made concrete**: `03e` specifies tracking "3+ near-duplicate asks in one conversation" for mentor escalation without specifying the mechanism. It reuses the embedding the Gateway already computes for RAG retrieval on every message — a new question's embedding is compared (cosine similarity, thresholded) against the last few user messages **in that same conversation**, which is nearly free given the embedding was being computed anyway for retrieval, rather than standing up a separate analysis pass purely for this check.

---

## Next

Phase 5b — feature-specific prompt design: AI Doubt Solver (hint/explain/practice modes) and AI Coding Assistant (debug/review + the sandbox-verification loop). Say "continue."
