# Delivery Phase 3g — Workflows: AI Risk Detection, Parent Visibility, Parent Reports, Payments/Freeze

Covers lifecycle phases 22–25. Tables from `02a` (credit wallet) and `02d` (`risk_scores`); two small new tables added below.

---

## 22. AI Risk Detection

### Business workflow

1. A nightly batch job computes risk signals per active student: attendance trend (declining %), assignment-completion trend, AI-conversation frequency/sentiment drop, repeated low assessment scores, reschedule/cancellation patterns (the "repeated last-minute cancellation" flag from Phase 3c feeds directly in here), and engagement drop (days since last login/activity).
2. Each signal type writes its own `risk_scores` row (`score_type`: `dropout_risk` / `low_engagement` / `weak_topic` / `absenteeism`), with `contributing_factors` capturing the *why* (e.g., `{"attendance_decline":"-30% over 3 weeks","missed_assignments":2}`) — a risk score without a visible reason is useless to whoever has to act on it.
3. **Escalation is staged and strictly forward-only**, governed by `intervention_status`:
   - Tier 1 (score ≥40): AI nudge — personalized in-app encouragement/check-in.
   - Tier 2 (≥60): WhatsApp message to student/parent.
   - Tier 3 (≥75): Email to parent with specifics.
   - Tier 4 (≥85): Mentor call scheduled (creates an academic-team task).
   - Tier 5 (≥95, or sustained at Tier 4 without improvement): formal parent escalation, with a PTM booking actively offered.
4. **Idempotency/escalation discipline**: before triggering a tier, the job checks `intervention_status` — it only ever moves *forward* (never re-sends a lower tier once a higher one has fired) unless the score has genuinely dropped back down and a fresh risk episode starts. Without this check, a student sitting at "WhatsApp sent" would get re-pinged every single night the job runs, which is exactly the kind of notification fatigue that makes parents tune out alerts altogether — undermining the whole point of having an escalation system.

### Edge cases & failure handling
- **False positive** (a planned vacation explains the attendance dip, student is fine): mentors get a "reviewed, not actionable" dismissal action on a `risk_scores` row — this doesn't delete the signal (it stays for audit/pattern history) but stops it from escalating further, and doesn't suppress *future*, genuinely new risk detection for that student.
- **Score improves after intervention**: `intervention_status` can reach a `resolved`-equivalent end state, after which the next computation cycle evaluates fresh rather than treating the student as permanently "already escalated."
- **Multiple risk types firing in the same window** (a student can be both `dropout_risk` and `weak_topic` at once): each gets its own row and its own escalation track, but the *notification layer* (Phase 6) batches co-occurring alerts for the same student into one consolidated message rather than three separate ones landing in a parent's WhatsApp in the same week — the risk detection and the notification dispatch are deliberately separate concerns so this batching can happen without complicating the scoring logic itself.

---

## 23. Parent Visibility

### Business workflow

1. A parent's portal view is a distinct role/nav within the same Laravel + React app (not a separate product) — every screen (attendance, assignments, recordings, progress, payments, reports) is filtered through that specific parent-student link's permission booleans (`can_view_recordings`/`can_view_billing`/`can_view_attendance`/`can_book_ptm`, from Phase 2a). A parent linked to multiple children gets a child switcher, not a merged view — each child's data stays attributed to that child.
2. Most views read the same precomputed snapshots used for student-facing progress analytics (Phase 3f) — acceptable staleness for academic trend data. **Billing/payments is the one exception**: always read live, never from a snapshot, since stale financial data shown to a parent is a trust problem in a way stale "completion %" simply isn't.

### Edge cases & failure handling
- **Split custody / divorced parents**: two parent accounts can be linked to the same student with *different* permission sets (one sees billing, the other doesn't) — this is exactly why Phase 2a used per-permission booleans on the link itself rather than a single "parent role."
- **Permission revoked mid-course** (custody change, court order): support/admin sets the relevant booleans to 0 / `consent_status='revoked'` — checked live on every request, never cached, so access is cut off immediately rather than on some delayed sync.
- **Student turns 18 during the course**: this is a genuine policy moment, not something to silently ignore. The system flags any account where computed age crosses the configured adult threshold for admin review — the default assumption shifts toward the now-adult student's own privacy/autonomy, with parent visibility requiring the student's own fresh consent going forward rather than continuing indefinitely on the original minor-era consent.

---

## 24. Monthly Parent Reports

```sql
CREATE TABLE `parent_reports` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` INT UNSIGNED NOT NULL,
  `enrollment_id` INT UNSIGNED NOT NULL,
  `period_month` DATE NOT NULL COMMENT 'first day of the reporting month',
  `is_partial_period` TINYINT(1) DEFAULT 0,
  `pdf_url` VARCHAR(500) DEFAULT NULL,
  `summary_text` TEXT DEFAULT NULL COMMENT 'AI-generated strengths/weaknesses/recommendations — see Phase 5',
  `generated_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `viewed_by_parent_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_report_period` (`student_id`, `enrollment_id`, `period_month`),
  FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Business workflow

1. A monthly job (calendar-month boundary, not a rolling 30-day-since-enrollment window — predictable for parents and for ops) pulls each active enrollment's latest `student_progress_snapshots`, recent `risk_scores`, attendance trend, and recent achievements (badges/certificates), feeds it to the AI Gateway for a natural-language strengths/weaknesses/recommendations summary (Phase 5), and renders the whole thing as a PDF stored in S3.
2. Delivery fans out per the family's configured channels: email (PDF attached or linked), WhatsApp (a condensed text summary + link to the full PDF) — and if the student's risk score is elevated that month, a mentor call is **proactively offered**, not just mentioned as available.
3. **Mid-month enrollments get a clearly labeled partial-period report** (`is_partial_period=1`) rather than a full-month-shaped report with misleadingly low numbers — a student who joined on the 20th showing "43% attendance" for the month without context would alarm a parent over nothing.
4. **Per-child reports, not a consolidated family report**, even when a family has multiple enrolled children — each child's academic journey is distinct enough that merging them would obscure rather than clarify, even though they can be delivered together in one communication batch (Phase 6) for convenience.

---

## 25. Payments / Refunds / Freeze (student-side)

### Business workflow

- **Renewal**: triggered by low/exhausted credits (Phase 3a) or approaching course completion — purchase flows into the *same* wallet if it's a top-up on the current enrollment, or a *new* `credit_wallets` row if it's actually a new course enrollment (these are deliberately not conflated, since mixing two different course's credit accounting in one wallet would make the ledger unreadable).
- **Payment failure**: gateway webhook reports failure → a retry prompt (update card, retry) with a grace period — no access change yet, and this uses its own reminder sequence distinct from low-credit alerts (Phase 6), since "your payment failed" and "you're running low on classes" are different messages with different urgency.
- **Refund request**: submitted via the existing support-ticket system (`category='payment'`) or a dedicated refund flow — admin reviews against policy (time window, unused-credits-only, etc.); an approved refund writes a `credit_transactions` (`type='refund'`) row and a corresponding gateway-side refund record, fully audit-logged with the approving admin.
- **Freeze (pause)**: parent/student requests a pause → `credit_wallets.status='frozen'`. **Takes effect from a specified date**, not necessarily immediately — any class already scheduled before the effective freeze date completes normally rather than being yanked mid-cycle. While frozen: no deductions, and no reminder/alert spam (low-balance nudges, assignment reminders) for that enrollment.
- **Resume**: wallet flips back to `active`, and `expiry_date` is extended by the exact frozen duration — a paused student never loses access time they already paid for.

```sql
CREATE TABLE `wallet_freeze_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `wallet_id` BIGINT UNSIGNED NOT NULL,
  `action` ENUM('frozen','resumed') NOT NULL,
  `reason` VARCHAR(255) DEFAULT NULL,
  `effective_date` DATE NOT NULL,
  `requested_by` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_freeze_wallet` (`wallet_id`, `created_at`),
  FOREIGN KEY (`wallet_id`) REFERENCES `credit_wallets`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Why a dedicated log instead of folding this into `credit_transactions`**: freezing/resuming doesn't move any credits — recording it as a zero-amount "transaction" would pollute a ledger that's supposed to be a clean financial audit trail with non-financial events. A student who freezes and resumes multiple times over a course's lifetime gets a full, readable history here instead of a single status flag with no memory of past cycles.

### Edge cases & failure handling
- **Freeze requested with a class scheduled for tomorrow**: resolved by the effective-date mechanism above — nothing auto-cancels retroactively or ambiguously.
- **Repeated refund requests from the same account** (potential abuse pattern): flagged for fraud/risk review rather than auto-approved or auto-denied — this feeds the broader security/fraud detection posture from Phase 1 §8, revisited with full detail in the dedicated security pass later in this program.

---

## Next

Phase 3h — Course Completion, Certificates, Renewal/Upsell, Referrals, Support System. Say "continue."
