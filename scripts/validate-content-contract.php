<?php

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(2);
}

$type = strtolower((string) ($argv[1] ?? ''));
$file = (string) ($argv[2] ?? '');

$contracts = [
    'provider' => [
        'Summary',
        'Best fit',
        'Does not fit',
        'Capabilities',
        'Hosting and jurisdiction',
        'Security evidence',
        'Integration and reversibility',
        'Pricing',
        'Evidence gaps',
        'Commercial relationship',
        'Alternatives',
        'Sources',
        'Review record',
    ],
    'alternatives' => [
        'Search intent',
        'Demand evidence',
        'Selection method',
        'Decision table',
        'Detailed alternatives',
        'Migration considerations',
        'Commercial relationships',
        'Sources',
        'Review record',
    ],
];

if (! isset($contracts[$type]) || $file === '' || ! is_file($file)) {
    fwrite(STDERR, "Usage: php scripts/validate-content-contract.php <provider|alternatives> <file.md>\n");
    exit(2);
}

$markdown = (string) file_get_contents($file);
preg_match_all('/^##\s+(.+?)\s*$/mu', $markdown, $matches);
$headings = array_map('trim', $matches[1] ?? []);
$normalized = array_map(function ($heading) {
    return strtolower($heading);
}, $headings);

$errors = [];
foreach ($contracts[$type] as $required) {
    if (! in_array(strtolower($required), $normalized, true)) {
        $errors[] = "Missing section: $required";
    }
}

if (! preg_match('/^##\s+Sources\s*$[\s\S]*?https?:\/\//mi', $markdown)) {
    $errors[] = 'Sources must contain at least one URL.';
}
if (! preg_match('/^##\s+Review record\s*$[\s\S]*?Owner\s*:\s*\S+/mi', $markdown)) {
    $errors[] = 'Review record must name an owner.';
}
if (! preg_match('/^##\s+Review record\s*$[\s\S]*?Reviewed\s*:\s*\d{4}-\d{2}-\d{2}/mi', $markdown)) {
    $errors[] = 'Review record must contain an ISO review date.';
}

if ($errors) {
    foreach ($errors as $error) {
        fwrite(STDERR, $error . "\n");
    }
    exit(1);
}

fwrite(STDOUT, "$type content contract: PASS\n");
