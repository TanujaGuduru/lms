# Delivery Phase 5c — AI Workflows: Notebook AI Prompt Design, Risk/Report Narrative Generation

Covers the prompt design for Digital Notebook AI features (lifecycle phase 32) and the natural-language narrative layer sitting on top of AI Risk Detection (22) and Monthly Parent Reports (24). The Gateway, RAG, model-tiering, quota, and moderation machinery from `05a` applies throughout and isn't restated here.

---

## 1. Voice-to-Text Cleanup

Raw speech-to-text output is choppy by default — no punctuation, filler words ("um," "so basically"), and a real risk of mangled technical jargon (a coding term mis-heard as a similar-sounding word). A light cleanup pass on the **fast/cheap** model tier runs after transcription, before the text lands in `notes.content`:

*"Add punctuation and paragraph breaks. Remove filler words. Do not correct or guess at technical terms you're not confident about — if a word is unclear, leave it marked `[unclear]` rather than substituting a plausible-sounding alternative."*

The explicit instruction to mark uncertainty rather than guess matters specifically because a confidently-wrong substitution of one coding term for another (e.g., a mis-heard "loop" guessed-and-replaced as something else entirely) is worse than visible uncertainty — and this is exactly why `03i`'s rule that voice-transcribed text is **always editable, never locked as read-only AI output** is the actual correctness mechanism, not this cleanup pass; the cleanup pass just reduces how often a student needs to use that editability, it doesn't replace the need for it.

---

## 2. AI-Generated Class Notes — map-reduce over a transcript

A full class transcript (60–90 minutes) is too long to summarize in a single pass economically, and a single giant summarization call would also tend to flatten distinct topics together. Generation runs as **map-reduce**, not one call:

1. **Map**: the transcript is split into time-aligned chunks (e.g., ~10-minute segments). Each chunk is summarized independently on the fast/cheap tier into bullet points, each bullet tagged with its source timestamp.
2. **Reduce**: the chunk summaries (now short) are combined in a single deep-tier call that **reorganizes by topic, not by chunk boundary** — a topic discussed for 15 minutes spanning two chunks should read as one coherent section in the final note, not two disconnected bullet lists glued together. This pass is instructed: *"Organize by concept covered, not by time order. Preserve the timestamp reference for each point so the student can jump back to that moment in the recording."*
3. The resulting note is created with `is_ai_generated=1`, `linked_live_class_id` set (`02c`/`03i`), and timestamp references that the client renders as clickable jumps into the recording player — reusing the same timestamp-seek UX `02c` already built for `recording_bookmarks`/`code_replay_markers`, rather than inventing a second way to jump around a recording.

This only ever runs **on-click** (`04h`'s `POST /recordings/{id}/generate-notes`), never automatically per class (`03i`'s explicit reasoning) — so the map-reduce cost is incurred only for classes a student actually decides are worth summarizing, not every class held.

---

## 3. Note Summarize / Flashcards / Quiz-from-Notes

These three operate on an existing note's `content` — already short relative to a class transcript, so each is a **single-pass** call (no map-reduce needed), all on the deep/quality tier since none are on a tight latency path (`05a`'s tiering table).

### Summarize
*"Condense this note into 3–5 key points, in the student's own conceptual framing, not a generic restatement."* Returned as a suggestion (`04h`) — the model is never asked to "rewrite the note," only to produce a separate condensed version the student explicitly accepts (replace) or appends.

### Flashcards
*"Extract question/answer pairs that test understanding of a concept, not recall of an exact sentence from the note. Skip anything too trivial to be useful as a review card. Generate at most 8 cards per request."* The trivial-card exclusion and the hard cap both exist for the same reason: an AI flashcard generator with no quality filter or volume cap left running on every note would flood `flashcards` with low-value cards that make the spaced-repetition deck worse, not better, to actually review.

### Quiz-from-Notes
Structured-output generation matching the existing `questions` table's `options`/`correct_answer` JSON shape (`02c`'s `ALTER TABLE exams ADD source_note_id`) — same JSON conventions already used elsewhere in this catalog (e.g., coding-question test cases, `04e`), not a new ad-hoc format. **These generated questions skip the normal `questions.status` review gate** (`draft → pending_review → approved`) and are created directly as usable practice content — they're scoped privately to that student's own personal practice quiz (`source_note_id`-linked), never promoted into the shared question bank for other students to encounter, so the human-curation gate that exists specifically to protect bank-wide question quality doesn't apply to a one-student practice artifact generated from that student's own notes.

---

## 4. A Deliberate Non-AI Boundary: Risk Scoring Stays Deterministic

Worth stating explicitly, since this section is otherwise all about LLM-generated content: **the risk scores themselves (`risk_scores.score_value`/`contributing_factors`, `03g` §22) are computed by deterministic, rule-based logic — attendance-trend math, assignment-completion-rate math, login-recency — never by an LLM.** This is a deliberate boundary, not an oversight: a score that triggers real interventions (a WhatsApp message to a parent, a mentor call, formal escalation) needs to be exactly reproducible and auditable — "why did this fire" has to be answerable by reading the same arithmetic every time, which a model's free-text judgment can't guarantee in the same way. The LLM's role in the risk pipeline starts **after** a score already exists — narrating it for a human to read (§5 below) — never deciding what the score is.

---

## 5. Monthly Parent Report Narrative

**Inputs** (assembled by the report-generation job, `03g` §24, before any model call): the enrollment's latest `student_progress_snapshots` row, recent `risk_scores` (if any), attendance trend, and recent achievements (badges/certificates). **No transcript or open-ended free text goes into this prompt** — every input is already a small set of structured numbers and labels, so (unlike §2) there's nothing to chunk; it's a single deep-tier call.

**Grounding instruction, stated explicitly because the audience trusts this document**: *"Use only the data provided below. Do not invent specific events, dates, or numbers not present in this data. If something isn't covered by the provided data, don't speculate about it."* A hallucinated specific ("scored 92% on last week's quiz") landing in a document a paying parent reads as authoritative is a much worse failure mode here than in a chat interface the student can immediately push back on.

**Structure, not one undifferentiated paragraph** — the prompt requires three labeled sections (strengths / areas for growth / recommended next steps), matching `03g`'s explicit ask, and:
- **Tone is parent-appropriate, not student-appropriate** — this is the one AI-generated surface in the whole design where the reader's age band, not the student's, drives the tone/persona layer (`05a` §1's persona resolution branches on *audience*, not just on the student's own profile, specifically for this feature).
- **An elevated risk score is narrated softly and always paired with a concrete next step**, never surfaced as a raw number — the exact same "advisory, never raw score" principle `04f`'s parent-facing risk-summary endpoint already applies, restated here because this is the other surface where a risk score reaches a parent. *"If risk_scores indicates an elevated signal, mention it gently and tie it to one concrete, specific recommended action (e.g., a suggested check-in call) — never state a numeric score or clinical-sounding language."*
- **`is_partial_period=1` changes the framing instruction entirely**: *"This student joined partway through the month — frame all percentages/trends as 'in their first X days' rather than implying a full month's pattern."* Skipping this for a partial-period report is exactly the failure mode `03g` calls out: a low attendance percentage that's actually just "joined on the 20th" reading as alarming with no context.

The model's output becomes `parent_reports.summary_text`, rendered into the PDF alongside the (non-AI-generated) charts/numbers from the same snapshot data — the narrative explains the numbers sitting right next to it, rather than being the only place those numbers appear.

---

## Next

Phase 5d — the three remaining AI-driven features from Phases 2/3 not yet covered: AI-scored open-ended/communication assessment responses (Skill Assessment, Phase 4), the project originality/plagiarism check (Phase 19–20), and the Renewal/Upsell course-recommendation engine (`course_recommendations`, Phase 28). Closes out Delivery Phase 5. Say "continue."
