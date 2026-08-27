<?php

namespace IndependantDigital\Publishing;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Throwable;

final class ScheduledPublisher {

	public function __construct(private PDO $pdo) {
		$this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	}

	/** @return list<int> */
	public function publishDue(?DateTimeImmutable $now = null): array {
		$now = ($now ?? new DateTimeImmutable('now', new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('UTC'));
		$timestamp = $now->format('Y-m-d H:i:s');

		$this->pdo->beginTransaction();
		try {
			$select = $this->pdo->prepare(
				"SELECT p.post_id
				 FROM post p
				 INNER JOIN post_meta pm
				   ON pm.post_id = p.post_id
				  AND pm.namespace = 'independant_digital'
				  AND pm.key = 'editorial_ready'
				  AND pm.value = '1'
				 WHERE p.status = 'scheduled'
				   AND p.created_at <= :due_at
				 ORDER BY p.created_at, p.post_id"
			);
			$select->execute(['due_at' => $timestamp]);
			$candidates = array_map('intval', $select->fetchAll(PDO::FETCH_COLUMN));

			$update = $this->pdo->prepare(
				"UPDATE post
				 SET status = 'publish', updated_at = :updated_at
				 WHERE post_id = :post_id
				   AND status = 'scheduled'
				   AND created_at <= :due_at
				   AND EXISTS (
				     SELECT 1 FROM post_meta pm
				     WHERE pm.post_id = post.post_id
				       AND pm.namespace = 'independant_digital'
				       AND pm.key = 'editorial_ready'
				       AND pm.value = '1'
				   )"
			);

			$published = [];
			foreach ($candidates as $postId) {
				$update->execute(['updated_at' => $timestamp, 'post_id' => $postId, 'due_at' => $timestamp]);
				if ($update->rowCount() === 1) {
					$published[] = $postId;
				}
			}

			$this->pdo->commit();

			return $published;
		} catch (Throwable $error) {
			if ($this->pdo->inTransaction()) {
				$this->pdo->rollBack();
			}
			throw $error;
		}
	}
}
