# Delivery Phase 5d — AI Workflows: Placement Scoring, Project Originality Check, Course Recommendation Engine

Covers the three remaining AI-driven features from Phases 2/3: AI-scored open-ended Skill Assessment responses (Phase 4, `03b`), the project originality/plagiarism check (Phase 19–20, `03f`), and the Renewal/Upsell course-recommendation engine (Phase 28, `03h`). **This closes out Delivery Phase 5 — AI Workflows in full.** Gateway/RAG/tiering/cost/moderation machinery from `05a` applies throughout.

A theme runs through all three, worth naming once up front since it recurs a third time here after `05c` §4: **every consequential decision is made by deterministic logic over model-produced inputs — the model itself never decides the outcome, only scores or narrates something a rule then acts on.** A placement level, a plagiarism flag, and a recommended course are all too consequential (cost money, affect a transcript, or shape a parent-facing claim) to leave to free-text model judgment with no reproducible rule behind it.

---

## 1. AI-Scored Placement Assessment (Skill Assessment, Phase 4)

The placement exam's objective sections (coding MCQs, logic puzzles) are already auto-graded mechanically — nothing AI-driven there. The AI layer is specifically the **open-ended communication section**.

**Scoring call** (fast/cheap tier — this runs synchronously while the student waits for their result, so it shares the Doubt Solver's `<3s`-class latency expectations, not the report-generation jobs' relaxed budget): a rubric-based prompt — clarity of expression, grammatical correctness appropriate to the student's age band, ability to explain a technical concept in their own words — returns a `communication_score` (0–100) **plus a short rationale**, the same transparency principle `risk_scores.contributing_factors` already established: a score with no visible "why" is useless to the mentor who has to act on it.

**Borderline routing is a deterministic threshold check on the returned score, not the model self-reporting uncertainty.** `03b` calls for borderline cases to route to a mentor mini-check rather than auto-deciding — that threshold (e.g., a configured band around the level-cutoff scores) is evaluated in application code against the number that comes back, the same way `05a`'s near-duplicate detection is a thresholded similarity check rather than asking a model "was that a duplicate?" and trusting its self-assessment.

**The placement decision itself — `recommended_level`/`recommended_course_id`/`recommended_batch_type` — is a separate, fully deterministic step**, not part of the scoring call: a rules/threshold matrix combines the now-three independent scores (`coding_score`, `logical_reasoning_score` — both already objective — plus the AI-produced `communication_score`) against the course catalog's level bands. The LLM scored one input; it never picks the placement. This is the exact same separation `05c` §4 draws for risk scoring, applied to a second consequential decision for the same reason: `placement_results.ai_generated=1` plus the mandatory `reviewed_by` mentor confirmation (`03b`'s explicit requirement, unchanged by anything in this phase) only works as a real safety net if what the mentor is reviewing is a small set of legible numbers and a rule, not an opaque model judgment call they're expected to rubber-stamp.

---

## 2. Project Originality / Plagiarism Check (Phase 19–20)

Two complementary signals, run async after submission (`04e` already specifies this doesn't block the submit response) — neither is a single check:

1. **Embedding similarity** reuses the exact same embedding pipeline as RAG and near-duplicate detection (`05a` §2/§5) — the submission's content is embedded once and compared against a corpus of prior submissions. This catches *conceptual/paraphrased* copying — the same idea, reworded.
2. **A public-code plagiarism service** (MOSS-style), checked separately, for code submissions specifically — catches *literal* copying from a public repo or Stack Overflow that embedding similarity alone might not flag strongly if the variable names/structure were superficially changed.

**Self-similarity is deliberately distinguished from other-similarity** — `03f`'s own stated false-positive case is "a student legitimately building on their own earlier work." The embedding comparison runs in two scoped passes: first against the *same student's* own prior submissions (a high match here is expected and unflagged, or labeled `self_reuse` informationally rather than as a concern), then against *other students'* submissions to the same assignment (a high match here is the actual plagiarism signal). Conflating these into one undifferentiated similarity number would punish exactly the legitimate case `03f` warns against.

**Score direction, stated explicitly since the column name alone is ambiguous**: `originality_score` — higher means *more* original (less concern); a *low* score is what surfaces the flag, alongside `plagiarism_report_url` from the external service, onto the teacher's grading queue. **Still purely advisory** (`03f`'s explicit principle, unchanged here) — grading proceeds normally regardless of the score; nothing in this pipeline auto-rejects or auto-blocks a submission.

---

## 3. Course Recommendation Engine (Renewal/Upsell, Phase 28)

**Not a single open-ended call** ("here's the whole catalog, pick something") — too expensive to run at scale, too inconsistent across calls for the same student, and impossible to evaluate or tune from outcome data the way `03h`'s `shown_at`/`converted_at` measurement loop wants to. Two stages instead:

**Stage 1 (deterministic, no model call)**: a curriculum-maintained `course_next_steps` mapping (e.g., "Python Beginner" → `["Python Intermediate", "Web Dev with Python"]`) is filtered against the student's actual performance bucket in the just-completed course (from `student_progress_snapshots`, `04e`) and their `student_profiles.interests`/`goals` controlled-vocabulary tags (`04a`) — producing a short, ranked candidate list (2–4 courses) through plain filtering logic. `confidence_score` is computed **here**, from how strong the catalog-mapping + performance/interest alignment was — not asked of the model, keeping the one number this feature's accuracy gets measured by out of the LLM's hands, same as the placement and originality flows above.

**Stage 2 (fast/cheap-tier model call, narration only)**: for each already-decided candidate, generate the short `reason_summary` text ("Builds directly on your strong project work in..."). The model's job is explaining a pick that's already been made, never making the pick — identical in spirit to `05c` §5's parent-report narration sitting on top of deterministically-computed risk scores.

**No candidates above a minimum relevance bar in Stage 1 → Stage 2 is skipped entirely** (no wasted model call) and the response is the graceful "explore other tracks" state `04g` already specifies — a student ahead of the current catalog gets an honest non-answer, not a forced, confidently-wrong suggestion (`03h`'s explicit reasoning).

**Why keeping the decision deterministic actually matters here, beyond consistency**: `03h`'s `shown_at`/`converted_at` tracking is what makes this engine's real-world hit rate measurable over time. That measurement is only meaningful because Stage 1's matching logic is a fixed, inspectable rule that can be tuned against outcome data — if an LLM had made the actual pick, "why did conversion improve after we changed X" would have no stable thing to attribute the change to.

---

## Delivery Phase 5 — AI Workflows: complete

All four sub-parts (`05a`–`05d`) are done: the shared Gateway/RAG/cost/moderation infrastructure, the Doubt Solver/Coding Assistant prompts and sandbox-verification loop, the Notebook AI prompts plus risk/report narrative generation, and this document's three remaining scoring/recommendation features. Every AI-touching workflow from Phases 2/3 now has its prompt-level design specified.

## Next

**Delivery Phase 6 — Communication Orchestration Engine**: the multi-channel (email/WhatsApp/SMS/in-app) dispatch system that every workflow phase since `03a` has referenced in passing ("Phase 6") without yet specifying — reminder cadences, escalation batching, template management, delivery-channel fallback. Say "continue."
