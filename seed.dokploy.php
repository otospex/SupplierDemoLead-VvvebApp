<?php
/**
 * One-time DB seeder for the souverainete-digitale multi-page content.
 *
 * Invoked by init.dokploy.sh on container start. Guarded by a marker file in
 * the persistent volume so it runs ONCE and never clobbers later live edits.
 *
 * - Connects via mysqli using the same env vars the app uses (DB_HOST/DB_DATABASE/
 *   DB_USER/DB_PASSWORD), falling back to VVVEB_* if present.
 * - Waits for MySQL to accept connections (the db service may start after php).
 * - Skips entirely if the marker exists, OR if the DB has no expected page rows
 *   yet (fresh install before Vvveb's own installer has seeded the schema).
 * - Runs seed.dokploy.sql, then busts the relevant caches.
 * - Always exits 0 so a seeding hiccup never blocks the site from starting.
 */

$root      = '/var/www/html';
// Marker version: bump whenever seed.dokploy.sql gains new idempotent content so
// a redeploy re-runs the seed on existing persistent volumes. v3 adds the French
// language + page translations and the SEO blog posts. v4 additionally flushes
// the full-page HTML cache (public/page-cache) so stale pre-fix renders clear.
// v5 makes the French page rows delete-then-insert so they overwrite leftover
// Romanian demo rows that occupied the French language_id on existing prod DBs.
// v6 adds the 14 FR + 14 EN styled resource pages (page-hero + TOC + sidebar +
// JSON-LD), rewrites the 6 service pages + services/about/method in both
// languages, and sets the EN/FR homepage <title>/meta in site.settings JSON.
$marker    = $root . '/storage/.seed-souverainete-applied-v7';
$sqlFile   = __DIR__ . '/seed.dokploy.sql';

function out($m) { fwrite(STDOUT, "[seed] $m\n"); }

// Already applied? Done.
if (file_exists($marker)) {
    out('marker present — already seeded, skipping.');
    exit(0);
}

if (!is_file($sqlFile)) {
    out("seed SQL not found at $sqlFile — skipping.");
    exit(0);
}

// Credentials come from the same env the app/compose uses (DB_*), with VVVEB_*
// only as a last-resort host/password fallback (compose sets VVVEB_HOST/PASSWORD).
$host = getenv('DB_HOST')     ?: getenv('VVVEB_HOST')     ?: 'db';
$db   = getenv('DB_DATABASE') ?: 'vvveb';
$user = getenv('DB_USER')     ?: 'vvveb';
$pass = getenv('DB_PASSWORD') ?: getenv('VVVEB_PASSWORD') ?: '';

mysqli_report(MYSQLI_REPORT_OFF);

// Wait for MySQL (up to ~60s).
$conn = null;
for ($i = 0; $i < 30; $i++) {
    $conn = @mysqli_connect($host, $user, $pass, $db);
    if ($conn) { break; }
    out("waiting for MySQL at $host/$db … ($i)");
    sleep(2);
}
if (!$conn) {
    out('could not connect to MySQL — will retry on next deploy.');
    exit(0);
}

// Guard: only seed once Vvveb's own schema/data exists. If the expected page
// rows are not there yet (very first install), skip and let a later deploy do it.
$res = @mysqli_query($conn, "SELECT COUNT(*) AS c FROM post_content WHERE slug IN ('contact','about','services')");
if (!$res) {
    out('post_content not ready yet (fresh install) — skipping this run.');
    mysqli_close($conn);
    exit(0);
}
$row = mysqli_fetch_assoc($res);
if ((int)$row['c'] === 0) {
    out('expected page rows absent — skipping (Vvveb installer has not seeded yet).');
    mysqli_close($conn);
    exit(0);
}

out('applying seed.dokploy.sql …');
$sql = file_get_contents($sqlFile);

// Run the batch and check EVERY statement. mysqli_error() after the drain loop
// only reflects the LAST statement, so a mid-batch failure could otherwise look
// like success and write the marker over a half-applied seed. Inspect each
// result transition and capture the first error, withholding the marker so the
// next deploy retries (the SQL is idempotent, so a clean re-run heals it).
$ok  = mysqli_multi_query($conn, $sql);
$err = $ok ? '' : mysqli_error($conn);
while ($ok && $err === '') {
    if ($r = mysqli_store_result($conn)) { mysqli_free_result($r); }
    if (!mysqli_more_results($conn)) { break; }
    if (!mysqli_next_result($conn)) { $err = mysqli_error($conn) ?: 'unknown error advancing result set'; break; }
}
mysqli_close($conn);

if ($err) {
    out("seed reported an error: $err");
    out('NOT writing marker — will retry on next deploy.');
    exit(0);
}

// Bust caches so the seeded pages/content show immediately.
$cacheDir = $root . '/storage/cache';
foreach ([
    $cacheDir,
    $root . '/storage/compiled-templates',
] as $dir) {
    if (is_dir($dir)) {
        foreach (glob($dir . '/{url.*,component.posts.*,posts.archives.*,*.tpl,admin.template-list-*}', GLOB_BRACE) ?: [] as $f) {
            @unlink($f);
        }
    }
}

// Also flush the full-page HTML cache (public/page-cache/**). The seeder runs
// content changes (translations, new pages/posts, per-language homepage
// template), and stale cached HTML — e.g. an English /fr/ render captured
// before the fix — would otherwise keep serving until each entry expired.
$pageCache = $root . '/public/page-cache';
if (is_dir($pageCache)) {
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($pageCache, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $f) {
        if ($f->isFile() || $f->isLink()) {
            @unlink($f->getPathname());
        } elseif ($f->isDir()) {
            @rmdir($f->getPathname());
        }
    }
    out('flushed public/page-cache.');
}

// Write the marker so we never re-run (and never clobber live edits).
@file_put_contents($marker, date('c') . " seeded souverainete multi-page content\n");
out('seed applied successfully; marker written.');
exit(0);
