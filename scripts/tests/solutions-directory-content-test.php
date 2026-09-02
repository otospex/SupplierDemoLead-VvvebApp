<?php

// The plugin's own guard clause: defining it lets this test require the plugin
// units directly instead of grepping their source for hopeful substrings.
define('V_VERSION', 'test');

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
foreach (['submitted_by_email', 'partner_interest'] as $privateMeta) {
    if (str_contains($publicTemplates, $privateMeta)) {
        directoryFail("$privateMeta must never appear in public templates.");
    }
}

$routes = (string) file_get_contents($root . '/config/app-routes.php');
foreach (['/annuaire', '/annuaire/categorie/{categorie}', '/annuaire/alternative-a/{alternative_a}', '/solution/{slug}', '/annuaire/referencer-une-solution'] as $route) {
    if (! str_contains($routes, "'$route'")) {
        directoryFail("route is missing: $route");
    }
}

// Reverse-URL hijack guard. A route data key (here the fixed 'slug') is merged
// into the module's reverse-lookup parameter list by
// system/routes.php::processRoute, and Routes::url() returns the FIRST route of
// a module whose parameters are all satisfied. Registering these two fixed-slug
// routes on content/page/index therefore made url('content/page/index',
// ['slug' => ...]) return /annuaire/referencer-une-solution for EVERY page, so
// feeds, sitemaps and menus all collapsed onto one URL. They must stay on a
// delegate module.
foreach (['/annuaire', '/annuaire/referencer-une-solution'] as $staticRoute) {
    if (! preg_match("#'" . preg_quote($staticRoute, '#') . "'\\s*=>\\s*\\[[^\\]]*\\]#", $routes, $routeMatch)) {
        directoryFail("static route $staticRoute could not be parsed from config/app-routes.php.");
        continue;
    }
    if (! preg_match("#'module'\\s*=>\\s*'([^']+)'#", $routeMatch[0], $moduleMatch)) {
        directoryFail("static route $staticRoute declares no module.");
        continue;
    }
    if (! preg_match("#'slug'\\s*=>#", $routeMatch[0])) {
        directoryFail("static route $staticRoute must pin its page with a 'slug' data key.");
    }
    if ($moduleMatch[1] === 'content/page/index') {
        directoryFail("static route $staticRoute must not bind a fixed slug onto content/page/index; it hijacks Routes::url() for every page.");
    }
}
$delegateController = $root . '/plugins/solutions-directory/app/controller/page.php';
if (! is_file($delegateController)) {
    directoryFail('the annuaire page delegate controller is missing.');
} elseif (! str_contains((string) file_get_contents($delegateController), 'Vvveb\\Controller\\Content\\Page')) {
    directoryFail('the annuaire page delegate must extend the stock content page controller.');
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
// --- Admin queue injection -------------------------------------------------
// Exercised through the plugin's own units. A source-substring check here used
// to pass while the hook compared against a template name Vvveb never emits.
require_once $root . '/plugins/solutions-directory/system/queue-integration.php';

use Vvveb\Plugins\SolutionsDirectory\System\QueueIntegration;

$pluginConfig = require $root . '/plugins/solutions-directory/config.php';

// Vvveb's FrontController builds the view template from the module name, which
// has no admin/ segment (system/core/frontcontroller.php:196 with
// system/core/view.php:315,383). Both shapes must match so a core change to
// that derivation cannot silently drop the button.
if (QueueIntegration::isQueueTemplate('plugins/lead-platform-connector/submissions.html') !== true) {
    directoryFail('the queue hook must match the module-derived template name (no admin/ segment).');
}
if (QueueIntegration::isQueueTemplate('plugins/lead-platform-connector/admin/submissions.html') !== true) {
    directoryFail('the queue hook must also match the admin/ template shape.');
}
if (QueueIntegration::isQueueTemplate('plugins\\lead-platform-connector\\submissions.html') !== true) {
    directoryFail('the queue hook must normalise Windows directory separators.');
}
if (QueueIntegration::isQueueTemplate('plugins/lead-platform-connector/endpoints.html') !== false) {
    directoryFail('the queue hook must not substitute other connector templates.');
}
if (QueueIntegration::isQueueTemplate('content/page.html') !== false) {
    directoryFail('the queue hook must not substitute theme templates.');
}

$queueTemplateFile = QueueIntegration::queueTemplateFile();
if (! is_file($queueTemplateFile)) {
    directoryFail('the substituted queue template does not exist at the path the hook returns.');
    $queueTemplate = '';
} else {
    $queueTemplate = (string) file_get_contents($queueTemplateFile);
}
if (! str_contains($queueTemplate, 'Créer une fiche brouillon')) {
    directoryFail('the substituted queue template must carry the draft button label.');
}
// The button is only rendered for rows the hook flagged, and the row id is what
// the listing template binds onto it.
if (! str_contains($queueTemplate, 'data-v-if="lead_submission.solution_action_url"')) {
    directoryFail('the draft button must be bound to the row flag set by the FrontController hook.');
}
if (! str_contains($queueTemplate, 'data-v-lead_submission-lead_submission_id></button>')
    && ! preg_match('/<button[^>]*data-v-lead_submission-lead_submission_id/u', $queueTemplate)) {
    directoryFail('the draft button must bind the submission id it posts.');
}
if (! preg_match('/<form[^>]+id="sd-draft-form"[^>]+method="post"/u', $queueTemplate)
    || ! preg_match('/<form[^>]+id="sd-draft-form".*?name="csrf"/su', $queueTemplate)) {
    directoryFail('the draft action must POST from a form carrying a hidden CSRF field.');
}
if (preg_match('/csrf=/u', $queueTemplate)) {
    directoryFail('the CSRF token must never travel in the draft action URL.');
}
// The fork must stay a fork: only the marked additions may differ from upstream.
$upstreamQueue = (string) file_get_contents($root . '/plugins/lead-platform-connector/public/admin/submissions.html');
$strippedFork = preg_replace('/^\s*<!--.*?-->\s*$/ms', '', $queueTemplate);
foreach (['Soumissions', 'Statut', 'Tentatives'] as $relocalised) {
    if (str_contains($strippedFork, '<th>' . $relocalised . '</th>') || str_contains($strippedFork, '<span>' . $relocalised . '</span>')) {
        directoryFail("the queue fork must keep upstream's generic chrome ($relocalised) so its diff stays minimal.");
    }
}
foreach (['<th>Attempts</th>', '<th>Status</th>', 'No submissions yet.'] as $upstreamString) {
    if (str_contains($upstreamQueue, $upstreamString) && ! str_contains($queueTemplate, $upstreamString)) {
        directoryFail("the queue fork dropped upstream chrome: $upstreamString");
    }
}

// The FrontController hook flags exactly the registration rows.
$queueRows = QueueIntegration::decorateRows(
    [
        ['lead_submission_id' => 314, 'endpoint_slug' => $pluginConfig['endpoint_slug']],
        ['lead_submission_id' => 315, 'endpoint_slug' => 'independant-digital-intake'],
    ],
    $pluginConfig,
    QueueIntegration::draftActionUrl($pluginConfig, '/admin/')
);
if (($queueRows[0]['solution_action_url'] ?? '') !== '/admin/index.php?module=plugins/solutions-directory/draft') {
    directoryFail('the queue hook must publish the draft action URL for registration submissions.');
}
if (($queueRows[1]['solution_action_url'] ?? null) !== '') {
    directoryFail('submissions from another endpoint must not offer the draft action.');
}
foreach ($queueRows as $row) {
    if (str_contains((string) ($row['solution_action_url'] ?? ''), 'csrf')) {
        directoryFail('the draft action URL must not carry a CSRF token.');
    }
    if (str_contains((string) ($row['solution_action_url'] ?? ''), 'lead_submission_id=')) {
        directoryFail('the draft action URL must not carry the submission id; it is posted.');
    }
}
if (QueueIntegration::draftActionUrl($pluginConfig, '/back-office/') !== '/back-office/index.php?module=plugins/solutions-directory/draft') {
    directoryFail('the draft action URL must follow the configured admin path.');
}

$draftController = (string) file_get_contents($root . '/plugins/solutions-directory/admin/controller/draft.php');
if (! str_contains($draftController, "get('csrf')") || ! str_contains($draftController, 'hash_equals')) {
    directoryFail('the state-changing draft action must validate an admin CSRF token with hash_equals.');
}
if (! str_contains($draftController, "request->post['csrf']") || ! str_contains($draftController, "request->post['lead_submission_id']")) {
    directoryFail('the draft action must read its token and submission id from the POST body.');
}
if (str_contains($draftController, '&rsquo;')) {
    directoryFail('admin notices are escaped by the framework; they must not carry HTML entities.');
}
if (! str_contains($draftController, "language_slug")) {
    directoryFail('the draft action must resolve the site language, not the admin session language.');
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

// --- Spec §10 route wiring (static) ----------------------------------------
// The real 200/404 checks need a running site and live in the INTEGRATION
// section below. What is checkable offline is that every declared route points
// at a controller file and action that actually exist.
preg_match_all("/'([^']+)'\s*=>\s*\[\s*'module'\s*=>\s*'([^']+)'/", $routes, $routeMatches, PREG_SET_ORDER);
$declaredRoutes = [];
foreach ($routeMatches as $match) {
    $declaredRoutes[$match[1]] = $match[2];
}

$directoryRoutes = [
    '/annuaire',
    '/annuaire/categorie/{categorie}',
    '/annuaire/alternative-a/{alternative_a}',
    '/annuaire/referencer-une-solution',
    '/solution/{slug}',
    '/feed/solutions.xml',
];
foreach ($directoryRoutes as $route) {
    if (! isset($declaredRoutes[$route])) {
        directoryFail("route $route does not declare a module.");
        continue;
    }
    $segments = explode('/', trim($declaredRoutes[$route], '/'));
    $action = array_pop($segments);
    if ($segments && $segments[0] === 'plugins') {
        $pluginName = $segments[1] ?? '';
        $controller = implode('/', array_slice($segments, 2));
        $controllerFile = $root . "/plugins/$pluginName/app/controller/$controller.php";
    } else {
        $controllerFile = $root . '/app/controller/' . implode('/', $segments) . '.php';
    }
    if (! is_file($controllerFile)) {
        directoryFail("route $route points at a missing controller: $controllerFile");
        continue;
    }
    // Actions may be inherited (content/page extends content/post), so walk up
    // the `extends` chain inside the same controller directory.
    $searched = [];
    $found = false;
    $candidate = $controllerFile;
    for ($hop = 0; $hop < 4 && $candidate !== null && is_file($candidate); $hop++) {
        $searched[] = $candidate;
        $source = (string) file_get_contents($candidate);
        if (preg_match('/function\s+' . preg_quote($action, '/') . '\s*\(/i', $source)) {
            $found = true;
            break;
        }
        if (! preg_match('/class\s+\w+\s+extends\s+\\\\?([\w\\\\]+)/i', $source, $parent)) {
            break;
        }
        $parentClass = trim($parent[1], '\\');
        if (stripos($parentClass, 'Vvveb\\Controller\\') === 0) {
            // A fully-qualified core controller (e.g. a plugin delegate that
            // extends Vvveb\Controller\Content\Page) lives under app/controller.
            $relative = strtolower(str_replace('\\', '/', substr($parentClass, strlen('Vvveb\\Controller\\'))));
            $candidate = $root . '/app/controller/' . $relative . '.php';
            continue;
        }
        $parentName = strtolower((string) (array_slice(explode('\\', $parentClass), -1)[0] ?? ''));
        $candidate = $parentName === '' ? null : dirname($candidate) . "/$parentName.php";
    }
    if (! $found) {
        directoryFail("route $route points at a missing action: $action (searched " . implode(', ', $searched) . ')');
    }
}

// Term routes must be declared before the bare /annuaire page route, otherwise
// the page route would swallow them.
$termRoutePosition = strpos($routes, "'/annuaire/categorie/{categorie}'");
$indexRoutePosition = strpos($routes, "'/annuaire' =>");
if ($termRoutePosition === false || $indexRoutePosition === false || $termRoutePosition > $indexRoutePosition) {
    directoryFail('term routes must be declared before the /annuaire page route.');
}

// The unknown-term 404 is a controller behaviour, asserted here so a regression
// cannot quietly go back to rendering an empty annuaire at 200.
$directoryController = (string) file_get_contents($root . '/plugins/solutions-directory/app/controller/directory.php');
if (! str_contains($directoryController, 'notFound')) {
    directoryFail('an unknown term slug must return the framework 404, not an empty annuaire.');
}
if (! str_contains($directoryController, "repository->term(")) {
    directoryFail('the directory controller must verify the term exists before rendering.');
}

// --- Spec §9 editorial claim rules over the seeded directory copy ----------
// Reuses the real audit binary rather than a copy of its rules, so the seeded
// term intros and page rows are held to the same standard as the theme.
$auditScript = $root . '/scripts/editorial-audit.php';
if (! is_file($auditScript)) {
    directoryFail('scripts/editorial-audit.php is missing; directory copy cannot be audited.');
} elseif ($section) {
    $auditFile = tempnam(sys_get_temp_dir(), 'sd-seed-') . '.sql';
    file_put_contents($auditFile, $section);
    $auditCommand = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($auditScript) . ' ' . escapeshellarg($auditFile) . ' 2>&1';
    $auditOutput = [];
    $auditStatus = 0;
    exec($auditCommand, $auditOutput, $auditStatus);
    @unlink($auditFile);
    if ($auditStatus !== 0) {
        directoryFail('seeded directory copy fails the editorial audit: ' . implode(' | ', $auditOutput));
    }
}

// Same unsupported-claim shapes launch-content-test.php forbids in published
// French rows, applied to the directory section.
$unsupportedDirectoryClaims = [
    '/\d+\+/u' => 'an invented customer or project count',
    '/99\.9/u' => 'an invented availability figure',
    '/\bSLA\b/u' => 'an unsupported service-level claim',
    '#24/7#u' => 'an unsupported round-the-clock claim',
    '/\b(?:le|la|les)\s+meilleur/iu' => 'a superlative ranking claim',
    '/\b100\s*%\s*(?:s(?:û|u)r|sécuris)/iu' => 'an absolute security claim',
];
foreach ($unsupportedDirectoryClaims as $claimPattern => $claimLabel) {
    if ($section && preg_match($claimPattern, $section)) {
        directoryFail("the seeded directory section carries $claimLabel.");
    }
}

// The guide embeds required by spec §7/§8.
$guideEmbeds = [
    'choisir-visioconference-collaboration' => '["google-meet","microsoft-teams","zoom"]',
    'sortir-microsoft-365' => 'microsoft-365',
];
foreach ($guideEmbeds as $guideSlug => $alternatives) {
    if (! $section || ! str_contains($section, $guideSlug)) {
        directoryFail("the seed must embed the solutions block on $guideSlug.");
        continue;
    }
    if (! str_contains($section, 'data-v-component-plugin-solutions-directory-solutions')) {
        directoryFail('the guide embed must use the solutions component binding.');
    }
    if (! str_contains($section, $alternatives)) {
        directoryFail("the $guideSlug embed must filter on $alternatives.");
    }
}
// Guide bodies are owned by the launch section; this one may only append.
if ($section && preg_match('/REPLACE\s+INTO\s+post_content[^;]*(choisir-visioconference-collaboration|sortir-microsoft-365)/is', $section)) {
    directoryFail('the directory section must not rewrite guide page rows; append with a targeted UPDATE.');
}
if ($section && ! preg_match('/UPDATE\s+post_content\s+SET\s+content\s*=\s*CONCAT\(/i', $section)) {
    directoryFail('the guide embeds must be appended with a targeted CONCAT update.');
}
if ($section && ! str_contains($section, "NOT LIKE '%sd-solutions-embed%'")) {
    directoryFail('the guide embed updates must be idempotent across seed runs.');
}

// --- Spec §10 live route checks (INTEGRATION=1) ----------------------------
// Needs a running site and a seeded database, so it is skipped by default and
// runs in the integration pass: INTEGRATION=1 php scripts/tests/solutions-directory-content-test.php
if (getenv('INTEGRATION') === '1') {
    $baseUrl = rtrim((string) (getenv('INTEGRATION_BASE_URL') ?: 'http://127.0.0.1:8090'), '/');
    $draftSlug = (string) (getenv('INTEGRATION_DRAFT_SLUG') ?: 'fiche-brouillon-non-publiee');

    $fetch = static function (string $url): array {
        $command = 'curl -sS -o - -w "\n%{http_code}" --max-time 20 ' . escapeshellarg($url) . ' 2>/dev/null';
        $body = (string) shell_exec($command);
        $split = strrpos($body, "\n");
        if ($split === false) {
            return ['status' => 0, 'body' => $body];
        }

        return ['status' => (int) substr($body, $split + 1), 'body' => substr($body, 0, $split)];
    };

    $expectations = [
        ['/annuaire', 200, null],
        ['/annuaire/categorie/visioconference', 200, null],
        ['/annuaire/alternative-a/microsoft-365', 200, null],
        ['/annuaire/categorie/terme-qui-nexiste-pas', 404, null],
        ['/annuaire/alternative-a/terme-qui-nexiste-pas', 404, null],
        ['/annuaire/referencer-une-solution', 200, 'noindex,follow'],
        ['/solution/' . $draftSlug, 404, null],
    ];
    foreach ($expectations as [$path, $expectedStatus, $needle]) {
        $response = $fetch($baseUrl . $path);
        if ($response['status'] !== $expectedStatus) {
            directoryFail("INTEGRATION: $path returned {$response['status']}, expected $expectedStatus.");
            continue;
        }
        if ($needle !== null && ! str_contains($response['body'], $needle)) {
            directoryFail("INTEGRATION: $path is missing $needle.");
        }
    }

    // Live proof of the reverse-URL fix: every reverse-generated page URL used
    // to be /annuaire/referencer-une-solution. Each generated link must now be
    // distinct, and the registration URL may appear at most once.
    foreach (['/feed/pages' => 'link', '/feed/page-sitemap.xml' => 'loc'] as $feedPath => $tag) {
        $feed = $fetch($baseUrl . $feedPath);
        if ($feed['status'] === 404 && str_ends_with($feedPath, '.xml')) {
            // Known, separately tracked: the nginx static-extension location
            // (`location ~* "\.(?!php)([\w]{3,5})$"`) swallows /feed/*.xml, so
            // the XML sitemaps only answer from page-cache. Recorded under
            // "sitemap trio" in docs/launch/open-items.md; the RSS feed below
            // exercises the same reverse-URL code path.
            fwrite(STDOUT, "solutions-directory-content: SKIPPED $feedPath (nginx static-extension 404, see open-items).\n");
            continue;
        }
        if ($feed['status'] !== 200) {
            directoryFail("INTEGRATION: $feedPath returned {$feed['status']}, expected 200.");
            continue;
        }
        if (! preg_match_all('#<' . $tag . '[^>]*>([^<]+)</' . $tag . '>#', $feed['body'], $feedMatches)) {
            directoryFail("INTEGRATION: $feedPath exposed no <$tag> values to check.");
            continue;
        }
        $entryUrls = array_values(array_filter(
            $feedMatches[1],
            static fn (string $value): bool => str_contains($value, '/page/')
                || str_contains($value, '/annuaire')
        ));
        if (count($entryUrls) < 2) {
            directoryFail("INTEGRATION: $feedPath listed fewer than two page URLs; the distinctness check is vacuous.");
            continue;
        }
        $duplicates = array_filter(array_count_values($entryUrls), static fn (int $n): bool => $n > 1);
        if ($duplicates !== []) {
            directoryFail("INTEGRATION: $feedPath repeats page URL(s): " . implode(', ', array_keys($duplicates)) . '.');
        }
        $registrationHits = count(array_filter(
            $entryUrls,
            static fn (string $value): bool => str_ends_with($value, '/annuaire/referencer-une-solution')
        ));
        if ($registrationHits > 1) {
            directoryFail("INTEGRATION: $feedPath emits the registration URL $registrationHits times; Routes::url() is hijacked again.");
        }
    }
} else {
    fwrite(STDOUT, "solutions-directory-content: SKIPPED live route checks (set INTEGRATION=1 with the site running).\n");
}
if ($failures > 0) {
    fwrite(STDERR, "solutions-directory-content tests: FAIL ($failures issue(s))\n");
    exit(1);
}

fwrite(STDOUT, "solutions-directory-content tests: PASS\n");