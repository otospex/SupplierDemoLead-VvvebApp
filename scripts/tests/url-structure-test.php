<?php

// Pages answer at /{slug}, posts at /blog/{slug}, the historical /page/{slug}
// shape redirects permanently, and every page carries its own canonical.
// Static checks always run; the live checks need INTEGRATION=1.

$root   = dirname(__DIR__, 2);
$routes = (string) file_get_contents($root . '/config/app-routes.php');
$theme  = $root . '/public/themes/souverainete-digitale';

$failures = 0;
function expectTrue($condition, string $message): void {
    global $failures;
    if (! $condition) {
        fwrite(STDERR, "FAIL: $message\n");
        $failures++;
    }
}

// --- route table -----------------------------------------------------------
expectTrue(
    preg_match("#^\s*'/\{slug\}'\s*=>\s*\['module' => 'content/page/index'#m", $routes) === 1,
    '/{slug} must serve pages (content/page/index).'
);
expectTrue(
    preg_match("#^\s*'/blog/\{slug\}'\s*=>\s*\['module' => 'content/post/index'#m", $routes) === 1,
    '/blog/{slug} must serve posts (content/post/index).'
);
expectTrue(
    preg_match("#^\s*'/page/\{slug\}'\s*=>\s*\['module' => 'content/legacy-page/index'\]#m", $routes) === 1,
    '/page/{slug} must be a legacy redirect on its own module.'
);
expectTrue(
    preg_match("#^\s*'/\{language\{2,3\}\}/\{slug\}'\s*=>\s*\['module' => 'content/page/index'#m", $routes) === 1,
    '/{language}/{slug} must serve pages.'
);
expectTrue(
    preg_match("#^\s*'/\{language\{2,3\}\}/blog/\{slug\}'\s*=>\s*\['module' => 'content/post/index'#m", $routes) === 1,
    '/{language}/blog/{slug} must serve posts.'
);
expectTrue(
    preg_match("#^\s*'/\{language\{2,3\}\}/page/\{slug\}'\s*=>\s*\['module' => 'content/legacy-page/index'\]#m", $routes) === 1,
    '/{language}/page/{slug} must be a legacy redirect.'
);
expectTrue(
    ! str_contains($routes, 'p-#post_id#'),
    'the id-based p-#post_id# routes must be gone: they used to win the reverse map for non-default languages.'
);
expectTrue(
    strpos($routes, "'/blog/#page#'") < strpos($routes, "'/blog/{slug}'"),
    '/blog/#page# (pagination) must be declared before /blog/{slug}.'
);
expectTrue(
    preg_match_all("#^\s*'/\{slug\}'\s*=>#m", $routes) === 1,
    '/{slug} must be declared exactly once: a duplicate PHP array key silently drops one of them.'
);
expectTrue(is_file($root . '/app/controller/content/legacy-page.php'), 'the legacy-page controller must exist.');

// --- no link anywhere still points at the old prefix -------------------------
$legacy = '#(["\'(=]|independantdigital\.fr)/(?:(?:en|fr)/)?page/#';
$scan   = array_merge(
    glob($theme . '/*.html'),
    glob($theme . '/content/*.html'),
    glob($theme . '/generated/*.html'),
    [$root . '/seed.dokploy.sql']
);
foreach ($scan as $file) {
    if (preg_match($legacy, (string) file_get_contents($file), $m)) {
        expectTrue(false, basename($file) . ' still links to the /page/ prefix (' . $m[0] . ').');
    }
}

// --- canonical plumbing -----------------------------------------------------
$env    = (string) file_get_contents($root . '/env.php');
$common = (string) file_get_contents($root . '/app/template/common.tpl');
$base   = (string) file_get_contents($root . '/app/controller/base.php');
$post   = (string) file_get_contents($root . '/app/template/content/post.tpl');
expectTrue(str_contains($env, "define('CANONICAL_URL'"), 'env.php must define CANONICAL_URL.');
expectTrue(str_contains($env, "getenv('CANONICAL_URL')"), 'CANONICAL_URL must be overridable from the environment.');
expectTrue(str_contains($base, 'canonicalUrl('), 'the base controller must compute the per-page canonical.');
expectTrue(str_contains($common, 'link[rel="canonical"]|href') && str_contains($common, 'meta[property="og:url"]|content'), 'common.tpl must bind canonical and og:url per page.');
expectTrue(str_contains($post, 'og:title') && str_contains($post, 'og:description'), 'post.tpl must bind og:title and og:description from the row.');
foreach (['index.fr.html', 'index.html'] as $home) {
    $head = (string) file_get_contents("$theme/$home");
    expectTrue(str_contains($head, '<link rel="canonical"'), "$home must carry the canonical element the propagation binds.");
    expectTrue(str_contains($head, 'href="/sitemap.xml"'), "$home must advertise /sitemap.xml, not the old /feed/index.");
}

// canonicalUrl() itself
if (! defined('CANONICAL_URL')) {
    define('CANONICAL_URL', 'https://example.test');
}
require_once $root . '/system/functions.php';
foreach ([
    '/'                          => 'https://example.test/',
    '/methode-evaluation'        => 'https://example.test/methode-evaluation',
    '/methode-evaluation/'       => 'https://example.test/methode-evaluation',
    '/annuaire?utm_source=x'     => 'https://example.test/annuaire',
    '/index.php'                 => 'https://example.test/',
    ''                           => 'https://example.test/',
] as $in => $want) {
    $got = \Vvveb\canonicalUrl($in);
    expectTrue($got === $want, "canonicalUrl('$in') must be $want, got $got.");
}

// --- live checks ------------------------------------------------------------
if (getenv('INTEGRATION') === '1') {
    $baseUrl = rtrim((string) (getenv('INTEGRATION_BASE_URL') ?: 'http://127.0.0.1:8090'), '/');
    $fetch = static function (string $path) use ($baseUrl): array {
        $out = shell_exec('curl -sS -o - -D - --max-time 20 ' . escapeshellarg($baseUrl . $path) . ' 2>/dev/null') ?? '';
        [$headers, $body] = array_pad(explode("\r\n\r\n", $out, 2), 2, '');
        preg_match('#^HTTP/\S+ (\d{3})#', $headers, $m);
        preg_match('#^Location:\s*(\S+)#mi', $headers, $l);
        return ['status' => (int) ($m[1] ?? 0), 'location' => $l[1] ?? '', 'body' => $body];
    };
    $ok = $fetch('/methode-evaluation');
    expectTrue($ok['status'] === 200, '/methode-evaluation must answer 200, got ' . $ok['status'] . '.');
    expectTrue(str_contains($ok['body'], '<link rel="canonical" href="' . $baseUrl . '/methode-evaluation">'), 'the page must carry a self-referencing canonical.');
    expectTrue(str_contains($ok['body'], '<meta property="og:url" content="' . $baseUrl . '/methode-evaluation">'), 'og:url must match the canonical.');
    expectTrue(! str_contains($ok['body'], 'href="/page/'), 'the rendered page must not link to /page/ any more.');
    $old = $fetch('/page/methode-evaluation');
    expectTrue($old['status'] === 301 && rtrim($old['location'], '/') === $baseUrl . '/methode-evaluation', '/page/methode-evaluation must 301 to /methode-evaluation, got ' . $old['status'] . ' ' . $old['location'] . '.');
    $en = $fetch('/en/page/about');
    expectTrue($en['status'] === 301 && rtrim($en['location'], '/') === $baseUrl . '/en/about', '/en/page/about must 301 to /en/about.');
    expectTrue($fetch('/en/about')['status'] === 200, '/en/about must answer 200.');
    expectTrue($fetch('/no-such-page-' . time())['status'] === 404, 'an unknown slug must still 404.');
    $fr = $fetch('/fr/methode-evaluation');
    expectTrue($fr['status'] === 301 && rtrim($fr['location'], '/') === $baseUrl . '/methode-evaluation', 'the default-language prefix /fr/ must 301 to the unprefixed URL, got ' . $fr['status'] . ' ' . $fr['location'] . '.');
    $frRoot = $fetch('/fr/');
    expectTrue($frRoot['status'] === 301 && rtrim($frRoot['location'], '/') === $baseUrl, '/fr/ must 301 to the root.');
    $term = $fetch('/annuaire/categorie/visioconference')['body'];
    expectTrue(str_contains($term, '<title>Visioconférence — Annuaire des solutions souveraines</title>'), 'directory term pages must carry a title.');
    expectTrue(preg_match('/<meta name="description" content="[^"]{40,}"/', $term) === 1, 'directory term pages must carry a description.');
    $home = $fetch('/')['body'];
    expectTrue(! preg_match('/<script type="application\/ld\+json">[^<]*&[a-z]+;/', $home), 'JSON-LD must not contain HTML entities.');
    $hub = $fetch('/annuaire')['body'];
    expectTrue(str_contains($hub, 'href="/annuaire/categorie/sauvegarde"') && str_contains($hub, 'href="/annuaire/alternative-a/salesforce"'), '/annuaire must link every category and alternative page.');
}

if ($failures > 0) {
    fwrite(STDERR, "url-structure tests: FAIL ($failures issue(s))\n");
    exit(1);
}
fwrite(STDOUT, "url-structure tests: PASS\n");
