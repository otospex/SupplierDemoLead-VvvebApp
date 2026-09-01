<?php

namespace Vvveb\Plugins\SolutionsDirectory;

use Vvveb\System\Db;
use Vvveb\System\Import\Sql;

if (! defined('V_VERSION')) {
	die('Invalid request!');
}

final class Install {
	private $db;

	function __construct($db = null) {
		$this->db = $db ?? Db::getInstance();
	}

	private function one(string $sql, array $params = []): array {
		$stmt = $this->db->execute($sql, $params);
		$row = $stmt ? $this->db->fetchArray($stmt) : [];

		return is_array($row) ? $row : [];
	}

	private function taxonomy(string $postType, string $slug, string $name, int $languageId, int $siteId): int {
		$row = $this->one('SELECT t.taxonomy_id FROM taxonomy t JOIN taxonomy_content tc ON tc.taxonomy_id = t.taxonomy_id WHERE t.post_type = :post_type AND tc.slug = :slug LIMIT 1', ['post_type' => $postType, 'slug' => $slug]);
		$id = (int) ($row['taxonomy_id'] ?? 0);
		if (! $id) {
			$this->db->execute("INSERT INTO taxonomy (name, post_type, type, site_id) VALUES (:name, :post_type, 'categories', :site_id)", ['name' => $name, 'post_type' => $postType, 'site_id' => $siteId]);
			$row = $this->one('SELECT taxonomy_id FROM taxonomy WHERE post_type = :post_type AND name = :name ORDER BY taxonomy_id DESC LIMIT 1', ['post_type' => $postType, 'name' => $name]);
			$id = (int) ($row['taxonomy_id'] ?? 0);
			$this->db->execute('INSERT INTO taxonomy_content (taxonomy_id, language_id, name, slug, content) VALUES (:taxonomy_id, :language_id, :name, :slug, :content)', ['taxonomy_id' => $id, 'language_id' => $languageId, 'name' => $name, 'slug' => $slug, 'content' => '']);
		}

		return $id;
	}

	private function term(int $taxonomyId, int $languageId, int $siteId, int $order, string $name, string $slug, string $intro): void {
		$row = $this->one('SELECT ti.taxonomy_item_id FROM taxonomy_item ti JOIN taxonomy_item_content tic ON tic.taxonomy_item_id = ti.taxonomy_item_id WHERE ti.taxonomy_id = :taxonomy_id AND tic.language_id = :language_id AND tic.slug = :slug LIMIT 1', ['taxonomy_id' => $taxonomyId, 'language_id' => $languageId, 'slug' => $slug]);
		$id = (int) ($row['taxonomy_item_id'] ?? 0);
		if (! $id) {
			$this->db->execute('INSERT INTO taxonomy_item (taxonomy_id, image, template, parent_id, sort_order, status) VALUES (:taxonomy_id, :image, :template, 0, :sort_order, 1)', ['taxonomy_id' => $taxonomyId, 'image' => '', 'template' => '', 'sort_order' => $order]);
			$row = $this->one('SELECT taxonomy_item_id FROM taxonomy_item WHERE taxonomy_id = :taxonomy_id AND sort_order = :sort_order ORDER BY taxonomy_item_id DESC LIMIT 1', ['taxonomy_id' => $taxonomyId, 'sort_order' => $order]);
			$id = (int) ($row['taxonomy_item_id'] ?? 0);
			$this->db->execute('INSERT INTO taxonomy_item_content (taxonomy_item_id, language_id, name, slug, content, meta_title, meta_description, meta_keywords) VALUES (:taxonomy_item_id, :language_id, :name, :slug, :content, :meta_title, :meta_description, :meta_keywords)', ['taxonomy_item_id' => $id, 'language_id' => $languageId, 'name' => $name, 'slug' => $slug, 'content' => $intro, 'meta_title' => '', 'meta_description' => '', 'meta_keywords' => '']);
		}
		$this->db->execute('DELETE FROM taxonomy_to_site WHERE taxonomy_item_id = :taxonomy_item_id AND site_id = :site_id', ['taxonomy_item_id' => $id, 'site_id' => $siteId]);
		$this->db->execute('INSERT INTO taxonomy_to_site (taxonomy_item_id, site_id) VALUES (:taxonomy_item_id, :site_id)', ['taxonomy_item_id' => $id, 'site_id' => $siteId]);
	}

	/**
	 * Runs on plugin activation only.
	 *
	 * config/plugins.php lists solutions-directory as already active, which means
	 * Vvveb never fires the activation event on a fresh deployment and this
	 * installer never runs there. seed.dokploy.sql carries the same taxonomies,
	 * terms, pages and lead endpoint for that path; the two must stay in step.
	 */
	function run(): void {
		$config = require __DIR__ . '/config.php';
		$engine = DB_ENGINE;

		try {
			$import = new Sql();
			$import->setPath(__DIR__ . "/install/sql/$engine/data/");
			$import->createTables();
		} catch (\Throwable $e) {
			// A half-installed directory is worse than a loud failure: without the
			// tables the taxonomy writes below would fail one row at a time.
			throw new \RuntimeException(
				"solutions-directory: could not import install/sql/$engine/data/ — " . $e->getMessage(),
				0,
				$e
			);
		}

		$data = require __DIR__ . '/system/term-data.php';
		$languageSlug = (string) ($config['language_slug'] ?? 'fr');
		$language = $this->one(
			'SELECT language_id FROM language WHERE slug = :slug OR code LIKE :code ORDER BY language_id LIMIT 1',
			['slug' => $languageSlug, 'code' => $languageSlug . '%']
		);
		$languageId = (int) ($language['language_id'] ?? 2);
		$siteId = defined('SITE_ID') ? (int) SITE_ID : 1;
		$categoryId = $this->taxonomy($config['post_type'], $config['taxonomies']['categorie'], 'Catégories', $languageId, $siteId);
		$alternativeId = $this->taxonomy($config['post_type'], $config['taxonomies']['alternative_a'], 'Alternative à', $languageId, $siteId);
		foreach ($data['categorie'] as $index => $term) {
			$this->term($categoryId, $languageId, $siteId, $index + 1, $term[0], $term[1], $term[2]);
		}
		foreach ($data['alternative-a'] as $index => $term) {
			$intro = $term[2] ?? ('Solutions déclarées comme alternatives à ' . $term[0] . ' pour un cas d&rsquo;usage défini.');
			$this->term($alternativeId, $languageId, $siteId, $index + 1, $term[0], $term[1], $intro);
		}
	}
}
