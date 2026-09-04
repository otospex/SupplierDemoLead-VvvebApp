<?php

namespace Vvveb\Plugins\LeadPlatformConnector\System;

if (! defined('V_VERSION')) {
	die('Invalid request!');
}

final class DeliveryMode {

	public const QUEUE = 'queue';
	public const FORWARD = 'forward';
	public const MISCONFIGURED = 'misconfigured';

	public static function resolve(array $endpoint): string {
		$url = trim((string) ($endpoint['platform_url'] ?? ''));
		$key = trim((string) ($endpoint['api_key_enc'] ?? ''));

		if ($url === '' && $key === '') {
			return self::QUEUE;
		}

		if ($url !== '' && $key !== '') {
			return self::FORWARD;
		}

		return self::MISCONFIGURED;
	}
}
