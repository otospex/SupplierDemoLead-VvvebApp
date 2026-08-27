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

	public static function validate(array $fields, array $allowedProviders, ?\DateTimeImmutable $receivedAt = null): array {
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
		$allowed = [];
		foreach ($allowedProviders as $slug => $version) {
			$allowed[strtolower(trim((string) $slug))] = trim((string) $version);
		}

		if ($provider === '' || ! array_key_exists($provider, $allowed)) {
			return self::error('Le fournisseur demandé n’est pas autorisé.');
		}

		$version = trim((string) ($fields['consent_text_version'] ?? ''));
		if ($version === '' || ! hash_equals($allowed[$provider], $version)) {
			return self::error('La version du consentement est manquante ou invalide.');
		}

		$date = ($receivedAt ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
			->setTimezone(new \DateTimeZone('UTC'));

		return [
			'ok' => true,
			'message' => null,
			'audit' => [
				'provider_slug' => $provider,
				'consent_text_version' => $version,
				'consent_at' => $date->format('Y-m-d H:i:s'),
			],
		];
	}

	private static function error(string $message): array {
		return ['ok' => false, 'audit' => null, 'message' => $message];
	}
}
