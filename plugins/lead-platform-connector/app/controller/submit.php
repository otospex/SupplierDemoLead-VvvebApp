<?php

namespace Vvveb\Plugins\LeadPlatformConnector\Controller;

use Vvveb\System\Core\Request;
use Vvveb\Plugins\LeadPlatformConnector\System\Crypto;
use Vvveb\Plugins\LeadPlatformConnector\System\CsrfToken;
use Vvveb\Plugins\LeadPlatformConnector\System\DeliveryMode;
use Vvveb\Plugins\LeadPlatformConnector\System\LeadClient;
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
				  client_ip, user_agent, source_page, attempts, created_at, updated_at)
				 VALUES
				 (:slug, :status, :pid, :http, :phash, :ehash, :provider, :consent_version,
				  :consent_at, :payload, :payload_enc, :response, :error, :ip, :ua, :sp, :att,
				  CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)',
				$row
			);
			return $result !== false;
		} catch (\Throwable $e) {
			return false;
		}
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

		// Forward form fields verbatim. The platform's campaign-level field_schema.mappings
		// translates source field names → platform field names server-side.
		// (The plugin's field_map is only used by the editor to auto-generate the form.)
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
		$payload = array_filter($payload, function ($v) { return $v !== null && $v !== ''; });

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

		// Best-effort PII detection for hashing in audit log.
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

		// Strip raw PII fields from the stored payload.
		$payloadForLog = $payload;
		foreach ($payloadForLog as $k => $v) {
			$lk = strtolower((string) $k);
			if (str_contains($lk, 'phone') || str_contains($lk, 'telephone') || str_contains($lk, 'mobile') || str_contains($lk, 'email')) {
				unset($payloadForLog[$k]);
			}
		}
		foreach (['provider_introduction_requested', 'provider_slug', 'consent_text_version', 'consent_timestamp'] as $field) {
			unset($payloadForLog[$field]);
		}

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
}
