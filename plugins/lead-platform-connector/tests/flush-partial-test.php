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

$now = '2026-09-01 12:00:00';

// --- isFlushable(): an aged partial row (stage < FINAL_STAGE, created_at more
// than 24h before "now") must be flushable. ---------------------------------

$agedPartial = [
    'stage'      => 2,
    'created_at' => '2026-08-31 11:59:59', // 24h00m01s before $now
];
expectTrue(PartialLead::isFlushable($agedPartial, $now) === true, 'a partial row older than 24h must be flushable.');

$agedFirstStage = [
    'stage'      => PartialLead::FIRST_STAGE,
    'created_at' => '2026-08-01 00:00:00',
];
expectTrue(PartialLead::isFlushable($agedFirstStage, $now) === true, 'a stage-1 row older than 24h must be flushable.');

// Boundary: exactly 24h old must also be flushable ("<=").
$exactlyAged = [
    'stage'      => 1,
    'created_at' => '2026-08-31 12:00:00',
];
expectTrue(PartialLead::isFlushable($exactlyAged, $now) === true, 'a partial row exactly 24h old must be flushable.');

// --- isFlushable(): a fresh partial row (created within the last 24h) must
// not be flushable yet. ------------------------------------------------------

$freshPartial = [
    'stage'      => 1,
    'created_at' => '2026-09-01 11:00:00', // 1h before $now
];
expectTrue(PartialLead::isFlushable($freshPartial, $now) === false, 'a partial row younger than 24h must not be flushable.');

$justUnderThreshold = [
    'stage'      => 2,
    'created_at' => '2026-08-31 12:00:01', // 23h59m59s before $now
];
expectTrue(PartialLead::isFlushable($justUnderThreshold, $now) === false, 'a partial row 1s short of 24h must not be flushable.');

// --- isFlushable(): a complete row (stage === FINAL_STAGE) must never be
// flushable, no matter how old. ----------------------------------------------

$completeOld = [
    'stage'      => PartialLead::FINAL_STAGE,
    'created_at' => '2020-01-01 00:00:00',
];
expectTrue(PartialLead::isFlushable($completeOld, $now) === false, 'a complete row must never be flushable, however old.');

// --- isFlushable(): defensive — a missing/malformed created_at must not
// crash and must not be treated as flushable. --------------------------------

$malformed = [
    'stage'      => 1,
    'created_at' => 'not-a-date',
];
expectTrue(PartialLead::isFlushable($malformed, $now) === false, 'a malformed created_at must not be flushable.');

$missingCreatedAt = [
    'stage' => 1,
];
expectTrue(PartialLead::isFlushable($missingCreatedAt, $now) === false, 'a row with no created_at must not be flushable.');

// --- stripAcknowledgement(): removes only privacy_acknowledgement ----------

$stripped = PartialLead::stripAcknowledgement([
    'email'                   => 'dsi@example.fr',
    'privacy_acknowledgement' => '1',
]);
expectTrue(! array_key_exists('privacy_acknowledgement', $stripped), 'stripAcknowledgement must remove privacy_acknowledgement.');
expectTrue(($stripped['email'] ?? null) === 'dsi@example.fr', 'stripAcknowledgement must not touch other fields.');

if ($failures > 0) {
    fwrite(STDERR, "flush-partial tests: FAIL ($failures issue(s))\n");
    exit(1);
}

fwrite(STDOUT, "flush-partial tests: PASS\n");
