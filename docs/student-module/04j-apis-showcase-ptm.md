# Delivery Phase 4j — APIs: Achievement Showcase Wall, Parent-Teacher Meeting (PTM) Booking

Covers lifecycle phases 38–39. Conventions per `04a`. Schema from `02e` (`student_portfolios`/`portfolio_views`, `ptm_slots`/`ptm_bookings`/`ptm_summaries`). **This closes out Delivery Phase 4 — APIs in full.**

---

## Achievement Showcase Wall

| Method | Path | Purpose | Auth |
|---|---|---|---|
| GET | `/portfolio` | Own portfolio settings | Bearer |
| PUT | `/portfolio` | Create/update (slug, headline, bio, section toggles, `is_public`) | Bearer |
| GET | `/portfolio/preview` | Own full aggregated view, as it would render publicly — even while `is_public=0` | Bearer |
| GET | `/public/portfolio/{slug}` | The actual public page | Public |

### `PUT /portfolio`
**Request**: `{ "slug": "aarav-codes", "headline": "...", "show_certificates": true, "show_badges": true, "show_projects": true, "is_public": true }`
**Validation**: `422` `"reason":"slug_taken"` (`uk_portfolio_slug`); `422` `"reason":"inappropriate_slug"` from a basic content filter — the student can pick a pseudonym instead of their full legal name if they'd rather not have that public (`03k`'s explicit option). **Turning `is_public` off always takes effect immediately, for any student** — only the **off → on** transition is gated for a still-minor student:
```json
{ "success": true, "data": { "is_public": false, "status": "pending_parent_approval" } }
```
This reuses the **same** `parent_student_links` consent framework as account activation (`04a`) rather than a new mechanism (`03k`'s explicit point) — a minor toggling this alone, with no parent visibility into the decision, is exactly the gap the consent-first posture from Phase 1 exists to close, given the page carries a child's name and work to an audience that can include recruiters and strangers. The parent side of this gate is below.

### `GET /public/portfolio/{slug}`
No auth — has to be reachable by recruiters/family with no platform account (`03k`'s explicit reasoning). Aggregates live from `published_projects` (already mentor-approval-gated, `04e`), `certificates`, `student_badges`/`student_xp` (`04h`) — nothing duplicated into `student_portfolios` itself. `portfolio_views` increments per call, deduplicated by IP hash within a short window entirely server-side — refreshing your own page repeatedly doesn't inflate the count, and there's nothing for a client to manage here. A `published_projects` row an admin later revokes (e.g. plagiarism found after the fact) simply stops appearing on the next read — the row itself, and the original approval, stay intact for audit (`03k`'s explicit point); no separate "unpublish" call is needed on this side.

### Parent-side approval (the minor gate from above)

| Method | Path | Purpose | Auth |
|---|---|---|---|
| GET | `/parent/students/{id}/portfolio` | Preview the pending/live portfolio | Bearer (parent) |
| POST | `/parent/students/{id}/portfolio/approve` | Approve going public | Bearer (parent) |

Not gated by any of the four `can_view_*` booleans (`04f` made the same call for the risk-summary endpoint) — this is a distinct consent action with its own meaning, not an information-visibility setting. Approving flips `is_public=1` immediately; the student reverting it back to private at any point doesn't require re-approval to go public again later — each off→on transition re-triggers this same gate.

---

## Parent-Teacher Meeting (PTM) Booking

| Method | Path | Purpose | Auth |
|---|---|---|---|
| GET | `/ptm/slots?student_id=&meeting_type=` | Open slots, filtered to hosts relevant to this child/meeting type | Bearer (parent) |
| POST | `/ptm/bookings` | Book a slot | Bearer (parent) |
| GET | `/ptm/bookings` | Own bookings, past and upcoming | Bearer (parent) |
| GET | `/ptm/bookings/{id}` | Detail, incl. summary once the meeting is complete | Bearer (parent) |
| POST | `/ptm/bookings/{id}/cancel` | Cancel, frees the slot | Bearer (parent) |

### `GET /ptm/slots`
Already excludes anything with `is_booked=1`, and is filtered to **hosts actually relevant to this child and this meeting type** — the child's own teacher for a `progress_review`, an academic head specifically for `concern_discussion`/`performance_intervention` — never an undifferentiated list of every host on the platform (`03k`'s explicit point); host relevance is resolved server-side from the student's actual batch/teacher assignment, not a client-supplied filter the parent has to get right.

### `POST /ptm/bookings`
**Request**: `{ "slot_id": 991, "student_id": 9931, "meeting_type": "progress_review", "pre_meeting_notes": "..." }`
**Response (200)**: `{ "success": true, "data": { "booking_id": 5510, "meeting_link": "https://...", "status": "scheduled" } }`
**What actually prevents two parents racing to book the same slot is `ptm_bookings.uk_ptm_slot` (a DB unique constraint on `slot_id`), not a disabled button** — the losing concurrent request gets `409` `"reason":"slot_already_booked"` (`03k`'s explicit reasoning for why this can't be a UI-only safeguard). If this parent has a connected calendar (`04i`), the booking syncs to it automatically — no separate client call. `meeting_link` points at a lighter-weight video/audio room than the internal classroom — deliberately without the sandbox/whiteboard/AI-panel machinery built for student classes, since none of that applies to a teacher-and-parent conversation (`03k`'s explicit scoping).

### `GET /ptm/bookings/{id}`
Once `status='completed'`, the response includes the `ptm_summaries` row (`summary`/`action_items`/`follow_up_date`) if one exists. **There's no endpoint here for the parent to write a summary** — `ptm_summaries.created_by` is the host (teacher/mentor/academic head), an Admin/Teacher-portal action; a completed booking with no summary attached simply hasn't had one written yet, since `03k` enforces the summary as a precondition for marking the booking complete on that side, not something this API needs to re-check. A `status='no_show'` or `status='cancelled'` (the latter set by the host on a last-minute cancellation, with a rebooking notification per Phase 6) is likewise written by the host/admin side — this endpoint only ever reflects it.

### `POST /ptm/bookings/{id}/cancel`
Parent-initiated; frees the slot (`ptm_slots.is_booked=0`) for someone else to take. `03k` doesn't specify a minimum-notice cutoff for a parent-side cancellation (only for a host-side one), so none is invented here — any `scheduled` booking can be cancelled by the parent who made it.

---

## Delivery Phase 4 — APIs: complete

All ten sub-parts (`04a`–`04j`) are done, covering the full student/parent-facing REST surface for every lifecycle phase from `02`/`03`, including the two corrections made along the way: the `assignments`/`assignment_submissions` schema mistake fixed in `04d`, and the discovery that lifecycle phase 14 (Assignments) had no API home until then.

## Next

**Delivery Phase 5 — AI Workflows** (RAG retrieval mechanics, system prompts, model selection, cost control across the AI Gateway). Say "continue."
