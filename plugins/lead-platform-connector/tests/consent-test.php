<?php

define('V_VERSION', 'test');

$validator = __DIR__ . '/../system/provider-consent.php';
if (! is_file($validator)) {
    fwrite(STDERR, "FAIL: provider consent validator is missing.\n");
    exit(1);
}

require_once $validator;

use Vvveb\Plugins\LeadPlatformConnector\System\ProviderConsent;

$failures = 0;

function expectTrue($condition, string $message): void {
    global $failures;
    if (! $condition) {
        fwrite(STDERR, "FAIL: $message\n");
        $failures++;
    }
}

$general = ProviderConsent::validate([
    'email' => 'dsi@example.fr',
    'newsletter_consent' => '1',
], ['aifel']);
expectTrue($general['ok'] === true, 'a general diagnostic must not require a provider.');
expectTrue($general['audit'] === null, 'newsletter consent must not create provider consent.');

$missingExplicit = ProviderConsent::validate([
    'provider_slug' => 'aifel',
    'consent_text_version' => 'provider-intro-v1',
    'consent_timestamp' => '2026-08-27T12:00:00+00:00',
], ['aifel']);
expectTrue($missingExplicit['ok'] === false, 'a provider slug must not imply introduction consent.');

$missingVersion = ProviderConsent::validate([
    'provider_introduction_requested' => '1',
    'provider_slug' => 'aifel',
    'consent_timestamp' => '2026-08-27T12:00:00+00:00',
], ['aifel']);
expectTrue($missingVersion['ok'] === false, 'named consent requires a versioned consent sentence.');

$unknownProvider = ProviderConsent::validate([
    'provider_introduction_requested' => '1',
    'provider_slug' => 'unknown',
    'consent_text_version' => 'provider-intro-v1',
    'consent_timestamp' => '2026-08-27T12:00:00+00:00',
], ['aifel']);
expectTrue($unknownProvider['ok'] === false, 'provider slug must be allowed server-side.');

$aifel = ProviderConsent::validate([
    'provider_introduction_requested' => '1',
    'provider_slug' => 'aifel',
    'consent_text_version' => 'provider-intro-v1',
    'consent_timestamp' => '2026-08-27T12:00:00+00:00',
], ['aifel']);
expectTrue($aifel['ok'] === true, 'a complete AIFEL introduction consent must pass.');
expectTrue(($aifel['audit']['provider_slug'] ?? null) === 'aifel', 'audit record must name AIFEL.');
expectTrue(($aifel['audit']['consent_text_version'] ?? null) === 'provider-intro-v1', 'audit record must keep the consent version.');
expectTrue(($aifel['audit']['consent_at'] ?? null) === '2026-08-27 12:00:00', 'audit timestamp must be normalized to UTC SQL format.');

if ($failures > 0) {
    fwrite(STDERR, "provider-consent tests: FAIL ($failures issue(s))\n");
    exit(1);
}

fwrite(STDOUT, "provider-consent tests: PASS\n");
