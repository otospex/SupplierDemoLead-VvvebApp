<?php

namespace Vvveb\Plugins\LeadPlatformConnector\System;

if (! defined('V_VERSION')) {
	die('Invalid request!');
}

class CsrfToken {

	const TTL = 1800; // 30 min

	private static function secret(): string {
		$secret = defined('SECRET') ? SECRET : (defined('AUTH_KEY') ? AUTH_KEY : 'lead-connector-fallback');
		return hash('sha256', 'lpc-csrf|' . $secret, true);
	}

	public static function issue(string $endpointSlug): string {
		$nonce = bin2hex(random_bytes(8));
		$ts    = time();
		$body  = "$endpointSlug|$nonce|$ts";
		$sig   = hash_hmac('sha256', $body, self::secret());

		return base64_encode($body . '|' . $sig);
	}

	public static function verify(string $token, string $endpointSlug): bool {
		$decoded = base64_decode($token, true);
		if ($decoded === false) {
			return false;
		}
		$parts = explode('|', $decoded);
		if (count($parts) !== 4) {
			return false;
		}
		[$slug, $nonce, $ts, $sig] = $parts;

		if (! hash_equals($endpointSlug, $slug)) {
			return false;
		}
		if (abs(time() - (int) $ts) > self::TTL) {
			return false;
		}

		$expected = hash_hmac('sha256', "$slug|$nonce|$ts", self::secret());

		return hash_equals($expected, $sig);
	}
}
