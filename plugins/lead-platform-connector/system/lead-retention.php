<?php

namespace Vvveb\Plugins\LeadPlatformConnector\System;

if (! defined('V_VERSION')) {
	die('Invalid request!');
}

/**
 * Retention purge for the encrypted lead queue.
 *
 * Rows are deleted once their last change (updated_at, written by the
 * database in UTC) is older than the retention the operator has fixed in the
 * privacy notice. Partial rows are not special-cased: the flush job settles
 * them within TTL_HOURS, long before any realistic retention elapses, and the
 * notice promises they are purged on the same schedule as complete requests.
 *
 * The retention has no default on purpose. Purging is a policy decision the
 * notice must state first; a script that silently assumed "365 days" would
 * be a retention nobody decided.
 */
final class LeadRetention {

	/** UTC instant before which rows are out of retention. */
	public static function cutoff(int $days, string $nowUtc): string {
		if ($days <= 0) {
			throw new \InvalidArgumentException('retention must be a positive number of days, got ' . $days);
		}

		return gmdate('Y-m-d H:i:s', strtotime($nowUtc . ' UTC') - $days * 86400);
	}

	/**
	 * Count (dry run) or delete rows whose updated_at is strictly older than
	 * the cutoff. A row updated exactly at the cutoff is still in retention.
	 */
	public static function purge(\PDO $pdo, int $days, string $nowUtc, bool $apply): int {
		$cutoff = self::cutoff($days, $nowUtc);
		$verb   = $apply ? 'DELETE' : 'SELECT COUNT(*)';
		$stmt   = $pdo->prepare("$verb FROM lead_submission WHERE updated_at < :cutoff");
		$stmt->execute(['cutoff' => $cutoff]);

		return $apply ? $stmt->rowCount() : (int) $stmt->fetchColumn();
	}
}
