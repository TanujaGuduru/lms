# Delivery Phase 3c — Workflows: Live Classroom, Attendance, Reschedule/Cancellation, Teacher Change

Covers lifecycle phases 7–10 — the most operationally critical part of the product, since a broken live class directly damages trust in a premium offering. Tables from `02b`/`02c`. Agora/Pusher integration detail referenced per the Phase 1 architecture + GoDaddy addendum.

---

## 7. Internal Live Classroom

### Business workflow

1. Join button appears within a configurable window before `start_datetime` (e.g., 15 min early).
2. **Teacher starts the class** → `live_classes.status='live'`; the Agora channel (pre-allocated at materialization time, named from `live_classes.id` plus a server-side secret so it isn't guessable/replayable) begins cloud recording automatically.
3. **Student joins** → client requests a short-lived, channel-scoped Agora RTC token from the Laravel API. The API issues a token **only if**: the requester is actually in `batch_students` for this class's batch, the class is `live` (or within the join window), and the student's account is `active` (not `pending_consent`). This is the entire unauthorized-join defense — no token, no media access, regardless of what channel name someone might guess.
4. **In-class real-time features**, each routed through the channel that actually fits it (not everything is "video"):
   - Video/audio/screen-share → Agora SDK directly.
   - Chat, raise-hand, presence → a Pusher/Ably channel scoped to the class, persisted server-side as it happens (not held only in browser memory — see refresh handling below).
   - Whiteboard → synced the same way as collaborative code (Yjs CRDT over Pusher/Ably), so it survives reconnects.
   - Notes panel → opens a `notes` entry pre-linked to `linked_live_class_id` (Phase 2c notebook).
   - AI doubt panel → opens an `ai_conversations` row scoped to this class (Phase 3e).
   - Coding sandbox panel → opens (or joins, if the teacher initiates pairing) a `code_workspaces` / `collab_sessions` instance (Phase 2d).
5. **Class ends** (teacher ends it, or scheduled duration + grace period elapses) → `status='completed'`; Agora's recording-stopped webhook fires, handing off to the recording pipeline (Phase 3d). Attendance finalization (§8 below) runs immediately after.

### Edge cases & failure handling

- **Browser refresh**: client reconnects to the same Agora channel and requests a fresh token (the old one may have expired) — no special "resume" logic needed because chat/whiteboard/notes state was never *only* in the browser; it's persisted server-side as it happens, so a refresh just re-renders from the same source of truth everyone else sees.
- **Intermittent network drops**: Agora's adaptive bitrate degrades video quality automatically before dropping the connection. A drop longer than a configurable window (e.g., 2 minutes) stops that student's attendance-duration clock; reconnecting resumes it — handled as multiple join/leave intervals for the same session (§8), not as two separate attendance records.
- **Teacher disconnects**: the class does **not** auto-end. Students see a "reconnecting" state for a grace period (3–5 min) — ending the class immediately on a teacher's transient drop would falsely cancel viable sessions. If the teacher hasn't returned by the end of the grace period, it escalates to a support/ops alert for manual intervention (likely cancellation with `cancellation_reason='teacher_emergency'`, which guarantees no credit deduction per Phase 3a).
- **Audio/video permission denied**: the client degrades gracefully to chat/whiteboard-only participation rather than blocking the student from the class outright — and that degraded participation still counts toward attendance duration, since they're meaningfully present even without camera/mic.
- **Unauthorized join attempts**: rejected at the token-issuance step, not by trying to police the Agora channel itself after the fact — this is a deliberate "fail closed at the boundary" design, not a detection-and-kick approach.

---

## 8. Attendance Engine

### Business workflow

1. Join/leave events (from the Agora SDK, or a periodic heartbeat ping while in-call) write/update `attendance.join_time` / `leave_time` for that student+class.
2. **Multiple devices**: if a student joins from two devices (e.g., laptop + phone backup), the system takes the **union** of their connected-time intervals, not the sum — summing would overcount duration and inflate `attendance_percent` incorrectly.
3. At class end (or via a short-delay sweep job), `duration_seconds` is finalized from the merged intervals, and `attendance_percent = duration_seconds / (scheduled duration × 60) × 100`.
4. Status resolution: join within 10 min of start → `present` (downgraded to `partial` if `attendance_percent` ends up below 60% regardless of on-time join); join after 10 min → `late`; never joined → `absent` (set by the sweep job, not left blank — an enrolled student with zero attendance rows after a class completes is itself a bug to catch, not an expected state).
5. **Manual override**: a teacher/admin can override the auto-determined status (e.g., a platform-side issue unfairly marked someone absent) — recorded with `marked_method='manual_override'`, always with a note, since this directly affects credit deduction and shows up in parent-facing reports.

### Edge cases & failure handling

- **Platform-wide outage during a class** (detectable as a spike of simultaneous disconnects across one class, or across many classes at once): triggers automatic class-level remediation — the whole class is treated as platform-cancelled (no credit deduction for anyone in it, makeup class offered) rather than leaving it to accumulate individual manual-override requests after the fact. Detection threshold and the remediation trigger are an ops-configurable rule, not a hardcoded one-off.
- **Attendance data finalization races the credit-deduction job** (Phase 3a): the deduction job explicitly waits for attendance to be finalized for a class before processing it — it does not deduct against a still-accumulating duration.

---

## 9. Reschedule / Cancellation

### Student-initiated reschedule

1. Student/parent picks a proposed new slot (from the teacher's open availability) → `reschedule_requests` (`status='pending'`).
2. Validated against: monthly reschedule limit (computed live from `reschedule_requests`, not a stored counter — Phase 3b's anti-drift principle applies here too) and minimum advance notice (e.g., >24h before the original class).
3. Within policy and the new slot is genuinely conflict-free → `auto_approved`; otherwise routed to manual review (common for 1-on-1, where it specifically needs *that* teacher's availability, not just *any* slot).
4. On approval: new `live_classes` row (`class_type='makeup'`, `rescheduled_from_id` → original); original flips to `status='rescheduled'`.

**Group batch reschedules mean something different from 1-on-1 ones**: one student can't move the whole batch's time. For group batches, a "reschedule request" is really "I'll miss the regular session, arrange me a 1-off makeup" — a standalone `class_type='makeup'` session for just that student, layered on top of (not replacing) the batch's continuing regular schedule. The 1-on-1 case is a literal move of the one class that exists for that student.

### Platform/teacher-initiated cancellation

1. `live_classes.status='cancelled'` with `cancellation_reason` (`teacher_emergency` / `technical_outage` / `holiday` / `platform_maintenance`) and `cancelled_by`.
2. **No credit deduction**, unconditionally, for these reasons — enforced in the credit engine itself (Phase 3a), not just by convention here.
3. Automated makeup-class offer: system proposes the next available slot (same teacher preferred, else a qualified substitute matching the same level/language), student/parent confirms or picks an alternative from a short list.

### Edge cases & failure handling
- **Refunds** arising from cancellations are part of the broader Payments/Freeze workflow — covered in Phase 3g, not duplicated here, so the refund *business rules* live in exactly one place.
- **Repeated last-minute student-cancellations** (pattern, not a one-off): flagged for a risk/engagement signal (feeds Phase 3g's AI risk detection) rather than purely a scheduling event — a student who keeps last-minute-cancelling is often an early churn signal.

---

## 10. Teacher Change Request

### Business workflow

1. Student/parent submits a request with `reason` (teaching mismatch / language mismatch / schedule mismatch / complaint) + free-text `details` → `teacher_change_requests` (`status='pending'`).
2. Routed to academic ops (`status='under_review'`) — review checks both the individual request *and* whether it's part of a pattern (multiple students requesting change away from the same teacher is a teacher-performance signal, handled separately by Admin — it doesn't block resolving this individual student's request on its own timeline).
3. Decision:
   - **1-on-1**: approval directly reassigns the teacher (`new_teacher_id` set, `batch_allocation_log` reason `teacher_change`).
   - **Group**: approval moves the *student* to a different batch with a different teacher for the same course/level — the original batch and its teacher are untouched, since one student's preference isn't grounds for changing a whole group's teacher.
4. Either outcome (approved/rejected) is communicated with `resolution_notes` — a rejection always explains why (no alternative currently available, offered to wait, etc.), never a bare "no."

### Edge cases & failure handling
- **No alternative teacher available** (rare expertise/timezone/language combination): the request stays `under_review` with the student/parent kept informed of status rather than auto-rejected — auto-rejecting on the first pass would feel dismissive on something this personal (a teaching-style mismatch complaint, especially for a child) and works against the premium-product trust bar.

---

## Next

Phase 3d — Recordings, Video Library, Notes/Materials, Assignments (homework SLA engine). Say "continue."
