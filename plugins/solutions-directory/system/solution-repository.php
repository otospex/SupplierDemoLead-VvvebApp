<?php

namespace Vvveb\Plugins\SolutionsDirectory\System;

use Vvveb\System\Db;

if (! defined('V_VERSION')) {
	die('Invalid request!');
}

final class SolutionRepository {
	private array $config;
	private $db;

	function __construct(array $config, $db = null) {
		$this->config = $config;
		$this->db = $db ?? Db::getInstance();
	}

	private function fetchAll($statement): array {
		$rows = $this->db->fetchAll($statement);

		return is_array($rows) ? $rows : [];
	}

	private function metaSelect(string $key): string {
		$quote = $this->db->quote;

		return "(SELECT pm.value FROM post_meta pm WHERE pm.post_id = p.post_id AND pm.namespace = :meta_namespace AND pm.{$quote}key{$quote} = '$key' LIMIT 1)";
	}

	public function published(array $filters = [], int $limit = 12): array {
		$limit = max(1, min($limit, 100));
		$params = [
			'post_type'      => $this->config['post_type'],
			'meta_namespace' => $this->config['meta_namespace'],
		];
		$where = ["p.type = :post_type", "p.status = 'publish'"];
		$siteJoin = '';
		if (! empty($filters['language_id'])) {
			$where[] = 'pc.language_id = :language_id';
			$params['language_id'] = (int) $filters['language_id'];
		}
		if (! empty($filters['site_id'])) {
			$siteJoin = ' JOIN post_to_site ps ON ps.post_id = p.post_id';
			$where[] = 'ps.site_id = :site_id';
			$params['site_id'] = (int) $filters['site_id'];
		}

		if (! empty($filters['slug'])) {
			$where[] = 'pc.slug = :slug';
			$params['slug'] = $filters['slug'];
		}
		if (! empty($filters['exclude_post_id'])) {
			$where[] = 'p.post_id <> :exclude_post_id';
			$params['exclude_post_id'] = (int) $filters['exclude_post_id'];
		}
		if (! empty($filters['kind'])) {
			$where[] = $this->metaSelect('kind') . ' = :kind';
			$params['kind'] = $filters['kind'];
		}

		$taxonomyFilters = [
			'categorie'     => $this->config['taxonomies']['categorie'],
			'alternative_a' => $this->config['taxonomies']['alternative_a'],
		];
		foreach ($taxonomyFilters as $filter => $taxonomySlug) {
			if (empty($filters[$filter])) {
				continue;
			}
			$slugs = is_array($filters[$filter]) ? $filters[$filter] : explode(',', (string) $filters[$filter]);
			$slugs = array_values(array_filter(array_map('trim', $slugs)));
			if (! $slugs) {
				continue;
			}
			$holders = [];
			foreach ($slugs as $index => $slug) {
				$key = "{$filter}_$index";
				$holders[] = ':' . $key;
				$params[$key] = $slug;
			}
			$taxKey = "{$filter}_taxonomy";
			$params[$taxKey] = $taxonomySlug;
			$where[] = "EXISTS (SELECT 1 FROM post_to_taxonomy_item ptti
				JOIN taxonomy_item ti ON ti.taxonomy_item_id = ptti.taxonomy_item_id
				JOIN taxonomy t ON t.taxonomy_id = ti.taxonomy_id
				JOIN taxonomy_content tc ON tc.taxonomy_id = t.taxonomy_id
				JOIN taxonomy_item_content tic ON tic.taxonomy_item_id = ti.taxonomy_item_id AND tic.language_id = pc.language_id
				WHERE ptti.post_id = p.post_id AND tc.slug = :$taxKey AND tic.slug IN (" . implode(',', $holders) . '))';
		}

		$metaKeys = ['kind', 'website', 'hq_country', 'hosting_countries', 'pricing_model', 'qualifications', 'commercial_relationship', 'verification_status', 'reviewed_at', 'reviewer'];
		$metaColumns = [];
		foreach ($metaKeys as $key) {
			$metaColumns[] = $this->metaSelect($key) . " AS $key";
		}

		$sql = 'SELECT p.post_id, p.status, p.type, pc.language_id, pc.name, pc.slug, pc.excerpt, pc.content, pc.meta_description, '
			. implode(', ', $metaColumns)
			. ' FROM post p JOIN post_content pc ON pc.post_id = p.post_id' . $siteJoin . ' WHERE '
			. implode(' AND ', $where)
			. ' ORDER BY reviewed_at DESC, pc.name ASC LIMIT ' . $limit;

		try {
			$rows = $this->fetchAll($this->db->execute($sql, $params));
			$this->attachTerms($rows);

			return $rows;
		} catch (\Throwable $e) {
			return [];
		}
	}

	private function attachTerms(array &$rows): void {
		if (! $rows) {
			return;
		}

		$params = [];
		$holders = [];
		foreach ($rows as $index => $row) {
			$key = "post_$index";
			$holders[] = ':' . $key;
			$params[$key] = (int) $row['post_id'];
			$rows[$index]['categories'] = [];
			$rows[$index]['alternative_a'] = [];
		}
		$params['category_taxonomy'] = $this->config['taxonomies']['categorie'];
		$params['alternative_taxonomy'] = $this->config['taxonomies']['alternative_a'];

		$sql = 'SELECT ptti.post_id, tc.slug AS taxonomy_slug, tic.language_id, tic.name, tic.slug
			FROM post_to_taxonomy_item ptti
			JOIN taxonomy_item ti ON ti.taxonomy_item_id = ptti.taxonomy_item_id
			JOIN taxonomy t ON t.taxonomy_id = ti.taxonomy_id
			JOIN taxonomy_content tc ON tc.taxonomy_id = t.taxonomy_id
			JOIN taxonomy_item_content tic ON tic.taxonomy_item_id = ti.taxonomy_item_id
				AND tc.language_id = tic.language_id
			WHERE ptti.post_id IN (' . implode(',', $holders) . ')
			AND tc.slug IN (:category_taxonomy, :alternative_taxonomy)
			ORDER BY ti.sort_order, tic.name';
		$terms = $this->fetchAll($this->db->execute($sql, $params));
		$indexes = [];
		foreach ($rows as $index => $row) {
			$indexes[(int) $row['post_id']] = $index;
		}
		foreach ($terms as $term) {
			$index = $indexes[(int) $term['post_id']] ?? null;
			if ($index === null) {
				continue;
			}
			if ((int) $term['language_id'] !== (int) $rows[$index]['language_id']) {
				continue;
			}
			$key = $term['taxonomy_slug'] === $this->config['taxonomies']['categorie'] ? 'categories' : 'alternative_a';
			$rows[$index][$key][] = ['name' => $term['name'], 'slug' => $term['slug']];
		}
	}

	public function term(string $taxonomy, string $slug, int $languageId, int $siteId = 0): array {
		$sql = 'SELECT tic.name, tic.slug, tic.content
			FROM taxonomy_item ti
			JOIN taxonomy t ON t.taxonomy_id = ti.taxonomy_id
			JOIN taxonomy_content tc ON tc.taxonomy_id = t.taxonomy_id
			JOIN taxonomy_item_content tic ON tic.taxonomy_item_id = ti.taxonomy_item_id AND tc.language_id = tic.language_id
			WHERE t.post_type = :post_type AND tc.slug = :taxonomy AND tic.slug = :slug AND tic.language_id = :language_id';
		$params = [
			'post_type' => $this->config['post_type'],
			'taxonomy' => $taxonomy,
			'slug' => $slug,
			'language_id' => $languageId,
		];
		if ($siteId > 0) {
			$sql .= ' AND t.site_id = :site_id';
			$params['site_id'] = $siteId;
		}
		$sql .= ' LIMIT 1';
		try {
			$stmt = $this->db->execute($sql, $params);
			$row = $this->db->fetchArray($stmt);

			return is_array($row) ? $row : [];
		} catch (\Throwable $e) {
			return [];
		}
	}

	public function terms(string $taxonomy, int $languageId, int $siteId = 0): array {
		$sql = 'SELECT tic.name, tic.slug FROM taxonomy_item ti
			JOIN taxonomy t ON t.taxonomy_id = ti.taxonomy_id
			JOIN taxonomy_content tc ON tc.taxonomy_id = t.taxonomy_id
			JOIN taxonomy_item_content tic ON tic.taxonomy_item_id = ti.taxonomy_item_id AND tc.language_id = tic.language_id
			WHERE t.post_type = :post_type AND tc.slug = :taxonomy AND tic.language_id = :language_id AND ti.status = 1';
		$params = [
			'post_type' => $this->config['post_type'],
			'taxonomy' => $taxonomy,
			'language_id' => $languageId,
		];
		if ($siteId > 0) {
			$sql .= ' AND t.site_id = :site_id';
			$params['site_id'] = $siteId;
		}
		$sql .= ' ORDER BY ti.sort_order, tic.name';
		try {
			return $this->fetchAll($this->db->execute($sql, $params));
		} catch (\Throwable $e) {
			return [];
		}
	}

	public function sitemapSolutions(int $languageId, int $siteId): array {
		$sql = "SELECT p.post_id, p.status, pc.slug, " . $this->metaSelect('reviewed_at') . " AS reviewed_at
			FROM post p
			JOIN post_content pc ON pc.post_id = p.post_id
			JOIN post_to_site ps ON ps.post_id = p.post_id
			WHERE p.type = :post_type AND p.status = 'publish' AND pc.language_id = :language_id AND ps.site_id = :site_id
			ORDER BY p.post_id DESC LIMIT 10000";

		try {
			return $this->fetchAll($this->db->execute($sql, [
				'post_type' => $this->config['post_type'],
				'meta_namespace' => $this->config['meta_namespace'],
				'language_id' => $languageId,
				'site_id' => $siteId,
			]));
		} catch (\Throwable $e) {
			return [];
		}
	}

	/**
	 * Spec 5.4: other published solutions sharing at least one `alternative-a`
	 * OR one `categorie` term — the union of both neighbourhoods, deduped, self
	 * excluded, capped at $limit and ordered reviewed_at desc then name.
	 *
	 * The two sides are queried separately because a single statement would AND
	 * the two EXISTS clauses. Each side is already ordered, so the top $limit of
	 * the merged set is the true top $limit of the union.
	 */
	public function alternatives(array $solution, int $limit = 5, int $siteId = 0): array {
		$limit = max(1, $limit);
		$base  = [
			'exclude_post_id' => (int) ($solution['post_id'] ?? 0),
			'language_id'     => (int) ($solution['language_id'] ?? 0),
			'site_id'         => $siteId,
		];
		$neighbourhoods = [
			'alternative_a' => array_column($solution['alternative_a'] ?? [], 'slug'),
			'categorie'     => array_column($solution['categories'] ?? [], 'slug'),
		];

		$rows = [];
		foreach ($neighbourhoods as $filter => $slugs) {
			if (! $slugs) {
				continue;
			}
			foreach ($this->published($base + [$filter => $slugs], $limit) as $row) {
				$rows[(int) ($row['post_id'] ?? 0)] = $row;
			}
		}
		if (! $rows) {
			return [];
		}

		$rows = array_values($rows);
		usort($rows, static function (array $left, array $right): int {
			$date = strcmp((string) ($right['reviewed_at'] ?? ''), (string) ($left['reviewed_at'] ?? ''));

			return $date !== 0 ? $date : strcasecmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''));
		});

		return array_slice($rows, 0, $limit);
	}
}
