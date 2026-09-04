<?php

define('V_VERSION', 'test');

$file = __DIR__ . '/../system/honeypot.php';
if (! is_file($file)) {
    fwrite(STDERR, "FAIL: honeypot helper is missing.\n");
    exit(1);
}

require_once $file;

use Vvveb\Plugins\LeadPlatformConnector\System\Honeypot;

$failures = 0;

function expectHoneypot($condition, string $message): void {
    global $failures;
    if (! $condition) {
        fwrite(STDERR, "FAIL: $message\n");
        $failures++;
    }
}

// --- tripped(): a filled decoy is a bot that skipped the runtime -----------

expectHoneypot(
    Honeypot::tripped(['email' => 'dsi@example.fr', 'company_website' => 'https://spam.example']) === true,
    'a non-empty decoy must trip the honeypot.'
);
expectHoneypot(
    Honeypot::tripped(['email' => 'dsi@example.fr', 'company_website' => '']) === false,
    'an empty decoy is what a real browser sends; it must not trip.'
);
expectHoneypot(
    Honeypot::tripped(['email' => 'dsi@example.fr']) === false,
    'an absent decoy must not trip — the runtimes strip it before posting.'
);
// The runtimes test `if (hp && hp.value)`, where " " is truthy. Trimming here
// would accept a payload the browser itself would have refused.
expectHoneypot(
    Honeypot::tripped(['company_website' => ' ']) === true,
    'a whitespace-only decoy must trip, matching the runtimes\' truthiness test.'
);
expectHoneypot(
    Honeypot::tripped(['company_website' => '0']) === true,
    'the string "0" is a filled decoy, not an empty one.'
);
expectHoneypot(
    Honeypot::tripped(['company_website' => null]) === false,
    'a null decoy carries nothing and must not trip.'
);
expectHoneypot(
    Honeypot::tripped(['company_website' => []]) === false,
    'an empty array decoy carries nothing and must not trip.'
);
expectHoneypot(
    Honeypot::tripped(['company_website' => ['https://spam.example']]) === true,
    'an array decoy with a value in it must still trip.'
);

// --- strip(): an empty decoy is never an answer somebody gave --------------

$stripped = Honeypot::strip(['email' => 'dsi@example.fr', 'company_website' => '']);
expectHoneypot(! array_key_exists('company_website', $stripped), 'strip must remove the decoy field.');
expectHoneypot(($stripped['email'] ?? null) === 'dsi@example.fr', 'strip must not touch other fields.');
expectHoneypot(
    Honeypot::strip(['email' => 'dsi@example.fr']) === ['email' => 'dsi@example.fr'],
    'strip must be a no-op when the decoy is absent.'
);

// --- the field name has to be the same one the forms actually render -------

$runtimes = [
    'lead-form runtime' => __DIR__ . '/../public/js/lead-form.20260827.js',
    'diagnostic-form runtime' => __DIR__ . '/../public/js/diagnostic-form.20260901.js',
    'leadform component' => __DIR__ . '/../component/leadform.php',
    'solutions registration form' => __DIR__ . '/../../solutions-directory/system/solution-presenter.php',
];
foreach ($runtimes as $label => $path) {
    $source = is_file($path) ? (string) file_get_contents($path) : '';
    expectHoneypot(
        str_contains($source, Honeypot::FIELD),
        "$label does not use the honeypot field name the server enforces (" . Honeypot::FIELD . ').'
    );
}

// --- the controller must check before it validates or stores --------------

$submit = (string) file_get_contents(__DIR__ . '/../app/controller/submit.php');
expectHoneypot(str_contains($submit, 'Honeypot::tripped($fields)'), 'the submit controller must run the server-side honeypot check.');
expectHoneypot(str_contains($submit, 'Honeypot::strip($fields)'), 'the submit controller must strip the decoy from legitimate payloads.');
$honeypotAt = strpos($submit, 'Honeypot::tripped($fields)');
foreach ([
    'PartialLead::validate' => 'the staged-intake validator',
    'PrivacyAcknowledgement::validate($fields)' => 'privacy acknowledgement validation',
    '$this->stageInsert(' => 'the row insert',
] as $needle => $description) {
    $position = strpos($submit, $needle);
    expectHoneypot(
        $honeypotAt !== false && $position !== false && $honeypotAt < $position,
        "the honeypot check must run before $description, so a bot's request is never stored."
    );
}
// A distinguishable rejection teaches the next attempt what to avoid.
$afterCheck = $honeypotAt === false ? '' : substr($submit, $honeypotAt, 200);
expectHoneypot(
    str_contains($afterCheck, "['ok' => true, 'queued' => true]"),
    'a tripped honeypot must return the ordinary queued-success body, not a distinguishable rejection.'
);

if ($failures > 0) {
    fwrite(STDERR, "honeypot tests: FAIL ($failures issue(s))\n");
    exit(1);
}

fwrite(STDOUT, "honeypot tests: PASS\n");
