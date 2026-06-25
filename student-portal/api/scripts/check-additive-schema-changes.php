<?php

declare(strict_types=1);

/**
 * Manual/CI guard — run before merging any schema_student_portal.sql
 * change:
 *   php scripts/check-additive-schema-changes.php
 *
 * docs/student-module/08-infrastructure-devops.md §1: "The Admin PHP app
 * and the new [Student] app read/write the same MySQL schema, but only the
 * [Student] side has migration tooling — the Admin codebase's raw SQL
 * queries have no migration awareness to break gracefully against. So:
 * every migration touching a table the Admin app also reads/writes is
 * additive-only — never a rename or drop of a column/table — and ships
 * with a check... as part of the same PR, not a separate, skippable step."
 *
 * This build has no migration framework (one schema.sql, one additive
 * schema_student_portal.sql, applied directly per DEPLOYMENT.md) — so
 * "ships with a check" here means: this script scans
 * schema_student_portal.sql for any DROP TABLE / DROP COLUMN / RENAME /
 * MODIFY|CHANGE COLUMN statement (the doc's literal "never a rename or
 * drop" list) and fails loudly if it finds one, then separately lists
 * every table schema_student_portal.sql touches that the Admin codebase
 * also references directly — a reminder checklist for the human merging
 * the PR, not something this script can fully verify automatically (a
 * grep hit doesn't prove compatibility, only that a human should look).
 */

$schemaPath = __DIR__ . '/../database/schema_student_portal.sql';
$sql = file_get_contents($schemaPath);
if ($sql === false) {
    fwrite(STDERR, "Could not read {$schemaPath}\n");
    exit(1);
}

$violations = findNonAdditiveStatements($sql);
$touchedTables = extractTouchedTables($sql);
$adminCodebaseRoot = __DIR__ . '/../../../';
$sharedTables = findTablesReferencedInAdminCode($touchedTables, $adminCodebaseRoot);

if (! empty($violations)) {
    echo "FAILED — non-additive schema statement(s) found:\n\n";
    foreach ($violations as $v) {
        echo "  - {$v}\n";
    }
    echo "\nEvery migration touching a table the Admin app also reads/writes must be additive-only\n";
    echo "(CREATE TABLE / ADD COLUMN only) — see this script's docblock and 08 §1.\n";
    exit(1);
}

echo "OK — no DROP/RENAME/MODIFY statements found in schema_student_portal.sql.\n\n";

if (! empty($sharedTables)) {
    echo "Reminder — these tables are touched by BOTH schema_student_portal.sql and the Admin codebase.\n";
    echo "Verify manually that any new column/table here doesn't change a meaning the Admin code assumes:\n\n";
    foreach ($sharedTables as $table => $count) {
        echo "  - {$table} ({$count} reference(s) in Admin code)\n";
    }
}

exit(0);

function findNonAdditiveStatements(string $sql): array
{
    $patterns = [
        '/DROP\s+TABLE\s+`?\w+`?/i',
        '/DROP\s+COLUMN\s+`?\w+`?/i',
        '/RENAME\s+(TABLE|COLUMN)\s+/i',
        '/\bMODIFY\s+(COLUMN\s+)?`?\w+`?/i',
        '/\bCHANGE\s+(COLUMN\s+)?`?\w+`?/i',
    ];

    $found = [];
    foreach ($patterns as $pattern) {
        if (preg_match_all($pattern, $sql, $matches)) {
            $found = array_merge($found, $matches[0]);
        }
    }

    return $found;
}

function extractTouchedTables(string $sql): array
{
    $tables = [];
    if (preg_match_all('/CREATE TABLE `(\w+)`/i', $sql, $matches)) {
        $tables = array_merge($tables, $matches[1]);
    }
    if (preg_match_all('/ALTER TABLE `(\w+)`/i', $sql, $matches)) {
        $tables = array_merge($tables, $matches[1]);
    }

    return array_unique($tables);
}

function findTablesReferencedInAdminCode(array $tables, string $adminRoot): array
{
    $shared = [];
    foreach ($tables as $table) {
        $count = countReferencesInDirectory($table, $adminRoot . 'app');
        if ($count > 0) {
            $shared[$table] = $count;
        }
    }
    ksort($shared);

    return $shared;
}

function countReferencesInDirectory(string $table, string $dir): int
{
    if (! is_dir($dir)) {
        return 0;
    }

    $count = 0;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $contents = file_get_contents($file->getPathname());
        $count += substr_count($contents, $table);
    }

    return $count;
}
