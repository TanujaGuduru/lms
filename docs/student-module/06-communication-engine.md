# Delivery Phase 6 — Communication Orchestration Engine

Specifies the system every workflow phase since `03a` has referenced in passing as "Phase 6" — the multi-channel dispatch layer behind every reminder, alert, and notification in this design. Schema: `communication_logs`/`domain_events` (`02e` §7). Reuses the existing `users.language`/`users.timezone`/`users.notification_preferences` columns already in the Admin schema (`database/schema.sql`) — no schema amendment needed for this phase.

---

## 1. Architecture

**One dispatch entry point** — `Notifier::send(userId, triggerEvent, context)` — every feature across `03a`–`03k` calls this same entry point rather than hand-rolling its own send logic, the identical "one centralized abstraction instead of scattered per-feature logic" instinct the AI Gateway (`05a`) applies to LLM calls, applied here to comms.

**Two ways a send gets triggered**, both routing through the same entry point:
1. **In-process, immediately after a state change** — a badge earned, a class rescheduled, a completion recorded. `03i`'s explicit reasoning for badges ("a badge that takes a day to show up loses most of its motivational value") generalizes to every immediate-feel trigger in this catalog: if the triggering event already happened inside a request, the notify call happens in that same request/job, not deferred to a later scan.
2. **Cron-driven batch jobs scanning for a time-relative condition** — assignment-reminder tiers, consent-reminder backoff, a credit-threshold crossing. These already existed as separate, independently-built scanning jobs per feature (`03a`, `03d`, `03g`); this phase generalizes them into one reusable cadence-definition pattern (§6) rather than each feature reinventing its own cron logic.

**GoDaddy reality, restated from `01b`**: on shared hosting, every send queues and drains in ~60-second bursts via the standard `cron → schedule:run → queue:work --stop-when-empty` pattern — fine for everything in this catalog, since nothing here actually needs sub-second delivery (even "immediate" badge notification means "this request/job," not a hard real-time guarantee). On AWS, the identical logical job runs against SQS with a persistent worker — faster, not behaviorally different.

---

## 2. Idempotency — the one mechanism every trigger reuses

Already built per-feature in `03d` (assignment reminders) and `03a` (consent reminders); generalized here into the platform-wide rule: **before any send, check `communication_logs` for an existing row matching `(user_id, trigger_event)` for this occurrence** — a send only proceeds if no matching row exists yet. This is what makes "at-least-once" cron execution safe to build on top of, rather than requiring every individual job to independently guarantee exactly-once delivery (`03d`'s explicit reasoning, now the platform default instead of a one-off fix).

**Naming convention**: `{domain}_{event}[_{tier}]` — e.g. `assignment_reminder_24h`, `low_credit_alert`, `consent_reminder_d3`, `risk_tier2_whatsapp`. Distinguishable tiers/instances get their **own** `trigger_event` string rather than sharing a generic one, specifically because the idempotency check matches on the exact string — without per-tier naming, "already sent the 24h reminder" and "already sent the 6h reminder" would be indistinguishable to the check that's supposed to keep them from colliding.

---

## 3. Channel Selection & Fallback

Each `trigger_event` has a **configured preferred channel sequence**, not one platform-wide default: WhatsApp for urgent/time-sensitive (low-credit, assignment-due-soon, risk tiers 2–3), **email for anything document-bearing or formal** (monthly parent report, certificates, refund confirmations), SMS reserved as a **fallback only** when WhatsApp delivery fails (cost — never a primary channel by default), and **in-app is always logged regardless of which external channel also fired**, so nothing is external-channel-only: a parent who never checks WhatsApp still sees the same notification in their in-app center.

**`users.notification_preferences` (already in the Admin schema) is checked before the trigger's default sequence is applied** — a user who's opted out of WhatsApp gets the next channel in that trigger's configured sequence instead, never a hard failure; in-app logging still always happens regardless of any opt-out, since that's the floor every notification guarantees.

**Fallback mechanics**: a delivery failure (`communication_logs.status='failed'`, from a send-time error or a provider webhook) on the primary channel triggers a retry on the next configured channel — logged as a **new** `communication_logs` row (same `trigger_event`, different `channel`), never by mutating the failed row, so the full attempt history stays intact. `provider_message_id` plus async delivery webhooks (WhatsApp Cloud API / Twilio / SES) are what move a row from `sent` → `delivered`/`read`/`failed` after the fact.

**Send time respects `users.timezone`**, not server time — a reminder computed to fire "this evening" fires in the recipient's evening, not GoDaddy's or AWS's server region's, which matters specifically for international families and is the same timezone-at-render-time discipline `03b`'s scheduling engine already applies to class times.

---

## 4. Escalation Batching

`03g`'s explicit requirement: a student who's flagged both `dropout_risk` and `weak_topic` in the same week shouldn't get two separate WhatsApp pings. Before sending a **batchable** trigger, the dispatcher checks for other not-yet-sent batchable triggers queued for the same recipient within a short window (a few hours) and combines them into one consolidated message rather than firing each independently. **Not every trigger is batchable** — time-critical ones (a class starting in 10 minutes, a payment gateway requiring action) send immediately and standalone regardless of what else is queued; "batchable" is a per-trigger-event config flag, not a blanket platform rule.

**Family-level batching is a distinct, separate case** from same-student batching: `03g` §24 explicitly allows multiple children's monthly reports to go out together in one delivery batch for a family's convenience, while the underlying `parent_reports` documents themselves stay **per-child, never merged** — batching here is about the outer delivery wrapper, never about combining the actual report content (`03g`'s explicit point, restated because it's easy to conflate "batched delivery" with "merged content," and they're not the same thing).

---

## 5. Template Management

**Versioned the same way AI prompts are** (`05a`'s prompt-versioning instinct, applied to comms): `communication_logs.template_used` records which version actually went out, so a template edit's effect on a real outcome (e.g., consent-completion rate) is measurable against exactly which version was live at the time, not just which trigger fired.

**Per-channel variants of the same `trigger_event`**, not one template force-fit across channels — a WhatsApp message is short and punchy; an email for the identical trigger has room for more context. **Localized by `users.language`** (already in the Admin schema) at template-resolution time, alongside the channel variant — both axes (channel, language) are resolved together when picking which template version actually renders.

---

## 6. Cadence Definitions

A reusable, declarative pattern instead of each feature hand-rolling its own cron-scanning job: a cadence is `{trigger_event_prefix, anchor_field, offsets[]}` — e.g. assignment reminders anchor on `due_date` (or `assignment_submissions.extended_due_date` when set, per `03d`) with offsets `[-24h, -6h, +0h(overdue)]`; consent reminders anchor on `parent_student_links.created_at` (or `linked_at`) with widening offsets `[d1, d3, d7, d14(escalates to manual outreach)]`. **A single cron job evaluates every registered cadence definition each tick**, scanning for rows whose anchor-plus-offset has just crossed `now`, rather than one bespoke job per feature — adding a new reminder sequence later means registering a new cadence definition, not writing a new cron job from scratch.

**Selected trigger catalog** (the events explicitly named across `03a`–`03k`; not the full list, but every one tagged "Phase 6" in those documents):

| `trigger_event` | Source | Default channels | Batchable |
|---|---|---|---|
| `consent_reminder_d{1,3,7,14}` | `03a` §2 | WhatsApp → Email | No |
| `no_show_reengagement` | `03a` §1 | WhatsApp | No |
| `low_credit_alert` / `credit_exhausted` | `03a` §3 | WhatsApp, in-app | Yes |
| `assignment_reminder_24h` / `_6h` / `_overdue` | `03d` §14 | In-app/push, WhatsApp | Yes |
| `reschedule_notice` / `teacher_change_notice` | `03c` §9–10 | WhatsApp, email | No |
| `batch_reassignment_notice` | `03b` §5 | WhatsApp, email | No |
| `risk_tier1_nudge` … `risk_tier5_escalation` | `03g` §22 | In-app → WhatsApp → Email → mentor-call task | Yes |
| `payment_failed_retry` | `03g` §25 | Email, WhatsApp | No |
| `ptm_booking_reminder_24h` | `03k` §39 | WhatsApp + calendar sync | No |
| `completion_notification` | `03h` §26 | In-app, WhatsApp | No |
| `monthly_parent_report_ready` | `03g` §24 | Email (PDF) + WhatsApp (condensed + link) | Yes (per-family) |
| `badge_earned` / `level_up` | `03i` §31 | In-app push | No |
| `support_sla_breach` | `03h` §30 | Internal alert to supervisor (staff-facing, not student/parent) | No |

This table is illustrative of the pattern, not the literal final exhaustive list — new triggers register the same way as any of the above, through the same cadence/dispatch mechanism, never as a one-off.

---

## 7. The `domain_events` Outbox — Admin ↔ Student Cross-System Sync

`domain_events` (`02e`) is the durable outbox `01` §3 already decided on, to avoid a synchronous HTTP dependency between two different runtimes (PHP Admin, Laravel Student/Parent app) that don't need to both be up simultaneously for any single action to complete. **Mechanics**: whichever side an event originates on writes a row (`event_type`, `aggregate_type`/`aggregate_id`, `payload`); the **other** side's own cron job polls `WHERE processed_by_{x}=0 ORDER BY occurred_at LIMIT N`, applies the effect, and flips its own processed flag. Each side only ever sets its *own* flag — Admin never touches `processed_by_student_app` and vice versa, so the two consumers are fully independent and neither can block the other.

**Example flow**: an admin force-reassigns a teacher mid-term on the Admin panel → a `teacher.reassigned` row lands in `domain_events` → the Student app's poller picks it up, applies the reassignment to its own read models, and calls `Notifier::send(..., 'batch_reassignment_notice', ...)` (§6) — the actual notification logic lives entirely in the Communication Engine, the outbox just guarantees the trigger eventually fires even though the originating action happened in a completely different codebase and runtime.

**Failure handling**: a consumer job that errors partway through applying an event simply doesn't flip its processed flag — safe to retry on the next poll, since the application logic on the consuming side has to be idempotent against being handed the same event twice (the same principle as `communication_logs`' idempotency in §2, applied to cross-system event application instead of message sending).

---

## Next

**Delivery Phase 7 — Scaling Strategy** (100 → 100,000+ students): the cost-crossover points already flagged throughout this program (Redis vs. MySQL-driver queues, Pinecone vs. pgvector, Piston vs. Firecracker, Agora's managed SFU vs. self-hosted, GoDaddy vs. AWS) get a single consolidated decision framework — at what concrete scale signal does each switch, and in what order. Say "continue."
