<?php

namespace Vvveb\Plugins\LeadPlatformConnector\System;

if (! defined('V_VERSION')) {
	die('Invalid request!');
}

class ProviderConsent {

	private const PROVIDER_FIELDS = [
		'provider_introduction_requested',
		'provider_slug',
		'consent_text_version',
		'consent_timestamp',
	];

	public static function validate(array $fields, array $allowedProviders): array {
		$requested = $fields['provider_introduction_requested'] ?? null;
		$hasProviderData = false;

		foreach (self::PROVIDER_FIELDS as $field) {
			if (isset($fields[$field]) && trim((string) $fields[$field]) !== '') {
				$hasProviderData = true;
				break;
			}
		}

		if ($requested !== '1') {
			if ($hasProviderData) {
				return self::error('Une mise en relation exige un consentement explicite.');
			}

			return ['ok' => true, 'audit' => null, 'message' => null];
		}

		$provider = strtolower(trim((string) ($fields['provider_slug'] ?? '')));
		$allowed = array_map(function ($slug) {
			return strtolower(trim((string) $slug));
		}, $allowedProviders);

		if ($provider === '' || ! in_array($provider, $allowed, true)) {
			return self::error('Le fournisseur demandé n’est pas autorisé.');
		}

		$version = trim((string) ($fields['consent_text_version'] ?? ''));
		if ($version === '' || ! preg_match('/^[a-z0-9][a-z0-9._-]{2,63}$/i', $version)) {
			return self::error('La version du consentement est manquante ou invalide.');
		}

		$timestamp = trim((string) ($fields['consent_timestamp'] ?? ''));
		try {
			$date = new \DateTimeImmutable($timestamp);
		} catch (\Throwable $e) {
			return self::error('La date du consentement est invalide.');
		}

		if ($timestamp === '' || strpos($timestamp, 'T') === false) {
			return self::error('La date du consentement est invalide.');
		}

		return [
			'ok' => true,
			'message' => null,
			'audit' => [
				'provider_slug' => $provider,
				'consent_text_version' => $version,
				'consent_at' => $date->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
			],
		];
	}

	private static function error(string $message): array {
		return ['ok' => false, 'audit' => null, 'message' => $message];
	}
}
