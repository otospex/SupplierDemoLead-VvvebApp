<?php

namespace Vvveb\Plugins\SolutionsDirectory\System;

use Vvveb\Sql\PostSQL;
use Vvveb\System\Db;

if (! defined('V_VERSION')) {
	die('Invalid request!');
}

final class VvvebDraftStore {
	private array $config;
	private $db;

	function __construct(array $config, $db = null) {
		$this->config = $config;
		$this->db = $db ?? Db::getInstance();
	}

	public function findBySubmission(int $submissionId): ?int {
		$quote  = $this->db->quote;
		$siteId = (int) ($this->config['site_id'] ?? 0);
		$params = [
			'post_type'     => $this->config['post_type'],
			'namespace'     => $this->config['meta_namespace'],
			'submission_id' => (string) $submissionId,
		];
		// Submission ids are global but drafts are not: without the site join a
		// multi-site install would hand back another site's listing.
		$siteJoin = '';
		$siteWhere = '';
		if ($siteId > 0) {
			$siteJoin  = ' JOIN post_to_site ps ON ps.post_id = p.post_id';
			$siteWhere = ' AND ps.site_id = :site_id';
			$params['site_id'] = $siteId;
		}
		$sql = "SELECT p.post_id FROM post p JOIN post_meta pm ON pm.post_id = p.post_id$siteJoin
			WHERE p.type = :post_type AND pm.namespace = :namespace AND pm.{$quote}key{$quote} = 'submission_id'
			AND pm.value = :submission_id$siteWhere LIMIT 1";
		$stmt = $this->db->execute($sql, $params);
		$row = $stmt ? $this->db->fetchArray($stmt) : [];

		return ! empty($row['post_id']) ? (int) $row['post_id'] : null;
	}

	private function termIds(array $terms, int $languageId): array {
		$ids = [];
		foreach ($terms as $taxonomy => $slugs) {
			foreach ($slugs as $slug) {
				$sql = 'SELECT ti.taxonomy_item_id FROM taxonomy_item ti
					JOIN taxonomy t ON t.taxonomy_id = ti.taxonomy_id
					JOIN taxonomy_content tc ON tc.taxonomy_id = t.taxonomy_id
					JOIN taxonomy_item_content tic ON tic.taxonomy_item_id = ti.taxonomy_item_id
					WHERE t.post_type = :post_type AND tc.slug = :taxonomy AND tic.slug = :slug
					AND tic.language_id = :language_id LIMIT 1';
				$stmt = $this->db->execute($sql, [
					'post_type' => $this->config['post_type'],
					'taxonomy' => $taxonomy,
					'slug' => $slug,
					'language_id' => $languageId,
				]);
				$row = $stmt ? $this->db->fetchArray($stmt) : [];
				if (! empty($row['taxonomy_item_id'])) {
					$ids[] = (int) $row['taxonomy_item_id'];
				}
			}
		}

		return array_values(array_unique($ids));
	}

	private function saveMeta(int $postId, array $meta): void {
		$quote = $this->db->quote;
		foreach ($meta as $key => $value) {
			$params = [
				'post_id' => $postId,
				'namespace' => $this->config['meta_namespace'],
				'key' => $key,
				'value' => (string) $value,
			];
			$this->db->execute("DELETE FROM post_meta WHERE post_id = :post_id AND namespace = :namespace AND {$quote}key{$quote} = :key", $params);
			$this->db->execute("INSERT INTO post_meta (post_id, namespace, {$quote}key{$quote}, value) VALUES (:post_id, :namespace, :key, :value)", $params);
		}
	}

	public function create(array $draft): int {
		$posts = new PostSQL();
		$termIds = $this->termIds($draft['terms'], (int) $draft['content']['language_id']);
		$result = $posts->add([
			'post' => $draft['post'],
			'post_content' => [$draft['content']],
			'taxonomy_item_id' => $termIds,
			'post_field_value' => [],
			'site_id' => [(int) $draft['site_id']],
		]);
		$postId = (int) ($result['post'] ?? 0);
		if (! $postId) {
			throw new \RuntimeException('Solution draft could not be created.');
		}
		$this->saveMeta($postId, $draft['meta']);

		return $postId;
	}
}
