<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$audit = $root . '/scripts/editorial-audit.php';

if (! is_file($audit)) {
    fwrite(STDERR, "FAIL: scripts/editorial-audit.php does not exist\n");
    exit(1);
}

$fixtureDir = sys_get_temp_dir() . '/independant-digital-editorial-audit-' . bin2hex(random_bytes(6));
if (! mkdir($fixtureDir, 0700, true) && ! is_dir($fixtureDir)) {
    fwrite(STDERR, "FAIL: could not create fixture directory\n");
    exit(1);
}

$clean = <<<'HTML'
<h1>Indépendant Digital</h1>
<p>AIFEL indique que son service est hébergé en France. Cette affirmation reste à vérifier dans le cadre de notre revue fournisseur.</p>
HTML;

$risky = <<<'HTML'
<p>La confiance de plus de 250 organisations européennes.</p>
<p>ACMECORP utilise déjà notre méthode.</p>
<p>Son impact carbone est plus bas que tout le monde.</p>
<p>Indépendant Digital est certifié SecNumCloud.</p>
HTML;

file_put_contents($fixtureDir . '/clean.html', $clean);
file_put_contents($fixtureDir . '/risky.html', $risky);

/** @return array{code:int,output:string} */
function runAudit(string $audit, string $path): array
{
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($audit) . ' ' . escapeshellarg($path) . ' 2>&1';
    $lines = [];
    $code = 0;
    exec($command, $lines, $code);

    return ['code' => $code, 'output' => implode("\n", $lines)];
}

$failures = [];
$cleanResult = runAudit($audit, $fixtureDir . '/clean.html');
if ($cleanResult['code'] !== 0) {
    $failures[] = "clean fixture should pass:\n" . $cleanResult['output'];
}

$riskyResult = runAudit($audit, $fixtureDir . '/risky.html');
if ($riskyResult['code'] === 0) {
    $failures[] = 'risky fixture should fail';
}

foreach (['FABRICATED_PROOF', 'PLACEHOLDER_BRAND', 'CARBON_SUPERLATIVE', 'UNSCOPED_CERTIFICATION'] as $rule) {
    if (strpos($riskyResult['output'], '[' . $rule . ']') === false) {
        $failures[] = "risky fixture did not emit {$rule}:\n" . $riskyResult['output'];
    }
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($fixtureDir, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST
);
foreach ($iterator as $entry) {
    if ($entry->isDir()) {
        rmdir($entry->getPathname());
    } else {
        unlink($entry->getPathname());
    }
}
rmdir($fixtureDir);

if ($failures !== []) {
    fwrite(STDERR, "editorial-audit tests: FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "editorial-audit tests: PASS\n";
