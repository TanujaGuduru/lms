# Delivery Phase 3i — Workflows: Gamification, Digital Notebook, Collaborative Coding

Covers lifecycle phases 31–33. Schema for all three is already in `02d`/`02e` — this document is workflow detail the schema alone doesn't capture: how XP/badges/streaks actually get triggered, how the AI-powered notebook features behave, and how real-time pairing actually plays out session to session.

---

## 31. Gamification

### Business workflow

1. XP is awarded on specific, enumerated events (class attended, assignment submitted, project completed, quiz won, streak bonus, referral) — each with a defined point value (a business-configurable table/settings, not hardcoded in application code, since these values get tuned over time as the team learns what motivates engagement). Each award is an `xp_transactions` row; `student_xp.total_xp` is the denormalized running total, same pattern as credits.
2. `current_level` is derived from `total_xp` via a level curve (e.g., a lookup table of XP thresholds per level, simpler and easier to balance than a formula) and recomputed whenever XP changes.
3. **Badge awarding is a check, not a separate trigger**: after any XP transaction or qualifying event, the system checks all active `badges.criteria_type`/`criteria_value` thresholds — if newly met and not already in `student_badges`, it's awarded immediately with a notification, rather than running on a delayed batch job (a badge that takes a day to show up after you've earned it loses most of its motivational value).
4. Streaks: `student_streaks` updates on qualifying daily activity — same-day activity extends `current_streak_days`; a missed day consumes a `streak_freezes_remaining` if available (capped, e.g., one per month, not unlimited) before the streak resets to 0.
5. Leaderboard reads are cached briefly (e.g., a 5-minute cache) rather than re-querying on every dashboard load — it's a plain indexed query (per the GoDaddy-driven decision in Phase 2e), so this is purely about avoiding redundant identical queries within a short window, not a correctness concern.

### Edge cases & failure handling
- **XP needs to be reversed** (e.g., a submission that earned XP is later voided for plagiarism): a reversing `xp_transactions` entry (negative amount, `reason='manual_adjustment'`, with a note) — never an edit or delete of the original row, same ledger-integrity principle used for credits throughout this design.
- **Badge criteria change after some badges are already awarded** (threshold raised later): already-earned badges are **never retroactively revoked** — a badge is a permanent record of an achievement under the rules that existed when it was earned; only future awards evaluate against the new criteria.
- **Streak-freeze gaming**: freezes are capped and replenish slowly (monthly), not unlimited — this is what keeps the freeze a forgiving feature rather than a loophole that makes the streak meaningless.

---

## 32. Digital Notebook — AI Workflow Detail

(Schema: `notes`, `note_versions`, `note_tags`, `flashcards`, `recording_bookmarks` — Phase 2c.)

### Business workflow

1. **Manual notes**: standard rich-text creation, optionally linked to a course/lesson/live class, autosaving as already specified in Phase 2c.
2. **Voice-to-text**: audio is captured client-side and sent as a discrete recording to a speech-to-text API (via the AI Gateway, Phase 5) — the transcribed text inserts at the cursor position, and **is always editable afterward**, never locked as read-only AI output. Background noise, accents, and technical jargon (a real risk for transcribing coding terminology) all mean transcription will sometimes be wrong, so editability isn't optional polish — it's the actual correctness mechanism.
3. **AI-generated notes from a class**: once a class recording's transcript is ready (Phase 3d), the student gets a one-click "generate notes from this class" action rather than every single class automatically spawning a note — automatic generation for every class would clutter the notebook with summaries nobody asked for; on-demand generation respects that the student decides what's worth keeping. The resulting note is created with `is_ai_generated=1` and `linked_live_class_id` set, and the UI visibly badges it as AI-generated so it's never mistaken for the student's own understanding-in-their-own-words.
4. **Bookmark-to-note**: a recording bookmark (`recording_bookmarks`) can be "saved to notebook," which creates or appends to a note with the timestamp + label, setting `linked_note_id` on the bookmark — this is how "Recording timestamp 32:15 — important explanation of recursion" actually becomes a permanent, searchable note rather than just sitting in the player's bookmark list.
5. **AI features on an existing note**:
   - **Summarize**: AI Gateway returns a condensed version, shown as a *suggestion* the student can accept (replace) or append — never silently overwriting the original.
   - **Flashcards**: AI extracts Q&A pairs into `flashcards` rows, reviewed on a spaced-repetition schedule (`next_review_at`).
   - **Quiz from notes**: generates practice questions into the existing `questions`/`exams` tables (`source_note_id` set) — reusing the exam infrastructure rather than building a parallel quiz system, consistent with the reuse principle throughout this design.

### Edge cases & failure handling
- **AI-generated class summary contains an error or hallucination**: the `is_ai_generated` badge is the actual safeguard here — it sets the expectation that this needs verifying against the real recording, rather than presenting AI output with the same authority as the student's own notes.
- **Long voice recordings**: capped at a sensible duration with a clear in-UI message rather than silently truncating or timing out — a student should know *why* a 20-minute ramble didn't transcribe, not just see it fail.

---

## 33. Collaborative Coding — Workflow Detail

(Schema: `collab_sessions`, `collab_participants`, `collab_snapshots` — Phase 2d.)

### Business workflow

1. **Initiation**: either teacher-initiated during a live class (`linked_live_class_id` set) or student-initiated for standalone practice/hackathon use.
2. **Joining**: participants connect to the shared Yjs document over the Pusher/Ably channel; `collab_participants` records role and join time.
3. **Concurrent editing is conflict-free by construction** — this is the entire reason Yjs (CRDT) was chosen over hand-rolled Operational Transform in Phase 1. Two people typing in different parts of the same file simultaneously simply merge; there's no explicit locking mechanism to build or maintain.
4. **Teacher observation vs. teacher editing**: rather than a hard permission gate a teacher has to explicitly request to bypass, the design choice here is that a teacher always *has* write access (they need to actually demonstrate a fix, not just describe it) — what changes between "observing" and "editing" is purely a UI distinction: an observing teacher's cursor is shown unobtrusively, while actively typing is visually flagged clearly so a student isn't startled by code changing under their hands without warning. A hard permission-upgrade flow would add friction to exactly the moment (a student stuck and needing help) where friction is most costly.
5. **Voice discussion** piggybacks on the existing Agora audio channel when inside a live class; standalone practice/hackathon sessions get their own ad-hoc Agora voice room rather than a separate audio system.
6. **Session end**: explicit end action, or the auto-end-after-5-minutes-no-presence rule from Phase 2d, which takes a final `collab_snapshots` row before closing.

### Edge cases & failure handling
- **Simultaneous edits**: not a special case to handle — this is what choosing a CRDT solves at the architecture level, not something workflow logic needs to compensate for.
- **Network disconnects**: Yjs supports offline editing with sync-on-reconnect — a student's local edits during a brief drop queue locally and merge automatically once they're back, rather than being lost or causing a conflict on reconnect.
- **Stale/abandoned sessions**: already covered by the presence-based auto-end rule — no separate cleanup logic needed beyond what Phase 2d specified.

---

## Next

Phase 3j — Live Quizzes, Code Replay (workflow detail), Offline Access/DRM, Calendar Integration. Say "continue."
