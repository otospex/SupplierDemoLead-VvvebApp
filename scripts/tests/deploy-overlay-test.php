<?php

// The Dokploy image overlays a hand-picked file list onto the upstream Vvveb
// image (Dockerfile.dokploy). Anything the fork changed and forgot to list
// silently never reaches production. This test derives the fork's footprint
// from git history and checks every surviving file is under a COPY source.

$root = dirname(__DIR__, 2);
$dockerfile = (string) file_get_contents($root . '/Dockerfile.dokploy');

$failures = 0;
function expectTrue($condition, string $message): void {
    global $failures;
    if (! $condition) {
        fwrite(STDERR, "FAIL: $message\n");
        $failures++;
    }
}

// COPY <src> <dst> lines, including the two-line "COPY a \\\n dst" form.
preg_match_all('/^COPY\s+(\S+)\s*\\\\?\s*\n?\s*(\S+)/m', $dockerfile, $m, PREG_SET_ORDER);
$sources = array_map(static fn ($x) => rtrim($x[1], '/'), $m);
expectTrue($sources !== [], 'Dockerfile.dokploy must contain COPY lines.');

$covered = static function (string $file) use ($sources): bool {
    foreach ($sources as $src) {
        if ($file === $src || str_starts_with($file, $src . '/')) {
            return true;
        }
    }
    return false;
};

// Files the fork authored or modified (both otospex author identities).
$out = shell_exec('cd ' . escapeshellarg($root) . ' && git log --author=otospex --diff-filter=ACMR --name-only --format= 2>/dev/null') ?? '';
$files = array_values(array_unique(array_filter(array_map('trim', explode("\n", $out)))));
expectTrue(count($files) > 20, 'git history should list the fork\'s files (is this a git checkout?).');

// Not part of the runtime image, or staged by other means.
$ignore = [
    '#^docs/#', '#^\.claude/#', '#^\.hallmark/#', '#^\.github/#', '#^scripts/tests/#', '#/tests/#',
    '#^storage/#', '#^public/page-cache/#', '#^tokens\.css$#', '#^README\.md$#', '#^CONTEXT\.md$#',
    '#^\.gitignore$#', '#^\.dockerignore$#', '#^\.gitattributes$#', '#^LICENSE$#',
    '#^Dockerfile#', '#^docker-compose#', '#^nginx-#', '#^init\.dokploy\.sh$#', '#^build\.sh$#',
    '#^seed\.dokploy\.(php|sql)$#',       // staged under /opt/seed, applied by init
    '#^php\.ini$#', '#^nginx\.dokploy\.conf$#', // copied outside the webroot
    '#^public/themes/souverainete-digitale/backup/#',
    '#^config/app\.php$#',                // install-generated keys live on the volume
];

$missing = [];
foreach ($files as $file) {
    if (! is_file($root . '/' . $file)) {
        continue;
    }
    foreach ($ignore as $pattern) {
        if (preg_match($pattern, $file)) {
            continue 2;
        }
    }
    if (! $covered($file)) {
        $missing[] = $file;
    }
}
sort($missing);
expectTrue($missing === [], "fork files not covered by a Dockerfile.dokploy COPY (they never reach production):\n  - " . implode("\n  - ", $missing));

// Files that must ship whatever git says.
foreach (['config/app-routes.php', 'plugins/solutions-directory', 'public/plugins/solutions-directory', 'app/controller/sitemap.php', 'system/sitemap-builder.php', 'app/controller/feed/robots.php', 'public/vrobots.txt', 'scripts/flush-partial-leads.php', 'scripts/purge-leads.php', 'env.php', 'app/template/common.tpl', 'app/template/content/post.tpl', 'nginx.dokploy.conf'] as $must) {
    expectTrue($covered($must), "$must must be overlaid onto the image.");
}
// .dockerignore is default-deny: every COPY source must be un-ignored or the build fails.
$dockerignore = (string) file_get_contents($root . '/.dockerignore');
foreach ($sources as $src) {
    $parts = explode('/', $src);
    $ok = false;
    for ($i = count($parts); $i >= 1; $i--) {
        $prefix = implode('/', array_slice($parts, 0, $i));
        if (preg_match('#^!' . preg_quote($prefix, '#') . '(/\*\*)?$#m', $dockerignore)) {
            $ok = true;
            break;
        }
    }
    expectTrue($ok, "$src is a COPY source but .dockerignore never un-ignores it.");
}

// config/db.php is written by the installer on the volume and must never be overlaid.
expectTrue(! $covered('config/db.php'), 'config/db.php must not be overlaid: it is generated on the volume.');

if ($failures > 0) {
    fwrite(STDERR, "deploy-overlay tests: FAIL ($failures issue(s))\n");
    exit(1);
}
fwrite(STDOUT, "deploy-overlay tests: PASS\n");
