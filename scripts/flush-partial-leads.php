<?php

// No cross-run locking: concurrent runs may double-send a row still mid-flush.
// Schedule this single-flight (e.g. flock in cron, or a scheduler that never
// overlaps runs of the same job) rather than relying on this script alone.

if (PHP_SAPI !== 'cli') {
	fwrite(STDERR, "CLI only.\n");
	exit(1);
}

// Plugin classes each guard on V_VERSION being defined (they refuse to load
// standalone as a public endpoint); a CLI job is a legitimate direct caller.
if (! defined('V_VERSION')) {
	define('V_VERSION', 'cli');
}

// Crypto::secret() falls back to a key file under DIR_ROOT/storage when
// SECRET/AUTH_KEY are undefined (the normal case outside the full CMS
// bootstrap) — the same file submit.php's encrypt/decrypt calls use.
if (! defined('DIR_ROOT')) {
	define('DIR_ROOT', dirname(__DIR__) . '/');
}

require_once __DIR__ . '/../plugins/lead-platform-connector/system/partial-lead.php';
require_once __DIR__ . '/../plugins/lead-platform-connector/system/delivery-mode.php';
require_once __DIR__ . '/../plugins/lead-platform-connector/system/lead-client.php';
require_once __DIR__ . '/../plugins/lead-platform-connector/system/crypto.php';

use Vvveb\Plugins\LeadPlatformConnector\System\Crypto;
use Vvveb\Plugins\LeadPlatformConnector\System\DeliveryMode;
use Vvveb\Plugins\LeadPlatformConnector\System\LeadClient;
use Vvveb\Plugins\LeadPlatformConnector\System\PartialLead;

function flushEnv(string $name, ?string $fallback = null): ?string {
	$value = getenv($name);
	return $value === false || $value === '' ? $fallback : $value;
}

/**
 * Look up the endpoint config a flushed row's slug points at. Absent or
 * blank credentials resolve to DeliveryMode::QUEUE via DeliveryMode::resolve()
 * — an endpoint that has since been deleted or deactivated simply means the
 * row finalises locally instead of forwarding, exactly as a live request
 * from a misconfigured/never-configured endpoint would queue rather than
 * forward.
 */
function fetchEndpoint(PDO $pdo, string $slug): array {
	$stmt = $pdo->prepare('SELECT slug, platform_url, api_key_enc FROM lead_endpoint WHERE slug = :slug LIMIT 1');
	$stmt->execute(['slug' => $slug]);
	$row = $stmt->fetch(PDO::FETCH_ASSOC);

	return is_array($row) ? $row : ['platform_url' => '', 'api_key_enc' => ''];
}

/**
 * Finalise one aged partial row: stage -> FINAL_STAGE, resume token burned
 * (mirrors submit.php's finalizeStage()/settling invalidation), and — when
 * the endpoint resolves to DeliveryMode::FORWARD — the merged answers are
 * sent to the platform exactly as stage 3 of a live submission would. A
 * local (QUEUE/MISCONFIGURED) endpoint just finalises the row where it sits;
 * there is no visitor left to answer, so unlike submit.php there is no retry
 * ladder here — this is the row's last chance to settle.
 */
function flushRow(PDO $pdo, array $row): void {
	$deliverPayload = [];
	$storedEnc = (string) ($row['payload_enc'] ?? '');
	if ($storedEnc !== '') {
		try {
			$decoded = json_decode(Crypto::decrypt($storedEnc), true);
			if (is_array($decoded)) {
				// The payload behind a resumable row still carries the
				// acknowledgement (see PartialLead::merge()); a settling row
				// must not forward or store it any further.
				$deliverPayload = PartialLead::stripAcknowledgement($decoded);
			}
		} catch (\Throwable $e) {
			// Undecryptable payload: nothing left to forward, but the row is
			// still 24h+ old and must not sit in the resumable queue forever.
			$deliverPayload = [];
		}
	}

	$endpoint = fetchEndpoint($pdo, (string) ($row['endpoint_slug'] ?? ''));
	$deliveryMode = DeliveryMode::resolve($endpoint);
	// Named introductions stay in the confirmed local outbox for human
	// qualification and auditable routing, exactly as both of submit.php's
	// FORWARD-downgrade call sites enforce — an aged row is no exception.
	if (PartialLead::requiresLocalQueue($deliverPayload) && $deliveryMode === DeliveryMode::FORWARD) {
		$deliveryMode = DeliveryMode::QUEUE;
	}

	$status    = 'pending';
	$http      = null;
	$response  = null;
	$error     = null;
	$attempts  = 0;
	$payloadEnc = null;

	if ($deliveryMode === DeliveryMode::FORWARD && $deliverPayload) {
		try {
			$apiKey = Crypto::decrypt((string) $endpoint['api_key_enc']);
			$result = LeadClient::send((string) $endpoint['platform_url'], $apiKey, $deliverPayload, 8);
		} catch (\Throwable $e) {
			$result = ['ok' => false, 'http' => null, 'error' => $e->getMessage(), 'raw' => null];
		}

		$attempts = 1;
		$http     = $result['http'] ?? null;
		$response = isset($result['raw']) ? mb_substr((string) $result['raw'], 0, 4000) : null;
		$error    = $result['error'] ?? null;

		if ($result['ok'] || (int) ($result['http'] ?? 0) === 409) {
			// Delivered (or a known duplicate): settled, nothing left to keep.
			$status     = $result['ok'] ? 'sent' : 'duplicate';
			$payloadEnc = null;
		} else {
			// Forward failed and there is no visitor left to retry. Keep the
			// (ack-stripped) answers encrypted so a human can review or
			// re-drive delivery by hand.
			$status     = 'failed';
			$payloadEnc = Crypto::encrypt((string) json_encode($deliverPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
		}
	} elseif ($deliverPayload) {
		// Local queue: finalise in place, same shape a live QUEUE settlement
		// would store.
		$payloadEnc = Crypto::encrypt((string) json_encode($deliverPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
	}

	$update = $pdo->prepare(
		'UPDATE lead_submission SET
		  stage = :stage, lead_token_hash = NULL, lead_token_expires_at = NULL,
		  status = :status, http_status = :http, response = :response, error = :error,
		  attempts = :attempts, payload_enc = :payload_enc, updated_at = CURRENT_TIMESTAMP
		 WHERE lead_submission_id = :id'
	);
	$update->execute([
		'stage'       => PartialLead::FINAL_STAGE,
		'status'      => $status,
		'http'        => $http,
		'response'    => $response,
		'error'       => $error,
		'attempts'    => $attempts,
		'payload_enc' => $payloadEnc,
		'id'          => $row['lead_submission_id'],
	]);
}

try {
	$driver   = strtolower((string) flushEnv('DB_DRIVER', flushEnv('DB_CONNECTION', 'mysql')));
	$database = (string) flushEnv('DB_DATABASE', 'vvveb');
	$dsn      = flushEnv('DB_DSN');
	if (! $dsn) {
		if (in_array($driver, ['pgsql', 'postgres', 'postgresql'], true)) {
			$dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', flushEnv('DB_HOST', 'db'), flushEnv('DB_PORT', '5432'), $database);
		} elseif ($driver === 'sqlite') {
			$dsn = 'sqlite:' . $database;
		} else {
			$dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', flushEnv('DB_HOST', 'db'), flushEnv('DB_PORT', '3306'), $database);
		}
	}
	$pdo = new PDO($dsn, flushEnv('DB_USER', 'vvveb'), flushEnv('DB_PASSWORD', flushEnv('VVVEB_PASSWORD', '')), [
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
	]);

	$now = gmdate('Y-m-d H:i:s');

	$stmt = $pdo->prepare(
		'SELECT lead_submission_id, endpoint_slug, stage, payload_enc, created_at
		 FROM lead_submission
		 WHERE stage < :final'
	);
	$stmt->execute(['final' => PartialLead::FINAL_STAGE]);
	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$flushed = 0;
	foreach ($rows as $row) {
		if (! PartialLead::isFlushable($row, $now)) {
			continue;
		}

		flushRow($pdo, $row);
		$flushed++;
	}

	fwrite(STDOUT, sprintf("flushed %d partial leads\n", $flushed));
} catch (Throwable $error) {
	fwrite(STDERR, 'flush partial leads failed: ' . $error->getMessage() . "\n");
	exit(1);
}
