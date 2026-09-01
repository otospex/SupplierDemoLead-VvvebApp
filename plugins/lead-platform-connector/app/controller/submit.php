<?php

namespace Vvveb\Plugins\LeadPlatformConnector\Controller;

use Vvveb\System\Core\Request;
use Vvveb\Plugins\LeadPlatformConnector\System\Crypto;
use Vvveb\Plugins\LeadPlatformConnector\System\CsrfToken;
use Vvveb\Plugins\LeadPlatformConnector\System\DeliveryMode;
use Vvveb\Plugins\LeadPlatformConnector\System\LeadClient;
use Vvveb\Plugins\LeadPlatformConnector\System\PartialLead;
use Vvveb\Plugins\LeadPlatformConnector\System\PrivacyAcknowledgement;
use Vvveb\Plugins\LeadPlatformConnector\System\ProviderConsent;
use Vvveb\Plugins\LeadPlatformConnector\System\Repo;

if (! defined('V_VERSION')) {
	die('Invalid request!');
}

#[\AllowDynamicProperties]
class Submit {

	function __construct() {}

	private function json(int $http, array $body): void {
		http_response_code($http);
		header('Content-Type: application/json; charset=utf-8');
		header('X-Robots-Tag: noindex, nofollow');
		echo json_encode($body);
		exit;
	}

	private function clientIp(): string {
		$server = $_SERVER ?? [];
		// Trust only REMOTE_ADDR by default; admins can configure proxies later.
		return isset($server['REMOTE_ADDR']) ? (string) $server['REMOTE_ADDR'] : '';
	}

	private function originAllowed(?string $allowedJson, string $origin, string $referer): bool {
		// Strip port from a "host:port" value.
		$stripPort = function ($host) {
			$host = (string) $host;
			$colon = strpos($host, ':');
			return $colon === false ? $host : substr($host, 0, $colon);
		};

		if (! $allowedJson) {
			// If unset, allow same-host requests only.
			$myHost = $stripPort($_SERVER['HTTP_HOST'] ?? '');
			$candidates = array_filter([$origin, $referer]);
			if (! $candidates) {
				return true; // direct POST without origin/referer — handled by CSRF
			}
			foreach ($candidates as $url) {
				$parts = parse_url($url);
				$candHost = $stripPort($parts['host'] ?? '');
				if ($candHost !== '' && strcasecmp($candHost, $myHost) === 0) {
					return true;
				}
			}
			return false;
		}

		$list = json_decode($allowedJson, true);
		if (! is_array($list) || ! $list) {
			return false;
		}
		$candidates = array_filter([$origin, $referer]);
		foreach ($candidates as $url) {
			$parts = parse_url($url);
			$host  = $stripPort($parts['host'] ?? '');
			foreach ($list as $allowed) {
				$allowed = $stripPort(strtolower(trim((string) $allowed)));
				if ($allowed === '' ) continue;
				if (strcasecmp($allowed, $host) === 0) return true;
				if (strpos($allowed, '*.') === 0) {
					$suffix = substr($allowed, 1); // ".example.com"
					if (substr($host, -strlen($suffix)) === $suffix) return true;
				}
			}
		}
		return false;
	}

	private function rateLimit(string $key, int $limit, int $windowSec = 60): bool {
		$dir = (defined('DIR_ROOT') ? DIR_ROOT : __DIR__ . '/../../../../') . 'storage/lpc-rl';
		if (! is_dir($dir)) {
			@mkdir($dir, 0750, true);
		}
		$file = $dir . '/' . hash('sha256', $key);
		$now  = time();

		$handle = @fopen($file, 'c+');
		if (! $handle || ! flock($handle, LOCK_EX)) return false;
		$entries = [];
		rewind($handle);
		$raw = stream_get_contents($handle);
		$decoded = $raw ? json_decode($raw, true) : [];
		if (is_array($decoded)) {
			foreach ($decoded as $ts) {
				if (($now - (int) $ts) < $windowSec) $entries[] = (int) $ts;
			}
		}

		if (count($entries) >= $limit) {
			flock($handle, LOCK_UN);
			fclose($handle);
			return false;
		}
		$entries[] = $now;
		rewind($handle);
		ftruncate($handle, 0);
		$written = fwrite($handle, (string) json_encode($entries));
		fflush($handle);
		flock($handle, LOCK_UN);
		fclose($handle);
		if ($written === false) return false;
		return true;
	}

	private function fetchEndpoint(string $slug): ?array {
		try {
			return Repo::one(
				'SELECT slug, label, platform_url, api_key_enc, campaign, field_map, allowed_origins, rate_limit, active
				 FROM lead_endpoint WHERE slug = :slug AND active = 1 LIMIT 1',
				['slug' => $slug]
			);
		} catch (\Throwable $e) {
			return null;
		}
	}

	private function applyFieldMap(array $fields, ?string $mapJson): array {
		if (! $mapJson) {
			return $fields;
		}
		$map = json_decode($mapJson, true);
		if (! is_array($map)) {
			return $fields;
		}

		$out = [];
		foreach ($fields as $name => $value) {
			$target = $map[$name] ?? $name;
			if ($target === null || $target === '') continue;

			if (strpos($target, '.') !== false) {
				$parts = explode('.', $target);
				$ref =& $out;
				foreach ($parts as $p) {
					if (! isset($ref[$p]) || ! is_array($ref[$p])) {
						$ref[$p] = [];
					}
					$ref =& $ref[$p];
				}
				$ref = $value;
				unset($ref);
			} else {
				$out[$target] = $value;
			}
		}
		return $out;
	}

	private function logSubmission(array $row): bool {
		try {
			$result = Repo::exec(
				'INSERT INTO lead_submission
				 (endpoint_slug, status, platform_lead_id, http_status, phone_hash, email_hash,
				  provider_slug, consent_text_version, consent_at, payload, payload_enc, response, error,
				  client_ip, user_agent, source_page, attempts, stage, lead_token_hash,
				  lead_token_expires_at, created_at, updated_at)
				 VALUES
				 (:slug, :status, :pid, :http, :phash, :ehash, :provider, :consent_version,
				  :consent_at, :payload, :payload_enc, :response, :error, :ip, :ua, :sp, :att,
				  :stage, :token_hash, :token_expires,
				  CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)',
				$row
			);
			return $result !== false;
		} catch (\Throwable $e) {
			return false;
		}
	}

	/**
	 * Rewrite an existing row in place. Staged submissions keep one row for the
	 * whole diagnostic, so later steps update rather than insert.
	 */
	private function updateSubmission(array $row): bool {
		try {
			$result = Repo::exec(
				'UPDATE lead_submission SET
				  status = :status, platform_lead_id = :pid, http_status = :http,
				  phone_hash = :phash, email_hash = :ehash, provider_slug = :provider,
				  consent_text_version = :consent_version, consent_at = :consent_at,
				  payload = :payload, payload_enc = :payload_enc, response = :response,
				  error = :error, source_page = :sp, attempts = :att, stage = :stage,
				  lead_token_hash = :token_hash, lead_token_expires_at = :token_expires,
				  updated_at = CURRENT_TIMESTAMP
				 WHERE lead_submission_id = :id',
				$row
			);
			return $result !== false;
		} catch (\Throwable $e) {
			return false;
		}
	}

	/**
	 * Look up a partial row by token hash. The token is scoped to the endpoint
	 * that issued it, and a completed row (stage 3, token cleared) never
	 * matches — a replayed token reads as expired.
	 */
	private function findPartialByHash(string $hash, string $slug): ?array {
		try {
			return Repo::one(
				'SELECT lead_submission_id, endpoint_slug, stage, status, payload_enc, source_page,
				        provider_slug, consent_text_version, consent_at,
				        lead_token_hash, lead_token_expires_at, created_at
				 FROM lead_submission
				 WHERE lead_token_hash = :hash AND endpoint_slug = :slug AND stage < :final
				 LIMIT 1',
				['hash' => $hash, 'slug' => $slug, 'final' => PartialLead::FINAL_STAGE]
			);
		} catch (\Throwable $e) {
			return null;
		}
	}

	/** Best-effort PII detection for hashing in the audit log. */
	private function detectPii(array $payload): array {
		$phoneVal = null; $emailVal = null;
		foreach ($payload as $k => $v) {
			if (! is_string($v)) continue;
			$lk = strtolower((string) $k);
			if ($phoneVal === null && (str_contains($lk, 'phone') || str_contains($lk, 'telephone') || str_contains($lk, 'mobile'))) {
				$phoneVal = $v;
			}
			if ($emailVal === null && str_contains($lk, 'email')) {
				$emailVal = $v;
			}
		}

		return [$phoneVal, $emailVal];
	}

	/**
	 * Strip raw PII, the privacy acknowledgement and consent fields from the
	 * plaintext stored payload. (The acknowledgement is already gone on the
	 * full path; staged rows keep it in the encrypted payload only.)
	 */
	private function stripPii(array $payload): array {
		unset($payload['privacy_acknowledgement']);
		foreach ($payload as $k => $v) {
			$lk = strtolower((string) $k);
			if (str_contains($lk, 'phone') || str_contains($lk, 'telephone') || str_contains($lk, 'mobile') || str_contains($lk, 'email')) {
				unset($payload[$k]);
			}
		}
		foreach (['provider_introduction_requested', 'provider_slug', 'consent_text_version', 'consent_timestamp'] as $field) {
			unset($payload[$field]);
		}

		return $payload;
	}

	/**
	 * Forward form fields verbatim. The platform's campaign-level field_schema.mappings
	 * translates source field names → platform field names server-side.
	 * (The plugin's field_map is only used by the editor to auto-generate the form.)
	 */
	private function buildPayload(array $fields, array $endpoint, string $source, array $utm): array {
		$payload = $fields;
		if (empty($payload['name']) && ! empty($payload['full_name'])) {
			$payload['name'] = $payload['full_name'];
		}
		$payload['campaign'] = (string) $endpoint['campaign'];

		if ($source !== '' && empty($payload['source_page'])) {
			$payload['source_page'] = $source;
		}
		if ($utm) {
			$payload['utm_params'] = $utm;
		}

		// Drop empty values to keep the request tidy.
		return array_filter($payload, function ($v) { return $v !== null && $v !== ''; });
	}

	/**
	 * Stamp a row as a finished diagnostic: stage 3, and the resume token
	 * burned so a captured token cannot rewrite a settled lead. Called only
	 * once delivery has actually settled — sent, queued locally, or a known
	 * duplicate.
	 */
	private function finalizeStage(array $logRow): array {
		$logRow['stage']         = PartialLead::FINAL_STAGE;
		$logRow['token_hash']    = null;
		$logRow['token_expires'] = null;

		return $logRow;
	}

	/** A log row with every column the INSERT binds, ready to be overridden. */
	private function newLogRow(string $slug, string $source, string $ip): array {
		return [
			'slug'            => $slug,
			'status'          => 'pending',
			'pid'             => null,
			'http'            => null,
			'phash'           => null,
			'ehash'           => null,
			'provider'        => null,
			'consent_version' => null,
			'consent_at'      => null,
			'payload'         => null,
			'payload_enc'     => null,
			'response'        => null,
			'error'           => null,
			'ip'              => $ip,
			'ua'              => isset($_SERVER['HTTP_USER_AGENT']) ? mb_substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 255) : null,
			'sp'              => mb_substr($source, 0, 255),
			'att'             => 0,
			'stage'           => PartialLead::FINAL_STAGE,
			'token_hash'      => null,
			'token_expires'   => null,
		];
	}

	/**
	 * Public token endpoint used by lead-form.js on the published page to
	 * acquire a fresh CSRF token + submit URL for a given endpoint slug.
	 *
	 *   GET /index.php?module=plugins/lead-platform-connector/submit&action=token&slug=<slug>
	 *
	 * No session/auth — the slug must reference an active endpoint, and the
	 * issued token is bound to that slug (HMAC) so it can't be used elsewhere.
	 */
	function token() {
		$slug = isset($_GET['slug']) ? trim((string) $_GET['slug']) : '';
		if ($slug === '' || ! preg_match('/^[a-z0-9_-]{2,64}$/i', $slug)) {
			$this->json(400, ['ok' => false, 'message' => 'Identifiant de formulaire invalide.']);
		}

		$endpoint = $this->fetchEndpoint($slug);
		if (! $endpoint) {
			$this->json(404, ['ok' => false, 'message' => 'Formulaire inconnu.']);
		}

		// Same-origin headers; harmless on direct page-load fetches.
		header('Cache-Control: no-store');

		$this->json(200, [
			'ok'         => true,
			'csrf'       => CsrfToken::issue($slug),
			'submit_url' => '/index.php?module=plugins/lead-platform-connector/submit',
			'render_ts'  => (int) (microtime(true) * 1000),
		]);
	}

	function index() {
		$raw = file_get_contents('php://input');
		$req = json_decode($raw ?: '', true);

		if (! is_array($req)) {
			$this->json(400, ['ok' => false, 'message' => 'Requête invalide.']);
		}

		$slug = isset($req['endpoint']) ? trim((string) $req['endpoint']) : '';
		$csrf = isset($req['csrf'])     ? (string) $req['csrf']           : '';
		$fields = isset($req['fields']) && is_array($req['fields']) ? $req['fields'] : [];
		$utm    = isset($req['utm']) && is_array($req['utm'])       ? $req['utm']    : [];
		$source = isset($req['source_page']) ? (string) $req['source_page'] : '';
		$referer = isset($req['referrer']) ? (string) $req['referrer'] : '';

		if ($slug === '' || $csrf === '') {
			$this->json(400, ['ok' => false, 'message' => 'Formulaire ou jeton manquant.']);
		}

		if (! CsrfToken::verify($csrf, $slug)) {
			$this->json(419, ['ok' => false, 'message' => 'Le formulaire a expiré. Rechargez la page puis réessayez.']);
		}

		$endpoint = $this->fetchEndpoint($slug);
		if (! $endpoint) {
			$this->json(404, ['ok' => false, 'message' => 'Formulaire inconnu.']);
		}

		$origin  = $_SERVER['HTTP_ORIGIN']  ?? '';
		$header_referer = $_SERVER['HTTP_REFERER'] ?? '';
		if (! $this->originAllowed($endpoint['allowed_origins'] ?? null, $origin, $header_referer)) {
			$this->json(403, ['ok' => false, 'message' => 'Origine non autorisée.']);
		}

		$ip       = $this->clientIp();
		$rlKey    = $slug . '|' . $ip;
		$rlLimit  = (int) ($endpoint['rate_limit'] ?? 30);
		if ($rlLimit > 0 && ! $this->rateLimit($rlKey, $rlLimit, 60)) {
			$this->json(429, ['ok' => false, 'message' => 'Trop de demandes. Merci de réessayer plus tard.']);
		}

		// Staged intake: the diagnostic form posts the same endpoint three times.
		// A payload without a `stage` key is the historical single-shot
		// submission and falls through to the untouched full path below.
		$stageRequest = [];
		if (array_key_exists('stage', $req)) {
			$stageRequest['stage'] = $req['stage'];
		}
		if (array_key_exists('lead_token', $req)) {
			$stageRequest['lead_token'] = $req['lead_token'];
		}

		$staged = PartialLead::validate($stageRequest, function (string $hash) use ($slug) {
			return $this->findPartialByHash($hash, $slug);
		});

		if (! $staged['ok']) {
			$this->json((int) ($staged['http'] ?? 400), [
				'ok'      => false,
				'message' => $staged['error'] ?? 'Requête invalide.',
			]);
		}

		if ($staged['mode'] === 'insert') {
			$this->stageInsert($slug, $endpoint, $fields, $utm, $source, $ip);
		}

		if ($staged['mode'] === 'update') {
			$this->stageUpdate($endpoint, $staged['row'], (int) $staged['stage'], $fields, $utm, $source, $ip);
		}

		$privacy = PrivacyAcknowledgement::validate($fields);
		if (! $privacy['ok']) {
			$this->json(422, ['ok' => false, 'message' => $privacy['message']]);
		}
		$fields = $privacy['fields'];

		$consent = ProviderConsent::validate($fields, ['aifel' => 'provider-intro-v1']);
		if (! $consent['ok']) {
			$this->json(422, ['ok' => false, 'message' => $consent['message']]);
		}
		$consentAudit = $consent['audit'];
		if ($consentAudit) {
			$fields['provider_introduction_requested'] = '1';
			$fields['provider_slug'] = $consentAudit['provider_slug'];
			$fields['consent_text_version'] = $consentAudit['consent_text_version'];
			$fields['consent_timestamp'] = str_replace(' ', 'T', $consentAudit['consent_at']) . 'Z';
		} else {
			foreach (['provider_introduction_requested', 'provider_slug', 'consent_text_version', 'consent_timestamp'] as $field) {
				unset($fields[$field]);
			}
		}

		$deliveryMode = DeliveryMode::resolve($endpoint);
		if ($deliveryMode === DeliveryMode::MISCONFIGURED) {
			$this->json(500, ['ok' => false, 'message' => 'Le formulaire est temporairement indisponible.']);
		}
		// Named introductions stay in the confirmed local outbox for human
		// qualification and auditable routing. They are never forwarded directly
		// from a public form, even after a generic platform is configured.
		if ($consentAudit && $deliveryMode === DeliveryMode::FORWARD) {
			$deliveryMode = DeliveryMode::QUEUE;
		}

		$payload = $this->buildPayload($fields, $endpoint, $source, $utm);

		if ($deliveryMode === DeliveryMode::FORWARD) {
			try {
				$apiKey = Crypto::decrypt((string) $endpoint['api_key_enc']);
			} catch (\Throwable $e) {
				$this->json(500, ['ok' => false, 'message' => 'Le formulaire est temporairement indisponible.']);
			}
			$result = LeadClient::send((string) $endpoint['platform_url'], $apiKey, $payload, 8);
		} else {
			$result = ['ok' => false, 'http' => null, 'error' => null, 'raw' => null];
		}

		[$phoneVal, $emailVal] = $this->detectPii($payload);
		$payloadForLog = $this->stripPii($payload);

		$logRow = [
			'slug'     => $slug,
			'status'   => $result['ok'] ? 'sent' : 'failed',
			'pid'      => is_array($result['data'] ?? null) ? ($result['data']['id'] ?? null) : null,
			'http'     => $result['http'] ?? null,
			'phash'    => $phoneVal ? hash('sha256', $phoneVal) : null,
			'ehash'    => $emailVal ? hash('sha256', strtolower($emailVal)) : null,
			'provider' => $consentAudit['provider_slug'] ?? null,
			'consent_version' => $consentAudit['consent_text_version'] ?? null,
			'consent_at' => $consentAudit['consent_at'] ?? null,
			'payload'  => json_encode($payloadForLog),
			'payload_enc' => null,
			'response' => isset($result['raw']) ? mb_substr((string) $result['raw'], 0, 4000) : null,
			'error'    => $result['error'] ?? null,
			'ip'       => $ip,
			'ua'       => isset($_SERVER['HTTP_USER_AGENT']) ? mb_substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 255) : null,
			'sp'       => mb_substr($source, 0, 255),
			'att'      => 1,
			'stage'    => PartialLead::FINAL_STAGE,
			'token_hash'    => null,
			'token_expires' => null,
		];

		if ($deliveryMode === DeliveryMode::QUEUE) {
			try {
				$logRow['payload_enc'] = Crypto::encrypt((string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
			} catch (\Throwable $e) {
				$this->json(500, ['ok' => false, 'message' => 'La file sécurisée est temporairement indisponible.']);
			}
			$logRow['status'] = 'pending';
			$logRow['att'] = 0;
			if (! $this->logSubmission($logRow)) {
				$this->json(503, ['ok' => false, 'message' => 'La demande n’a pas pu être enregistrée. Merci de réessayer.']);
			}
			$this->json(200, ['ok' => true, 'queued' => true]);
		}

		if ($result['ok']) {
			$this->logSubmission($logRow);
			$this->json(200, ['ok' => true]);
		}

		// Mapped errors from the platform
		$http = (int) ($result['http'] ?? 0);
		$serverMsg = is_array($result['data'] ?? null) ? ($result['data']['error'] ?? null) : null;

		if ($http === 409) {
			$logRow['status'] = 'duplicate';
			$this->logSubmission($logRow);
			$this->json(200, ['ok' => true, 'duplicate' => true]);
		}

		if ($http === 422) {
			$this->logSubmission($logRow);
			$this->json(422, ['ok' => false, 'message' => $serverMsg ?: 'Vérifiez les champs du formulaire puis réessayez.']);
		}

		if ($http === 429) {
			$this->logSubmission($logRow);
			$this->json(429, ['ok' => false, 'message' => 'Le service de traitement est temporairement saturé. Réessayez dans un instant.']);
		}

		if ($http >= 400 && $http < 500) {
			$this->logSubmission($logRow);
			$this->json(502, ['ok' => false, 'message' => 'Le service de traitement a refusé la demande. Contactez-nous directement.']);
		}

		// 5xx / network: persist for retry, treat as soft success so user is not blocked.
		$logRow['status'] = 'pending';
		try {
			$logRow['payload_enc'] = Crypto::encrypt((string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
		} catch (\Throwable $e) {
			$this->json(503, ['ok' => false, 'message' => 'La demande n’a pas pu être mise en attente. Merci de réessayer.']);
		}
		if (! $this->logSubmission($logRow)) {
			$this->json(503, ['ok' => false, 'message' => 'La demande n’a pas pu être enregistrée. Merci de réessayer.']);
		}
		$this->json(200, ['ok' => true, 'queued' => true]);
	}

	/**
	 * Stage 1 — persist the contact block at once and hand back a resume token.
	 *
	 * Nothing is forwarded here: an abandoned diagnostic still leaves a usable
	 * lead in the local queue, and the flush job decides what becomes of it
	 * once the token has aged out.
	 */
	private function stageInsert(string $slug, array $endpoint, array $fields, array $utm, string $source, string $ip): void {
		$privacy = PrivacyAcknowledgement::validate($fields);
		if (! $privacy['ok']) {
			$this->json(422, ['ok' => false, 'message' => $privacy['message']]);
		}

		// Consent for a named introduction is collected at step 3 and validated
		// there. Anything claiming it this early is dropped, never trusted.
		$working = $privacy['fields'];
		foreach (['provider_introduction_requested', 'provider_slug', 'consent_text_version', 'consent_timestamp'] as $field) {
			unset($working[$field]);
		}

		$working = $this->buildPayload($working, $endpoint, $source, $utm);
		// The acknowledgement stays inside the encrypted payload so later steps
		// can merge against it; it is stripped again before delivery.
		$working['privacy_acknowledgement'] = '1';

		$token = PartialLead::issueToken();
		[$phoneVal, $emailVal] = $this->detectPii($working);

		$logRow                  = $this->newLogRow($slug, $source, $ip);
		$logRow['stage']         = PartialLead::FIRST_STAGE;
		$logRow['phash']         = $phoneVal ? hash('sha256', $phoneVal) : null;
		$logRow['ehash']         = $emailVal ? hash('sha256', strtolower($emailVal)) : null;
		$logRow['payload']       = json_encode($this->stripPii($working));
		$logRow['token_hash']    = $token['hash'];
		$logRow['token_expires'] = $token['expires_at'];

		try {
			$logRow['payload_enc'] = Crypto::encrypt((string) json_encode($working, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
		} catch (\Throwable $e) {
			$this->json(500, ['ok' => false, 'message' => 'La file sécurisée est temporairement indisponible.']);
		}

		if (! $this->logSubmission($logRow)) {
			$this->json(503, ['ok' => false, 'message' => 'La demande n’a pas pu être enregistrée. Merci de réessayer.']);
		}

		$this->json(200, ['ok' => true, 'lead_token' => $token['token']]);
	}

	/**
	 * Stages 2 and 3 — merge the new answers into the row the token points at.
	 *
	 * Stage 2 rewrites the encrypted payload and keeps the token live. Stage 3
	 * finalises: the token is cleared, and only then may the lead be forwarded
	 * to an external platform.
	 */
	private function stageUpdate(array $endpoint, array $row, int $stage, array $fields, array $utm, string $source, string $ip): void {
		// Fail closed. Without the earlier answers there is nothing to merge
		// into, and starting from an empty base would quietly drop them.
		$stored    = null;
		$storedEnc = (string) ($row['payload_enc'] ?? '');
		if ($storedEnc !== '') {
			try {
				$decoded = json_decode(Crypto::decrypt($storedEnc), true);
				if (is_array($decoded)) {
					$stored = $decoded;
				}
			} catch (\Throwable $e) {
				$stored = null;
			}
		}
		if ($stored === null) {
			$this->json(500, ['ok' => false, 'message' => 'Le formulaire est temporairement indisponible.']);
		}

		$merged = PartialLead::merge($stored, $fields);

		$privacy = PrivacyAcknowledgement::validate($merged);
		if (! $privacy['ok']) {
			$this->json(422, ['ok' => false, 'message' => $privacy['message']]);
		}

		// Consent is validated at the step that submits it — and re-validated
		// on the merged payload every time it is still being claimed.
		$consentAudit = null;
		if (array_key_exists('provider_introduction_requested', $merged)) {
			$consent = ProviderConsent::validate($merged, ['aifel' => 'provider-intro-v1']);
			if (! $consent['ok']) {
				$this->json(422, ['ok' => false, 'message' => $consent['message']]);
			}
			$consentAudit = $consent['audit'];
		}

		// A consent already on file keeps its original date: re-posting the
		// same provider and the same version at a later step re-affirms it, it
		// does not re-date it. Withdrawal still clears the columns below.
		$priorConsentAt = trim((string) ($row['consent_at'] ?? ''));
		if ($consentAudit && $priorConsentAt !== ''
			&& strtolower(trim((string) ($row['provider_slug'] ?? ''))) === $consentAudit['provider_slug']
			&& hash_equals(trim((string) ($row['consent_text_version'] ?? '')), $consentAudit['consent_text_version'])) {
			$consentAudit['consent_at'] = $priorConsentAt;
		}

		if ($consentAudit) {
			$merged['provider_introduction_requested'] = '1';
			$merged['provider_slug'] = $consentAudit['provider_slug'];
			$merged['consent_text_version'] = $consentAudit['consent_text_version'];
			$merged['consent_timestamp'] = str_replace(' ', 'T', $consentAudit['consent_at']) . 'Z';
		} else {
			foreach (['provider_introduction_requested', 'provider_slug', 'consent_text_version', 'consent_timestamp'] as $field) {
				unset($merged[$field]);
			}
		}

		// The stage a client asks for may only move forward, never past the
		// last one: the queue's "incomplete" flag has to stay trustworthy.
		$storedStage    = (int) ($row['stage'] ?? PartialLead::FIRST_STAGE);
		$effectiveStage = min(PartialLead::FINAL_STAGE, max($storedStage, $stage));
		$isFinal        = ($effectiveStage >= PartialLead::FINAL_STAGE);

		// Two shapes of the same answers. What would be delivered has the
		// acknowledgement stripped, exactly like the full path; what is stored
		// while the row is still resumable keeps it, because merge() at the
		// next step has nothing else to restore it from — store the delivery
		// shape on a row that can still come back and the next attempt would
		// be rejected for a missing acknowledgement.
		$deliverPayload = $this->buildPayload($merged, $endpoint, $source, $utm);
		unset($deliverPayload['privacy_acknowledgement']);
		$resumePayload  = $deliverPayload;
		$resumePayload['privacy_acknowledgement'] = '1';

		$payload = $isFinal ? $deliverPayload : $resumePayload;

		[$phoneVal, $emailVal] = $this->detectPii($payload);

		$logRow = $this->newLogRow((string) ($row['endpoint_slug'] ?? ''), $source, $ip);
		// The UPDATE rewrites neither the endpoint nor the stage-1 client
		// identity, so those entries would never be bound.
		unset($logRow['slug'], $logRow['ip'], $logRow['ua']);
		$logRow['id']              = (int) $row['lead_submission_id'];
		$logRow['phash']           = $phoneVal ? hash('sha256', $phoneVal) : null;
		$logRow['ehash']           = $emailVal ? hash('sha256', strtolower($emailVal)) : null;
		$logRow['provider']        = $consentAudit['provider_slug'] ?? null;
		$logRow['consent_version'] = $consentAudit['consent_text_version'] ?? null;
		$logRow['consent_at']      = $consentAudit['consent_at'] ?? null;
		$logRow['payload']         = json_encode($this->stripPii($payload));
		$logRow['sp']              = $source !== ''
			? mb_substr($source, 0, 255)
			: (isset($row['source_page']) ? (string) $row['source_page'] : null);
		// Until delivery actually settles the lead the row keeps its previous
		// stage and its live token, so a visitor told to retry really can.
		// finalizeStage() below stamps stage 3 and burns the token, and only
		// the branches that did settle the lead call it.
		$logRow['stage']         = $isFinal ? $storedStage : $effectiveStage;
		$logRow['token_hash']    = $row['lead_token_hash'] ?? null;
		$logRow['token_expires'] = $row['lead_token_expires_at'] ?? null;

		$encrypt = function (array $toStore) {
			try {
				return Crypto::encrypt((string) json_encode($toStore, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
			} catch (\Throwable $e) {
				$this->json(500, ['ok' => false, 'message' => 'La file sécurisée est temporairement indisponible.']);
			}
		};

		// Resumable by default: only the branches that settle the lead below
		// swap in the delivery shape or drop the copy altogether.
		$logRow['payload_enc'] = $encrypt($resumePayload);

		if (! $isFinal) {
			$logRow['status'] = 'pending';
			$logRow['att']    = 0;
			if (! $this->updateSubmission($logRow)) {
				$this->json(503, ['ok' => false, 'message' => 'La demande n’a pas pu être enregistrée. Merci de réessayer.']);
			}
			$this->json(200, ['ok' => true]);
		}

		$deliveryMode = DeliveryMode::resolve($endpoint);
		if ($deliveryMode === DeliveryMode::MISCONFIGURED) {
			$this->json(500, ['ok' => false, 'message' => 'Le formulaire est temporairement indisponible.']);
		}
		// Named introductions stay in the confirmed local outbox for human
		// qualification and auditable routing, exactly as on the full path.
		if ($consentAudit && $deliveryMode === DeliveryMode::FORWARD) {
			$deliveryMode = DeliveryMode::QUEUE;
		}

		if ($deliveryMode === DeliveryMode::FORWARD) {
			try {
				$apiKey = Crypto::decrypt((string) $endpoint['api_key_enc']);
			} catch (\Throwable $e) {
				$this->json(500, ['ok' => false, 'message' => 'Le formulaire est temporairement indisponible.']);
			}
			$result = LeadClient::send((string) $endpoint['platform_url'], $apiKey, $payload, 8);
		} else {
			$result = ['ok' => false, 'http' => null, 'error' => null, 'raw' => null];
		}

		$logRow['status']   = $result['ok'] ? 'sent' : 'failed';
		$logRow['pid']      = is_array($result['data'] ?? null) ? ($result['data']['id'] ?? null) : null;
		$logRow['http']     = $result['http'] ?? null;
		$logRow['response'] = isset($result['raw']) ? mb_substr((string) $result['raw'], 0, 4000) : null;
		$logRow['error']    = $result['error'] ?? null;
		$logRow['att']      = 1;

		if ($deliveryMode === DeliveryMode::QUEUE) {
			// Held locally on purpose — the diagnostic itself is complete, so
			// the stored copy takes the delivery shape.
			$logRow                = $this->finalizeStage($logRow);
			$logRow['payload_enc'] = $encrypt($deliverPayload);
			$logRow['status']      = 'pending';
			$logRow['att']         = 0;
			if (! $this->updateSubmission($logRow)) {
				$this->json(503, ['ok' => false, 'message' => 'La demande n’a pas pu être enregistrée. Merci de réessayer.']);
			}
			$this->json(200, ['ok' => true, 'queued' => true]);
		}

		if ($result['ok']) {
			// Delivered: the encrypted copy has no further purpose.
			$logRow                = $this->finalizeStage($logRow);
			$logRow['payload_enc'] = null;
			$this->updateSubmission($logRow);
			$this->json(200, ['ok' => true]);
		}

		$http      = (int) ($result['http'] ?? 0);
		$serverMsg = is_array($result['data'] ?? null) ? ($result['data']['error'] ?? null) : null;

		if ($http === 409) {
			$logRow                = $this->finalizeStage($logRow);
			$logRow['status']      = 'duplicate';
			$logRow['payload_enc'] = null;
			$this->updateSubmission($logRow);
			$this->json(200, ['ok' => true, 'duplicate' => true]);
		}

		// Every remaining branch keeps the encrypted payload, the previous
		// stage and the live token: unlike a one-shot submission this row
		// already held the visitor's answers, and « réessayez » has to be true
		// — a retry must still find its session.
		if ($http === 422) {
			$this->updateSubmission($logRow);
			$this->json(422, ['ok' => false, 'message' => $serverMsg ?: 'Vérifiez les champs du formulaire puis réessayez.']);
		}

		if ($http === 429) {
			$this->updateSubmission($logRow);
			$this->json(429, ['ok' => false, 'message' => 'Le service de traitement est temporairement saturé. Réessayez dans un instant.']);
		}

		if ($http >= 400 && $http < 500) {
			$this->updateSubmission($logRow);
			$this->json(502, ['ok' => false, 'message' => 'Le service de traitement a refusé la demande. Contactez-nous directement.']);
		}

		// 5xx / network: keep it pending for retry, soft success for the
		// visitor. The lead never reached the platform, so the row stays
		// resumable and the flush job will finalise it after 24 h.
		$logRow['status'] = 'pending';
		if (! $this->updateSubmission($logRow)) {
			$this->json(503, ['ok' => false, 'message' => 'La demande n’a pas pu être enregistrée. Merci de réessayer.']);
		}
		$this->json(200, ['ok' => true, 'queued' => true]);
	}
}
