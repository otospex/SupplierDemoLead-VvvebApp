<?php

namespace Vvveb\Plugins\SolutionsDirectory\Controller;

use Vvveb\Controller\Base;
use Vvveb\Plugins\SolutionsDirectory\System\SolutionRepository;

class Sitemap extends Base {
	private function xml(string $value): string {
		return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
	}

	private function absoluteUrl(string $path): string {
		$base = rtrim((string) ($this->global['site']['url'] ?? ''), '/');
		if (str_starts_with($base, '//')) {
			$scheme = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https:' : 'http:';
			$base = $scheme . $base;
		}

		return $base . '/' . ltrim($path, '/');
	}

	private function lastmod(string $value): string {
		$timestamp = strtotime($value);

		return $timestamp ? date(DATE_ATOM, $timestamp) : '';
	}

	function index() {
		require_once dirname(__DIR__, 2) . '/system/solution-repository.php';
		$config = require dirname(__DIR__, 2) . '/config.php';
		$repository = new SolutionRepository($config);
		$languageId = (int) ($this->global['language_id'] ?? 1);
		$siteId = (int) ($this->global['site_id'] ?? 1);
		$urls = [['path' => '/annuaire', 'lastmod' => '']];

		foreach ($config['taxonomies'] as $routeKey => $taxonomy) {
			$routeSegment = str_replace('_', '-', $routeKey);
			foreach ($repository->terms($taxonomy, $languageId, $siteId) as $term) {
				$urls[] = ['path' => "/annuaire/$routeSegment/{$term['slug']}", 'lastmod' => ''];
			}
		}

		$solutions = array_filter(
			$repository->sitemapSolutions($languageId, $siteId),
			static fn (array $solution): bool => ($solution['status'] ?? '') === 'publish'
		);
		foreach ($solutions as $solution) {
			$urls[] = [
				'path' => '/solution/' . $solution['slug'],
				'lastmod' => $this->lastmod((string) ($solution['reviewed_at'] ?? '')),
			];
		}

		$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
		foreach ($urls as $url) {
			$xml .= "\t<url><loc>" . $this->xml($this->absoluteUrl($url['path'])) . '</loc>';
			if ($url['lastmod'] !== '') {
				$xml .= '<lastmod>' . $this->xml($url['lastmod']) . '</lastmod>';
			}
			$xml .= "</url>\n";
		}
		$xml .= "</urlset>\n";

		$this->response->setType('text');
		$this->response->addHeader('Content-Type', 'application/xml; charset=UTF-8');
		$this->response->output($xml);

		return false;
	}
}
