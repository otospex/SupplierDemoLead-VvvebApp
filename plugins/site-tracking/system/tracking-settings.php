<?php

namespace Vvveb\Plugins\SiteTracking\System;

use Vvveb\System\Cache;
use Vvveb\System\Db;

if (! defined('V_VERSION')) {
	die('Invalid request!');
}

/**
 * Key/value storage in Vvveb's `setting` table (namespace "site-tracking"),
 * per site, cached with the app cache and invalidated on save.
 */
final class TrackingSettings {
	public const NAMESPACE = 'site-tracking';

	public const KEYS = ['matomo_url', 'matomo_site_id', 'matomo_goal_id', 'head_scripts', 'marketing_scripts', 'consent_text'];

	private static function siteId(): int {
		return defined('SITE_ID') ? (int) SITE_ID : 1;
	}

	private static function cacheKey(int $siteId): string {
		return self::NAMESPACE . '.' . $siteId;
	}

	public static function load($db = null, ?int $siteId = null): array {
		$siteId = $siteId ?? self::siteId();
		$read   = static function () use ($db, $siteId): array {
			$db     = $db ?? Db::getInstance();
			$quote  = $db->quote;
			$statement = $db->execute("SELECT {$quote}key{$quote} AS k, value FROM setting WHERE site_id = :site_id AND namespace = :ns", ['site_id' => $siteId, 'ns' => self::NAMESPACE]);
			$out    = array_fill_keys(self::KEYS, '');
			$rows   = $db->fetchAll($statement);
			$rows   = is_array($rows) ? $rows : [];
			foreach ($rows as $row) {
				$k = (string) ($row['k'] ?? '');
				if (in_array($k, self::KEYS, true)) {
					$out[$k] = (string) ($row['value'] ?? '');
				}
			}

			return $out;
		};
		if ($db !== null || ! class_exists(Cache::class)) {
			return $read();
		}

		return Cache::getInstance()->cache(APP, self::cacheKey($siteId), $read, 86400) ?: array_fill_keys(self::KEYS, '');
	}

	/** Validates, stores and clears caches. Returns the stored values. */
	public static function save(array $input, $db = null, ?int $siteId = null): array {
		$siteId = $siteId ?? self::siteId();
		$db     = $db ?? Db::getInstance();
		$quote  = $db->quote;
		$clean  = [];
		foreach (self::KEYS as $key) {
			$value = trim((string) ($input[$key] ?? ''));
			if ($key === 'matomo_url' && $value !== '') {
				$value = Tracking::matomoUrl(['matomo_url' => $value]);
			}
			if (in_array($key, ['matomo_site_id', 'matomo_goal_id'], true) && $value !== '' && ! preg_match('/^\d{1,9}$/', $value)) {
				$value = '';
			}
			$clean[$key] = $value;
			$params = ['site_id' => $siteId, 'ns' => self::NAMESPACE, 'k' => $key, 'v' => $value];
			$db->execute("DELETE FROM setting WHERE site_id = :site_id AND namespace = :ns AND {$quote}key{$quote} = :k", ['site_id' => $siteId, 'ns' => self::NAMESPACE, 'k' => $key]);
			if ($value !== '') {
				$db->execute("INSERT INTO setting (site_id, namespace, {$quote}key{$quote}, value) VALUES (:site_id, :ns, :k, :v)", $params);
			}
		}
		if (class_exists(Cache::class)) {
			foreach (['app', 'admin'] as $ns) {
				Cache::getInstance()->delete($ns, self::cacheKey($siteId));
			}
		}

		return $clean;
	}
}
