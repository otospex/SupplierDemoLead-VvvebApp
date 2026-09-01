<?php

$root = dirname(__DIR__, 2);
$theme = $root . '/public/themes/souverainete-digitale';
$required = [
    'page' => "$theme/content/page.fr.html",
    'annuaire' => "$theme/content/annuaire.fr.html",
    'solution' => "$theme/content/solution.fr.html",
    'registration' => "$theme/content/solution-registration.fr.html",
];
$failures = 0;

function directoryFail(string $message): void {
    global $failures;
    fwrite(STDERR, "FAIL: $message\n");
    $failures++;
}

foreach ($required as $name => $file) {
    if (! is_file($file)) {
        directoryFail("$name template is missing.");
    }
}

if ($failures) {
    fwrite(STDERR, "solutions-directory-content tests: FAIL ($failures issue(s))\n");
    exit(1);
}

$files = array_map(fn ($file) => (string) file_get_contents($file), $required);
foreach (['nav class="sd-nav"' => 'nav', 'footer class="sd-footer"' => 'footer'] as $needle => $label) {
    $tag = $label === 'nav' ? 'nav' : 'footer';
    preg_match('#<' . $tag . ' class="sd-' . $label . '".*?</' . $tag . '>#s', $files['page'], $source);
    foreach (['annuaire', 'solution', 'registration'] as $template) {
        preg_match('#<' . $tag . ' class="sd-' . $label . '".*?</' . $tag . '>#s', $files[$template], $copy);
        if (($copy[0] ?? '') !== ($source[0] ?? '')) {
            directoryFail("$template does not copy the page.fr.html $label exactly.");
        }
    }
}

preg_match('#souverainete\.css\?v=([a-f0-9]{8})#', $files['page'], $version);
foreach (['annuaire', 'solution', 'registration'] as $template) {
    if (substr_count($files[$template], 'souverainete.css?v=') !== 1 || ! str_contains($files[$template], 'souverainete.css?v=' . ($version[1] ?? 'missing'))) {
        directoryFail("$template must load the same versioned French stylesheet exactly once.");
    }
}

$expectations = [
    'Le référencement dans l&rsquo;annuaire est gratuit.',
    'Chaque fiche est publiée après une revue humaine.',
    'Nous publions uniquement des affirmations vérifiables.',
    'Aucun classement ni emplacement n&rsquo;est à vendre.',
    'Un partenariat fait l&rsquo;objet d&rsquo;une conversation séparée et reste toujours signalé.',
    'Un lien depuis le site de la solution vers sa fiche est bienvenu par courtoisie, mais ne constitue jamais une condition.',
];
foreach ($expectations as $sentence) {
    if (! str_contains($files['registration'], $sentence)) {
        directoryFail("registration page is missing expectation: $sentence");
    }
}
if (! str_contains($files['registration'], '<meta name="robots" content="noindex,follow">')) {
    directoryFail('registration page must be noindex,follow.');
}
if (! str_contains($files['solution'], '>Alternatives</h2>')) {
    directoryFail('solution template must contain the Alternatives section.');
}

$publicTemplates = $files['annuaire'] . $files['solution'] . $files['registration'];
foreach (glob($root . '/plugins/solutions-directory/app/template/*.tpl') as $template) {
    $publicTemplates .= (string) file_get_contents($template);
}
if (str_contains($publicTemplates, 'submitted_by_email')) {
    directoryFail('submitted_by_email must never appear in public templates.');
}

$routes = (string) file_get_contents($root . '/config/app-routes.php');
foreach (['/annuaire', '/annuaire/categorie/{categorie}', '/annuaire/alternative-a/{alternative_a}', '/solution/{slug}', '/annuaire/referencer-une-solution'] as $route) {
    if (! str_contains($routes, "'$route'")) {
        directoryFail("route is missing: $route");
    }
}

$seed = (string) file_get_contents($root . '/seed.dokploy.sql');
$delimiter = '-- === solutions-directory (spec 2026-09-01) ===';
$sectionStart = strrpos($seed, $delimiter);
$section = $sectionStart === false ? false : substr($seed, $sectionStart);
if ($section === false || ! str_starts_with($section, $delimiter)) {
    directoryFail('the clearly-delimited solutions seed section must be the final section.');
}
foreach (['annuaire', 'referencer-une-solution', 'solution-registration', 'categorie', 'alternative-a'] as $seedValue) {
    if (! $section || ! str_contains($section, "'$seedValue'") && ! str_contains($section, "''$seedValue''")) {
        directoryFail("final seed section is missing $seedValue.");
    }
}
$initialTerms = ['visioconference','messagerie','bureautique','fichiers-et-partage','identite-et-acces','hebergement-et-cloud','sauvegarde','cybersecurite','ia-et-agents','accompagnement-et-migration','microsoft-365','microsoft-teams','google-workspace','google-meet','zoom','slack','dropbox','onedrive','sharepoint','aws','microsoft-azure','google-cloud','chatgpt-openai','salesforce','notion'];
foreach ($initialTerms as $slug) {
    if (! str_contains($section ?: '', "'$slug'")) {
        directoryFail("final seed section is missing initial term $slug.");
    }
}
foreach (['/page/sortir-microsoft-365', '/page/choisir-visioconference-collaboration'] as $guidePath) {
    if (! str_contains($section ?: '', $guidePath)) {
        directoryFail("alternative term intros are missing guide mapping $guidePath.");
    }
}

foreach (['mysqli', 'pgsql', 'sqlite'] as $driver) {
    $install = $root . "/plugins/solutions-directory/install/sql/$driver/data/solutions-directory.sql";
    if (! is_file($install)) {
        directoryFail("$driver install data is missing.");
    }
}

$registrationRuntime = $root . '/plugins/solutions-directory/public/js/solution-registration.js';
if (! is_file($registrationRuntime)) {
    directoryFail('registration array/success runtime is missing.');
} else {
    $registrationJs = (string) file_get_contents($registrationRuntime);
    foreach (['lead-platform-connector:fields', 'categories[]', 'alternative_to[]', 'lead-platform-connector:success', 'Merci. Votre solution sera examinée avant publication.'] as $contract) {
        if (! str_contains($registrationJs, $contract)) {
            directoryFail("registration runtime is missing $contract.");
        }
    }
}
$connectorRuntime = (string) file_get_contents($root . '/plugins/lead-platform-connector/public/js/lead-form.20260827.js');
foreach (['lead-platform-connector:fields', 'lead-platform-connector:success'] as $event) {
    if (! str_contains($connectorRuntime, $event)) {
        directoryFail("connector must emit the additive $event event.");
    }
}
$pluginBootstrap = (string) file_get_contents($root . '/plugins/solutions-directory/plugin.php');
$queueTemplate = (string) file_get_contents($root . '/plugins/solutions-directory/public/admin/submissions.html');
if (! str_contains($pluginBootstrap, "'compile'") || ! str_contains($queueTemplate, 'Créer une fiche brouillon')) {
    directoryFail('the solutions plugin must inject its draft action into the queue through a Vvveb event.');
}
$draftController = (string) file_get_contents($root . '/plugins/solutions-directory/admin/controller/draft.php');
if (! str_contains($pluginBootstrap, "get('csrf')") || ! str_contains($draftController, "get('csrf')")) {
    directoryFail('the state-changing draft action must carry and validate an admin CSRF token.');
}
if (! str_contains($routes, "'/feed/solutions.xml'")) {
    directoryFail('published solution and term URLs need a dedicated sitemap route.');
}
$repository = (string) file_get_contents($root . '/plugins/solutions-directory/system/solution-repository.php');
foreach (['pc.language_id = :language_id', 'post_to_site', 'ps.site_id = :site_id'] as $scopeGuard) {
    if (! str_contains($repository, $scopeGuard)) {
        directoryFail("published solution queries are missing scope guard: $scopeGuard");
    }
}
$sitemapController = $root . '/plugins/solutions-directory/app/controller/sitemap.php';
if (! is_file($sitemapController)) {
    directoryFail('solutions sitemap controller is missing.');
} else {
    $sitemap = (string) file_get_contents($sitemapController);
    if (! str_contains($sitemap, "'publish'") || str_contains($sitemap, 'referencer-une-solution')) {
        directoryFail('solutions sitemap must include published content and exclude registration.');
    }
}
$sitemapIndex = $theme . '/feed/index.xml';
if (! is_file($sitemapIndex) || ! str_contains((string) file_get_contents($sitemapIndex), 'solutions.xml')) {
    directoryFail('the theme sitemap index must advertise the solutions sitemap.');
}

if ($failures > 0) {
    fwrite(STDERR, "solutions-directory-content tests: FAIL ($failures issue(s))\n");
    exit(1);
}

fwrite(STDOUT, "solutions-directory-content tests: PASS\n");
