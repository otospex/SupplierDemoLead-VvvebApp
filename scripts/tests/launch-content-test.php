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

foreach ([
    'methode-evaluation',
    'transparence-partenariats',
    'diagnostic-souverainete',
    'contact',
    'a-propos',
    'independance-numerique',
    'sortir-microsoft-365',
    'choisir-visioconference-collaboration',
    'confidentialite',
] as $slug) {
    requirePattern("/'" . preg_quote($slug, '/') . "'/u", $seed, "launch seed is missing $slug.");
}

requirePattern('/independant-digital-intake/u', $seed, 'queue-only lead endpoint is missing from the seed.');
requirePattern('/data-v-endpoint="independant-digital-intake"/u', $homepage, 'homepage form is not connected to the launch endpoint.');
requirePattern('/name="privacy_acknowledgement"/u', $homepage, 'homepage form is missing privacy acknowledgement.');
requirePattern('/data-v-endpoint="independant-digital-intake"/u', $contact, 'contact form is not connected to the launch endpoint.');
requirePattern('/name="privacy_acknowledgement"/u', $contact, 'contact form is missing privacy acknowledgement.');

if ($failures > 0) {
    fwrite(STDERR, "launch-content tests: FAIL ($failures issue(s))\n");
    exit(1);
}

fwrite(STDOUT, "launch-content tests: PASS\n");
