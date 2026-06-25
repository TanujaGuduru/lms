<?php

declare(strict_types=1);

/**
 * GoDaddy cPanel Cron Job — run nightly, e.g. 1:30 AM:
 *   php /home/yourusername/public_html/api/cron/backup-database.php
 *
 * docs/student-module/08-infrastructure-devops.md §4 (GoDaddy path):
 * "cPanel's own backup tooling is never treated as the sole backup — an
 * independent nightly mysqldump piped directly to S3 runs regardless of
 * whatever GoDaddy's own backup product does." The "piped to S3" part
 * doesn't apply here — S3 is a cloud service outside this build's one
 * accepted exception (a pay-per-call AI API) — so this writes to local
 * disk instead (`storage/app/backups/`, gzip-compressed, with rotation).
 *
 * **Stated honestly, not overclaimed**: a local-disk backup does NOT fully
 * replace what piping to S3 would have given — the doc's whole reasoning
 * for an *independent* backup was protection against the hosting account
 * itself being compromised, suspended, or mismanaged, and a backup that
 * lives on that same account doesn't protect against that specific
 * failure mode. What this cron *does* give, within the no-cloud
 * commitment: protection against cPanel's own backup product failing,
 * being misconfigured, or being deleted independently of an account-level
 * incident — plus a local file an operator can manually download off-box
 * (via cPanel File Manager/SFTP) on whatever cadence they choose, which
 * cPanel's own backup UI also supports doing manually already. A true
 * second-location backup requires a second storage location by
 * definition — there is no way to honestly provide one without some
 * external destination, cloud or otherwise.
 */

require __DIR__ . '/../bootstrap/cli.php';

use App\Core\Logger;

const RETENTION_DAYS = 14;

$dbConfig = require __DIR__ . '/../config/database.php';
$backupDir = BASE_PATH . '/storage/app/backups';
if (! is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

$filename = 'codegurukul-' . date('Y-m-d') . '.sql.gz';
$path = "{$backupDir}/{$filename}";

$command = sprintf(
    'mysqldump --host=%s --port=%s --user=%s %s %s | gzip > %s',
    escapeshellarg($dbConfig['host']),
    escapeshellarg((string) $dbConfig['port']),
    escapeshellarg($dbConfig['username']),
    $dbConfig['password'] !== '' ? '--password=' . escapeshellarg($dbConfig['password']) : '',
    escapeshellarg($dbConfig['database']),
    escapeshellarg($path)
);

exec($command . ' 2>&1', $output, $exitCode);

if ($exitCode !== 0 || ! is_file($path) || filesize($path) === 0) {
    Logger::critical('Nightly database backup failed', ['exit_code' => $exitCode, 'output' => implode("\n", $output)]);
    @unlink($path); // don't leave a zero-byte/partial file masquerading as a valid backup.
    echo "Backup FAILED — see logs.\n";
    exit(1);
}

$pruned = pruneOldBackups($backupDir);

echo "Backup written: {$filename} (" . round(filesize($path) / 1024 / 1024, 2) . " MB). {$pruned} old backup(s) pruned.\n";

function pruneOldBackups(string $backupDir): int
{
    $cutoff = time() - (RETENTION_DAYS * 86400);
    $pruned = 0;

    foreach (glob("{$backupDir}/codegurukul-*.sql.gz") ?: [] as $file) {
        if (filemtime($file) < $cutoff) {
            unlink($file);
            $pruned++;
        }
    }

    return $pruned;
}
