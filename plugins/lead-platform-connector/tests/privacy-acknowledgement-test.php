<?php

define('V_VERSION', 'test');

$validator = __DIR__ . '/../system/privacy-acknowledgement.php';
if (! is_file($validator)) {
    fwrite(STDERR, "FAIL: privacy acknowledgement validator is missing.\n");
    exit(1);
}

require_once $validator;

use Vvveb\Plugins\LeadPlatformConnector\System\PrivacyAcknowledgement;

$failures = 0;

foreach ([[], ['privacy_acknowledgement' => '0'], ['privacy_acknowledgement' => 'yes']] as $fields) {
    if (PrivacyAcknowledgement::validate($fields)['ok'] !== false) {
        fwrite(STDERR, "FAIL: only the explicit value 1 may acknowledge the privacy notice.\n");
        $failures++;
    }
}

$accepted = PrivacyAcknowledgement::validate(['privacy_acknowledgement' => '1']);
if ($accepted['ok'] !== true || array_key_exists('privacy_acknowledgement', $accepted['fields'])) {
    fwrite(STDERR, "FAIL: acknowledgement must pass and be removed from the delivery payload.\n");
    $failures++;
}

if ($failures > 0) {
    fwrite(STDERR, "privacy-acknowledgement tests: FAIL ($failures issue(s))\n");
    exit(1);
}

fwrite(STDOUT, "privacy-acknowledgement tests: PASS\n");
