<?php

require_once __DIR__ . '/lib/scheduled-publisher.php';

use IndependantDigital\Publishing\ScheduledPublisher;

function envValue(string $name, ?string $fallback = null): ?string {
	$value = getenv($name);
	return $value === false || $value === '' ? $fallback : $value;
}

try {
	$driver = strtolower((string) envValue('DB_DRIVER', envValue('DB_CONNECTION', 'mysql')));
	$database = (string) envValue('DB_DATABASE', 'vvveb');
	$dsn = envValue('DB_DSN');

	if (! $dsn) {
		if ($driver === 'pgsql' || $driver === 'postgres' || $driver === 'postgresql') {
			$dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', envValue('DB_HOST', 'db'), envValue('DB_PORT', '5432'), $database);
		} elseif ($driver === 'sqlite') {
			$dsn = 'sqlite:' . $database;
		} else {
			$dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', envValue('DB_HOST', 'db'), envValue('DB_PORT', '3306'), $database);
		}
	}

	$pdo = new PDO($dsn, envValue('DB_USER', 'vvveb'), envValue('DB_PASSWORD', envValue('VVVEB_PASSWORD', '')), [
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
	]);
	$published = (new ScheduledPublisher($pdo))->publishDue();
	if ($published || envValue('SCHEDULED_PUBLISHER_QUIET', '0') !== '1') {
		fwrite(STDOUT, sprintf("scheduled publisher: %d post(s) published%s\n", count($published), $published ? ' [' . implode(',', $published) . ']' : ''));
	}
} catch (Throwable $error) {
	fwrite(STDERR, 'scheduled publisher failed: ' . $error->getMessage() . "\n");
	exit(1);
}
