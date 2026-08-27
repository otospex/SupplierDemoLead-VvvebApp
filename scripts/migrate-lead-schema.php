<?php

if (PHP_SAPI !== 'cli') {
	fwrite(STDERR, "CLI only.\n");
	exit(1);
}

function migrationEnv(string $name, ?string $fallback = null): ?string {
	$value = getenv($name);
	return $value === false || $value === '' ? $fallback : $value;
}

try {
	$driver = strtolower((string) migrationEnv('DB_DRIVER', migrationEnv('DB_CONNECTION', 'mysql')));
	$database = (string) migrationEnv('DB_DATABASE', 'vvveb');
	$dsn = migrationEnv('DB_DSN');
	if (! $dsn) {
		if (in_array($driver, ['pgsql', 'postgres', 'postgresql'], true)) {
			$dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', migrationEnv('DB_HOST', 'db'), migrationEnv('DB_PORT', '5432'), $database);
		} elseif ($driver === 'sqlite') {
			$dsn = 'sqlite:' . $database;
		} else {
			$dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', migrationEnv('DB_HOST', 'db'), migrationEnv('DB_PORT', '3306'), $database);
		}
	}
	$pdo = new PDO($dsn, migrationEnv('DB_USER', 'vvveb'), migrationEnv('DB_PASSWORD', migrationEnv('VVVEB_PASSWORD', '')), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
	$definitions = [
		'provider_slug' => $driver === 'sqlite' ? 'TEXT DEFAULT NULL' : 'varchar(64) DEFAULT NULL',
		'consent_text_version' => $driver === 'sqlite' ? 'TEXT DEFAULT NULL' : 'varchar(64) DEFAULT NULL',
		'consent_at' => in_array($driver, ['pgsql', 'postgres', 'postgresql'], true) ? 'timestamp(0) DEFAULT NULL' : 'datetime DEFAULT NULL',
		'payload_enc' => in_array($driver, ['mysql', 'mysqli'], true) ? 'longtext DEFAULT NULL' : 'TEXT DEFAULT NULL',
	];
	foreach ($definitions as $column => $definition) {
		$ifMissing = in_array($driver, ['pgsql', 'postgres', 'postgresql'], true) ? ' IF NOT EXISTS' : '';
		try {
			$pdo->exec("ALTER TABLE lead_submission ADD COLUMN$ifMissing $column $definition");
		} catch (Throwable $ignored) {
			// Duplicate columns are expected after the first successful run.
		}
	}
	$pdo->query('SELECT provider_slug, consent_text_version, consent_at, payload_enc FROM lead_submission WHERE 1 = 0');
	fwrite(STDOUT, "lead schema migration: ready\n");
} catch (Throwable $error) {
	fwrite(STDERR, 'lead schema migration failed: ' . $error->getMessage() . "\n");
	exit(1);
}
