<?php

namespace Vvveb\Plugins\LeadPlatformConnector\System;

if (! defined('V_VERSION')) {
	die('Invalid request!');
}

/**
 * The intake honeypot, server side.
 *
 * Every form renders a visually-hidden decoy input that no human ever fills.
 * The browser runtimes already refuse to post when it has a value, and their
 * serializer excludes the field entirely — so a request that carries it with
 * anything in it did not come from the script. Enforcing the same rule here is
 * what makes the honeypot worth having: a bot that posts the endpoint directly
 * would otherwise sail straight past it.
 *
 * Pure by design (no DB, no response): the controller decides what to answer.
 */
final class Honeypot {

	/** Must match HONEYPOT in the form runtimes and the component's default. */
	public const FIELD = 'company_website';

	public static function tripped(array $fields): bool {
		if (! array_key_exists(self::FIELD, $fields)) {
			return false;
		}

		$value = $fields[self::FIELD];

		if (is_array($value)) {
			foreach ($value as $entry) {
				if (self::filled($entry)) {
					return true;
				}
			}

			return false;
		}

		return self::filled($value);
	}

	/**
	 * Drop the decoy from an otherwise-legitimate payload. An empty decoy is
	 * harmless but meaningless, and it must never be stored or forwarded as if
	 * it were an answer somebody gave.
	 */
	public static function strip(array $fields): array {
		unset($fields[self::FIELD]);

		return $fields;
	}

	/**
	 * Mirrors the runtimes' `if (hp && hp.value)`: any non-empty string counts,
	 * whitespace included. Deliberately no trim — trimming here would accept a
	 * payload the browser would have refused.
	 */
	private static function filled($value): bool {
		if ($value === null || $value === false || is_array($value)) {
			return false;
		}

		return (string) $value !== '';
	}
}
