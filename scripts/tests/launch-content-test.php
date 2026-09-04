<?php

$root = dirname(__DIR__, 2);
$seed = (string) file_get_contents($root . '/seed.dokploy.sql');
$homepage = (string) file_get_contents($root . '/public/themes/souverainete-digitale/index.fr.html');
$contact = (string) file_get_contents($root . '/public/themes/souverainete-digitale/content/contact.fr.html');

$failures = 0;
function requirePattern(string $pattern, string $subject, string $message): void {
    global $failures;
    if (! preg_match($pattern, $subject)) {
        fwrite(STDERR, "FAIL: $message\n");
        $failures++;
    }
}

$launchSlugs = [
    'methode-evaluation',
    'transparence-partenariats',
    'diagnostic-souverainete',
    'contact',
    'a-propos',
    'independance-numerique',
    'sortir-microsoft-365',
    'choisir-visioconference-collaboration',
    'confidentialite',
    'mentions-legales',
];
foreach ($launchSlugs as $slug) {
    requirePattern("/'" . preg_quote($slug, '/') . "'/u", $seed, "launch seed is missing $slug.");
}

// Published French pages must not carry invented metrics or unscoped availability
// claims. Scan statement by statement so English rows and SVG gradient stops
// (stop-offset="100%") are not mistaken for copy.
function sqlStatements(string $sql): array {
    $out = [];
    $start = 0;
    $inQuote = false;
    $len = strlen($sql);
    for ($i = 0; $i < $len; $i++) {
        $c = $sql[$i];
        if ($inQuote) {
            if ($c === '\\') { $i++; continue; }
            if ($c === "'") {
                if ($i + 1 < $len && $sql[$i + 1] === "'") { $i++; continue; }
                $inQuote = false;
            }
            continue;
        }
        if ($c === "'") { $inQuote = true; continue; }
        if ($c === ';') { $out[] = substr($sql, $start, $i - $start + 1); $start = $i + 1; }
    }
    if ($start < $len) { $out[] = substr($sql, $start); }
    return $out;
}

$unsupportedClaims = [
    '/\d+\+/u' => 'an invented customer or project count',
    '/99\.9/u' => 'an invented availability figure',
    '/\bSLA\b/u' => 'an unsupported service-level claim',
    '#24/7#u' => 'an unsupported round-the-clock claim',
];
foreach (sqlStatements($seed) as $statement) {
    if (! str_contains($statement, '@lang_fr')) {
        continue;
    }
    $slugsInStatement = array_values(array_filter(
        $launchSlugs,
        static fn (string $slug): bool => str_contains($statement, "'" . $slug . "'")
    ));
    if ($slugsInStatement === []) {
        continue;
    }
    foreach ($unsupportedClaims as $pattern => $label) {
        if (preg_match($pattern, $statement)) {
            $where = implode('/', $slugsInStatement);
            fwrite(STDERR, "FAIL: published French row ($where) carries $label.\n");
            $failures++;
        }
    }
}

requirePattern('/independant-digital-intake/u', $seed, 'queue-only lead endpoint is missing from the seed.');
requirePattern('/CREATE TABLE IF NOT EXISTS `lead_endpoint`/u', $seed, 'launch seed does not bootstrap the lead endpoint table.');
requirePattern('/CREATE TABLE IF NOT EXISTS `lead_submission`/u', $seed, 'launch seed does not bootstrap the encrypted lead queue table.');
requirePattern('/data-v-endpoint="independant-digital-intake"/u', $homepage, 'homepage form is not connected to the launch endpoint.');
requirePattern('/name="privacy_acknowledgement"/u', $homepage, 'homepage form is missing privacy acknowledgement.');
requirePattern('/data-v-endpoint="independant-digital-intake"/u', $contact, 'contact form is not connected to the launch endpoint.');
requirePattern('/name="privacy_acknowledgement"/u', $contact, 'contact form is missing privacy acknowledgement.');
// Either versioned runtime satisfies this: lead-form is the one-shot form,
// diagnostic-form is the three-step superset that the intake placements load.
$formRuntimeLink = '#/plugins/lead-platform-connector/js/(?:lead|diagnostic)-form\.\d+\.js#u';
requirePattern($formRuntimeLink, $homepage, 'homepage does not load the versioned form runtime directly.');
requirePattern($formRuntimeLink, $contact, 'contact page does not load the versioned form runtime directly.');

$pageTemplate = (string) file_get_contents($root . '/public/themes/souverainete-digitale/content/page.fr.html');
requirePattern($formRuntimeLink, $pageTemplate, 'French page template does not load the versioned form runtime for seeded diagnostic forms.');

$runtime = (string) file_get_contents($root . '/plugins/lead-platform-connector/public/js/lead-form.20260827.js');
$publicRuntime = (string) file_get_contents($root . '/public/plugins/lead-platform-connector/js/lead-form.20260827.js');
$pluginBootstrap = (string) file_get_contents($root . '/plugins/lead-platform-connector/plugin.php');
$submitController = (string) file_get_contents($root . '/plugins/lead-platform-connector/app/controller/submit.php');
$installer = (string) file_get_contents($root . '/plugins/lead-platform-connector/install.php');
requirePattern('/Merci[^\n]+demande/u', $runtime, 'form success feedback is not localized in French.');
if (str_contains($runtime, 'Thanks — your request was received.')) {
    fwrite(STDERR, "FAIL: form runtime still exposes English success copy.\n");
    $failures++;
}
if ($runtime !== $publicRuntime) {
    fwrite(STDERR, "FAIL: browser-served form runtime is not synchronized with the plugin source.\n");
    $failures++;
}

// The diagnostic stepper ships the same way: plugin source plus a byte-identical
// copy under public/, which is what the browser actually fetches.
$stepper = (string) file_get_contents($root . '/plugins/lead-platform-connector/public/js/diagnostic-form.20260901.js');
$publicStepper = (string) file_get_contents($root . '/public/plugins/lead-platform-connector/js/diagnostic-form.20260901.js');
if ($stepper === '' || $publicStepper === '') {
    fwrite(STDERR, "FAIL: diagnostic stepper runtime is missing from the plugin or from public/.\n");
    $failures++;
} elseif ($stepper !== $publicStepper) {
    fwrite(STDERR, "FAIL: browser-served diagnostic stepper is not synchronized with the plugin source.\n");
    $failures++;
}
requirePattern('/Merci[^\n]+diagnostic est enregistré/u', $stepper, 'diagnostic completion copy is not localized in French.');
requirePattern('/cfg\.ready/', $stepper, 'diagnostic stepper does not fail closed while acquiring its token.');
// A resume token is a bearer credential for one lead row: it must never be
// written into a durable store the next visitor could read.
if (preg_match('/localStorage/', $stepper)) {
    fwrite(STDERR, "FAIL: diagnostic stepper persists the resume token beyond the session.\n");
    $failures++;
}
requirePattern('/lead-form\.\d+\.js/', $pluginBootstrap, 'plugin runtime asset is not cache-busted.');
requirePattern('/if \(! \$this->logSubmission\(\$logRow\)\)/', $submitController, 'queue persistence is not verified before acknowledging receipt.');
requirePattern('/\$this->json\(503,/', $submitController, 'queue persistence failure does not return a non-success response.');
requirePattern('/cfg\.ready/', $runtime, 'form runtime does not fail closed while acquiring its token.');
foreach ([$homepage, $contact, $seed] as $formSource) {
    requirePattern('/onsubmit="return false"/', $formSource, 'a launch form can fall back to an unintended native POST.');
}
foreach (['homepage' => $homepage, 'contact' => $contact] as $labelSource => $formSource) {
    if (preg_match('/<label class="form-label">/', $formSource)) {
        fwrite(STDERR, "FAIL: $labelSource form contains a label without an explicit for attribute.\n");
        $failures++;
    }
}
$frenchChrome = $homepage . $contact . $pageTemplate;
if (str_contains($frenchChrome, "document.getElementById('sd-year').textContent")) {
    fwrite(STDERR, "FAIL: French chrome dereferences a missing footer year element.\n");
    $failures++;
}
requirePattern('/migrateLeadSubmission/', $installer, 'existing lead queue schemas are not migrated.');
requirePattern('/migration verification failed/', $installer, 'lead queue migration is not verified.');
requirePattern('/lpc-schema-v\d+/', $pluginBootstrap, 'existing installs do not receive versioned schema migrations.');
requirePattern('/function app\(\) \{\s*\$this->ensureInstalled\(\);/s', $pluginBootstrap, 'schema migration is not triggered on existing public installations.');
requirePattern('/PartialLead::requiresLocalQueue\(\$\w+\) && \$deliveryMode === DeliveryMode::FORWARD/', $submitController, 'named provider introductions can bypass the durable local consent outbox.');

if ($failures > 0) {
    fwrite(STDERR, "launch-content tests: FAIL ($failures issue(s))\n");
    exit(1);
}

fwrite(STDOUT, "launch-content tests: PASS\n");
