# Delivery Phase 3h — Workflows: Completion, Certificates, Renewal/Upsell, Referrals, Support

Covers lifecycle phases 26–30. Reuses existing `enrollments`/`certificates`/`certificate_templates`/`support_tickets` tables; adds three small new tables below.

---

## 26. Course Completion

### Business workflow

1. A completion check runs whenever a relevant event happens (final assessment submitted, last required assignment graded) rather than only on a fixed schedule, evaluating: minimum attendance (e.g., ≥75% overall), required assignments complete, capstone project complete (if the course has one), final assessment passed, and credit consumption consistent with expectations (no unresolved disputes on the wallet).
2. All conditions met → `enrollments.status='completed'`, `completed_at` set — cascades into certificate eligibility (§27), a completion notification, a portfolio-publishing prompt, and the renewal/upsell trigger (§28).
3. **Course timeline ended but requirements aren't fully met** is its own explicit state, not silently lumped into "completed" or left ambiguous — a configurable grace period after the nominal end date gives the student a real chance to finish remaining requirements before the system finalizes the enrollment as `incomplete`. Declaring "incomplete" the instant the calendar date passes, with zero buffer, would punish a student who's 95% done over a technicality.

### Edge cases & failure handling
- **A platform-side issue affected a key assessment** (an outage during a final exam, say): that incident's resolution (manual extension/retry, per Phase 3f) happens *before* the completion check runs against it — a student's completion status is never penalized for something that was the platform's fault, not theirs.

---

## 27. Certificates

Builds entirely on the existing `certificates`/`certificate_templates` tables and the public verification page already built (`Public\CertificateController::verify()`).

### Business workflow

1. Confirmed course completion **auto-triggers** certificate issuance — no manual approval gate here, unlike project-portfolio publishing (Phase 3f). The distinction is deliberate: a completion certificate is issued against objectively-met criteria (attendance/assessment/assignment thresholds already checked), whereas portfolio publishing is a subjective judgment call about whether specific work is public-presentable. One needs a human in the loop; the other doesn't.
2. The certificate carries its existing `verification_code` (QR-linkable to the public verification page), `certificate_number`, and is downloadable as PDF and shareable into the achievement wall (Phase 3k).

### Edge cases & failure handling
- **Name mismatch** (a nickname was used during enrollment, doesn't match the legal name wanted on a credential): a confirm/correct step before final PDF generation, rather than auto-generating from whatever's in `users.first_name`/`last_name` and discovering the mismatch after the fact.
- **Re-issuance request** (lost certificate, name correction after the fact): **regenerates the PDF under the same `certificate_number`/`verification_code`**, not a new certificate row — a re-issuance is the same achievement, and creating a second record with a different verification code would make it look like two separate certificates were earned.

---

## 28. Renewal / Upsell

```sql
CREATE TABLE `course_recommendations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `student_id` INT UNSIGNED NOT NULL,
  `source_enrollment_id` INT UNSIGNED DEFAULT NULL,
  `recommended_course_id` INT UNSIGNED NOT NULL,
  `confidence_score` TINYINT UNSIGNED DEFAULT NULL COMMENT '0-100',
  `reason_summary` VARCHAR(255) DEFAULT NULL,
  `shown_at` DATETIME DEFAULT NULL,
  `converted_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_reco_student` (`student_id`),
  FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`recommended_course_id`) REFERENCES `courses`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Business workflow

1. Near course completion, the recommendation engine (full mechanics in Phase 5) weighs the completed course, performance data, `student_profiles.interests`/`goals`, and typical next-step paths (e.g., "Python Beginner" graduates commonly move to "Python Intermediate" or "Web Dev with Python") into a ranked next-course list.
2. Surfaced in three places: an in-app dashboard prompt near completion, the completion communication sequence (Phase 6), and the monthly parent report (Phase 3g) when relevant — not just one channel, since a recommendation only a student sees but a paying parent never does is a weaker upsell path.
3. **Every recommendation shown is logged** (`shown_at`), independent of whether it's acted on — `converted_at` tracks whether a purchase of that specific recommended course happens within a reasonable window. This is what makes the recommendation engine's accuracy *measurable* over time, not just a black box that either works or doesn't with no feedback loop.

### Edge cases & failure handling
- **Student already knows what they want next, picks something else entirely**: still logged as "shown, not converted" rather than silently dropped — that's a real, useful signal about recommendation quality, not noise to discard.
- **No good next-course fit exists yet** (student is ahead of the current catalog): the engine returns a graceful "explore other tracks" state rather than forcing a poor-fit recommendation just to have something to show — a confidently-wrong upsell suggestion actively damages trust in future recommendations.

---

## 29. Referral System

```sql
CREATE TABLE `referral_codes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `code` VARCHAR(20) NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_referral_user` (`user_id`),
  UNIQUE KEY `uk_referral_code` (`code`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `referrals` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `referrer_user_id` INT UNSIGNED NOT NULL,
  `referral_code` VARCHAR(20) NOT NULL,
  `referred_lead_id` BIGINT UNSIGNED DEFAULT NULL,
  `status` ENUM('pending','converted','expired') DEFAULT 'pending',
  `reward_type` ENUM('credits','cash','discount') DEFAULT 'credits',
  `reward_value` DECIMAL(10,2) DEFAULT NULL,
  `reward_status` ENUM('pending','approved','paid') DEFAULT 'pending',
  `converted_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_referral_referrer` (`referrer_user_id`),
  FOREIGN KEY (`referrer_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`referred_lead_id`) REFERENCES `leads`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Business workflow

1. Every student/parent gets a permanent referral code (`referral_codes`, one per user, generated once).
2. A new lead arriving via that code is captured with the attribution on the `leads` row itself (`referred_lead_id` links back) so the existing lead-to-enrollment pipeline (Phase 3a) doesn't need a parallel path — referral is metadata on a normal lead, not a different funnel.
3. **Conversion within a bounded attribution window** (default 90 days from referral) triggers the reward — `status='converted'`, `reward_status` moves `pending → approved → paid` as ops processes it. A reward type of `credits` writes a `credit_transactions` (`type='bonus'`) entry on the referrer's wallet; cash/discount payouts route through the existing finance/payments process.

### Edge cases & failure handling
- **Self-referral fraud** (referring a second account, a sibling, to farm rewards): reward approval is a manual/flagged step (`reward_status='pending' → 'approved'`) specifically so basic fraud signals (same payment method, same device/household) can be checked before payout, rather than rewards auto-paying the instant a conversion is detected.
- **Lead converts long after the referral**, outside the 90-day window: the referral simply expires (`status='expired'`) and that later conversion is treated as a normal, unattributed enrollment — no reward, no dispute, since the attribution window is fixed and known up front.

---

## 30. Support System

Reuses the existing `support_tickets`/`ticket_replies`/`support_categories` (with `sla_hours` already built in) entirely — **no new tables**.

### Business workflow

1. Student/parent raises a ticket via an in-app widget, categorized (technical/academic/payment/scheduling).
2. **Category determines routing**, not a single generic queue — payment tickets go to finance-trained staff, academic ones to a teacher/mentor, technical to platform support. This is a routing-table concern (category → team mapping), not a schema change.
3. SLA clock starts from `support_categories.sla_hours`; a breach (no first response within that window) auto-escalates priority and alerts a supervisor — this is what actually makes an SLA meaningful, since an SLA nobody enforces is just a number on a settings page.
4. Resolution closes the loop with the existing `satisfaction_rating` capture.

### Edge cases & failure handling
- **Mis-categorized urgency** (a student picks "scheduling" but the real issue is "I can't join my class right now"): support staff can re-prioritize on first triage with a single action — the system doesn't lock a ticket's urgency to whatever category the (often panicked, sometimes a child) submitter picked at creation time.

---

## Next

Phase 3i — Gamification, Digital Notebook (workflow detail beyond schema), Collaborative Coding (workflow detail beyond schema). Say "continue."
