<?php

// Declarative cadence registry (docs/student-module/06 §6) — a single cron
// job (cron/process-cadences.php) evaluates every registered cadence each
// tick, scanning for rows whose anchor-plus-offset has crossed `now`, rather
// than one bespoke scanning job per feature. Adding a new reminder sequence
// later means registering a new entry here, not writing a new cron job.
//
// `anchor_query` must return one row per (recipient, occurrence) pair, with
// columns: `recipient_id` (who gets notified), `entity_id` (disambiguates
// repeat occurrences — becomes the `:{entity_id}` suffix on the
// trigger_event, per App\Core\Notifier's docblock), `anchor_at` (the
// DATETIME every offset below is relative to), plus any extra columns
// listed in `context_columns` for template placeholder substitution.
//
// `offsets`: list of [seconds_relative_to_anchor, trigger_suffix]. Negative
// = before the anchor (a reminder ahead of time); 0 or positive = at/after
// it (e.g. "overdue"). The fired trigger_event is
// "{trigger_event_prefix}_{trigger_suffix}:{entity_id}".
return [
    'assignment_reminder' => [
        'trigger_event_prefix' => 'assignment_reminder',
        'anchor_query' => "
            SELECT e.user_id AS recipient_id, a.id AS entity_id, a.title AS assignment_title,
                   COALESCE(sub.extended_due_date, a.due_date) AS anchor_at
            FROM assignments a
            JOIN enrollments e ON e.course_id = a.course_id AND e.status = 'active'
            LEFT JOIN assignment_submissions sub ON sub.assignment_id = a.id AND sub.student_id = e.user_id
            WHERE a.status IN ('published', 'closed')
              AND a.due_date IS NOT NULL
              AND (sub.id IS NULL OR sub.status NOT IN ('submitted', 'graded', 'resubmitted', 'returned'))
        ",
        'context_columns' => ['assignment_title'],
        'offsets' => [
            [-86400, '24h'],
            [-21600, '6h'],
            [0, 'overdue'],
        ],
    ],
    // Admin panel's "Schedule Live Class" feature — the "scheduled" notice
    // itself goes out immediately via the domain_events outbox
    // (cron/process-domain-events.php); this cadence is purely the
    // 15-minutes-before reminder, evaluated independently since it's
    // time-relative rather than triggered by the Admin's create action.
    // Recipient is the PARENT, resolved the same way as elsewhere in this
    // file — via batch_students -> parent_student_links, consent required.
    // DISTINCT collapses a parent with two children in the same batch down
    // to one row (and hence one reminder) for that class.
    'live_class_reminder' => [
        'trigger_event_prefix' => 'live_class_reminder',
        'anchor_query' => "
            SELECT DISTINCT psl.parent_id AS recipient_id, lc.id AS entity_id,
                   lc.title AS class_title,
                   DATE_FORMAT(lc.start_datetime, '%a, %d %b %Y at %h:%i %p') AS class_datetime,
                   lc.start_datetime AS anchor_at
            FROM live_classes lc
            JOIN batch_students bs ON bs.batch_id = lc.batch_id
            JOIN parent_student_links psl ON psl.student_id = bs.student_id AND psl.consent_status = 'granted'
            WHERE lc.status = 'scheduled'
        ",
        'context_columns' => ['class_title', 'class_datetime'],
        'offsets' => [
            [-900, '15min'],
        ],
    ],
];
