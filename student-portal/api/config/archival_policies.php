<?php

// Generalized ledger-content archival — docs/student-module/07-scaling-strategy.md
// §5 generalizes 02d's ai_messages pattern ("monthly archival, keeping the
// summary row, dropping the body") to every other high-volume ledger that
// has a genuinely large per-row body worth stripping. `credit_transactions`/
// `xp_transactions`/`communication_logs` are deliberately NOT here — their
// rows are already small (amount/reason/status, no large body column), so
// there's nothing to strip; their growth is addressed by partitioning
// instead (see database/partition-ledger-tables.sql), not body archival.
//
// Each entry's `age_query` takes exactly one bound parameter (the cutoff
// datetime) and must return `id` plus every `body_column`, plus `age_at` —
// the real per-row timestamp to compare against the cutoff, joined from a
// parent table where the row has no honest timestamp of its own (a
// response's true age is when the exam was *submitted*, not when the row
// happened to be inserted). The query itself already excludes rows whose
// body is already stripped, so a repeat run only ever touches new rows
// that have aged past the cutoff since the last run.
return [
    'ai_messages' => [
        'retention_months' => 12,
        'body_columns' => ['content', 'rag_sources'],
        'age_query' => "SELECT id, content, rag_sources, created_at AS age_at FROM ai_messages WHERE content != '[archived]' AND created_at < ?",
        'strip' => fn (\App\Core\Database $db, int $id) => $db->updateTable('ai_messages', ['content' => '[archived]', 'rag_sources' => null], 'id = ?', [$id]),
    ],
    'code_executions' => [
        'retention_months' => 6,
        'body_columns' => ['stdin', 'stdout', 'stderr'],
        'age_query' => 'SELECT id, stdin, stdout, stderr, created_at AS age_at FROM code_executions WHERE stdout IS NOT NULL AND created_at < ?',
        'strip' => fn (\App\Core\Database $db, int $id) => $db->updateTable('code_executions', ['stdin' => null, 'stdout' => null, 'stderr' => null], 'id = ?', [$id]),
    ],
    'exam_responses' => [
        'retention_months' => 24,
        'body_columns' => ['response', 'grader_feedback'],
        'age_query' => "
            SELECT er.id, er.response, er.grader_feedback, ea.submitted_at AS age_at
            FROM exam_responses er JOIN exam_attempts ea ON ea.id = er.attempt_id
            WHERE er.response IS NOT NULL AND ea.submitted_at IS NOT NULL AND ea.submitted_at < ?
        ",
        'strip' => fn (\App\Core\Database $db, int $id) => $db->updateTable('exam_responses', ['response' => null, 'grader_feedback' => null], 'id = ?', [$id]),
    ],
];
