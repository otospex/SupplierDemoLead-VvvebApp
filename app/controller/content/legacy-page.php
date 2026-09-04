<?php

/**
 * Vvveb
 *
 * Permanent redirect for the historical /page/{slug} and /{lang}/page/{slug}
 * URLs. Pages answer at /{slug} now; the old shape is kept only so links
 * already indexed or shared keep resolving. Its own module so its routes
 * never enter the content/page/index reverse map.
 */

namespace Vvveb\Controller\Content;

use Vvveb\Controller\Base;
use Vvveb\System\Core\FrontController;
use function Vvveb\canonicalUrl;
use function Vvveb\url;

class LegacyPage extends Base {
	function index() {
		$slug     = (string) ($this->request->get['slug'] ?? '');
		$language = (string) ($this->request->get['language'] ?? '');

		if ($slug === '') {
			return $this->notFound(true);
		}

		$parameters = ['slug' => $slug];

		if ($language !== '') {
			$parameters['language'] = $language;
		}

		$target = url('content/page/index', $parameters);

		if (! $target) {
			return $this->notFound(true);
		}

		//absolute, on the public origin: a legacy link on www. or a preview host
		//lands on the canonical host in one hop.
		$target = canonicalUrl($target);

		FrontController::setStatus(301);

		if (class_exists('\PageCache')) {
			\PageCache::getInstance()->cleanUp();
		}

		$this->response->redirect($target, 301);
	}
}
