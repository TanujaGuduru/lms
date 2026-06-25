# Delivery Phase 4a — APIs: Conventions, Auth/Account, Enrollment, Credit Wallet

Covers the API conventions used across all of Phase 4, plus endpoints for lifecycle phases 1–3. Scope note: this is the **Student/Parent-facing API surface** specifically — counselor/teacher/admin actions (assigning leads, reviewing demo notes) are Admin/Teacher-portal concerns already largely served by the existing Admin panel's data, and aren't duplicated here. Where a student-facing flow depends on something an admin does (e.g., a demo being scheduled), that's noted but not given its own endpoint in this catalog.

---

## API Conventions (apply to every endpoint in Phases 4a–4f)

**Base path**: `/api/v1/...` — versioned from day one so a breaking v2 change (inevitable eventually) never forces a simultaneous mobile-app update.

**Auth**: Laravel Sanctum bearer tokens. `Authorization: Bearer {token}` on every authenticated request. Token issued at login, scoped to the user (student or parent — same auth mechanism, different permission set applied per-request based on role and, for parents, the specific `parent_student_links` permissions for whichever child is in context).

**Standard success envelope**:
```json
{ "success": true, "data": { /* ... */ }, "meta": { /* pagination etc, when relevant */ } }
```
**Standard error envelope**:
```json
{ "success": false, "message": "Human-readable summary", "errors": { "field_name": ["Specific validation message"] } }
```

**Pagination** (offset-based — consistent with the existing Admin panel's `paginate()` convention, not a different scheme for the new app):
```
GET /api/v1/whatever?page=2&per_page=20
```
```json
"meta": { "current_page": 2, "per_page": 20, "total": 143, "last_page": 8 }
```

**HTTP status conventions**: `400` malformed request, `401` unauthenticated, `403` authenticated but not permitted (e.g., parent lacks `can_view_billing`), `404` not found, `409` conflict (double-booking a slot), `422` validation/business-rule failure, `429` rate-limited (AI quota, sandbox execution throttling), `500` server error.

**Idempotency**: any endpoint that triggers a financial or irreversible side effect (credit purchase confirmation, certificate issuance trigger) accepts an optional `Idempotency-Key` header — a retried request with the same key returns the original result rather than double-processing. This matters more on GoDaddy specifically, where a cron-driven job or a flaky shared-hosting connection makes retries more likely than on a more reliable always-on infrastructure.

---

## Auth & Account

| Method | Path | Purpose | Auth |
|---|---|---|---|
| POST | `/auth/login` | Email+password login | Public |
| POST | `/auth/logout` | Revoke current token | Bearer |
| POST | `/auth/refresh` | Rotate token before expiry | Bearer |
| GET | `/auth/me` | Current user + role + linked children (if parent) | Bearer |
| POST | `/auth/forgot-password` | Trigger reset email | Public |
| POST | `/auth/reset-password` | Complete reset with token | Public |
| GET | `/account/profile` | Full profile (student_profiles + ai_profiles) | Bearer |
| PATCH | `/account/profile` | Update profile fields | Bearer |
| POST | `/account/complete-onboarding` | Submit the onboarding wizard | Bearer |

### `POST /auth/login`
**Request**:
```json
{ "email": "parent@example.com", "password": "••••••••" }
```
**Response (200)**:
```json
{
  "success": true,
  "data": {
    "token": "1|abcdef...",
    "user": { "id": 4821, "role": "parent", "first_name": "Anita", "linked_students": [
      { "student_id": 9931, "first_name": "Aarav", "can_view_billing": true, "can_view_recordings": true }
    ]}
  }
}
```
**Validation**: `email` required|email, `password` required. **Failure modes**: `401` invalid credentials (generic message — never reveals whether the email exists, same account-enumeration defense as the Admin panel's login). `403` if the account is `status='pending_consent'` (student trying to log in before a parent has granted consent) — response includes `"reason": "pending_consent"` so the client can render the right explanation rather than a bare permission error.

### `POST /account/complete-onboarding`
**Request**:
```json
{
  "interests": ["robotics", "game_dev"],
  "goals": ["build_portfolio", "crack_placement"],
  "coding_experience": "beginner",
  "preferred_language": "python"
}
```
**Response (200)**: updated profile object. **Validation**: `coding_experience` required|in:none,beginner,intermediate,advanced; `interests`/`goals` arrays of known tag values (validated against a small controlled vocabulary, not free text — this is what makes the AI recommendation engine in Phase 5 actually able to use these fields reliably).

---

## Parent Consent

| Method | Path | Purpose | Auth |
|---|---|---|---|
| GET | `/parent/consent-requests` | Pending consent items for the logged-in parent | Bearer (parent) |
| POST | `/parent/consent/{linkId}/initiate` | Start OTP/e-signature verification | Bearer (parent) |
| POST | `/parent/consent/{linkId}/grant` | Complete verification, grant consent | Bearer (parent) |
| POST | `/parent/consent/{linkId}/revoke` | Revoke a previously granted consent | Bearer (parent) |

### `POST /parent/consent/{linkId}/grant`
**Request**:
```json
{ "method": "otp_verified", "otp_code": "482913" }
```
**Response (200)**:
```json
{ "success": true, "data": { "link_id": 551, "consent_status": "granted", "consent_recorded_at": "2026-03-01T10:22:00Z" } }
```
**Side effect**: if this was the student's only pending consent gate, their account flips `pending_consent → active` in the same transaction — not a separate follow-up step that could fail independently and leave the account stuck.
**Validation**: `otp_code` required when `method='otp_verified'`, must match the OTP issued for this link within its validity window (default 10 minutes); expired/wrong code returns `422` with a specific, re-sendable error (not a generic failure).

---

## Credit Wallet

| Method | Path | Purpose | Auth |
|---|---|---|---|
| GET | `/wallet` | Current balance + status for the active enrollment | Bearer |
| GET | `/wallet/transactions` | Paginated ledger (the full append-only history) | Bearer |
| POST | `/wallet/purchase` | Initiate a credit top-up (returns a payment gateway session) | Bearer |
| POST | `/wallet/freeze` | Request a pause | Bearer |
| POST | `/wallet/resume` | Request resume | Bearer |

### `GET /wallet`
**Response (200)**:
```json
{
  "success": true,
  "data": {
    "wallet_id": 3310, "status": "active",
    "credits_purchased": 20, "credits_consumed": 14, "credits_balance": 6,
    "low_balance_threshold": 3, "expiry_date": "2026-06-30"
  }
}
```
Parent calling this for a linked child checks `can_view_billing` first — `403` with `"reason":"billing_not_visible_to_this_guardian"` if that specific parent link doesn't have billing visibility (the split-custody case from Phase 3g).

### `POST /wallet/freeze`
**Request**:
```json
{ "reason": "Family travel", "effective_date": "2026-04-01" }
```
**Response (200)**:
```json
{ "success": true, "data": { "wallet_id": 3310, "status": "active", "pending_freeze_effective": "2026-04-01" } }
```
Note `status` stays `active` until the effective date actually arrives — a scheduled job flips it on that date, rather than the API call itself immediately freezing access to classes already booked before then (Phase 3g's "freeze doesn't retroactively cancel an imminent class" rule, enforced here at the API/data level).
**Validation**: `effective_date` required|date|after_or_equal:today. **Business-rule failure (422)**: a wallet already `frozen` cannot be frozen again — returns a clear "already frozen since X" rather than a generic validation error.

---

## Next

Phase 4b — APIs for Assessment, Batch Allocation, Scheduling. Say "continue."
