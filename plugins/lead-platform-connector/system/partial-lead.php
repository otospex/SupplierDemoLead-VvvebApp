<?php

namespace Vvveb\Plugins\LeadPlatformConnector\System;

if (! defined('V_VERSION')) {
	die('Invalid request!');
}

/**
 * Staged ("partial save") intake for the diagnostic form.
 *
 * The three-step diagnostic posts to the same submit endpoint three times.
 * Step 1 stores a usable contact lead immediately and hands back a one-off
 * token; steps 2 and 3 present that token to merge more fields into the same
 * encrypted row. Everything here is pure: no DB, no HTTP — the caller injects
 * the row lookup so the controller keeps ownership of SQL and of the response.
 */
final class PartialLead {

	/** Tokens are single-session credentials: 24 h, matching the spec. */
	public const TTL_HOURS = 24;

	public const FIRST_STAGE = 1;

	public const FINAL_STAGE = 3;

	/** Consent fields that must not survive a merge without an explicit request. */
	private const PROVIDER_FIELDS = [
		'provider_slug',
		'consent_text_version',
		'consent_timestamp',
	];

	public const EXPIRED_MESSAGE = 'Votre session de diagnostic a expiré. Recommencez, vos informations n’ont pas été perdues côté serveur.';

	public const MISSING_TOKEN_MESSAGE = 'Formulaire ou jeton manquant.';

	public const INVALID_STAGE_MESSAGE = 'Étape de formulaire invalide.';

	/**
	 * Mint a resume token. Only the sha256 hash is ever persisted, so a stolen
	 * database row cannot be replayed against the form.
	 *
	 * @return array{token:string, hash:string, expires_at:string}
	 */
	public static function issueToken(): array {
		$token = bin2hex(random_bytes(32));

		$expiresAt = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
			->modify('+' . self::TTL_HOURS . ' hours')
			->format('Y-m-d H:i:s');

		return [
			'token'      => $token,
			'hash'       => hash('sha256', $token),
			'expires_at' => $expiresAt,
		];
	}

	/**
	 * Decide which write the request asks for.
	 *
	 * No `stage` key at all ⇒ `full`, the historical single-shot submission —
	 * $findByHash is never consulted. `stage = 1` ⇒ `insert`. Stages 2 and 3
	 * require a live token; unknown or expired ⇒ ok=false with http 410.
	 *
	 * @param callable(string):(array|null) $findByHash receives the sha256 hash
	 * @return array{ok:bool, mode:string, row?:array, error?:string, http?:int}
	 */
	public static function validate(array $payload, callable $findByHash): array {
		if (! array_key_exists('stage', $payload)) {
			return ['ok' => true, 'mode' => 'full'];
		}

		$rawStage = $payload['stage'];
		if (! is_int($rawStage) && ! (is_string($rawStage) && preg_match('/^\d+$/', $rawStage) === 1)) {
			return self::error('full', 400, self::INVALID_STAGE_MESSAGE);
		}
		$stage = (int) $rawStage;

		if ($stage === self::FIRST_STAGE) {
			return ['ok' => true, 'mode' => 'insert'];
		}

		if ($stage < self::FIRST_STAGE || $stage > self::FINAL_STAGE) {
			return self::error('full', 400, self::INVALID_STAGE_MESSAGE);
		}

		// isset() rather than array_key_exists(): a null token is as absent as
		// a missing key, and neither may reach the hash call as a warning.
		$token = isset($payload['lead_token']) ? trim((string) $payload['lead_token']) : '';
		if (preg_match('/^[0-9a-f]{64}$/', $token) !== 1) {
			return self::error('update', 400, self::MISSING_TOKEN_MESSAGE);
		}

		$row = $findByHash(hash('sha256', $token));
		if (! is_array($row) || ! $row) {
			return self::error('update', 410, self::EXPIRED_MESSAGE);
		}

		if (self::isExpired($row['lead_token_expires_at'] ?? null)) {
			return self::error('update', 410, self::EXPIRED_MESSAGE);
		}

		return ['ok' => true, 'mode' => 'update', 'row' => $row, 'stage' => $stage];
	}

	/**
	 * Fold a later step's fields into what the earlier steps already stored.
	 *
	 * New values win, untouched keys survive. Two exceptions carry legal
	 * weight: the privacy acknowledgement, once given, cannot be revoked by a
	 * later payload that omits or negates it; and named-introduction consent
	 * data is dropped the moment the merged result stops asking for one, so a
	 * stale provider slug can never outlive the consent that justified it.
	 */
	public static function merge(array $existingFields, array $newFields): array {
		$acknowledged = ($existingFields['privacy_acknowledgement'] ?? null) === '1';

		$merged = array_merge($existingFields, $newFields);

		if ($acknowledged) {
			$merged['privacy_acknowledgement'] = '1';
		}

		if (($merged['provider_introduction_requested'] ?? null) !== '1') {
			foreach (self::PROVIDER_FIELDS as $field) {
				unset($merged[$field]);
			}
		}

		return $merged;
	}

	private static function isExpired($expiresAt): bool {
		$value = trim((string) ($expiresAt ?? ''));
		if ($value === '') {
			return true;
		}

		$expiry = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value, new \DateTimeZone('UTC'));
		if (! $expiry instanceof \DateTimeImmutable) {
			return true;
		}

		return $expiry->getTimestamp() < time();
	}

	private static function error(string $mode, int $http, string $message): array {
		return ['ok' => false, 'mode' => $mode, 'http' => $http, 'error' => $message];
	}
}
