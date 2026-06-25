# Delivery Phase 4g — APIs: Course Completion, Certificates, Renewal/Upsell, Referrals, Support System

Covers lifecycle phases 26–30. Conventions per `04a`. Reuses the existing Admin-panel `enrollments`/`certificates`/`certificate_templates`/`support_tickets`/`ticket_replies`/`support_categories` tables as-is — no amendments needed in this phase. Adds endpoints for the three new tables `03h` defines (`course_recommendations`, `referral_codes`, `referrals`).

---

## Course Completion

Completion itself is computed server-side by an event-driven check (final assessment submitted, last required assignment graded — `03h` §26), never a client-triggered action. The API surface here is read-only status.

| Method | Path | Purpose | Auth |
|---|---|---|---|
| GET | `/enrollments/{id}` | Status, progress, completion/grace-period state | Bearer |
| GET | `/enrollments/{id}/completion-requirements` | Checklist of what's still outstanding | Bearer |

### `GET /enrollments/{id}/completion-requirements`
**Response (200)**:
```json
{
  "success": true,
  "data": {
    "attendance_met": true, "required_assignments_complete": false,
    "capstone_complete": true, "final_assessment_passed": true, "wallet_disputes_clear": true,
    "grace_period_ends_at": "2026-03-20T00:00:00Z"
  }
}
```
`grace_period_ends_at` is only present once the course's nominal end date has passed without every requirement met — the explicit, non-final "still time to finish" state `03h` calls out, distinct from both `completed` and a hard `incomplete`. If a requirement was unmet because of a platform-side issue (an outage during a final exam), that incident's manual extension/retry (`04e`'s exam-attempt handling) resolves it *before* this checklist is evaluated — a student is never shown a red flag here for something that wasn't their fault.

---

## Certificates

| Method | Path | Purpose | Auth |
|---|---|---|---|
| GET | `/certificates` | List own | Bearer |
| GET | `/certificates/{id}` | Detail | Bearer |
| GET | `/certificates/{id}/download` | Signed PDF URL | Bearer |
| POST | `/enrollments/{id}/confirm-certificate-name` | Confirm/correct the legal name to print, pre-issuance | Bearer |
| POST | `/certificates/{id}/reissue-request` | Lost certificate / post-issuance name correction | Bearer |

### `POST /enrollments/{id}/confirm-certificate-name`
**Request**: `{ "name_on_certificate": "Aarav Sharma" }`
**Response (200)**: `{ "success": true, "data": { "confirmed": true } }`
Fires as part of the near-completion flow, **before** auto-issuance — issuance is otherwise fully automatic against objectively-met criteria (`03h`'s explicit distinction from project-portfolio publishing, which needs a human approval gate; a completion certificate doesn't). This call exists specifically so a nickname used at enrollment never silently ends up on a credential — skipping it just means the certificate generates from `users.first_name`/`last_name` as-is.

### `POST /certificates/{id}/reissue-request`
**Response (200)**: `{ "success": true, "data": { "certificate_id": 1182, "status": "regenerating" } }`
**Regenerates the PDF under the same `certificate_number`/`verification_code`** — never a new certificate row (`03h`'s explicit reasoning: it's the same achievement, and a second verification code would make it look like two were earned). The existing public verification page (`Public\CertificateController::verify()`) needs no change either way, since the code it looks up never changes.

---

## Renewal / Upsell

| Method | Path | Purpose | Auth |
|---|---|---|---|
| GET | `/recommendations` | Own ranked next-course list | Bearer |

### `GET /recommendations`
**Response (200) — a fit exists**:
```json
{ "success": true, "data": [
  { "id": 4471, "recommended_course_id": 88, "title": "Python Intermediate", "confidence_score": 82, "reason_summary": "Builds directly on your completed Python Beginner course" }
]}
```
**Response (200) — no good fit yet**: `{ "success": true, "data": [], "meta": { "state": "explore_other_tracks" } }` — `03h`'s explicit "graceful no-fit state" rather than forcing a poor-fit suggestion just to populate the screen. The full ranking mechanics are Phase 5's concern; this endpoint just serves whatever the engine already computed. **Every row returned with `shown_at IS NULL` gets `shown_at` set to now as a side effect of this call** — the same "the request is the access event" pattern `04c` already uses for material downloads — which is what makes the recommendation engine's hit rate measurable later (`converted_at` is set by a backend job watching for a matching new enrollment, never by a client call).

---

## Referral System

| Method | Path | Purpose | Auth |
|---|---|---|---|
| GET | `/referrals/my-code` | Own referral code (generated on first call if none exists yet) | Bearer |
| GET | `/referrals` | Own referrals + status + reward status | Bearer |

### `GET /referrals/my-code`
**Response (200)**: `{ "success": true, "data": { "code": "AARAV4821", "share_url": "https://codegurukul.example/join?ref=AARAV4821" } }`
`referral_codes` has exactly one row per user (`uk_referral_user`) — generated lazily on first request rather than at account creation, since most users never visit this screen. There's deliberately no "create a referral" endpoint: a new lead arriving via `?ref=` is captured as ordinary lead-attribution metadata by the existing public lead-capture flow (`04a`'s lifecycle-1 territory), not a parallel referral funnel (`03h`'s explicit reasoning).

### `GET /referrals`
**Response (200)**:
```json
{ "success": true, "data": [
  { "referral_code": "AARAV4821", "status": "converted", "reward_type": "credits", "reward_value": 5, "reward_status": "approved", "converted_at": "2026-02-10T12:00:00Z" }
]}
```
`reward_status` moving `pending → approved → paid` is entirely an ops/finance action (fraud signals — same payment method, same household — get checked before approval, per `03h`) — nothing here lets a student push their own reward forward. A `credits` reward, once approved, lands as an ordinary `credit_transactions` (`type='bonus'`) row, already visible via `04a`'s `GET /wallet/transactions` with no separate payout endpoint needed.

---

## Support System

| Method | Path | Purpose | Auth |
|---|---|---|---|
| GET | `/support/categories` | Active categories, for the ticket form | Bearer |
| GET | `/support/tickets` | List own | Bearer |
| POST | `/support/tickets` | Create | Bearer |
| GET | `/support/tickets/{id}` | Detail + replies | Bearer |
| POST | `/support/tickets/{id}/replies` | Add a reply | Bearer |
| POST | `/support/tickets/{id}/satisfaction` | Rate after resolution | Bearer |

### `GET /support/tickets/{id}`
**Response (200)** includes `replies: [...]` — **`ticket_replies` rows with `is_internal_note=1` are always filtered out** for this role, regardless of who the requester is; internal staff notes are an Admin/Teacher-portal concern and never cross into the student/parent-facing API, full stop. **Category-based routing** (payment tickets to finance, academic to a mentor, technical to platform support) and the **SLA clock** (`support_categories.sla_hours`, breach auto-escalation) are both entirely server/ops-side — `first_response_at` is a read-only field here, never something this API sets.

### `POST /support/tickets/{id}/satisfaction`
**Request**: `{ "satisfaction_rating": 5, "satisfaction_feedback": "Resolved quickly" }`
**Validation**: `422` `"reason":"ticket_not_resolved"` if `status` isn't `resolved`/`closed` — rating a still-open ticket isn't meaningful feedback on a resolution that hasn't happened yet. A ticket's `priority`/category can still be corrected by support staff after creation (`03h`'s "mis-categorized urgency" edge case) — that's an Admin-portal re-triage action, not exposed for the student to change themselves once submitted.

---

## Next

Phase 4h — APIs for Gamification, Digital Notebook, Collaborative Coding. Say "continue."
