<?php

// Per-trigger_event channel sequence — docs/student-module/06 §3/§4. Keyed
// by the BASE trigger_event (before any ":entityId" disambiguation suffix —
// see App\Core\Notifier::baseTriggerEvent()). `in_app` is never listed here;
// it fires unconditionally on every send (06 §3) and isn't part of the
// external-channel fallback/parallel sequence this config controls.
//
// 'mode' => 'fallback': try external_channels in order, stop at first
// success (06 §3's main mechanic). 'mode' => 'all': send to every listed
// channel regardless of the others (06 §3/§6's "+" notation, e.g. the
// monthly parent report going out by both email AND WhatsApp, not one as a
// fallback for the other).
//
// Not the full catalog from 06 §6's table — only the triggers actually
// wired to a producer in this pass (see student-portal/README.md's Phase 6
// section for which). New triggers register here the same way, without
// needing a new dispatch mechanism.
return [
    'badge_earned' => ['external_channels' => [], 'mode' => 'fallback', 'batchable' => false],
    'completion_notification' => ['external_channels' => ['whatsapp'], 'mode' => 'fallback', 'batchable' => false],
    'monthly_parent_report_ready' => ['external_channels' => ['email', 'whatsapp'], 'mode' => 'all', 'batchable' => true],
    // 06 §6's risk_tier1_nudge...risk_tier5_escalation collapse onto this
    // build's 3 real intervention_status tiers (cron/compute-risk-scores.php) —
    // there's no 5-level granularity in the schema to map onto.
    'risk_tier_nudge' => ['external_channels' => [], 'mode' => 'fallback', 'batchable' => true],
    'risk_tier_mentor_call' => ['external_channels' => ['whatsapp', 'email'], 'mode' => 'fallback', 'batchable' => true],
    'risk_tier_parent_escalation' => ['external_channels' => ['whatsapp', 'email'], 'mode' => 'fallback', 'batchable' => true],
    'assignment_reminder_24h' => ['external_channels' => ['whatsapp'], 'mode' => 'fallback', 'batchable' => true],
    'assignment_reminder_6h' => ['external_channels' => ['whatsapp'], 'mode' => 'fallback', 'batchable' => true],
    'assignment_reminder_overdue' => ['external_channels' => ['whatsapp'], 'mode' => 'fallback', 'batchable' => true],
    // 06 §7's own worked example for the domain_events outbox — fired by cron/process-domain-events.php.
    'batch_reassignment_notice' => ['external_channels' => ['whatsapp', 'email'], 'mode' => 'fallback', 'batchable' => false],
    // Admin panel's "Schedule Live Class" feature — live_class.scheduled
    // fires immediately via the domain_events outbox (cron/process-domain-events.php);
    // live_class_reminder is a cadence (config/cadences.php) anchored 15
    // minutes before start_datetime. Both go to the parent, not the
    // student, and never batch — a same-day class reminder sitting in the
    // queue until the next batch-drain tick would defeat the point.
    'live_class_scheduled' => ['external_channels' => ['email'], 'mode' => 'fallback', 'batchable' => false],
    'live_class_reminder_15min' => ['external_channels' => ['email'], 'mode' => 'fallback', 'batchable' => false],
    // Staff-facing ops alerts (08 §3) — never batched: "pages someone
    // immediately" is incompatible with sitting in notification_queue
    // until the next batch-drain tick.
    'credit_ledger_drift_detected' => ['external_channels' => ['email'], 'mode' => 'fallback', 'batchable' => false],
    'support_sla_breach' => ['external_channels' => ['email'], 'mode' => 'fallback', 'batchable' => false],
];
