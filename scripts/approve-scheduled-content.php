<?php

if (PHP_SAPI !== 'cli') {
	fwrite(STDERR, "CLI only.\n");
	exit(1);
}

$options = getopt('', ['slug:', 'reviewer:']);
$slug = trim((string) ($options['slug'] ?? ''));
$reviewer = trim((string) ($options['reviewer'] ?? ''));
if (! preg_match('/^[a-z0-9-]{2,160}$/', $slug) || $reviewer === '') {
	fwrite(STDERR, "Usage: php scripts/approve-scheduled-content.php --slug=<slug> --reviewer=<name>\n");
	exit(2);
}

function approvalEnv(string $name, ?string $fallback = null): ?string {
	$value = getenv($name);
	return $value === false || $value === '' ? $fallback : $value;
}

try {
	$driver = strtolower((string) approvalEnv('DB_DRIVER', approvalEnv('DB_CONNECTION', 'mysql')));
	$database = (string) approvalEnv('DB_DATABASE', 'vvveb');
	$dsn = approvalEnv('DB_DSN');
	if (! $dsn) {
		if (in_array($driver, ['pgsql', 'postgres', 'postgresql'], true)) {
			$dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', approvalEnv('DB_HOST', 'db'), approvalEnv('DB_PORT', '5432'), $database);
		} elseif ($driver === 'sqlite') {
			$dsn = 'sqlite:' . $database;
		} else {
			$dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', approvalEnv('DB_HOST', 'db'), approvalEnv('DB_PORT', '3306'), $database);
		}
	}
	$pdo = new PDO($dsn, approvalEnv('DB_USER', 'vvveb'), approvalEnv('DB_PASSWORD', approvalEnv('VVVEB_PASSWORD', '')), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
	$pdo->beginTransaction();
	$find = $pdo->prepare("SELECT p.post_id FROM post p INNER JOIN post_content pc ON pc.post_id = p.post_id WHERE pc.slug = :slug AND p.status = 'scheduled' ORDER BY p.post_id LIMIT 1");
	$find->execute(['slug' => $slug]);
	$postId = $find->fetchColumn();
	if (! $postId) throw new RuntimeException('Article programmé introuvable.');
	$keyColumn = in_array($driver, ['pgsql', 'postgres', 'postgresql'], true) ? '"key"' : '`key`';
	$delete = $pdo->prepare("DELETE FROM post_meta WHERE post_id = :post_id AND namespace = 'independant_digital' AND $keyColumn IN ('editorial_ready','editorial_reviewer','editorial_approved_at')");
	$delete->execute(['post_id' => $postId]);
	$upsert = $pdo->prepare("INSERT INTO post_meta (post_id, namespace, $keyColumn, value) VALUES (:post_id, 'independant_digital', :meta_key, :value)");
	foreach (['editorial_ready' => '1', 'editorial_reviewer' => $reviewer, 'editorial_approved_at' => gmdate('Y-m-d H:i:s')] as $key => $value) {
		$upsert->execute(['post_id' => $postId, 'meta_key' => $key, 'value' => $value]);
	}
	$pdo->commit();
	fwrite(STDOUT, "Publication approuvée: $slug par $reviewer\n");
} catch (Throwable $error) {
	if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
	fwrite(STDERR, 'Approbation refusée: ' . $error->getMessage() . "\n");
	exit(1);
}
