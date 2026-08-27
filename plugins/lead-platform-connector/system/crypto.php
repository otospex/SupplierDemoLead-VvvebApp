<?php

namespace Vvveb\Plugins\LeadPlatformConnector\System;

if (! defined('V_VERSION')) {
	die('Invalid request!');
}

class Crypto {

	/**
	 * The per-installation secret every plugin HMAC/cipher key derives from.
	 *
	 * Vvveb defines neither SECRET nor AUTH_KEY, so in practice this is the
	 * random key file generated once under storage/ (git-ignored, 0600). It is
	 * shared with CsrfToken so the lead-form token cannot fall back to a
	 * hard-coded string that anyone reading the public repo could reproduce.
	 */
	public static function secret(): string {
		$secret = defined('SECRET') ? SECRET : (defined('AUTH_KEY') ? AUTH_KEY : '');

		if (! $secret) {
			$file = DIR_ROOT . 'storage/lead-platform-connector.key';

			if (! is_file($file)) {
				$dir = dirname($file);
				if (! is_dir($dir)) {
					@mkdir($dir, 0750, true);
				}
				@file_put_contents($file, base64_encode(random_bytes(32)));
				@chmod($file, 0600);
			}
			$secret = (string) @file_get_contents($file);
		}

		if ($secret === '') {
			throw new \RuntimeException('Lead Platform Connector secret unavailable: storage/ is not writable');
		}

		return $secret;
	}

	private static function key(): string {
		return hash('sha256', self::secret(), true);
	}

	public static function encrypt(string $plaintext): string {
		$iv     = random_bytes(12);
		$tag    = '';
		$cipher = openssl_encrypt($plaintext, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);

		if ($cipher === false) {
			throw new \RuntimeException('Encryption failed');
		}

		return 'v1:' . base64_encode($iv . $tag . $cipher);
	}

	public static function decrypt(string $payload): string {
		if (strncmp($payload, 'v1:', 3) !== 0) {
			throw new \RuntimeException('Unknown ciphertext format');
		}
		$raw = base64_decode(substr($payload, 3), true);

		if ($raw === false || strlen($raw) < 28) {
			throw new \RuntimeException('Invalid ciphertext');
		}
		$iv     = substr($raw, 0, 12);
		$tag    = substr($raw, 12, 16);
		$cipher = substr($raw, 28);
		$plain  = openssl_decrypt($cipher, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);

		if ($plain === false) {
			throw new \RuntimeException('Decryption failed');
		}

		return $plain;
	}

	public static function maskKey(string $apiKey): string {
		$len = strlen($apiKey);
		if ($len <= 8) {
			return str_repeat('•', max(0, $len));
		}
		return substr($apiKey, 0, 3) . str_repeat('•', 6) . substr($apiKey, -4);
	}
}
