<?php

declare(strict_types=1);

/**
 * GoDaddy cPanel Cron Job — run once a month, e.g. the 1st at 4:00 AM:
 *   php /home/yourusername/public_html/api/cron/archive-ledger-content.php
 *
 * docs/student-module/07-scaling-strategy.md §5: "Append-only ledgers grow
 * unboundedly by design... The mitigation already exists for one table
 * (ai_messages → archival, keeping the summary row, dropping the body) —
 * this generalizes to every other high-volume ledger." This is that
 * generalization, driven by config/archival_policies.php rather than one
 * bespoke script per table.
 *
 * For each registered policy: finds rows older than `retention_months`
 * whose body hasn't already been archived, writes the full row (every
 * `body_column`, plus `id`/`age_at`) as one JSON line to a local file under
 * `storage/app/archives/{table}/{YYYY-MM}.jsonl` — grouped by the row's own
 * `age_at` month, not the run date, so files line up with when the content
 * actually happened — then strips the body columns in place via the
 * policy's `strip` callback. Local disk, not S3/cloud storage (the
 * doc's own "→ S3" framing doesn't apply to this no-cloud build the same
 * way it didn't for Materials/Recordings — see App\Core\FileStorage).
 */

require __DIR__ . '/../bootstrap/cli.php';

use App\Core\Database;
use App\Core\Logger;

const BATCH_SIZE_PER_TABLE = 500;

$db = Database::getInstance();
$policies = require __DIR__ . '/../config/archival_policies.php';

$totalArchived = 0;
foreach ($policies as $table => $policy) {
    $archived = archiveTable($db, $table, $policy);
    echo "{$table}: {$archived} row(s) archived and stripped.\n";
    $totalArchived += $archived;
}

echo "Total: {$totalArchived} row(s) across " . count($policies) . " polic(y/ies).\n";

function archiveTable(Database $db, string $table, array $policy): int
{
    $cutoff = date('Y-m-d H:i:s', strtotime("-{$policy['retention_months']} months"));
    $rows = $db->select($policy['age_query'] . " LIMIT " . BATCH_SIZE_PER_TABLE, [$cutoff]);

    $count = 0;
    foreach ($rows as $row) {
        try {
            writeArchiveLine($table, $row);
            ($policy['strip'])($db, (int) $row['id']);
            $count++;
        } catch (\Throwable $e) {
            Logger::error('Ledger archival failed for one row — left unstripped, will retry next run', [
                'table' => $table,
                'id' => $row['id'],
                'error' => $e->getMessage(),
            ]);
        }
    }

    return $count;
}

function writeArchiveLine(string $table, array $row): void
{
    $monthKey = $row['age_at'] ? date('Y-m', strtotime($row['age_at'])) : 'unknown';
    $dir = BASE_PATH . "/storage/app/archives/{$table}";
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $path = "{$dir}/{$monthKey}.jsonl";
    file_put_contents($path, json_encode($row, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
}
