<?php

/**
 * Intake stepper markup parity.
 *
 * The three-step diagnostic form is currently duplicated across three hand-kept
 * sources: the homepage (stage 1 only, as a teaser that redirects into the full
 * form), the contact page, and the seeded diagnostic-souverainete page body in
 * seed.dokploy.sql. A shared .tpl partial is a post-launch refactor; until then
 * these copies can drift silently, and drift in a field NAME is not cosmetic —
 * the connector stores what it receives, so a renamed field means a lost answer
 * and a resumed session that no longer matches the stored partial.
 *
 * This test pins the contract the runtime depends on: the same field names, in
 * the same stages, with the same stage scaffolding, in every copy.
 */

$root = dirname(__DIR__, 2);
$failures = 0;

function intakeFail(string $message): void {
    global $failures;
    fwrite(STDERR, "FAIL: $message\n");
    $failures++;
}

/** The one intake form in a document, matched on the connector's component binding. */
function intakeForm(string $document): ?string {
    $pattern = '#<form\b[^>]*data-v-component-plugin-lead-platform-connector-leadform[^>]*>.*?</form>#s';

    return preg_match($pattern, $document, $match) ? $match[0] : null;
}

/** stage number => fieldset markup. Stage fieldsets are never nested. */
function intakeStages(string $form): array {
    preg_match_all('#<fieldset\b[^>]*data-v-stage="(\d+)"[^>]*>.*?</fieldset>#s', $form, $matches, PREG_SET_ORDER);
    $stages = [];
    foreach ($matches as $match) {
        $stages[(int) $match[1]] = $match[0];
    }
    ksort($stages);

    return $stages;
}

/** Every submitted field name inside a chunk of markup, sorted and de-duplicated. */
function intakeFieldNames(string $markup): array {
    preg_match_all('#<(?:input|select|textarea)\b[^>]*\bname="([^"]+)"#i', $markup, $matches);
    $names = array_values(array_unique($matches[1]));
    sort($names);

    return $names;
}

/**
 * The seeded page body lives inside a single-quoted SQL literal, so pull the
 * statement that carries the diagnostic-souverainete row and un-escape it.
 */
function seededDiagnosticBody(string $seed): ?string {
    $statements = preg_split('/;\s*\n/', $seed) ?: [];
    foreach ($statements as $statement) {
        if (str_contains($statement, "'diagnostic-souverainete'") && str_contains($statement, '<form')) {
            return str_replace(["\\'", "''"], ["'", "'"], $statement);
        }
    }

    return null;
}

$homepageDocument = (string) file_get_contents($root . '/public/themes/souverainete-digitale/index.fr.html');
$contactDocument = (string) file_get_contents($root . '/public/themes/souverainete-digitale/content/contact.fr.html');
$seed = (string) file_get_contents($root . '/seed.dokploy.sql');
$seededDocument = seededDiagnosticBody($seed);

if ($seededDocument === null) {
    intakeFail('the seeded diagnostic-souverainete row no longer carries an intake form.');
    fwrite(STDERR, "intake-parity tests: FAIL ($failures issue(s))\n");
    exit(1);
}

$documents = [
    'homepage' => $homepageDocument,
    'contact' => $contactDocument,
    'seeded diagnostic page' => $seededDocument,
];

$forms = [];
foreach ($documents as $label => $document) {
    $form = intakeForm($document);
    if ($form === null) {
        intakeFail("$label has no intake form bound to the lead-platform-connector component.");
        continue;
    }
    $forms[$label] = $form;
}

if (count($forms) !== count($documents)) {
    fwrite(STDERR, "intake-parity tests: FAIL ($failures issue(s))\n");
    exit(1);
}

$stages = array_map('intakeStages', $forms);

// (c) The two full sources carry all three stages, and stages 2 and 3 ship both
// hidden (not rendered) and disabled (not submitted) so a no-JS visitor cannot
// post a half-built payload.
$fullSources = ['contact', 'seeded diagnostic page'];
foreach ($fullSources as $label) {
    if (array_keys($stages[$label]) !== [1, 2, 3]) {
        intakeFail("$label must declare exactly three data-v-stage fieldsets (found: " . implode(',', array_keys($stages[$label])) . ').');
        continue;
    }
    foreach ([2, 3] as $stageNumber) {
        $openingTag = substr($stages[$label][$stageNumber], 0, (int) strpos($stages[$label][$stageNumber], '>') + 1);
        foreach (['hidden', 'disabled'] as $attribute) {
            if (! preg_match('/\b' . $attribute . '\b/', $openingTag)) {
                intakeFail("$label stage $stageNumber is missing the $attribute attribute.");
            }
        }
    }
}
if (array_keys($stages['homepage']) !== [1]) {
    intakeFail('the homepage teaser must expose stage 1 only (found: ' . implode(',', array_keys($stages['homepage'])) . ').');
}

// (a) The full sources agree on the complete field set and on which stage each
// field belongs to.
if (isset($stages['contact'][1], $stages['seeded diagnostic page'][1])) {
    foreach ([1, 2, 3] as $stageNumber) {
        $contactFields = intakeFieldNames($stages['contact'][$stageNumber] ?? '');
        $seededFields = intakeFieldNames($stages['seeded diagnostic page'][$stageNumber] ?? '');
        if ($contactFields !== $seededFields) {
            $onlyContact = array_diff($contactFields, $seededFields);
            $onlySeeded = array_diff($seededFields, $contactFields);
            intakeFail(
                "stage $stageNumber field assignment differs between contact and the seeded diagnostic page"
                . ($onlyContact ? '; only on contact: ' . implode(', ', $onlyContact) : '')
                . ($onlySeeded ? '; only in the seed: ' . implode(', ', $onlySeeded) : '')
                . '.'
            );
        }
    }
    $contactAll = intakeFieldNames($forms['contact']);
    $seededAll = intakeFieldNames($forms['seeded diagnostic page']);
    if ($contactAll !== $seededAll) {
        intakeFail('the full intake sources do not submit the same field set.');
    }
    if (count($contactAll) < 10) {
        intakeFail('the intake field set is implausibly small; the parity check would be vacuous.');
    }
}

// (b) The homepage teaser collects exactly the stage-1 fields, no more and no
// fewer: a visitor who starts there must land on a stage 2 that has nothing to
// re-ask and nothing missing.
$homepageStageOne = intakeFieldNames($stages['homepage'][1] ?? '');
foreach ($fullSources as $label) {
    $fullStageOne = intakeFieldNames($stages[$label][1] ?? '');
    if ($homepageStageOne !== $fullStageOne) {
        intakeFail(
            "the homepage stage-1 field set does not match $label"
            . '; homepage: ' . implode(', ', $homepageStageOne)
            . '; ' . $label . ': ' . implode(', ', $fullStageOne) . '.'
        );
    }
}

// (d) The progress indicator states the full length up front, so nobody starts
// a three-step form believing it is one.
foreach ($forms as $label => $form) {
    if (! str_contains($form, 'Étape 1 sur 3')) {
        intakeFail("$label does not announce « Étape 1 sur 3 ».");
    }
}

// (e) A phone number is never a condition of being answered.
foreach ($forms as $label => $form) {
    if (preg_match('#<input\b[^>]*\bname="phone"[^>]*>#i', $form, $phoneField) && preg_match('/\brequired\b/', $phoneField[0])) {
        intakeFail("$label marks the phone field required.");
    }
}

// (f) A named introduction is a separate, explicit consent: the block, its
// checkbox, the provider it names and the versioned consent text all live in
// stage 3 so the consent cannot be collected before the choice that triggers it.
foreach ($fullSources as $label) {
    $stageThree = $stages[$label][3] ?? '';
    foreach ([
        'data-v-consent-block' => 'the consent block wrapper',
        'name="provider_introduction_requested"' => 'the explicit introduction consent checkbox',
        'name="provider_slug"' => 'the named provider',
        'name="consent_text_version"' => 'the consent text version',
    ] as $needle => $description) {
        if (! str_contains($stageThree, $needle)) {
            intakeFail("$label stage 3 is missing $description.");
        }
    }
    if (str_contains($stages[$label][1] ?? '', 'provider_introduction_requested')) {
        intakeFail("$label collects introduction consent in stage 1, before the choice that triggers it.");
    }
}

if ($failures > 0) {
    fwrite(STDERR, "intake-parity tests: FAIL ($failures issue(s))\n");
    exit(1);
}

fwrite(STDOUT, "intake-parity tests: PASS\n");
