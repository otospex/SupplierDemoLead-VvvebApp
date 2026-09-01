<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$homepage = (string) file_get_contents($root . '/public/themes/souverainete-digitale/index.fr.html');
$customCss = (string) file_get_contents($root . '/public/themes/souverainete-digitale/css/custom.css');
$hallmarkCss = (string) file_get_contents($root . '/public/themes/souverainete-digitale/css/hallmark-redesign.css');
$tokensCss = (string) file_get_contents($root . '/public/themes/souverainete-digitale/css/hallmark-tokens.css');
$seedSql = (string) file_get_contents($root . '/seed.dokploy.sql');
$contactTemplate = (string) file_get_contents($root . '/public/themes/souverainete-digitale/content/contact.fr.html');
$brandLogoPath = $root . '/public/media/independant-digital-logo.png';
$editorialArtwork = [
    'independence trajectory' => $root . '/public/media/editorial/independence-trajectory.svg',
    'dependency map' => $root . '/public/media/editorial/dependency-map.svg',
];
$frenchTemplates = [
    'homepage' => $homepage,
    'content index' => (string) file_get_contents($root . '/public/themes/souverainete-digitale/content/index.fr.html'),
    'page' => (string) file_get_contents($root . '/public/themes/souverainete-digitale/content/page.fr.html'),
    'post' => (string) file_get_contents($root . '/public/themes/souverainete-digitale/content/post.fr.html'),
    'contact' => $contactTemplate,
];

$failures = [];

/** @param mixed $condition */
function expectUi($condition, string $message): void
{
    global $failures;
    if (! $condition) {
        $failures[] = $message;
    }
}

foreach ($frenchTemplates as $label => $template) {
    expectUi(
        substr_count($template, 'href="css/custom.css?v=20260827-ui4"') === 1,
        "{$label} must load the cache-busted custom.css exactly once"
    );
    expectUi(
        substr_count($template, 'href="css/hallmark-redesign.css?v=20260829-home3"') === 1,
        "{$label} must load the cache-busted hallmark-redesign.css exactly once after the legacy base"
    );
    expectUi(
        substr_count($template, 'class="sd-brand-logo"') === 1
            && substr_count($template, 'src="/media/independant-digital-logo.png"') === 2,
        "{$label} must render the supplied brand logo once in the header and once in the footer"
    );
    expectUi(
        ! str_contains($template, 'id="sd-logo-grad"')
            && ! str_contains($template, '<div class="sd-footer-brand"><strong>Indépendant Digital</strong></div>'),
        "{$label} must not retain the superseded shield or text-only footer wordmark"
    );
    expectUi(
        ! str_contains($template, '<a class="nav-link" href="/page/transparence-partenariats">'),
        "{$label} must keep partnership disclosure out of the primary navigation"
    );
    expectUi(
        str_contains($template, '<strong>Diagnostic indépendant</strong>')
            && str_contains($template, '<a href="/page/methode-evaluation">Voir la méthode &rarr;</a>'),
        "{$label} announcement must lead with the ICP problem and the public method"
    );
}
expectUi(
    str_contains($hallmarkCss, '@import url("hallmark-tokens.css?v=20260829-home3");'),
    'Hallmark token import must be cache-busted with the template stylesheets'
);
expectUi(is_file($brandLogoPath), 'the approved Indépendant Digital logo asset must be published');
$brandLogoDimensions = is_file($brandLogoPath) ? getimagesize($brandLogoPath) : false;
expectUi(
    is_array($brandLogoDimensions)
        && ($brandLogoDimensions[0] ?? null) === 760
        && ($brandLogoDimensions[1] ?? null) === 65
        && ($brandLogoDimensions['mime'] ?? null) === 'image/png',
    'the published brand logo must preserve the supplied 760x65 PNG asset'
);
expectUi(
    str_contains($hallmarkCss, '.sd-brand-logo {')
        && str_contains($hallmarkCss, '.sd-footer-logo {')
        && str_contains($hallmarkCss, 'width: min(14rem, calc(100vw - 7rem));'),
    'brand logo CSS must cover the header, dark footer, and 320px navigation fit'
);
expectUi(
    str_contains($tokensCss, '--color-brand-navy:')
        && str_contains($tokensCss, '--color-brand-blue:')
        && str_contains($tokensCss, '--color-brand-coral:'),
    'design tokens must expose the three supplied logo colours'
);
foreach ($editorialArtwork as $label => $artworkPath) {
    expectUi(is_file($artworkPath), "{$label} SVG must be published");
    $artwork = is_file($artworkPath) ? (string) file_get_contents($artworkPath) : '';
    expectUi(
        preg_match('/<svg\b[^>]*viewBox="0 0 [0-9]+ [0-9]+"/i', $artwork) === 1
            && str_contains($artwork, '<title>')
            && str_contains($artwork, '<desc>'),
        "{$label} SVG must be responsive and self-described"
    );
}
expectUi(
    preg_match('/\.sd-announce strong\s*\{[^}]*color:\s*var\(--color-paper\);/s', $hallmarkCss) === 1,
    'announcement emphasis must remain legible on the brand navy background'
);
expectUi(
    str_contains($hallmarkCss, ".sd-footer .col-6 {\n    flex: 0 0 100%;\n    max-width: 100%;"),
    'mobile footer columns must stack so clickable labels never wrap'
);

foreach (['sd-quote-stars', 'sd-quote-avatar', 'sd-quote-mark'] as $falseProofClass) {
    expectUi(
        ! str_contains($homepage, $falseProofClass),
        "homepage decision prompts must not use testimonial signal {$falseProofClass}"
    );
}
expectUi(
    str_contains($homepage, 'class="sd-decision-list"'),
    'homepage must render its three evaluation questions as a decision list'
);
expectUi(
    str_contains($homepage, 'class="sd-path-index"'),
    'homepage must use the asymmetric path index instead of the equal icon-card grid'
);
expectUi(
    str_contains($homepage, '<section class="sd-hero sd-hero-centered">')
        && str_contains($homepage, 'class="sd-hero-centered-copy"')
        && ! str_contains($homepage, 'sd-hero-visual')
        && ! str_contains($homepage, 'sd-hero-brief'),
    'homepage hero must be a single centred composition without the competing decision brief'
);
expectUi(
    str_contains($homepage, 'Réduisez vos <span class="sd-gradient-text">dépendances numériques</span>, sans casser l&rsquo;existant.')
        && str_contains($homepage, 'Pour les DSI, RSSI et dirigeants publics'),
    'homepage hero must lead with the ICP problem and the approved migration promise'
);
expectUi(
    str_contains($homepage, 'class="sd-hero-principles"')
        && ! str_contains($homepage, 'sd-hero-trust-item'),
    'hero proof points must use a quiet semantic list instead of button-like boxes'
);
expectUi(
    str_contains($homepage, 'src="/media/editorial/independence-trajectory.svg"')
        && str_contains($homepage, 'src="/media/editorial/dependency-map.svg"'),
    'homepage must integrate both custom explanatory illustrations'
);
expectUi(
    ! str_contains($homepage, 'Comprendre nos partenariats')
        && ! str_contains($homepage, '<a href="/page/transparence-partenariats" class="sd-card-link">'),
    'homepage discovery paths must solve ICP problems instead of promoting the partner model'
);
expectUi(
    substr_count($homepage, '<span class="sd-eyebrow">') <= 2,
    'homepage must use no more than two meaningful eyebrow labels'
);

expectUi(
    str_contains($customCss, '.sd-toc a:not(.sd-btn)'),
    'TOC text-link styling must explicitly exclude button links'
);
expectUi(
    ! preg_match('/\.sd-toc\s+a\s*\{/', $customCss),
    'a broad .sd-toc a selector would strip CTA horizontal padding'
);
expectUi(
    ! preg_match('/transition\s*:\s*all\b/', $customCss),
    'theme CSS must not animate every property with transition: all'
);
expectUi(
    str_contains($hallmarkCss, 'padding-inline: var(--space-lg);'),
    'base buttons must provide 24px horizontal padding'
);
expectUi(
    str_contains($hallmarkCss, ".sd-form-card .sd-btn {\n  min-height: 3.25rem;\n  padding-block: var(--space-sm);\n  padding-inline: var(--space-lg);"),
    'form submit buttons must match the 52px control height and 24px horizontal button padding'
);
foreach (['.sd-hero-centered-copy {', '.sd-hero-principles {', '.sd-hero-journey {', '.sd-path-index {', '.sd-decision-list {', '.sd-decision-item {'] as $componentRule) {
    expectUi(
        str_contains($hallmarkCss, $componentRule),
        "Hallmark CSS must style the new component {$componentRule}"
    );
}
expectUi(
    preg_match('/\.sd-hero-centered-copy\s*\{[^}]*text-align:\s*center;[^}]*margin-inline:\s*auto;/s', $hallmarkCss) === 1,
    'centred hero copy must use an explicit bounded centre axis'
);
expectUi(
    preg_match('/\.sd-hero-principles\s*\{[^}]*border:\s*0;[^}]*background:\s*transparent;/s', $hallmarkCss) === 1,
    'hero principles must remain unboxed and visually quieter than the calls to action'
);
expectUi(
    str_contains($hallmarkCss, ".sd-section-header {\n  max-width: none;\n  margin-bottom: var(--space-2xl);\n  text-align: left;"),
    'homepage section introductions must not inherit the centred-everything template pattern'
);
expectUi(
    str_contains($hallmarkCss, 'grid-template-columns: minmax(0, 1.15fr) minmax(0, 0.85fr);'),
    'homepage path index must use asymmetric grid tracks'
);
expectUi(
    str_contains($hallmarkCss, '.sd-page-hero .sd-eyebrow'),
    'Hallmark layer must own content-page eyebrow colour and contrast'
);
expectUi(
    str_contains($hallmarkCss, 'padding-block: var(--space-page-hero-start) var(--space-page-hero-end);'),
    'content-page hero must use the asymmetric page-hero spacing tokens'
);
expectUi(
    str_contains($tokensCss, '--space-page-hero-start: 4rem;')
        && str_contains($tokensCss, '--space-page-hero-end: 5.5rem;'),
    'page-hero spacing tokens must encode 64px top and 88px bottom spacing'
);

$publicFrenchSources = array_merge(
    [$homepage, $seedSql],
    array_map(
        static fn (string $path): string => (string) file_get_contents($path),
        glob($root . '/public/themes/souverainete-digitale/generated/*.fr.html') ?: []
    )
);
foreach (['sovereignty.example', '+44 20 1234 5678', 'Londres &middot; Paris &middot; Bruxelles &middot; Luxembourg'] as $placeholder) {
    expectUi(
        ! str_contains(implode("\n", $publicFrenchSources), $placeholder),
        "French launch sources must not publish placeholder contact detail: {$placeholder}"
    );
}
expectUi(
    strpos($contactTemplate, 'data-v-component-post') < strpos($contactTemplate, 'data-v-post-name'),
    'contact page title must remain inside the post component so the CMS can render a non-empty h1'
);
expectUi(
    str_contains($hallmarkCss, ".sd-section.sd-contact-heading {\n  padding-block: var(--space-3xl) 0;"),
    'contact heading spacing must outrank the generic section padding rule'
);

if ($failures !== []) {
    fwrite(STDERR, "ui-polish tests: FAIL\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

fwrite(STDOUT, "ui-polish tests: PASS\n");
