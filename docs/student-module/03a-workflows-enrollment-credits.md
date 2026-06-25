# Delivery Phase 3a — Workflows: Lead→Enrollment, Account Creation, Credit Engine

Covers lifecycle phases 1–3. Tables referenced are from `02a-schema-enrollment-billing.md`. API contracts, WhatsApp/email templates, AI prompt details, and scaling notes are intentionally **not** duplicated here — they get their own full treatment in Delivery Phases 4, 5, 6, and 7 respectively, so this document stays focused on business + backend workflow, screens, and edge cases.

---

## 1. Demo → Enrollment

### Business workflow

1. **Lead capture** — website form / ad campaign / referral / walk-in → `leads` row, `status='new'`, deduplicated against existing `leads` and `users` by phone+email before insert (prevents a re-submitted form creating a duplicate lead).
2. **Counselor assignment** — round-robin or manual → `assigned_counselor_id` set, `status='contacted'` once first outreach happens.
3. **Demo scheduling** — counselor books a slot with an available teacher matching the lead's interest/age band → `demo_sessions` row, `status='scheduled'`. Demo runs inside the same internal classroom infrastructure as real classes (Phase 3c), just flagged as a demo session (no credit implications).
4. **Demo delivery** — teacher conducts the demo; during/after, logs go into `demo_notes` (`teacher_observation`), counselor logs `counselor_remark`/`objection` entries, and a `demo_skill_assessments` row captures coding/logical/communication scores with a recommended level + course.
5. **Demo outcome**: `demo_sessions.status` → `completed` / `no_show` / `cancelled`.
6. **Conversion**: payment completes → `lead_conversions` row links `lead_id` → the resulting `enrollment_id` + `payment_id`. The lead's interested course and the enrolled course can differ (counselor pivots, or an upsell happens during the demo) — `lead_conversions` simply records what was actually bought.
7. **Loss**: if the lead doesn't convert, `leads.status='lost'` with `lost_reason` captured for funnel analytics.

### UI screens
- Public demo-booking page (pre-account, just name/phone/age/course interest)
- Counselor console: lead pipeline (Kanban-style by status), demo notes entry, objection logging
- Teacher: demo session view (same classroom UI as a real class, with an assessment form overlay)

### Edge cases & failure handling
- **No-show**: triggers an automated re-engagement sequence (not a dead end) — handled in Phase 6.
- **Lead returns after being marked lost**: reactivate the *same* `leads` row (status reset to `contacted`) rather than creating a duplicate — preserves the full history for counselor context. Only create a fresh row if the original is older than a configurable staleness window (default 6 months).
- **Multiple demos for one lead** (first inconclusive): just multiple `demo_sessions` rows under the same `lead_id` — no special handling needed, the schema already supports it.
- **Demo teacher cancels last-minute**: reuses the same reschedule mechanics as a real class (Phase 3c) rather than a separate code path.

---

## 2. Student Account Creation

### Business workflow

1. **Payment webhook fires** (existing `payments` table + gateway callback) → triggers account provisioning, wrapped in a single DB transaction so it's all-or-nothing:
   - If the email/phone already matches an existing `users` row (sibling enrollment, repeat purchase) → **reuse** that user, do not create a duplicate. Just add a new `enrollments` row + new `credit_wallets` row tied to it.
   - If new → create `users` (role=student), `student_profiles`, `ai_profiles` (defaults), `enrollments`, `credit_wallets` (seeded with `credits_purchased` = package size).
2. **Age check** (computed from `date_of_birth`, not stored): if under the configured minor threshold, the account is created with status `pending_consent` — fully provisioned in the database, but **gated from logging in/joining classes** until consent clears.
3. **Parent linking**: if a parent account already exists (captured during demo, or a returning family), link via `parent_student_links`; otherwise create the parent `users` row and send an account-setup link. `consent_status='pending'` on the link until the parent completes OTP/e-signature verification.
4. **Consent clears** → `parent_student_links.consent_status='granted'`, `consent_recorded_at` set → student account flips `pending_consent` → `active`.
5. **Onboarding sequence** begins (profile completion wizard: interests, goals, coding experience, preferred language — feeds `ai_profiles` for personalization).

### UI screens
- Post-payment "Welcome" / account-claim screen (sets password, confirms details)
- Parent consent screen (OTP-verified or e-signature, plain-language explanation of what's being consented to — recordings, data use, communication)
- Student profile-completion wizard (interests/goals/coding experience/preferred language)

### Edge cases & failure handling
- **Payment succeeds, provisioning crashes mid-way**: the whole block is one transaction — a crash rolls back cleanly, and a reconciliation job (cron-driven) scans for `payments` with `status='success'` that have no matching `enrollments` row and retries provisioning, keyed by `payments.id` so it's safe to retry (won't double-create).
- **Parent never completes consent**: automated reminders at increasing intervals (Phase 6); after a configurable window (default 14 days) it escalates to manual counselor outreach; after 30 days, the business decides per policy whether to treat it as a churned/refundable enrollment — this is a policy switch, not a hardcoded deadline, since different course price points may warrant different patience.
- **International student, different age-of-majority context**: the minor threshold is a setting per country code, not a single global constant.

---

## 3. Credit-Based Learning Engine

### Business workflow — deduction

Runs as an async job triggered when a `live_classes` row transitions to `completed` (Phase 3c covers the classroom side of that transition):

1. Look up the applicable `credit_deduction_policies` row for the class's course (falling back to the global default if no course-specific policy exists).
2. Read `attendance.attendance_percent` for the student/class.
3. Decision:
   - **Attendance ≥ threshold** → deduct 1 credit: insert `credit_transactions` (`type='consumption'`, `amount=-1`, `related_class_id`), update `credit_wallets.credits_consumed`/`credits_balance`.
   - **Attendance < threshold, partial exit allowed by policy** → deduct the partial amount (see note below on fractional credits).
   - **Class cancelled with reason `teacher_emergency` / `technical_outage` / `platform_maintenance`** → **no deduction**, full stop, regardless of any attendance recorded.
   - **Class cancelled by `student_emergency`** → no deduction if the cancellation/reschedule request met the advance-notice rule (Phase 3b); a late cancellation may incur a deduction per policy.
4. After any deduction, check `credits_balance` against `low_balance_threshold`. If just crossed (not already alerted for this threshold-crossing — checked against `credit_alerts_log`), queue a low-balance notification. If balance hits 0, set `credit_wallets.status='exhausted'` and trigger the renewal/upsell sequence (Phase 3h).

**On fractional ("partial") deductions**: the schema as designed keeps `credit_transactions.amount` as a signed integer, and the recommended default is **binary** — either a full 1-credit deduction or none — because true fractional credits (e.g., 0.5) would require widening `credit_wallets`/`credit_transactions` numeric columns to `DECIMAL`, and most credit-pack pricing (e.g., "20 classes = 20 credits") doesn't actually need fractional accounting to make business sense. If the business specifically wants fractional partial-exit deductions, that's a one-column schema amendment (`INT` → `DECIMAL(6,2)`) flagged here rather than baked in by default, since it's a real but optional complexity tradeoff worth a deliberate decision rather than a silent default.

**Idempotency (critical for billing trust):** the deduction job must never double-charge a class. Enforced by checking for an existing `credit_transactions` row with that `related_class_id` before inserting — if a cron run is interrupted and re-triggered, already-processed classes are simply skipped. Each class is deducted inside its own small transaction (not one giant transaction for a whole batch), so a mid-batch crash on GoDaddy's cron-driven job runner only ever loses *unprocessed* progress, never corrupts already-committed deductions.

### Business workflow — purchase / refund / freeze

- **Purchase/top-up**: payment success → `credit_transactions` (`type='purchase'`, positive amount) → if wallet was `exhausted`/`expired`, flip back to `active`.
- **Refund** (e.g., a credit was wrongly deducted for a teacher no-show that was mis-flagged): support/admin action → `credit_transactions` (`type='refund'`), fully audit-logged with the reason and the actor who issued it.
- **Freeze** (pause): `credit_wallets.status='frozen'`, `frozen_reason` set; no deductions process while frozen; on resume, `expiry_date` is pushed out by the frozen duration so a paused student doesn't lose access time they already paid for. Full pause/resume *business* workflow (parent-initiated freeze requests, approval, etc.) is detailed in Phase 3g (Payments/Freeze).

### UI screens
- Student/parent dashboard: credit balance widget (prominent, always visible), full transaction history (the ledger, read-only and permanent), "buy more credits" CTA appearing automatically once low-balance threshold is crossed
- Admin/support: manual adjustment screen (refunds, bonus credits) — every action here writes a `credit_transactions` row with `created_by` set, never a silent balance edit

### Edge cases & failure handling
- **Attendance data arrives late** (e.g., the classroom system's attendance write lags the class-completion event): the deduction job only processes classes where attendance has actually been finalized — it waits/retries rather than deducting against incomplete data and having to reverse it later.
- **Balance/ledger drift**: a nightly reconciliation job re-sums each wallet's `credit_transactions` and compares to the cached `credits_balance`; any mismatch is an application bug, logged loudly rather than silently auto-corrected, since silently rewriting a balance is exactly the kind of thing that erodes billing trust if it's ever wrong in the student's favor or the company's.

---

## Next

Phase 3b — Skill Assessment, Batch Allocation, Class Scheduling workflows. Say "continue."
