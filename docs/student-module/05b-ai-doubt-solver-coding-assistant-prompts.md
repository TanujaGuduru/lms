# Delivery Phase 5b — AI Workflows: Doubt Solver & Coding Assistant Prompt Design

Covers lifecycle phases 16–17. The Gateway plumbing, RAG mechanics, model tiering, quota enforcement, and moderation pipeline from `05a` all apply here without restatement — this document is specifically the prompt templates and the model-call orchestration layered on top of that shared machinery.

---

## 1. Prompt Assembly — shared structure across every mode

Every call assembles one system prompt from composable, independently-versioned layers (per `05a`'s prompt-versioning point) rather than one hand-written mega-string per mode:

1. **Base persona/safety layer** (always present): age-band tone + reading level from `ai_profiles.persona_settings`, `ai_conversations.language`, the platform-wide safety instructions, and the core directive — *"You are CodeGurukul's tutor. You teach; you do not hand over direct answers unless explicitly told you may."*
2. **Mode-specific instruction layer** — swapped in based on `ai_conversations.mode` (§2/§3 below).
3. **RAG context layer** — the top-K retrieved chunks (`05a` §2), wrapped with an explicit *"use this context only if it actually helps; ignore it if it doesn't"* instruction, specifically so a weak or borderline-irrelevant retrieval doesn't get overweighted just because it's present in the prompt.
4. **Windowed conversation history** — last N turns, not the full conversation, to keep token cost (and therefore `cost_usd`) bounded regardless of how long a conversation runs.
5. **The current message** — student's question, or for the Coding Assistant, the current code/error context.

`doubt_solver` conversations use modes `hint`/`explain`/`practice`; `coding_assistant` conversations use `debug`/`review` — the same `ai_conversations.mode` column (`02d`) serves both, with which values are valid simply depending on `conversation_type`.

---

## 2. AI Doubt Solver

### Hint mode
*"Do not give the direct answer. Point at the relevant concept or ask a guiding question instead. If asked directly for the answer, redirect with a guiding question once. If the student asks directly a second time in this conversation, do not capitulate — that is handled outside this prompt."* The model is never told about escalation mechanics in its own context — staying firm on a second direct ask and routing to the explicit escalation path (§4) is an application-level decision, not something the prompt asks the model to self-police, since LLMs are unreliable at correctly applying their own conditional "give in after N tries" logic.

### Explain mode
Full conceptual explanation, instructed to use age-appropriate analogies and to reference the relevant lesson **at a concept level** ("this connects to what Lesson 4.2 covers about recursion"), never by quoting the retrieved RAG text verbatim — the no-verbatim-RAG rule from `05a` §2 applies identically here, it isn't a hint-mode-only restriction.

### Practice mode
Generates an **analogous** problem, not a restatement of the original — the prompt explicitly instructs against reusing the student's exact numbers/scenario, since a thinly-reskinned version of the same problem doesn't actually test transfer of understanding. Response includes an optional small starter-code stub the student can carry into a new sandbox workspace (`04d`) — never an answer key alongside it.

### Near-duplicate handling
When the embedding-similarity check from `05a` §5 fires (3+ near-duplicate asks), **the model's response to the student is unaffected** — same hint-mode behavior as normal. Only a side flag gets logged for mentor review. The model is deliberately never informed a duplicate was detected; enforcement is the application's job, which keeps the prompt itself simple and means a prompt change can never accidentally break the detection logic (they're fully decoupled).

---

## 3. AI Coding Assistant

Primary context is the **code and error**, not the course RAG corpus — unlike the Doubt Solver, where curriculum retrieval is central, here RAG is a secondary layer invoked only when a question is conceptual rather than code-specific ("why does recursion overflow the stack" benefits from curriculum content; "why is line 4 wrong" doesn't need it). The Gateway still has RAG available to this mode (`05a`'s retrieval layer is shared infrastructure), it's just rarely the dominant signal here.

### Debug mode
Triggered automatically (`code_executions.stderr` non-empty → "want help with this?") or manually. Context: current `workspace_files` content for every file in the workspace (not just the entry point — same bundling already used for execution itself, `04d`) plus the `stderr`/student question.

### Review mode
Flags style/structure/best-practice issues, not just correctness bugs — teaching-first framing throughout: *explain why something is a smell*, don't just emit a corrected block.

### The sandbox-verification loop — the actual control flow

This is the one place `03e` calls out as needing "a concrete mechanism, not just careful prompting" to close the gap between the model sounding confident and the model being right. The full sequence, run server-side before anything streams to the student as a confirmed fix:

1. **Model proposes a fix** — explanation text plus the changed code in a clearly fenced, structurally consistent block (the prompt is specific about this formatting precisely so the next step is reliable string extraction, not a second LLM call just to parse the first one's output).
2. **Gateway extracts the proposed change** programmatically and applies it to a **throwaway copy** of the workspace — never the student's live files.
3. **Executes that copy** through the exact same sandbox path as `04d`'s `POST /workspaces/{id}/run`, with `trigger_source='ai_assistant_check'`.
4. **Compares outcome to the original failure**: did the original error disappear, did `exit_code` become `0`, or — when test cases are known from an assignment/assessment context — does `stdout` now match expected output?
5. **Branches the response**:
   - **Confirmed**: streams with `sandbox_verified: true` (the field `04d`'s `POST /ai/conversations/{id}/escalate` already specifies) and confident wording.
   - **Not confirmed**: `sandbox_verified: false`, and a **second, short follow-up instruction is injected only at this point** — *"Your suggested fix did not resolve the error when actually run. Rephrase your explanation to reflect appropriate uncertainty — present it as an idea to try, not a confirmed solution."* This hedging instruction is deliberately **conditional, not baked into the base prompt** — applying it unconditionally would needlessly soften responses that are confidently and correctly right too.
6. **Not every response has something to verify**: if the model's answer contains no extractable code block (a purely conceptual answer, or it's recommending a multi-step refactor rather than a single fix), the verification step is skipped entirely and the response carries no `sandbox_verified` field at all — there's nothing to run.

**This loop is exactly why the Coding Assistant's latency budget is `<8s` rather than the Doubt Solver's `<3s`** (`01`/`05a`'s stated SLAs) — it has to account for a real model-call → execute → possible-second-model-call round trip, not a single completion.

### Length-limit guard
A large, unrelated code dump is checked **before** any model call — a simple length/line-count threshold, not a request to the model to ask for narrowing itself — and short-circuits to a canned *"let's focus on one function"* response with zero tokens spent. Enforcing this in code rather than hoping the model reliably asks first is both cheaper and more reliable (`03e`'s edge case, made concrete).

---

## 4. "Show Me the Fix" Escalation

`POST /ai/conversations/{id}/escalate` (`04d`) doesn't reuse the hint-mode prompt with a flag the model is asked to honor — it swaps in a **distinct, more permissive prompt variant** where the redirect-to-a-guiding-question instruction from §2 is simply absent, and the model is told directly it may give the answer/fix this one time. Using a genuinely different prompt rather than a conditional inside one mega-prompt is the reliable choice here for the same reason noted in §2: LLMs don't dependably self-enforce "only do X if the user explicitly unlocked it" instructions embedded alongside instructions telling them not to do X by default. Every escalation call is logged on the conversation regardless of outcome — visible to the teacher, which is the actual mechanism (not a policy statement alone) behind making a pattern of "always skips straight to the answer" visible (`03e`'s stated requirement).

---

## Next

Phase 5c — prompt design for Notebook AI features (voice transcription handling, summarize, flashcard generation, quiz-from-notes) and the AI Risk Detection / Monthly Parent Report narrative generation. Say "continue."
