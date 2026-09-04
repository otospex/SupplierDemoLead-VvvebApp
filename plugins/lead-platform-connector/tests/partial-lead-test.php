<?php

define('V_VERSION', 'test');

$file = __DIR__ . '/../system/partial-lead.php';
if (! is_file($file)) {
    fwrite(STDERR, "FAIL: partial lead helper is missing.\n");
    exit(1);
}

require_once $file;

use Vvveb\Plugins\LeadPlatformConnector\System\PartialLead;

$failures = 0;

function expectTrue($condition, string $message): void {
    global $failures;
    if (! $condition) {
        fwrite(STDERR, "FAIL: $message\n");
        $failures++;
    }
}

function neverCalled(): callable {
    return function ($hash) {
        throw new \RuntimeException('findByHash should not have been called');
    };
}

// --- issueToken(): shape and 24h expiry -----------------------------------

$issued = PartialLead::issueToken();

expectTrue(is_string($issued['token'] ?? null), 'issueToken must return a token string.');
expectTrue(preg_match('/^[0-9a-f]{64}$/', $issued['token'] ?? '') === 1, 'token must be 64 lowercase hex chars.');
expectTrue(($issued['hash'] ?? null) === hash('sha256', $issued['token']), 'hash must be sha256 of the token.');

$expiresAt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $issued['expires_at'] ?? '', new \DateTimeZone('UTC'));
expectTrue($expiresAt instanceof \DateTimeImmutable, 'expires_at must parse as Y-m-d H:i:s.');

if ($expiresAt instanceof \DateTimeImmutable) {
    $expectedExpiry = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->modify('+24 hours');
    $driftSeconds   = abs($expectedExpiry->getTimestamp() - $expiresAt->getTimestamp());
    expectTrue($driftSeconds <= 5, "expires_at must be ~24h from now (drift was {$driftSeconds}s).");
}

$reissued = PartialLead::issueToken();
expectTrue($reissued['token'] !== $issued['token'], 'each issued token must be unique.');

// --- validate(): full mode when stage is absent ----------------------------

$full = PartialLead::validate([
    'email'                    => 'dsi@example.fr',
    'privacy_acknowledgement'  => '1',
], neverCalled());

expectTrue(($full['ok'] ?? null) === true, 'a payload without a stage key must validate ok.');
expectTrue(($full['mode'] ?? null) === 'full', 'a payload without a stage key must resolve to full mode.');

// --- validate(): insert mode at stage 1, no token needed --------------------

$insert = PartialLead::validate([
    'stage' => 1,
    'email' => 'dsi@example.fr',
], neverCalled());

expectTrue(($insert['ok'] ?? null) === true, 'stage 1 without a token must validate ok.');
expectTrue(($insert['mode'] ?? null) === 'insert', 'stage 1 without a token must resolve to insert mode.');

// --- validate(): update mode at stages 2 and 3 with a valid token -----------

foreach ([2, 3] as $stage) {
    $tokenPair = PartialLead::issueToken();
    $storedRow = [
        'id'                     => 42,
        'lead_token_hash'        => $tokenPair['hash'],
        'lead_token_expires_at'  => gmdate('Y-m-d H:i:s', time() + 3600),
        'stage'                  => 1,
    ];

    $findByHash = function (string $hash) use ($tokenPair, $storedRow) {
        return $hash === $tokenPair['hash'] ? $storedRow : null;
    };

    $update = PartialLead::validate([
        'stage'      => $stage,
        'lead_token' => $tokenPair['token'],
    ], $findByHash);

    expectTrue(($update['ok'] ?? null) === true, "stage $stage with a valid token must validate ok.");
    expectTrue(($update['mode'] ?? null) === 'update', "stage $stage with a valid token must resolve to update mode.");
    expectTrue(($update['row'] ?? null) === $storedRow, "stage $stage must return the row found via findByHash.");
}

// --- validate(): 400 on stage 2/3 with no lead_token key --------------------

foreach ([2, 3] as $stage) {
    $missingToken = PartialLead::validate([
        'stage' => $stage,
    ], neverCalled());

    expectTrue(($missingToken['ok'] ?? null) === false, "stage $stage without a lead_token key must not validate ok.");
    expectTrue(($missingToken['http'] ?? null) === 400, "stage $stage without a lead_token key must report http 400.");
}

// --- validate(): 410 on unknown hash ----------------------------------------

$unknown = PartialLead::validate([
    'stage'      => 2,
    'lead_token' => str_repeat('a', 64),
], function (string $hash) {
    return null;
});

expectTrue(($unknown['ok'] ?? null) === false, 'an unknown token hash must not validate ok.');
expectTrue(($unknown['http'] ?? null) === 410, 'an unknown token hash must report http 410.');

// --- validate(): 410 on expired row -----------------------------------------

$expiredPair = PartialLead::issueToken();
$expiredRow  = [
    'id'                     => 7,
    'lead_token_hash'        => $expiredPair['hash'],
    'lead_token_expires_at'  => gmdate('Y-m-d H:i:s', time() - 3600),
    'stage'                  => 1,
];

$expired = PartialLead::validate([
    'stage'      => 3,
    'lead_token' => $expiredPair['token'],
], function (string $hash) use ($expiredPair, $expiredRow) {
    return $hash === $expiredPair['hash'] ? $expiredRow : null;
});

expectTrue(($expired['ok'] ?? null) === false, 'an expired token row must not validate ok.');
expectTrue(($expired['http'] ?? null) === 410, 'an expired token row must report http 410.');

// --- merge(): new keys win ---------------------------------------------------

$merged = PartialLead::merge(
    ['email' => 'old@example.fr', 'name' => 'Ancien Nom'],
    ['email' => 'new@example.fr']
);

expectTrue(($merged['email'] ?? null) === 'new@example.fr', 'merge must let new values overwrite existing ones.');
expectTrue(($merged['name'] ?? null) === 'Ancien Nom', 'merge must keep existing keys the new payload does not touch.');

// --- merge(): privacy_acknowledgement is never downgraded from '1' ---------

$ackKept = PartialLead::merge(
    ['privacy_acknowledgement' => '1'],
    ['privacy_acknowledgement' => '0']
);
expectTrue(($ackKept['privacy_acknowledgement'] ?? null) === '1', 'privacy_acknowledgement must never be downgraded from 1.');

$ackKeptOnOmit = PartialLead::merge(
    ['privacy_acknowledgement' => '1'],
    ['email' => 'later-stage@example.fr']
);
expectTrue(($ackKeptOnOmit['privacy_acknowledgement'] ?? null) === '1', 'privacy_acknowledgement must survive a later stage that omits it.');

$ackCanBeSet = PartialLead::merge(
    [],
    ['privacy_acknowledgement' => '1']
);
expectTrue(($ackCanBeSet['privacy_acknowledgement'] ?? null) === '1', 'privacy_acknowledgement must be settable when previously absent.');

// --- merge(): provider-consent fields only survive when requested ----------

$providerKept = PartialLead::merge(
    [],
    [
        'provider_introduction_requested' => '1',
        'provider_slug'                   => 'aifel',
        'consent_text_version'            => 'provider-intro-v1',
        'consent_timestamp'               => '2026-08-27T12:00:00+00:00',
    ]
);
expectTrue(($providerKept['provider_slug'] ?? null) === 'aifel', 'provider_slug must survive merge when introduction was requested.');
expectTrue(($providerKept['consent_text_version'] ?? null) === 'provider-intro-v1', 'consent_text_version must survive merge when introduction was requested.');
expectTrue(($providerKept['consent_timestamp'] ?? null) === '2026-08-27T12:00:00+00:00', 'consent_timestamp must survive merge when introduction was requested.');

$providerStripped = PartialLead::merge(
    [
        'provider_introduction_requested' => '1',
        'provider_slug'                   => 'aifel',
        'consent_text_version'            => 'provider-intro-v1',
        'consent_timestamp'               => '2026-08-27T12:00:00+00:00',
    ],
    ['provider_introduction_requested' => '0']
);
expectTrue(! array_key_exists('provider_slug', $providerStripped), 'provider_slug must be stripped once introduction is no longer requested.');
expectTrue(! array_key_exists('consent_text_version', $providerStripped), 'consent_text_version must be stripped once introduction is no longer requested.');
expectTrue(! array_key_exists('consent_timestamp', $providerStripped), 'consent_timestamp must be stripped once introduction is no longer requested.');

// --- merge(): a blank later value never erases an earlier answer -----------

$blankKept = PartialLead::merge(
    ['email' => 'dsi@example.fr', 'company' => 'Mairie de Test'],
    ['email' => '', 'company' => '   ']
);
expectTrue(($blankKept['email'] ?? null) === 'dsi@example.fr', 'an empty string must not overwrite an existing value.');
expectTrue(($blankKept['company'] ?? null) === 'Mairie de Test', 'a whitespace-only string must not overwrite an existing value.');

$nullKept = PartialLead::merge(
    ['full_name' => 'Camille Duval'],
    ['full_name' => null]
);
expectTrue(($nullKept['full_name'] ?? null) === 'Camille Duval', 'a null must not overwrite an existing value.');

$emptyListKept = PartialLead::merge(
    ['constraints' => ['NIS2']],
    ['constraints' => []]
);
expectTrue(($emptyListKept['constraints'] ?? null) === ['NIS2'], 'an empty list must not overwrite an existing selection.');

$nonEmptyStillWins = PartialLead::merge(
    ['email' => 'old@example.fr', 'constraints' => ['NIS2']],
    ['email' => 'new@example.fr', 'constraints' => ['HDS']]
);
expectTrue(($nonEmptyStillWins['email'] ?? null) === 'new@example.fr', 'a non-empty new value must still win over an existing one.');
expectTrue(($nonEmptyStillWins['constraints'] ?? null) === ['HDS'], 'a non-empty new list must still win over an existing one.');

$blankFillsGap = PartialLead::merge(
    ['email' => 'dsi@example.fr'],
    ['message' => '']
);
expectTrue(array_key_exists('message', $blankFillsGap), 'a blank value must still be accepted for a key that did not exist.');
expectTrue(($blankFillsGap['message'] ?? null) === '', 'a blank value stored for a new key must stay blank.');

$blankOverBlank = PartialLead::merge(
    ['budget' => ''],
    ['budget' => 'Non défini']
);
expectTrue(($blankOverBlank['budget'] ?? null) === 'Non défini', 'a real value must replace a previously blank one.');

$providerNeverRequested = PartialLead::merge(
    ['email' => 'dsi@example.fr'],
    ['provider_slug' => 'aifel']
);
expectTrue(! array_key_exists('provider_slug', $providerNeverRequested), 'provider_slug must not appear when introduction was never requested.');

if ($failures > 0) {
    fwrite(STDERR, "partial-lead tests: FAIL ($failures issue(s))\n");
    exit(1);
}

fwrite(STDOUT, "partial-lead tests: PASS\n");
