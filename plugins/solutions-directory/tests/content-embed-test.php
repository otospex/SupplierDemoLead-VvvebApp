<?php

define('V_VERSION', 'test');

$embedFile = __DIR__ . '/../system/content-embed.php';
if (! is_file($embedFile)) {
    fwrite(STDERR, "FAIL: content-embed is missing.\n");
    exit(1);
}

require_once $embedFile;

use Vvveb\Plugins\SolutionsDirectory\System\ContentEmbed;

$failures = 0;

function expectEmbed(bool $condition, string $message): void {
    global $failures;
    if (! $condition) {
        fwrite(STDERR, "FAIL: $message\n");
        $failures++;
    }
}

// The guide embed the seed appends: a JSON-array filter in single quotes, a
// limit, and the block's own fallback markup nested one level deep.
$guide = '<section class="sd-solutions-embed"><div class="container"><h2>Titre</h2>'
    . '<div data-v-component-plugin-solutions-directory-solutions="guide" '
    . 'data-v-alternative_a=\'["google-meet","microsoft-teams","zoom"]\' data-v-limit="6">'
    . '<div class="sd-directory-empty"><p>Aucune solution publiée ne correspond à ces critères.</p></div>'
    . '</div></div></section>';

$seen = [];
$rendered = ContentEmbed::render($guide, function (array $options) use (&$seen): string {
    $seen[] = $options;

    return '<ul class="sd-solution-cards"><li>Une fiche</li></ul>';
});

expectEmbed(count($seen) === 1, 'the stored-content block must be rendered exactly once.');
expectEmbed(
    ($seen[0]['alternative_a'] ?? null) === ['google-meet', 'microsoft-teams', 'zoom'],
    'a JSON-array filter must reach the component as an array, not a string.'
);
expectEmbed(($seen[0]['limit'] ?? null) === '6', 'the limit option must be read off the block.');
expectEmbed(
    ! array_key_exists('component-plugin-solutions-directory-solutions', $seen[0]),
    'the component attribute itself must not become an option.'
);
expectEmbed(str_contains($rendered, 'sd-solution-cards'), 'the rendered HTML must replace the block children.');
expectEmbed(
    ! str_contains($rendered, 'Aucune solution publiée'),
    'the fallback markup must be gone once the block is rendered.'
);
expectEmbed(str_contains($rendered, 'data-sd-rendered="1"'), 'a rendered block must be marked as rendered.');
expectEmbed(
    substr_count($rendered, '</section>') === 1 && substr_count($rendered, '<h2>Titre</h2>') === 1,
    'the markup around the block must survive untouched.'
);

// Second pass: the marker makes the rewrite idempotent, which is what keeps a
// block that vtpl already rendered from being rendered a second time.
$again = [];
$twice = ContentEmbed::render($rendered, function (array $options) use (&$again): string {
    $again[] = $options;

    return '<p>second pass</p>';
});
expectEmbed($again === [], 'a block carrying data-sd-rendered must be skipped.');
expectEmbed($twice === $rendered, 'a rendered block must come back unchanged.');

// Content with no block at all is returned byte-identical and never parsed.
$plain = '<p>Un paragraphe sans annuaire.</p>';
expectEmbed(
    ContentEmbed::render($plain, function (): string { return 'never'; }) === $plain,
    'content without a directory block must be returned unchanged.'
);

// A scalar filter stays a scalar; the comma rule mirrors Vvveb's own parser, so
// a bare comma list decodes to null instead of silently filtering on nothing.
$scalar = ContentEmbed::options(
    '<div data-v-component-plugin-solutions-directory-solutions="guide" data-v-alternative_a="microsoft-365">'
);
expectEmbed(($scalar['alternative_a'] ?? null) === 'microsoft-365', 'a scalar filter must stay a scalar.');

$commaList = ContentEmbed::options(
    '<div data-v-component-plugin-solutions-directory-solutions="guide" data-v-alternative_a="a,b,c">'
);
expectEmbed(
    array_key_exists('alternative_a', $commaList) && $commaList['alternative_a'] === null,
    'a comma list must decode to null, the same way Vvveb\'s component parser treats it.'
);

// Unbalanced markup must be left alone rather than half-rewritten.
$broken = '<div data-v-component-plugin-solutions-directory-solutions="guide"><p>sans fermeture';
expectEmbed(
    ContentEmbed::render($broken, function (): string { return 'never'; }) === $broken,
    'unbalanced markup must be left untouched.'
);

if ($failures > 0) {
    fwrite(STDERR, "content-embed tests: FAIL ($failures issue(s))\n");
    exit(1);
}

fwrite(STDOUT, "content-embed tests: PASS\n");
