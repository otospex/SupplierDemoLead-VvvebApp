<?php

define('V_VERSION', 'test');

$file = __DIR__ . '/../system/delivery-mode.php';
if (! is_file($file)) {
    fwrite(STDERR, "FAIL: delivery mode resolver is missing.\n");
    exit(1);
}

require_once $file;

use Vvveb\Plugins\LeadPlatformConnector\System\DeliveryMode;

$failures = 0;

$queue = DeliveryMode::resolve(['platform_url' => '', 'api_key_enc' => '']);
if ($queue !== DeliveryMode::QUEUE) {
    fwrite(STDERR, "FAIL: blank delivery credentials must select local queue mode.\n");
    $failures++;
}

$forward = DeliveryMode::resolve(['platform_url' => 'https://leads.example.test/api/v1/leads', 'api_key_enc' => 'ciphertext']);
if ($forward !== DeliveryMode::FORWARD) {
    fwrite(STDERR, "FAIL: complete delivery credentials must select forwarding mode.\n");
    $failures++;
}

$broken = DeliveryMode::resolve(['platform_url' => 'https://leads.example.test/api/v1/leads', 'api_key_enc' => '']);
if ($broken !== DeliveryMode::MISCONFIGURED) {
    fwrite(STDERR, "FAIL: partial credentials must be treated as misconfigured.\n");
    $failures++;
}

$root = dirname(__DIR__);
$controller = (string) file_get_contents($root . '/app/controller/submit.php');
$schemas = [
    $root . '/install/sql/mysqli/schema/lead_submission.sql',
    $root . '/install/sql/pgsql/schema/lead_submission.sql',
    $root . '/install/sql/sqlite/schema/lead_submission.sql',
];

if (! str_contains($controller, "'payload_enc'")) {
    fwrite(STDERR, "FAIL: queued leads must persist a separately encrypted delivery payload.\n");
    $failures++;
}

foreach ($schemas as $schema) {
    if (! str_contains((string) file_get_contents($schema), 'payload_enc')) {
        fwrite(STDERR, "FAIL: lead submission schema is missing payload_enc: $schema\n");
        $failures++;
    }
}

if ($failures > 0) {
    fwrite(STDERR, "delivery-mode tests: FAIL ($failures issue(s))\n");
    exit(1);
}

fwrite(STDOUT, "delivery-mode tests: PASS\n");
