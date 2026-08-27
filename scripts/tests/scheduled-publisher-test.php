<?php

$library = dirname(__DIR__) . '/lib/scheduled-publisher.php';
if (! is_file($library)) {
    fwrite(STDERR, "FAIL: scheduled publisher library is missing.\n");
    exit(1);
}

require_once $library;

use IndependantDigital\Publishing\ScheduledPublisher;

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE post (post_id INTEGER PRIMARY KEY, status TEXT NOT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL)');
$pdo->exec('CREATE TABLE post_meta (post_id INTEGER NOT NULL, namespace TEXT NOT NULL, key TEXT NOT NULL, value TEXT, PRIMARY KEY(post_id, namespace, key))');

$posts = [
    [1, 'scheduled', '2026-08-27 08:00:00'],
    [2, 'scheduled', '2026-08-27 08:00:00'],
    [3, 'scheduled', '2026-09-01 08:00:00'],
    [4, 'draft',     '2026-08-27 08:00:00'],
    [5, 'scheduled', '2026-08-27 08:00:00'],
];
$insertPost = $pdo->prepare('INSERT INTO post (post_id,status,created_at,updated_at) VALUES (?,?,?,?)');
foreach ($posts as [$id, $status, $created]) {
    $insertPost->execute([$id, $status, $created, $created]);
}

$insertMeta = $pdo->prepare('INSERT INTO post_meta (post_id,namespace,key,value) VALUES (?,?,?,?)');
$insertMeta->execute([1, 'independant_digital', 'editorial_ready', '1']);
$insertMeta->execute([2, 'independant_digital', 'editorial_ready', '0']);
$insertMeta->execute([3, 'independant_digital', 'editorial_ready', '1']);
$insertMeta->execute([4, 'independant_digital', 'editorial_ready', '1']);
$insertMeta->execute([5, 'unrelated', 'editorial_ready', '1']);

$publisher = new ScheduledPublisher($pdo);
$published = $publisher->publishDue(new DateTimeImmutable('2026-08-27 12:00:00', new DateTimeZone('UTC')));

$failures = 0;
if ($published !== [1]) {
    fwrite(STDERR, 'FAIL: only the due and editorially approved post may publish; got ' . json_encode($published) . "\n");
    $failures++;
}

$statuses = $pdo->query('SELECT post_id,status FROM post ORDER BY post_id')->fetchAll(PDO::FETCH_KEY_PAIR);
$expected = [1 => 'publish', 2 => 'scheduled', 3 => 'scheduled', 4 => 'draft', 5 => 'scheduled'];
if ($statuses !== $expected) {
    fwrite(STDERR, 'FAIL: held or future records changed state: ' . json_encode($statuses) . "\n");
    $failures++;
}

if ($publisher->publishDue(new DateTimeImmutable('2026-08-27 12:05:00', new DateTimeZone('UTC'))) !== []) {
    fwrite(STDERR, "FAIL: a second scheduler run must be idempotent.\n");
    $failures++;
}

$root = dirname(__DIR__, 2);
foreach (['mysqli', 'pgsql', 'sqlite'] as $driver) {
    $sql = (string) file_get_contents($root . "/app/sql/$driver/post.sql");
    if (! str_contains($sql, "AND post.status = 'publish'") || ! str_contains($sql, "AND _.status = 'publish'")) {
        fwrite(STDERR, "FAIL: $driver frontend post queries do not hide non-published records.\n");
        $failures++;
    }
}

$seed = (string) file_get_contents($root . '/seed.dokploy.sql');
foreach (['alternatives-microsoft-teams', 'alternative-zoom-francaise', 'suite-collaborative-francaise'] as $slug) {
    if (! str_contains($seed, "'$slug'")) {
        fwrite(STDERR, "FAIL: scheduled content calendar is missing $slug.\n");
        $failures++;
    }
}
if (substr_count($seed, "'independant_digital','editorial_ready','0'") < 3) {
    fwrite(STDERR, "FAIL: all scheduled acquisition drafts must start behind editorial_ready=0.\n");
    $failures++;
}

$dockerfile = (string) file_get_contents($root . '/Dockerfile.dokploy');
$dockerignore = (string) file_get_contents($root . '/.dockerignore');
$init = (string) file_get_contents($root . '/init.dokploy.sh');
if (! str_contains($dockerfile, 'publish-scheduled-content.php') || ! str_contains($dockerfile, 'scheduled-publisher.php')) {
    fwrite(STDERR, "FAIL: production image does not include the scheduled publisher.\n");
    $failures++;
}
foreach (['!app/sql/mysqli/post.sql', '!app/sql/pgsql/post.sql', '!app/sql/sqlite/post.sql', '!scripts/lib/scheduled-publisher.php', '!scripts/publish-scheduled-content.php'] as $buildInput) {
    if (! str_contains($dockerignore, $buildInput)) {
        fwrite(STDERR, "FAIL: Docker build context excludes required scheduler input $buildInput.\n");
        $failures++;
    }
}
if (! str_contains($init, 'publish-scheduled-content.php')) {
    fwrite(STDERR, "FAIL: production startup does not run the scheduled publisher.\n");
    $failures++;
}

if ($failures > 0) {
    fwrite(STDERR, "scheduled-publisher tests: FAIL ($failures issue(s))\n");
    exit(1);
}

fwrite(STDOUT, "scheduled-publisher tests: PASS\n");
