<?php

namespace Vvveb\Plugins\LeadPlatformConnector\System;

use Vvveb\System\Db;

if (! defined('V_VERSION')) {
	die('Invalid request!');
}

class Repo {

	public static function db() {
		return Db::getInstance();
	}

	public static function exec(string $sql, array $params = []) {
		return self::db()->execute($sql, $params);
	}

	public static function one(string $sql, array $params = []): ?array {
		$stmt = self::db()->execute($sql, $params);
		if (! $stmt) {
			return null;
		}
		$row = self::db()->fetchArray($stmt);
		return is_array($row) ? $row : null;
	}

	public static function many(string $sql, array $params = []): array {
		$stmt = self::db()->execute($sql, $params);
		if (! $stmt) {
			return [];
		}
		$rows = self::db()->fetchAll($stmt);
		return is_array($rows) ? $rows : [];
	}

	public static function nowSql(): string {
		// Engine-portable "now" — works on pgsql, mysqli, sqlite.
		return 'CURRENT_TIMESTAMP';
	}
}
