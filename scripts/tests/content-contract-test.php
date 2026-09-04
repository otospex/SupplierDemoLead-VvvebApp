<?php

$root = dirname(__DIR__, 2);
$validator = $root . '/scripts/validate-content-contract.php';

if (! is_file($validator)) {
    fwrite(STDERR, "FAIL: content contract validator is missing.\n");
    exit(1);
}

$dir = sys_get_temp_dir() . '/independent-digital-contract-' . bin2hex(random_bytes(4));
mkdir($dir, 0700, true);

$providerIncomplete = "# Fournisseur\n\n## Summary\nBref.\n\n## Best fit\nCas.\n";
$providerComplete = <<<'MD'
# Fournisseur

## Summary
Résumé factuel.
## Best fit
Cas adapté.
## Does not fit
Cas inadapté.
## Capabilities
Fonctions vérifiées.
## Hosting and jurisdiction
Périmètre documenté.
## Security evidence
Sources disponibles.
## Integration and reversibility
Exports et API.
## Pricing
Prix vérifié ou inconnu.
## Evidence gaps
Questions ouvertes.
## Commercial relationship
Aucune ou déclarée.
## Alternatives
Substituts crédibles.
## Sources
- https://example.test/source
## Review record
Owner: rédaction
Reviewed: 2026-08-27
MD;

$alternativesIncomplete = "# Alternatives\n\n## Search intent\nComparer.\n\n## Decision table\nTableau.\n";
$alternativesComplete = <<<'MD'
# Alternatives

## Search intent
Besoin précis.
## Demand evidence
Source: registre éditorial.
## Selection method
Critères publiés.
## Decision table
Comparaison homogène.
## Detailed alternatives
Options étudiées.
## Migration considerations
Coût et coexistence.
## Commercial relationships
Relations déclarées.
## Sources
- https://example.test/source
## Review record
Owner: rédaction
Reviewed: 2026-08-27
MD;

$fixtures = [
    'provider-incomplete.md' => $providerIncomplete,
    'provider-complete.md' => $providerComplete,
    'alternatives-incomplete.md' => $alternativesIncomplete,
    'alternatives-complete.md' => $alternativesComplete,
];
foreach ($fixtures as $name => $content) {
    file_put_contents($dir . '/' . $name, $content);
}

function runValidator(string $validator, string $type, string $file): array {
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($validator) . ' ' . escapeshellarg($type) . ' ' . escapeshellarg($file) . ' 2>&1';
    exec($command, $output, $code);
    return [$code, implode("\n", $output)];
}

$failures = 0;
[$code, $output] = runValidator($validator, 'provider', $dir . '/provider-incomplete.md');
if ($code === 0 || strpos($output, 'Does not fit') === false || strpos($output, 'Alternatives') === false) {
    fwrite(STDERR, "FAIL: incomplete provider contract did not report missing fit and alternatives sections.\n");
    $failures++;
}

[$code] = runValidator($validator, 'provider', $dir . '/provider-complete.md');
if ($code !== 0) {
    fwrite(STDERR, "FAIL: complete provider contract was rejected.\n");
    $failures++;
}

[$code, $output] = runValidator($validator, 'alternatives', $dir . '/alternatives-incomplete.md');
if ($code === 0 || strpos($output, 'Demand evidence') === false || strpos($output, 'Selection method') === false) {
    fwrite(STDERR, "FAIL: incomplete alternatives contract did not report demand and methodology gaps.\n");
    $failures++;
}

[$code] = runValidator($validator, 'alternatives', $dir . '/alternatives-complete.md');
if ($code !== 0) {
    fwrite(STDERR, "FAIL: complete alternatives contract was rejected.\n");
    $failures++;
}

foreach (array_keys($fixtures) as $name) {
    @unlink($dir . '/' . $name);
}
@rmdir($dir);

if ($failures > 0) {
    fwrite(STDERR, "content-contract tests: FAIL ($failures issue(s))\n");
    exit(1);
}

fwrite(STDOUT, "content-contract tests: PASS\n");
