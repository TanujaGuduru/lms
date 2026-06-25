# Delivery Phase 4f — APIs: AI Risk Detection, Parent Visibility, Monthly Parent Reports, Payments/Refunds/Freeze

Covers lifecycle phases 22–25. Conventions per `04a`. Reuses `credit_wallets`/`credit_transactions` (`02a`), `risk_scores` (`02d`), `parent_student_links` (`02a`, already extended with the `can_view_*` booleans), the existing Admin-panel `payments`/`support_tickets` tables, and `parent_reports`/`wallet_freeze_log` (`02e`/`03g`).

**Scope note on AI Risk Detection (22):** it has **no API surface of its own**. It's a nightly internal batch job (`03g` §22) that writes `risk_scores` and drives escalation through the Communication Engine (Phase 6) — there's nothing for a student/parent client to call. What *is* exposed below is the one parent-facing read that needs to exist somewhere: a softened advisory view, not the raw `score_value`/`contributing_factors` (those stay mentor/admin-internal — a numeric "dropout risk: 78" surfaced raw to a parent would cause more harm than the signal is worth without a human framing it).

---

## Risk Visibility (parent-facing slice of Phase 22)

| Method | Path | Purpose | Auth |
|---|---|---|---|
| GET | `/parent/students/{id}/risk-summary` | Advisory flag + recommended action, if any | Bearer (parent) |

### `GET /parent/students/{id}/risk-summary`
**Response (200) — nothing elevated**: `{ "success": true, "data": { "has_active_flag": false } }`
**Response (200) — elevated**:
```json
{
  "success": true,
  "data": {
    "has_active_flag": true,
    "recommended_action": "mentor_call_recommended",
    "message": "We've noticed a few missed classes recently — a quick check-in call might help.",
    "ptm_booking_available": true
  }
}
```
`recommended_action` is derived from `risk_scores.intervention_status` (Tier 4/5 → a call or PTM is actively offered, per `03g`'s staged-escalation table) — never the raw score or `contributing_factors`. **Deliberately not gated by any of the four `can_view_*` booleans** — none of them map to a wellbeing signal, and withholding "your child might benefit from a check-in" because a permission boolean happens to be off would be the wrong tradeoff; basic link validity (`consent_status='granted'`, not revoked) is the only check. A mentor's "reviewed, not actionable" dismissal (`03g`'s false-positive handling) is an Admin/Teacher-portal action — once dismissed, this endpoint reverts to `has_active_flag: false` for that episode automatically, with no separate API call needed on the parent side.

---

## Parent Visibility

| Method | Path | Purpose | Auth |
|---|---|---|---|
| GET | `/parent/children` | Linked students + this parent's permission booleans per child | Bearer (parent) |
| GET | `/parent/students/{id}/dashboard` | Aggregated overview (reads the same snapshot as `04e`'s progress analytics) | Bearer (parent) |
| GET | `/parent/students/{id}/attendance` | Proxies `04c`'s attendance endpoints, scoped to this child | Bearer (parent) |
| GET | `/parent/students/{id}/recordings` | Proxies `04c`'s recordings list, scoped to this child | Bearer (parent) |
| GET | `/parent/students/{id}/wallet` | Live balance — never the snapshot | Bearer (parent) |

### `GET /parent/children`
**Response (200)**:
```json
{
  "success": true,
  "data": [
    { "student_id": 9931, "first_name": "Aarav", "relationship": "father", "is_primary_guardian": true,
      "can_view_recordings": true, "can_view_billing": true, "can_view_attendance": true, "can_book_ptm": true }
  ]
}
```
Drives the child switcher — a parent linked to multiple children always sees a per-child list, never a merged view (`03g`'s explicit reasoning: each child's data stays attributed to that child).

### `GET /parent/students/{id}/wallet`
Same response shape as `04a`'s `GET /wallet`, requires `can_view_billing=1` on the link (`403` `"reason":"billing_not_visible_to_this_guardian"` already specified in `04a` for exactly this case) — called out again here because **this is the one parent view that is never served from a precomputed snapshot**, read live every time (`03g`'s explicit exception: stale academic trend data is an acceptable tradeoff, stale financial data shown to a parent is a trust problem).

Every endpoint in this section re-checks the relevant `can_view_*` boolean **and** `consent_status != 'revoked'` on every single request, never cached — a custody change or revoked consent cuts off access on the very next call, not on some delayed sync (`03g`'s explicit reasoning). A request for a student whose computed age has crossed the configured adult threshold returns `403` `"reason":"student_now_adult_consent_required"` rather than silently continuing on stale minor-era consent — the policy-moment flag `03g` calls out, enforced here at the API boundary rather than left as a dashboard-only notice.

---

## Monthly Parent Reports

| Method | Path | Purpose | Auth |
|---|---|---|---|
| GET | `/parent/students/{id}/reports` | List `parent_reports` for this child | Bearer (parent) |
| GET | `/reports/{id}` | Detail incl. `pdf_url`/`summary_text` | Bearer (parent) |
| POST | `/reports/{id}/viewed` | Mark `viewed_by_parent_at` | Bearer (parent) |

### `GET /reports/{id}`
**Response (200)**:
```json
{
  "success": true,
  "data": {
    "id": 771, "period_month": "2026-03-01", "is_partial_period": false,
    "pdf_url": "https://s3.../report.pdf", "summary_text": "Aarav showed strong improvement in..."
  }
}
```
`is_partial_period=true` renders with an explicit "partial month" label client-side rather than a normal report shape with numbers that would otherwise read as alarmingly low for a student who joined mid-month (`03g`'s stated reasoning). `POST .../viewed` is a one-way flag (an already-`viewed_by_parent_at` report ignores a repeat call rather than overwriting the timestamp) — its only purpose is engagement tracking, not anything that gates content.

---

## Payments / Refunds / Freeze

| Method | Path | Purpose | Auth |
|---|---|---|---|
| GET | `/payments?enrollment_id=` | Own (or, for a parent, child's) payment history — always live | Bearer |
| GET | `/payments/{id}` | Detail / receipt | Bearer |
| POST | `/payments/{id}/retry` | Re-attempt a failed payment | Bearer |
| POST | `/wallet/refund-request` | Opens a refund request | Bearer |
| GET | `/wallet/freeze-history` | Full freeze/resume audit trail | Bearer |

`POST /wallet/freeze` / `POST /wallet/resume` are already specified in `04a` — not repeated here.

### `POST /payments/{id}/retry`
Only valid when `payments.status='failed'`. **Response (200)**: a fresh gateway session (`gateway_order_id`), same shape as the initial `POST /wallet/purchase` response from `04a` — a retry is the same checkout flow re-entered, not a special-cased path. No access change happens before or during this — the grace period from `03g` means a failed payment alone never interrupts an active enrollment.

### `POST /wallet/refund-request`
**Request**:
```json
{ "reason": "Switching to a different batch schedule", "payment_id": 5510 }
```
**Response (200)**: `{ "success": true, "data": { "ticket_id": 8821, "ticket_number": "SUP-2026-08821", "status": "open" } }`
Creates a `support_tickets` row (category mapped to Payments) rather than an immediate refund — per `03g`, an admin reviews against policy (time window, unused-credits-only) before anything is approved. An approved refund is recorded as a `credit_transactions` row (`type='refund'`) on the wallet's existing ledger — already visible via `04a`'s `GET /wallet/transactions` with no separate "my refunds" endpoint needed. **Repeated refund requests from the same account** are flagged for fraud/risk review server-side (`03g`'s explicit note) — this endpoint still returns a normal `200`/ticket either way, since silently rejecting at request time would tip off exactly the abuse pattern being watched for.

### `GET /wallet/freeze-history`
**Response (200)**:
```json
{ "success": true, "data": [
  { "action": "frozen", "reason": "Family travel", "effective_date": "2026-04-01", "created_at": "2026-03-15T09:00:00Z" },
  { "action": "resumed", "effective_date": "2026-04-20", "created_at": "2026-04-18T11:00:00Z" }
]}
```
Reads `wallet_freeze_log` directly — kept as its own table rather than folded into `credit_transactions` precisely so a student who pauses/resumes several times over a course gets this full readable history (`03g`'s stated reasoning), distinct from the financial ledger.

---

## Next

Phase 4g — APIs for Course Completion, Certificates, Renewal/Upsell, Referrals, Support System. Say "continue."
