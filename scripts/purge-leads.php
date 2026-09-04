<?php

// Retention purge for lead_submission. Dry run by default: prints how many
// rows are out of retention and deletes nothing. Pass --apply to delete.
//
//   LEAD_RETENTION_DAYS=365 php scripts/purge-leads.php          # report only
//   LEAD_RETENTION_DAYS=365 php scripts/purge-leads.php --apply  # delete
//
// The retention is deliberately not defaulted: it must match the duration
// published in the privacy notice (docs/launch/open-items.md, "Durée de
// conservation"). Single-flight through the same kind of flock the flush job
// uses, so an overlapping cron tick is a no-op rather than a second pass.

if (PHP_SAPI !== 'cli') {
	fwrite(STDERR, "CLI only.\n");
	exit(1);
}

if (! defined('V_VERSION')) {
	define('V_VERSION', 'cli');
}

if (! defined('DIR_ROOT')) {
	define('DIR_ROOT', dirname(__DIR__) . '/');
}

require_once __DIR__ . '/../plugins/lead-platform-connector/system/lead-retention.php';

use Vvveb\Plugins\LeadPlatformConnector\System\LeadRetention;

function purgeEnv(string $name, ?string $fallback = null): ?string {
	$value = getenv($name);

	return $value === false || $value === '' ? $fallback : $value;
}

$apply = in_array('--apply', $argv, true);
$daysRaw = purgeEnv('LEAD_RETENTION_DAYS');
foreach ($argv as $arg) {
	if (str_starts_with($arg, '--days=')) {
		$daysRaw = substr($arg, 7);
	}
}
if ($daysRaw === null || ! ctype_digit($daysRaw) || (int) $daysRaw <= 0) {
	fwrite(STDERR, "purge leads: set LEAD_RETENTION_DAYS (or --days=N) to the retention published in the privacy notice; refusing to guess.\n");
	exit(2);
}
$days = (int) $daysRaw;

$lockPath = purgeEnv('PURGE_LOCK_FILE', DIR_ROOT . 'storage/purge-leads.lock');
$lock     = @fopen($lockPath, 'c');
if ($lock === false) {
	fwrite(STDERR, "purge leads failed: cannot open lock file $lockPath\n");
	exit(1);
}
if (! flock($lock, LOCK_EX | LOCK_NB)) {
	fwrite(STDOUT, "another purge run holds the lock; nothing to do\n");
	exit(0);
}

try {
	$driver   = strtolower((string) purgeEnv('DB_DRIVER', purgeEnv('DB_CONNECTION', 'mysql')));
	$database = (string) purgeEnv('DB_DATABASE', 'vvveb');
	$dsn      = purgeEnv('DB_DSN');
	if (! $dsn) {
		if (in_array($driver, ['pgsql', 'postgres', 'postgresql'], true)) {
			$dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', purgeEnv('DB_HOST', 'db'), purgeEnv('DB_PORT', '5432'), $database);
		} elseif ($driver === 'sqlite') {
			$dsn = 'sqlite:' . $database;
		} else {
			$dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', purgeEnv('DB_HOST', 'db'), purgeEnv('DB_PORT', '3306'), $database);
		}
	}
	$pdo = new PDO($dsn, purgeEnv('DB_USER', 'vvveb'), purgeEnv('DB_PASSWORD', purgeEnv('VVVEB_PASSWORD', '')), [
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
	]);

	$now   = gmdate('Y-m-d H:i:s');
	$count = LeadRetention::purge($pdo, $days, $now, $apply);
	fwrite(STDOUT, sprintf(
		"%s %d lead rows older than %d days (cutoff %s UTC)%s\n",
		$apply ? 'deleted' : 'would delete',
		$count,
		$days,
		LeadRetention::cutoff($days, $now),
		$apply ? '' : '; pass --apply to delete'
	));
} catch (Throwable $error) {
	fwrite(STDERR, 'purge leads failed: ' . $error->getMessage() . "\n");
	exit(1);
} finally {
	flock($lock, LOCK_UN);
	fclose($lock);
}
