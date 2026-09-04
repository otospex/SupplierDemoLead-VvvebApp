<?php

/**
 * Vvveb
 *
 * Pure sitemap assembly: rows in, XML out. No database, no request, so the
 * controller stays thin and the shape of the output is unit-testable.
 */

namespace Vvveb\System;

final class SitemapBuilder {
	/**
	 * Group post_content rows (post_id, language_id, slug, updated_at) into one
	 * sitemap entry per row, each carrying hreflang alternates for the other
	 * languages of the same post. $languages is keyed by language_id and gives
	 * ['slug' => 'fr', 'code' => 'fr_FR']; $pathFor(slug, languageSlugOrNull)
	 * returns the site-relative path; $absolute(path) makes it absolute.
	 */
	static function entries(array $rows, array $languages, int $defaultLanguageId, callable $pathFor, callable $absolute, array $excludeSlugs = []) : array {
		$byPost = [];

		foreach ($rows as $row) {
			$slug       = trim((string) ($row['slug'] ?? ''));
			$languageId = (int) ($row['language_id'] ?? 0);

			if ($slug === '' || in_array($slug, $excludeSlugs, true) || ! isset($languages[$languageId])) {
				continue;
			}

			$language = $languages[$languageId];
			$path     = $pathFor($slug, $languageId === $defaultLanguageId ? null : (string) $language['slug']);

			if (! $path) {
				continue;
			}

			$byPost[(int) $row['post_id']][$languageId] = [
				'loc'      => $absolute($path),
				'lastmod'  => self::lastmod((string) ($row['updated_at'] ?? '')),
				'hreflang' => self::hreflang((string) ($language['code'] ?? $language['slug'] ?? '')),
				'default'  => $languageId === $defaultLanguageId,
			];
		}

		$entries = [];

		foreach ($byPost as $translations) {
			$alternates = [];

			if (count($translations) > 1) {
				foreach ($translations as $t) {
					$alternates[] = ['hreflang' => $t['hreflang'], 'href' => $t['loc']];

					if ($t['default']) {
						$alternates[] = ['hreflang' => 'x-default', 'href' => $t['loc']];
					}
				}
			}

			foreach ($translations as $t) {
				$entries[] = ['loc' => $t['loc'], 'lastmod' => $t['lastmod'], 'alternates' => $alternates];
			}
		}

		return $entries;
	}

	static function urlset(array $entries) : string {
		$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";

		foreach ($entries as $entry) {
			$xml .= "\t<url>\n\t\t<loc>" . self::xml($entry['loc']) . "</loc>\n";

			if (! empty($entry['lastmod'])) {
				$xml .= "\t\t<lastmod>" . self::xml($entry['lastmod']) . "</lastmod>\n";
			}

			foreach ($entry['alternates'] ?? [] as $alternate) {
				$xml .= "\t\t" . '<xhtml:link rel="alternate" hreflang="' . self::xml($alternate['hreflang']) . '" href="' . self::xml($alternate['href']) . '"/>' . "\n";
			}

			$xml .= "\t</url>\n";
		}

		return $xml . "</urlset>\n";
	}

	static function index(array $sitemaps) : string {
		$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

		foreach ($sitemaps as $sitemap) {
			$xml .= "\t<sitemap>\n\t\t<loc>" . self::xml($sitemap['loc']) . "</loc>\n";

			if (! empty($sitemap['lastmod'])) {
				$xml .= "\t\t<lastmod>" . self::xml($sitemap['lastmod']) . "</lastmod>\n";
			}

			$xml .= "\t</sitemap>\n";
		}

		return $xml . "</sitemapindex>\n";
	}

	static function lastmod(string $value) : string {
		$timestamp = $value !== '' ? strtotime($value . ' UTC') : false;

		return $timestamp ? gmdate('Y-m-d\TH:i:s+00:00', $timestamp) : '';
	}

	/** fr_FR -> fr, en_US -> en; a bare slug passes through. */
	static function hreflang(string $code) : string {
		$code = strtolower(str_replace('_', '-', $code));
		$parts = explode('-', $code);

		return $parts[0] ?: $code;
	}

	static function xml(string $value) : string {
		return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
	}
}
