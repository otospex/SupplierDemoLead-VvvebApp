<?php

// Automatic sitemaps and robots policy: the builder's XML shape, the routes and
// the nginx/Docker wiring that make /sitemap*.xml answer 200 in production, the
// robots.txt policy (LLM crawlers allowed, absolute Sitemap line), and — with
// INTEGRATION=1 — the live responses.

$root = dirname(__DIR__, 2);
if (! defined('CANONICAL_URL')) {
    define('CANONICAL_URL', 'https://example.test');
}
require_once $root . '/system/functions.php';
require_once $root . '/system/sitemap-builder.php';

use Vvveb\System\SitemapBuilder;

$failures = 0;
function expectTrue($condition, string $message): void {
    global $failures;
    if (! $condition) {
        fwrite(STDERR, "FAIL: $message\n");
        $failures++;
    }
}

// --- builder ----------------------------------------------------------------
$languages = [1 => ['slug' => 'en', 'code' => 'en_US'], 2 => ['slug' => 'fr', 'code' => 'fr_FR']];
$rows = [
    ['post_id' => 7, 'language_id' => 1, 'slug' => 'contact', 'updated_at' => '2026-09-01 10:00:00'],
    ['post_id' => 7, 'language_id' => 2, 'slug' => 'contact', 'updated_at' => '2026-09-01 10:00:00'],
    ['post_id' => 9, 'language_id' => 2, 'slug' => 'methode-evaluation', 'updated_at' => '2026-08-30 08:00:00'],
    ['post_id' => 11, 'language_id' => 2, 'slug' => 'referencer-une-solution', 'updated_at' => '2026-08-30 08:00:00'],
    ['post_id' => 12, 'language_id' => 2, 'slug' => '', 'updated_at' => '2026-08-30 08:00:00'],
    ['post_id' => 13, 'language_id' => 3, 'slug' => 'ghost-language', 'updated_at' => '2026-08-30 08:00:00'],
];
$pathFor  = static fn (string $slug, ?string $lang) => ($lang ? "/$lang" : '') . "/$slug";
$absolute = static fn (string $path) => \Vvveb\canonicalUrl($path);
$entries  = SitemapBuilder::entries($rows, $languages, 2, $pathFor, $absolute, ['referencer-une-solution']);

$locs = array_column($entries, 'loc');
expectTrue($locs === [
    'https://example.test/en/contact',
    'https://example.test/contact',
    'https://example.test/methode-evaluation',
], 'entries must cover both languages, default language unprefixed, and drop excluded, empty-slug and unknown-language rows; got ' . json_encode($locs));

$contactEn = $entries[0];
expectTrue($contactEn['lastmod'] === '2026-09-01T10:00:00+00:00', 'lastmod must be W3C datetime in UTC, got ' . $contactEn['lastmod']);
$alt = array_map(static fn ($a) => $a['hreflang'] . '=' . $a['href'], $contactEn['alternates']);
expectTrue($alt === [
    'en=https://example.test/en/contact',
    'fr=https://example.test/contact',
    'x-default=https://example.test/contact',
], 'a translated page must list every language plus x-default on the default language, got ' . json_encode($alt));
expectTrue($entries[2]['alternates'] === [], 'a page with one language carries no alternates.');

$xml = SitemapBuilder::urlset($entries);
expectTrue(str_starts_with($xml, '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">'), 'urlset must declare the sitemap and xhtml namespaces.');
expectTrue(substr_count($xml, '<url>') === 3 && substr_count($xml, '<xhtml:link rel="alternate" hreflang="x-default"') === 2, 'urlset must emit one <url> per entry with its alternates.');
$dom = new DOMDocument();
expectTrue(@$dom->loadXML($xml) !== false, 'urlset must be well-formed XML.');

$index = SitemapBuilder::index([['loc' => 'https://example.test/sitemap-pages.xml', 'lastmod' => '2026-09-01T10:00:00+00:00'], ['loc' => 'https://example.test/sitemap-solutions.xml', 'lastmod' => '']]);
expectTrue(@$dom->loadXML($index) !== false && substr_count($index, '<sitemap>') === 2 && substr_count($index, '<lastmod>') === 1, 'sitemap index must list each sitemap and only emit lastmod when known.');
expectTrue(SitemapBuilder::hreflang('fr_FR') === 'fr' && SitemapBuilder::hreflang('en') === 'en', 'hreflang codes are the bare language.');
expectTrue(SitemapBuilder::xml('a&b<c>') === 'a&amp;b&lt;c&gt;', 'values are XML-escaped.');

// --- routes, controller, nginx, docker ----------------------------------------
$routes = (string) file_get_contents($root . '/config/app-routes.php');
expectTrue(str_contains($routes, "'/sitemap.xml'           => ['module' => 'sitemap/index']"), '/sitemap.xml must route to the sitemap controller.');
expectTrue(str_contains($routes, "'/sitemap-{section}.xml' => ['module' => 'sitemap/index']"), '/sitemap-{section}.xml must route to the sitemap controller.');
expectTrue(str_contains($routes, "'/sitemap-solutions.xml' => ['module' => 'plugins/solutions-directory/sitemap/index']"), 'the directory sitemap must live at /sitemap-solutions.xml.');
expectTrue(strpos($routes, "'/sitemap-solutions.xml'") < strpos($routes, "'/sitemap-{section}.xml'"), 'the plugin sitemap route must be declared before the generic section route.');
expectTrue(! str_contains($routes, "'/feed/solutions.xml'"), 'the old /feed/solutions.xml route must be gone.');
expectTrue(is_file($root . '/app/controller/sitemap.php'), 'app/controller/sitemap.php must exist.');
$pluginSitemap = (string) file_get_contents($root . '/plugins/solutions-directory/app/controller/sitemap.php');
expectTrue(str_contains($pluginSitemap, 'canonicalUrl('), 'the directory sitemap must build URLs on the canonical origin.');
expectTrue(! str_contains($pluginSitemap, "\$this->global['site']['url']"), 'the directory sitemap must not read the site host pattern.');

$nginx = (string) file_get_contents($root . '/nginx.dokploy.conf');
expectTrue(preg_match('#location ~ \^/sitemap\(-\[a-z0-9-\]\+\)\?\\\\\.xml\$ \{#', $nginx) === 1, 'nginx.dokploy.conf must carry the sitemap location.');
expectTrue(strpos($nginx, '^/sitemap(') < strpos($nginx, 'location ~* "\.(?!php)([\w]{3,5})$"'), 'the sitemap location must precede the static-extension regex, or the 404 fallthrough wins.');
$dockerfile = (string) file_get_contents($root . '/Dockerfile.dokploy');
expectTrue(str_contains($dockerfile, 'COPY nginx.dokploy.conf /etc/nginx/http.d/vvveb.conf'), 'Dockerfile.dokploy must install nginx.dokploy.conf over the image conf.');

// --- robots -------------------------------------------------------------------
$robots = (string) file_get_contents($root . '/public/vrobots.txt');
foreach (['GPTBot', 'ClaudeBot', 'PerplexityBot', 'Google-Extended', 'CCBot', 'OAI-SearchBot', 'anthropic-ai'] as $bot) {
    expectTrue(preg_match('/^User-agent: ' . preg_quote($bot, '/') . '$/mi', $robots) === 1, "robots.txt must name $bot.");
}
expectTrue(preg_match('/^Allow: \/$/m', $robots) === 1, 'the LLM crawler group must be allowed everything public.');
expectTrue(preg_match('/^Sitemap: \/sitemap\.xml$/m', $robots) === 1, 'the source robots file declares the sitemap by path; the controller absolutizes it.');
expectTrue(! str_contains($robots, '/feed/'), 'robots.txt must not point at the old feed sitemaps.');
$robotsController = (string) file_get_contents($root . '/app/controller/feed/robots.php');
expectTrue(str_contains($robotsController, 'canonicalUrl('), 'robots.php must absolutize Sitemap lines on the canonical origin.');
expectTrue(! preg_match('/if \(\$path\) \{\s*\$text = preg_replace\(\'@\(sitemap\)/', $robotsController), 'the sitemap absolutizing must not be gated on a sub-path.');

// --- head links ---------------------------------------------------------------
foreach (glob($root . '/public/themes/souverainete-digitale/**/*.html') + glob($root . '/public/themes/souverainete-digitale/*.html') as $file) {
    if (str_contains((string) file_get_contents($file), 'href="/feed/index"')) {
        expectTrue(false, basename($file) . ' still advertises /feed/index as the sitemap.');
    }
}

// --- live ---------------------------------------------------------------------
if (getenv('INTEGRATION') === '1') {
    $baseUrl = rtrim((string) (getenv('INTEGRATION_BASE_URL') ?: 'http://127.0.0.1:8090'), '/');
    $fetch = static function (string $path) use ($baseUrl): array {
        $out = shell_exec('curl -sS -o - -D - --max-time 20 ' . escapeshellarg($baseUrl . $path) . ' 2>/dev/null') ?? '';
        [$headers, $body] = array_pad(explode("\r\n\r\n", $out, 2), 2, '');
        preg_match('#^HTTP/\S+ (\d{3})#', $headers, $m);
        preg_match('#^Content-Type:\s*([^\r\n]+)#mi', $headers, $c);
        return ['status' => (int) ($m[1] ?? 0), 'type' => strtolower($c[1] ?? ''), 'body' => $body];
    };
    foreach (['/sitemap.xml', '/sitemap-pages.xml', '/sitemap-solutions.xml'] as $path) {
        $r = $fetch($path);
        expectTrue($r['status'] === 200, "$path must answer 200, got {$r['status']}.");
        expectTrue(str_contains($r['type'], 'xml'), "$path must be served as XML, got {$r['type']}.");
        expectTrue(@$dom->loadXML($r['body']) !== false, "$path must be well-formed XML.");
        expectTrue(! str_contains($r['body'], '*.*.*') && ! str_contains($r['body'], '<loc>/'), "$path must carry absolute canonical <loc> values.");
    }
    $idx = $fetch('/sitemap.xml')['body'];
    expectTrue(str_contains($idx, $baseUrl . '/sitemap-pages.xml') && str_contains($idx, $baseUrl . '/sitemap-solutions.xml'), 'the index must list the pages and solutions sitemaps on the canonical origin.');
    $pages = $fetch('/sitemap-pages.xml')['body'];
    expectTrue(str_contains($pages, '<loc>' . $baseUrl . '/methode-evaluation</loc>'), 'the pages sitemap must list /methode-evaluation without a /page/ prefix.');
    expectTrue(! str_contains($pages, '/page/'), 'the pages sitemap must not contain /page/ URLs.');
    expectTrue(! str_contains($pages, 'referencer-une-solution'), 'the registration page is noindex and must not be in the sitemap.');
    expectTrue(str_contains($pages, 'hreflang="x-default"'), 'translated pages must carry hreflang alternates.');
    expectTrue($fetch('/sitemap-nope.xml')['status'] === 404, 'an unknown sitemap section must 404.');
    $rb = $fetch('/robots.txt');
    expectTrue($rb['status'] === 200 && str_contains($rb['body'], 'Sitemap: ' . $baseUrl . '/sitemap.xml'), 'robots.txt must declare the absolute sitemap URL, got: ' . trim(strrchr($rb['body'], "\n") ?: ''));
}

if ($failures > 0) {
    fwrite(STDERR, "sitemap tests: FAIL ($failures issue(s))\n");
    exit(1);
}
fwrite(STDOUT, "sitemap tests: PASS\n");
