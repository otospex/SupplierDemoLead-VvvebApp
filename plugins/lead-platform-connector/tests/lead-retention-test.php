<?php

define('V_VERSION', 'test');

$file = __DIR__ . '/../system/lead-retention.php';
if (! is_file($file)) {
    fwrite(STDERR, "FAIL: lead retention helper is missing.\n");
    exit(1);
}

require_once $file;

use Vvveb\Plugins\LeadPlatformConnector\System\LeadRetention;

$failures = 0;

function expectTrue($condition, string $message): void {
    global $failures;
    if (! $condition) {
        fwrite(STDERR, "FAIL: $message\n");
        $failures++;
    }
}

$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('CREATE TABLE lead_submission (
    lead_submission_id INTEGER PRIMARY KEY AUTOINCREMENT,
    status TEXT NOT NULL DEFAULT "pending",
    stage INTEGER NOT NULL DEFAULT 3,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
)');

$now = '2026-09-02 12:00:00';
$rows = [
    // [status, stage, updated_at]
    ['sent',      3, '2025-06-01 00:00:00'], // well past 365 days
    ['queued',    3, '2025-09-02 11:59:59'], // one second past the cutoff
    ['queued',    3, '2025-09-02 12:00:00'], // exactly at the cutoff: kept
    ['failed',    3, '2026-08-01 00:00:00'], // recent
    ['pending',   1, '2025-01-01 00:00:00'], // old partial: purged too
];
$insert = $pdo->prepare('INSERT INTO lead_submission (status, stage, created_at, updated_at) VALUES (?, ?, ?, ?)');
foreach ($rows as [$status, $stage, $updated]) {
    $insert->execute([$status, $stage, $updated, $updated]);
}

// --- cutoff arithmetic ------------------------------------------------------
expectTrue(LeadRetention::cutoff(365, $now) === '2025-09-02 12:00:00', 'a 365-day retention from 2026-09-02 12:00 must cut at 2025-09-02 12:00 UTC.');
expectTrue(LeadRetention::cutoff(30, '2026-03-01 00:00:00') === '2026-01-30 00:00:00', 'a 30-day retention must subtract exactly 30 days.');

// --- retention must be an explicit positive integer -------------------------
foreach ([0, -1] as $bad) {
    $threw = false;
    try {
        LeadRetention::cutoff($bad, $now);
    } catch (\InvalidArgumentException $e) {
        $threw = true;
    }
    expectTrue($threw, "a retention of $bad days must be refused, not treated as purge-everything.");
}

// --- dry run: counts but deletes nothing ------------------------------------
$dry = LeadRetention::purge($pdo, 365, $now, false);
expectTrue($dry === 3, 'dry run must report the 3 rows older than the cutoff, got ' . var_export($dry, true) . '.');
expectTrue((int) $pdo->query('SELECT COUNT(*) FROM lead_submission')->fetchColumn() === 5, 'dry run must not delete anything.');

// --- apply: deletes exactly those rows -------------------------------------
$deleted = LeadRetention::purge($pdo, 365, $now, true);
expectTrue($deleted === 3, 'apply must delete the same 3 rows the dry run counted.');
$left = $pdo->query('SELECT updated_at FROM lead_submission ORDER BY updated_at')->fetchAll(PDO::FETCH_COLUMN);
expectTrue($left === ['2025-09-02 12:00:00', '2026-08-01 00:00:00'], 'only the at-cutoff row and the recent row may survive, got ' . json_encode($left) . '.');

// --- the CLI script wires the helper behind an explicit retention and a lock -
$script = (string) file_get_contents(__DIR__ . '/../../../scripts/purge-leads.php');
expectTrue(str_contains($script, 'LEAD_RETENTION_DAYS'), 'the purge script must read the retention from LEAD_RETENTION_DAYS.');
expectTrue(str_contains($script, '--apply'), 'the purge script must default to a dry run and require --apply to delete.');
expectTrue(str_contains($script, 'LOCK_EX | LOCK_NB'), 'the purge script must be single-flight like the flush job.');
expectTrue(str_contains($script, 'LeadRetention::purge('), 'the purge script must delegate to LeadRetention::purge().');

if ($failures > 0) {
    fwrite(STDERR, "lead-retention tests: FAIL ($failures issue(s))\n");
    exit(1);
}

fwrite(STDOUT, "lead-retention tests: PASS\n");
