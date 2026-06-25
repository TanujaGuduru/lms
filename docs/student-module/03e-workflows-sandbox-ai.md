# Delivery Phase 3e — Workflows: Coding Sandbox, AI Doubt Solver, AI Coding Assistant

Covers lifecycle phases 15–17. Tables from `02d`. Prompt-level detail (the actual system prompts, RAG retrieval mechanics, model selection) is deliberately deferred to Phase 5 — this document covers the *workflow* the AI sits inside, not the AI itself.

---

## 15. Coding Sandbox / Cloud IDE

### Business workflow

1. Student creates/opens a `code_workspaces` row (language selected up front; optionally linked to a course/lesson/assignment).
2. Files (`workspace_files`) autosave continuously and directly — no explicit "save" needed for the live state to persist.
3. **Run**: client sends the current file contents + language + stdin to the Laravel API, which forwards to the dedicated sandbox VPS's execution API (never executes anything itself — see Phase 1/2d). A `file_versions` snapshot is taken immediately before execution, so "last known working version" is always one click away even after a bad edit.
4. The sandbox runs the code in a disposable Piston container with hard limits (CPU time, memory, wall-clock timeout, output size), returns stdout/stderr/exit_code/timing.
5. Result lands in `code_executions`; client renders it. Execution history and version restore are just reads against `code_executions`/`file_versions`.

### Language-specific branching (this is not one uniform "run" path)
- **Python / JavaScript / PHP**: direct interpreted execution.
- **Java / C / C++**: compile step first — compile errors are captured and shown **distinctly** from runtime stderr (a student needs to know "this didn't even build" vs. "it ran and crashed," which are different debugging mental models). Entry-point resolution for Java specifically has to account for the file name needing to match the public class name — `workspace_files.is_entry_point` alone isn't sufficient language-agnostic logic here; the execution request includes language-aware entry-point resolution.
- **HTML/CSS**: not "executed" in the stdout sense at all — rendered into a sandboxed preview iframe. The "Run" action branches entirely for this language: no `code_executions` row in the traditional sense, just a live preview render.
- **SQL**: needs something to actually query against. An ephemeral SQLite database (seeded with whatever schema the lesson/assignment defines) is spun up per execution, the student's query runs against it, and the result set is rendered as a table — there's no "production" database involved at any point.

### Edge cases & failure handling (explicitly called out in your brief)
- **Infinite loops**: a wall-clock timeout (default 10s, configurable per exercise difficulty) kills the container and returns `status='timeout'` — this is what actually catches it, not anything code-analysis-based.
- **Memory abuse**: enforced by the container runtime's own resource limits (cgroups), not application logic — exceeding it returns `status='memory_exceeded'`.
- **Malicious code** (network access attempts, fork bombs, filesystem escape attempts): this is precisely why Piston was chosen over bare Docker in Phase 1 — it restricts network/filesystem access and process limits (`ulimit -u` style fork-bomb prevention) by default, as a sandboxing tool's actual purpose, rather than something bolted on after the fact.
- **Multi-file projects with cross-file references** (e.g., a C project with multiple `.c`/`.h` files): the execution request bundles the whole file set, not just the entry point, so compiler/interpreter includes resolve correctly.

---

## 16. AI Doubt Solver

### Business workflow

1. Student opens the AI panel (from a live class, a lesson, or standalone) → creates/continues `ai_conversations` (`conversation_type='doubt_solver'`), picks a mode (hint / explain / practice).
2. **Quota check happens before any LLM call is made** — `ai_usage_quotas` is checked first; if exhausted, the student gets a clear, friendly message about when it resets, rather than the call silently failing or (worse) going through anyway and the cost being eaten with no governance.
3. RAG retrieval scoped to the relevant course/topic (Pinecone embeddings namespaced per course, per Phase 1 §5 — so a Python course question never retrieves Java curriculum content).
4. LLM call, streamed back (SSE) for fast perceived response — age-aware and language-aware based on `ai_profiles.persona_settings` and `ai_conversations.language`.
5. Response stored as an `ai_messages` row with token/cost/latency logged; `ai_usage_quotas` incremented in the same operation that records the message, not as a separate, possibly-skipped step.

### Mode behavior (workflow-level distinction; full prompt design is Phase 5)
- **Hint**: nudges toward the approach, withholds the direct answer.
- **Explain**: full conceptual explanation.
- **Practice**: generates an analogous problem rather than answering the original directly.

### Edge cases & failure handling
- **Student tries to game hint mode into spoon-feeding** (rephrasing the same question repeatedly to wear the AI down): tracked at the conversation level — 3+ near-duplicate asks in one conversation is flagged for a human mentor check-in rather than the AI eventually just capitulating with the answer. This is the concrete mechanism behind "AI should teach, not spoon-feed" — a policy statement alone doesn't enforce itself; this detection-and-escalation step does.
- **Off-topic / inappropriate use**: a content-moderation pass (detailed in Phase 5) runs before the response is shown, particularly important given the user base includes children.
- **LLM provider outage or rate-limited**: this is exactly why Phase 1 specified an AI Gateway abstraction rather than calling Anthropic/OpenAI directly from every feature — a provider failure triggers an automatic fallback to the secondary provider at the gateway level, invisible to the feature code calling it.

---

## 17. AI Coding Assistant

### Business workflow

1. Same conversation structure (`conversation_type='coding_assistant'`), typically linked to a `code_workspace`.
2. Triggered either automatically (a `code_executions` row comes back with non-empty `stderr` and the UI offers "want help with this error?") or manually (student asks for a review).
3. The assistant receives the current `workspace_files.content` + the error/question as context.
4. **Same teach-don't-spoon-feed principle as the Doubt Solver**, applied to code specifically: leads with *why* something is wrong and a guiding question, not a corrected code block — with an explicit "show me the fix" escalation available if the student insists, which is itself logged (visible to the teacher) so a pattern of always escalating straight to the answer is visible, not hidden.
5. **Review mode** flags style/structure/best-practice issues, not just correctness bugs — same teaching-first framing.

### The hallucination problem, and how this design actually addresses it
LLMs do sometimes claim a fix works when it doesn't, especially for less-common language behavior. Rather than trusting the model's claim at face value, **any suggested fix the AI proposes is run through the sandbox (§15) before being presented as confirmed** — the loop is: AI suggests → sandbox executes the suggested change → only a fix that actually produces correct output (or at least successfully compiles/runs without the original error) is shown as "this works," anything else is shown as "here's an idea, but I'm not certain — try it and see." This is the one place in the whole AI workflow where a concrete mechanism (execution), not just careful prompting, closes the gap between "the AI sounds confident" and "the AI is actually right."

### Edge cases & failure handling
- **Student pastes a large, unrelated code dump for general review**: a length-based soft limit prompts narrowing the scope ("let's focus on one function") rather than either silently truncating context (risking a worse answer) or burning an outsized amount of quota on one request.
- **Shared vs. separate quota pools for Doubt Solver and Coding Assistant**: left as a configurable business decision rather than hardcoded — some products want a single AI-help budget per student, others want coding help (often more compute-intensive given execution-validated fixes) governed separately. The schema (`ai_usage_quotas` keyed just by student+period) supports either by choosing whether `conversation_type` factors into the quota query.

---

## Next

Phase 3f — Assessments, Project Lifecycle, Project Publishing, Progress Analytics. Say "continue."
