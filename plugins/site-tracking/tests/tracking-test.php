<?php

define('V_VERSION', 'test');

require_once __DIR__ . '/../system/tracking.php';

use Vvveb\Plugins\SiteTracking\System\Tracking;

$failures = 0;
function expectTracking(bool $condition, string $message): void {
    global $failures;
    if (! $condition) {
        fwrite(STDERR, "FAIL: $message\n");
        $failures++;
    }
}

// --- Nothing configured: nothing rendered, ever ------------------------------
expectTracking(Tracking::head([]) === '' && Tracking::body([]) === '', 'empty settings render nothing.');
expectTracking(Tracking::head(['matomo_url' => '', 'matomo_site_id' => '', 'head_scripts' => '   ', 'marketing_scripts' => '']) === '', 'blank settings render nothing.');

// --- Matomo by URL + site id: cookieless tag, values escaped -----------------
$head = Tracking::head(['matomo_url' => 'https://stats.example.fr/', 'matomo_site_id' => '3']);
expectTracking(str_contains($head, "_paq.push(['disableCookies'])"), 'the Matomo tag must run cookieless (CNIL exemption).');
expectTracking(str_contains($head, '"https://stats.example.fr/"') && str_contains($head, "setSiteId',\"3\"") , 'Matomo URL and site id must be emitted as JSON strings.');
expectTracking(str_contains($head, 'matomo.js') && str_contains($head, 'matomo.php'), 'the tag must load matomo.js and post to matomo.php.');
$noSlash = Tracking::head(['matomo_url' => 'https://stats.example.fr', 'matomo_site_id' => '3']);
expectTracking(str_contains($noSlash, '"https://stats.example.fr/"'), 'a trailing slash is added to the Matomo URL.');
$hostile = Tracking::head(['matomo_url' => 'javascript:alert(1)', 'matomo_site_id' => '3']);
expectTracking($hostile === '', 'a non-http Matomo URL renders nothing.');
$hostileId = Tracking::head(['matomo_url' => 'https://stats.example.fr/', 'matomo_site_id' => '3"; alert(1); "']);
expectTracking($hostileId === '', 'a non-numeric site id renders nothing.');
$quoted = Tracking::head(['matomo_url' => 'https://stats.example.fr/</script><script>alert(1)</script>', 'matomo_site_id' => '1']);
expectTracking(! str_contains($quoted, '</script><script>alert'), 'a URL cannot break out of the script element.');

// --- Raw head scripts: verbatim, always -------------------------------------
$raw = Tracking::head(['head_scripts' => '<script src="https://cdn.example/analytics.js" async></script>']);
expectTracking(str_contains($raw, '<script src="https://cdn.example/analytics.js" async></script>'), 'raw head scripts are emitted verbatim.');

// --- Marketing scripts: inert until consent, banner present -----------------
$body = Tracking::body(['marketing_scripts' => '<script>window.__mk=1</script><noscript><img src="https://px.example/1.gif"></noscript>']);
expectTracking(str_contains($body, '<script type="text/plain" data-id-consent="marketing">'), 'marketing scripts must be stored inert (text/plain) until consent.');
expectTracking(! preg_match('#<script>window\.__mk=1</script>#', $body), 'marketing scripts must not be emitted as executable markup.');
expectTracking(str_contains($body, 'id="id-consent"') && str_contains($body, 'data-id-consent-accept') && str_contains($body, 'data-id-consent-refuse'), 'a consent banner with accept and refuse controls is rendered.');
expectTracking(str_contains($body, 'id_consent'), 'the choice is persisted under a stable key.');
expectTracking(str_contains($body, 'lead-platform-connector:success'), 'the body script listens for lead form success.');
$noMarketing = Tracking::body(['matomo_url' => 'https://stats.example.fr/', 'matomo_site_id' => '1']);
expectTracking(! str_contains($noMarketing, 'id="id-consent"'), 'no marketing scripts means no consent banner.');
expectTracking(str_contains($noMarketing, 'window.idTrack'), 'the tracking helper is always present once tracking is on.');
$goal = Tracking::body(['matomo_url' => 'https://stats.example.fr/', 'matomo_site_id' => '1', 'matomo_goal_id' => '7']);
expectTracking(str_contains($goal, "'trackGoal', 7"), 'a Matomo goal id is tracked on lead success.');
expectTracking(! str_contains(Tracking::body(['matomo_url' => 'https://stats.example.fr/', 'matomo_site_id' => '1', 'matomo_goal_id' => 'x']), 'trackGoal'), 'a non-numeric goal id is ignored.');
$html = Tracking::body(['marketing_scripts' => '</script><script>alert(1)</script>']);
expectTracking(! str_contains($html, '</script><script>alert(1)</script>'), 'marketing markup cannot close the inert container early.');

// --- Consent text is escaped ---------------------------------------------------
$text = Tracking::body(['marketing_scripts' => '<script>1</script>', 'consent_text' => '<b>x</b> & y']);
expectTracking(str_contains($text, '&lt;b&gt;x&lt;/b&gt; &amp; y'), 'the consent text is escaped.');

if ($failures > 0) {
    fwrite(STDERR, "site-tracking tests: FAIL ($failures issue(s))\n");
    exit(1);
}
echo "site-tracking tests: PASS\n";
