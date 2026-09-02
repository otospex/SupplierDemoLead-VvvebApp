<?php

/**
 * Vvveb
 *
 * Automatic XML sitemaps on the public origin (CANONICAL_URL):
 *   /sitemap.xml            index of the sections below that have URLs
 *   /sitemap-pages.xml      published pages, every language, hreflang paired
 *   /sitemap-posts.xml      published blog posts, same shape
 *   /sitemap-solutions.xml  served by the solutions-directory plugin
 *
 * URLs come from the reverse route map, so they follow config/app-routes.php.
 */

namespace Vvveb\Controller;

use Vvveb\System\Db;
use Vvveb\System\Locale;
use Vvveb\System\SitemapBuilder;
use function Vvveb\canonicalUrl;
use function Vvveb\url;

class Sitemap extends Base {
	/** Published pages that must stay out of search: noindex by policy. */
	const EXCLUDED_PAGE_SLUGS = ['referencer-une-solution'];

	const SECTIONS = [
		'pages' => ['type' => 'page', 'module' => 'content/page/index'],
		'posts' => ['type' => 'post', 'module' => 'content/post/index'],
	];

	function index() {
		require_once DIR_SYSTEM . 'sitemap-builder.php';

		$section = (string) ($this->request->get['section'] ?? '');

		if ($section === '') {
			return $this->output(SitemapBuilder::index($this->sitemapIndex()));
		}

		if (! isset(self::SECTIONS[$section])) {
			return $this->notFound(true);
		}

		return $this->output(SitemapBuilder::urlset($this->entries($section)));
	}

	private function sitemapIndex() : array {
		$sitemaps = [];

		foreach (array_keys(self::SECTIONS) as $section) {
			$entries = $this->entries($section);

			if (! $entries) {
				continue;
			}

			$lastmod = array_reduce($entries, static fn ($carry, $e) => max((string) $carry, (string) $e['lastmod']), '');
			$sitemaps[] = ['loc' => canonicalUrl("/sitemap-$section.xml"), 'lastmod' => $lastmod];
		}

		if (is_dir(DIR_PLUGINS . 'solutions-directory')) {
			$sitemaps[] = ['loc' => canonicalUrl('/sitemap-solutions.xml'), 'lastmod' => ''];
		}

		return $sitemaps;
	}

	private function entries(string $section) : array {
		$type   = self::SECTIONS[$section]['type'];
		$module = self::SECTIONS[$section]['module'];
		$siteId = (int) ($this->global['site_id'] ?? 1);

		$languages = [];

		foreach (Locale::availableLanguages() as $language) {
			$languages[(int) $language['language_id']] = ['slug' => $language['slug'], 'code' => $language['code'] ?? $language['slug']];
		}

		$defaultLanguageId = (int) ($this->global['default_language_id'] ?? array_key_first($languages) ?? 1);

		$pathFor = static function (string $slug, ?string $languageSlug) use ($module) {
			$parameters = ['slug' => $slug];

			if ($languageSlug !== null) {
				$parameters['language'] = $languageSlug;
			}

			return url($module, $parameters);
		};

		return SitemapBuilder::entries(
			$this->rows($type, $siteId),
			$languages,
			$defaultLanguageId,
			$pathFor,
			static fn (string $path) => canonicalUrl($path),
			$type === 'page' ? self::EXCLUDED_PAGE_SLUGS : []
		);
	}

	private function rows(string $type, int $siteId) : array {
		$db  = Db::getInstance();
		$sql = "SELECT p.post_id, p.updated_at, pc.language_id, pc.slug
			FROM post p
			JOIN post_content pc ON pc.post_id = p.post_id
			JOIN post_to_site ps ON ps.post_id = p.post_id
			WHERE p.type = :type AND p.status = 'publish' AND ps.site_id = :site_id AND pc.slug <> ''
			ORDER BY p.post_id, pc.language_id
			LIMIT 10000";

		try {
			$rows = $db->fetchAll($db->execute($sql, ['type' => $type, 'site_id' => $siteId]));
		} catch (\Throwable $e) {
			$rows = [];
		}

		return is_array($rows) ? $rows : [];
	}

	private function output(string $xml) {
		$this->response->setType('text');
		$this->response->addHeader('Content-Type', 'application/xml; charset=UTF-8');
		$this->response->output($xml);

		return false;
	}
}
