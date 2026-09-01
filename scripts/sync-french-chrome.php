<?php

declare(strict_types=1);

// Keeps the four content/*.fr.html templates in step with the chrome
// (nav + footer) that lives in index.fr.html, the source of truth. It
// extracts the current blocks at runtime instead of hardcoding markup, so it
// stays correct across future chrome edits (see Task 4/6, 2026-09-01).

$root = dirname(__DIR__);
$source = $root . '/public/themes/souverainete-digitale/index.fr.html';
$targets = [
	$root . '/public/themes/souverainete-digitale/content/index.fr.html',
	$root . '/public/themes/souverainete-digitale/content/page.fr.html',
	$root . '/public/themes/souverainete-digitale/content/post.fr.html',
	$root . '/public/themes/souverainete-digitale/content/contact.fr.html',
];

$sourceHtml = file_get_contents($source);
if ($sourceHtml === false || $sourceHtml === '') {
	fwrite(STDERR, "Could not read source template: {$source}\n");
	exit(1);
}

$extract = static function (string $html, string $pattern, string $label) use ($source): string {
	if (!preg_match($pattern, $html, $m)) {
		fwrite(STDERR, "Could not extract {$label} from source template: {$source}\n");
		exit(1);
	}
	return $m[0];
};

$navigation = $extract($sourceHtml, '#<nav class="sd-nav\b.*?</nav>#s', 'nav');
$footer = $extract($sourceHtml, '#<footer class="sd-footer\b.*?</footer>#s', 'footer');

$patterns = [
	'#<nav class="sd-nav\b.*?</nav>#s' => $navigation,
	'#<footer class="sd-footer\b.*?</footer>#s' => $footer,
];

foreach ($targets as $file) {
	$html = (string) file_get_contents($file);
	foreach ($patterns as $pattern => $replacement) {
		$count = 0;
		// preg_replace_callback (not preg_replace) so that a literal "$" or
		// "\" in future chrome markup can never be misread as a backreference.
		$html = (string) preg_replace_callback(
			$pattern,
			static function () use ($replacement): string {
				return $replacement;
			},
			$html,
			1,
			$count
		);
		if ($count !== 1) {
			fwrite(STDERR, "Could not replace chrome block in {$file}: {$pattern}\n");
			exit(1);
		}
	}
	file_put_contents($file, $html);
}

fwrite(STDOUT, "French chrome synchronized across " . count($targets) . " templates.\n");
