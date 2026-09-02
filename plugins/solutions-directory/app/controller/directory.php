<?php

namespace Vvveb\Plugins\SolutionsDirectory\Controller;

use Vvveb\Controller\Base;
use Vvveb\Plugins\SolutionsDirectory\System\SolutionRepository;

class Directory extends Base {
	function index() {
		require_once dirname(__DIR__, 2) . '/system/solution-repository.php';
		$config     = require dirname(__DIR__, 2) . '/config.php';
		$repository = new SolutionRepository($config);
		$languageId = (int) ($this->global['language_id'] ?? 0);
		$siteId     = (int) ($this->global['site_id'] ?? 0);

		// A term slug that does not exist is a wrong URL, not an empty annuaire:
		// rendering the unfiltered listing at 200 would let any string mint an
		// indexable near-duplicate of /annuaire.
		foreach ($config['taxonomies'] as $routeKey => $taxonomy) {
			$slug = (string) ($this->request->get[$routeKey] ?? '');
			if ($slug === '') {
				continue;
			}
			$term = $repository->term($taxonomy, $slug, $languageId, $siteId);
			if (! $term) {
				$error = 'Terme introuvable dans l’annuaire.';

				return $this->notFound(true, ['message' => $error, 'title' => $error]);
			}

			// <title>, meta description and og:* are bound from $this->post by
			// the content templates; a term page is not a post, so it supplies
			// the same keys itself. The description is the term intro, plain.
			$name  = (string) ($term['name'] ?? $slug);
			$title = ($routeKey === 'alternative_a' ? 'Alternatives à ' . $name : $name) . ' — Annuaire des solutions souveraines';
			$intro = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) ($term['content'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
			if ($intro === '') {
				$intro = $routeKey === 'alternative_a'
					? "Solutions françaises et européennes documentées comme alternatives à $name : hébergement, qualifications, tarification et limites, revues par Indépendant Digital."
					: "Solutions françaises et européennes documentées pour le cas d’usage $name : hébergement, qualifications, tarification et limites, revues par Indépendant Digital.";
			}
			if (mb_strlen($intro) > 158) {
				$intro = rtrim(mb_substr($intro, 0, 155)) . '…';
			}
			$this->view->post = ['title' => $title, 'meta_description' => $intro, 'meta_keywords' => $name . ', annuaire, souveraineté numérique'];
		}

		$this->view->template($config['term_template'] ?? $config['directory_template']);
		// The view derives the .tpl name from the template file name, so a
		// language-suffixed template (content/annuaire.fr.html) would look for
		// content/annuaire.fr.tpl and silently fall back to common.tpl, losing
		// the title/description binding. Force the post bindings like the post
		// controller does.
		$this->view->tplFile('content/post.tpl');
	}
}
