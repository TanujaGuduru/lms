<?php

// Versioned the same way AI prompts are (06 §5) — communication_logs.template_used
// records exactly which version rendered, so a template edit's effect on a
// real outcome is measurable against exactly which version was live.
// Per-channel variants of the same trigger_event, localized by users.language
// (falls back to 'en' when no language-specific variant exists — see
// App\Core\Notifier::templateFor()). `{placeholder}` tokens are substituted
// from the `context` array passed to Notifier::send().
return [
    'badge_earned' => [
        'in_app' => [
            'en' => ['version' => 'badge_earned_in_app_v1', 'subject' => 'New badge earned!', 'body' => 'You just earned the "{badge_name}" badge. Keep it up!'],
        ],
    ],
    'completion_notification' => [
        'in_app' => [
            'en' => ['version' => 'completion_in_app_v1', 'subject' => 'Course completed', 'body' => 'Congratulations on completing {course_title}!'],
        ],
        'whatsapp' => [
            'en' => ['version' => 'completion_whatsapp_v1', 'subject' => '', 'body' => 'Congrats on finishing {course_title}! Your certificate is ready in the app.'],
        ],
    ],
    'monthly_parent_report_ready' => [
        'in_app' => [
            'en' => ['version' => 'parent_report_in_app_v1', 'subject' => 'New progress report', 'body' => "{student_name}'s {month_year} progress report is ready."],
        ],
        'email' => [
            'en' => ['version' => 'parent_report_email_v1', 'subject' => "{student_name}'s {month_year} Progress Report", 'body' => "Hello,\n\n{student_name}'s monthly progress report for {month_year} is now available in your parent dashboard.\n\n- CodeGurukul"],
        ],
        'whatsapp' => [
            'en' => ['version' => 'parent_report_whatsapp_v1', 'subject' => '', 'body' => "{student_name}'s {month_year} report is ready. View it in the app."],
        ],
    ],
    'risk_tier_nudge' => [
        'in_app' => [
            'en' => ['version' => 'risk_nudge_in_app_v1', 'subject' => 'Keep your momentum going', 'body' => "We've noticed your activity has slowed a bit lately — your mentors are here if you'd like a hand."],
        ],
    ],
    // Recipient is the STUDENT for this tier (a mentor reaching out to
    // *them*) — every channel variant must stay consistently student-facing,
    // since Notifier::dispatch() sends all of a trigger's channels to one
    // same recipient; mixing "you" (in_app) with a third-person
    // {student_name} (as if addressed to a parent) in the same trigger
    // would be incoherent for whichever channel actually delivers.
    'risk_tier_mentor_call' => [
        'in_app' => [
            'en' => ['version' => 'risk_mentor_in_app_v1', 'subject' => "Let's check in", 'body' => 'A mentor will be reaching out to you soon for a quick check-in call.'],
        ],
        'whatsapp' => [
            'en' => ['version' => 'risk_mentor_whatsapp_v1', 'subject' => '', 'body' => "Hi! A CodeGurukul mentor would like to schedule a quick check-in call with you. We'll be in touch shortly."],
        ],
        'email' => [
            'en' => ['version' => 'risk_mentor_email_v1', 'subject' => 'A mentor check-in for you', 'body' => "Hello,\n\nWe'd like to schedule a short mentor check-in call with you to see how things are going.\n\n- CodeGurukul"],
        ],
    ],
    'risk_tier_parent_escalation' => [
        'in_app' => [
            'en' => ['version' => 'risk_escalation_in_app_v1', 'subject' => 'Please check your email/WhatsApp', 'body' => 'We have an important update about {student_name} — please check your email or WhatsApp.'],
        ],
        'whatsapp' => [
            'en' => ['version' => 'risk_escalation_whatsapp_v1', 'subject' => '', 'body' => "Hi! We'd like to discuss {student_name}'s recent engagement — could you give us a call back when convenient?"],
        ],
        'email' => [
            'en' => ['version' => 'risk_escalation_email_v1', 'subject' => "An update on {student_name}'s engagement", 'body' => "Hello,\n\nWe'd like to discuss {student_name}'s recent engagement with their course. Please reach out to us at your convenience.\n\n- CodeGurukul"],
        ],
    ],
    'assignment_reminder_24h' => [
        'in_app' => ['en' => ['version' => 'assignment_reminder_in_app_v1', 'subject' => 'Assignment due soon', 'body' => '"{assignment_title}" is due in about 24 hours.']],
        'whatsapp' => ['en' => ['version' => 'assignment_reminder_whatsapp_v1', 'subject' => '', 'body' => 'Reminder: "{assignment_title}" is due in about 24 hours.']],
    ],
    'assignment_reminder_6h' => [
        'in_app' => ['en' => ['version' => 'assignment_reminder_in_app_v1', 'subject' => 'Assignment due soon', 'body' => '"{assignment_title}" is due in about 6 hours.']],
        'whatsapp' => ['en' => ['version' => 'assignment_reminder_whatsapp_v1', 'subject' => '', 'body' => 'Reminder: "{assignment_title}" is due in about 6 hours.']],
    ],
    'assignment_reminder_overdue' => [
        'in_app' => ['en' => ['version' => 'assignment_reminder_overdue_in_app_v1', 'subject' => 'Assignment overdue', 'body' => '"{assignment_title}" is now overdue. Submit as soon as you can.']],
        'whatsapp' => ['en' => ['version' => 'assignment_reminder_overdue_whatsapp_v1', 'subject' => '', 'body' => '"{assignment_title}" is now overdue.']],
    ],
    'batch_reassignment_notice' => [
        'in_app' => ['en' => ['version' => 'reassignment_in_app_v1', 'subject' => 'Teacher change', 'body' => 'Your batch has been reassigned to a new teacher: {new_teacher_name}.']],
        'whatsapp' => ['en' => ['version' => 'reassignment_whatsapp_v1', 'subject' => '', 'body' => 'Heads up — your batch now has a new teacher, {new_teacher_name}.']],
        'email' => ['en' => ['version' => 'reassignment_email_v1', 'subject' => 'Your batch has a new teacher', 'body' => "Hello,\n\nYour batch has been reassigned to a new teacher: {new_teacher_name}.\n\n- CodeGurukul"]],
    ],
    'credit_ledger_drift_detected' => [
        'in_app' => ['en' => ['version' => 'ledger_drift_in_app_v1', 'subject' => 'Credit ledger drift detected', 'body' => '{drift_summary}']],
        'email' => ['en' => ['version' => 'ledger_drift_email_v1', 'subject' => 'ALERT: Credit ledger reconciliation drift', 'body' => "{drift_summary}\n\nThis means a bug exists somewhere in credit deduction/refund logic — investigate immediately rather than letting it accumulate.\n\n- CodeGurukul automated reconciliation"]],
    ],
    'support_sla_breach' => [
        'in_app' => ['en' => ['version' => 'sla_breach_in_app_v1', 'subject' => 'Support SLA breach', 'body' => '{breach_summary}']],
        'email' => ['en' => ['version' => 'sla_breach_email_v1', 'subject' => 'ALERT: Support ticket SLA breach', 'body' => "{breach_summary}\n\n- CodeGurukul automated SLA monitor"]],
    ],
    '_default' => [
        'in_app' => ['en' => ['version' => 'generic_in_app_v1', 'subject' => 'Notification', 'body' => 'You have a new update.']],
    ],
];
