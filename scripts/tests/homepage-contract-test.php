<?php
$root = dirname(__DIR__, 2);
$theme = "$root/public/themes/souverainete-digitale";
$home = file_get_contents("$theme/index.fr.html");
// Copy needles use raw U+2019 (’). The codebase may emit either the raw glyph or the
// &rsquo;/&#8217; HTML entity, so copy/partner-rule/nav-label checks run against a
// normalized copy of the source. Structural checks (classes, stylesheet links, attributes)
// keep using the raw $home so entity-vs-glyph differences never mask a markup bug.
$homeText = str_replace(['&rsquo;', '&#8217;'], '’', $home);
$errors = [];
$fail = function ($m) use (&$errors) { $errors[] = $m; };

// Stylesheet chain: bootstrap, then exactly one souverainete.css, no custom.css, no hallmark-redesign.css
if (substr_count($home, 'souverainete.css') !== 1) $fail('homepage must link souverainete.css exactly once');
if (str_contains($home, 'custom.css')) $fail('homepage must not link custom.css');
if (str_contains($home, 'hallmark-redesign.css')) $fail('homepage must not link hallmark-redesign.css');

// Stylesheet order: the Bootstrap CDN link must load before souverainete.css so
// theme rules can override framework defaults with equal specificity.
$bootstrapCssPos = strpos($home, 'bootstrap@5.3.2/dist/css/bootstrap.min.css');
$themeCssPos = strpos($home, 'id="theme-css"');
if ($bootstrapCssPos === false) $fail('bootstrap CDN stylesheet link missing');
if ($themeCssPos === false) $fail('theme-css (souverainete.css) link missing');
if ($bootstrapCssPos !== false && $themeCssPos !== false && $bootstrapCssPos > $themeCssPos)
    $fail('Bootstrap CDN stylesheet must appear before the souverainete.css link');

// Banned patterns
foreach (['sd-gradient-text', 'sd-stats', 'sd-cert-card', 'sd-step-icon', 'sd-decision-rule', 'sd-announce',
          'sd-quote-stars', 'sd-quote-avatar', 'sd-quote-mark'] as $cls) {
    if (str_contains($home, $cls)) $fail("banned class present: $cls");
}
if (preg_match('/<[^>]+ style="/', $home)) $fail('inline style attribute present');

// Structure: exactly one h1, <= 50 chars text; five sections
if (preg_match_all('/<h1[^>]*>(.*?)<\/h1>/s', $home, $m) !== 1) $fail('exactly one h1 required');
else {
    $h1 = trim(html_entity_decode(strip_tags($m[1][0]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if (mb_strlen($h1) > 50) $fail('h1 longer than 50 chars: ' . $h1);
}
if (substr_count($home, '<section') > 5) $fail('more than 5 sections');

// Copy platform
foreach (['souveraineté numérique', 'Lancer le diagnostic', 'Voir la méthode', 'Voir l’annuaire',
          'Conseil et accompagnement', 'Solutions référencées', 'Financement',
          'Diagnostic de souveraineté'] as $needle) {
    if (!str_contains($homeText, $needle)) $fail("missing copy: $needle");
}
$rule = 'Le partenariat est toujours affiché';
if (!str_contains($homeText, $rule)) $fail('partner rule sentence missing');

// Nav contract
if (!str_contains($homeText, '>Annuaire<')) $fail('nav must contain Annuaire');
if (preg_match('/nav-link[^>]*>Accueil</', $homeText)) $fail('nav must not contain Accueil');

// Step-1 intake fields present, phone not required
foreach (['name="email"', 'name="full_name"', 'name="company"', 'name="job_title"', 'name="privacy_acknowledgement"'] as $f) {
    if (!str_contains($home, $f)) $fail("intake step-1 field missing: $f");
}
if (preg_match('/name="phone"[^>]*required/', $home)) $fail('phone must not be required');

// CSS discipline + still-valid checks carried over from the retired ui-polish-test.php.
// Guarded on file existence: souverainete.css is created in a later task, and when it's
// missing the "must link souverainete.css" assertions above already fail — this block
// just avoids PHP warnings and redundant noise on that red run.
//
// Also computes $cssContentHash here (before the ?v= checks below) so the
// cache-buster equality checks against index.fr.html and the four
// content/*.fr.html templates can reuse the same recomputed value.
$cssPath = "$theme/css/souverainete.css";
$cssContentHash = null;
if (is_file($cssPath)) {
    $css = file_get_contents($cssPath);

    // no raw color literals outside tokens file
    if (preg_match('/#[0-9a-fA-F]{3,8}\b|rgb\(|oklch\(/', preg_replace('/\/\*.*?\*\//s', '', $css)))
        $fail('souverainete.css contains raw color literals (must reference tokens)');
    if (str_contains($css, 'transition: all') || str_contains($css, 'transition:all'))
        $fail('souverainete.css uses transition: all');

    if (!str_contains($css, '.sd-toc a:not(.sd-btn)')) $fail('TOC text-link styling must explicitly exclude button links');
    if (preg_match('/\.sd-btn\s*\{[^}]*min-height:\s*([\d.]+)(px|rem)/s', $css, $bm)) {
        $btnMinHeightPx = $bm[2] === 'rem' ? ((float) $bm[1]) * 16 : (float) $bm[1];
        if ($btnMinHeightPx < 44) $fail('.sd-btn min-height must be at least 44px (touch target minimum)');
    } else {
        $fail('.sd-btn min-height rule missing');
    }

    // Cache-buster freshness, part 1: souverainete.css carries a trailing
    // content-hash marker (sha1 of the file with the marker line itself
    // excluded, first 8 hex chars). If the file changed and the marker
    // wasn't refreshed, this catches it.
    $markerPattern = '/\/\* content-hash: ([0-9a-f]{8}) \*\/\s*$/';
    if (!preg_match($markerPattern, $css, $hm)) {
        $fail('souverainete.css missing trailing "/* content-hash: XXXXXXXX */" marker');
    } else {
        $storedHash = $hm[1];
        $withoutMarker = preg_replace($markerPattern, '', $css);
        $cssContentHash = substr(sha1($withoutMarker), 0, 8);
        if ($cssContentHash !== $storedHash) {
            $fail("souverainete.css content-hash marker is stale (marker=$storedHash, actual=$cssContentHash) -- update the marker and every template's ?v= after editing this file");
        }
    }
}

// Cache-buster freshness, part 2: the "?v=" on every souverainete.css link
// (homepage + the four content templates) must equal the content-hash
// computed above -- the cache-buster IS the content hash, not a hand-picked
// date/version string, so a CSS edit with no matching ?v= bump fails the
// build instead of silently serving stale CSS to any browser with the old
// version cached (see README.md's version-bump rule).
$extractCssVersion = static function (string $html): ?string {
    if (preg_match('/souverainete\.css\?v=([^"\']+)/', $html, $vm)) return $vm[1];
    return null;
};
if ($cssContentHash !== null) {
    $homeVersion = $extractCssVersion($home);
    if ($homeVersion === null) $fail('could not extract ?v= from homepage souverainete.css link');
    elseif ($homeVersion !== $cssContentHash)
        $fail("homepage souverainete.css ?v=$homeVersion does not match content hash $cssContentHash -- update the ?v= (and the trailing content-hash marker) together");
}

// French templates use the single stylesheet, and (if the CSS hash is known)
// carry the exact same ?v= as the homepage.
foreach (['content/index.fr.html', 'content/page.fr.html', 'content/post.fr.html', 'content/contact.fr.html'] as $t) {
    $tpl = file_get_contents("$theme/$t");
    if (substr_count($tpl, 'souverainete.css') !== 1) $fail("$t must link souverainete.css exactly once");
    if (str_contains($tpl, 'custom.css') || str_contains($tpl, 'hallmark-redesign.css')) $fail("$t links a retired stylesheet");
    if ($cssContentHash !== null) {
        $tplVersion = $extractCssVersion($tpl);
        if ($tplVersion === null) $fail("could not extract ?v= from $t souverainete.css link");
        elseif ($tplVersion !== $cssContentHash)
            $fail("$t souverainete.css ?v=$tplVersion does not match content hash $cssContentHash -- update the ?v= (and the trailing content-hash marker) together");
    }
}

if ($errors) { foreach ($errors as $e) echo "FAIL: $e\n"; echo "homepage-contract tests: FAIL\n"; exit(1); }
echo "homepage-contract tests: PASS\n";
