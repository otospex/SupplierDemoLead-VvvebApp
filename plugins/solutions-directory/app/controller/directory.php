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
			if (! $repository->term($taxonomy, $slug, $languageId, $siteId)) {
				$error = 'Terme introuvable dans l’annuaire.';

				return $this->notFound(true, ['message' => $error, 'title' => $error]);
			}
		}

		$this->view->template($config['directory_template']);
	}
}
