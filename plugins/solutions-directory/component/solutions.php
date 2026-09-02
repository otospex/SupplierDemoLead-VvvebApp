<?php

namespace Vvveb\Plugins\SolutionsDirectory\Component;

use Vvveb\Plugins\SolutionsDirectory\System\SolutionPresenter;
use Vvveb\Plugins\SolutionsDirectory\System\SolutionRepository;
use Vvveb\System\Component\ComponentBase;

if (! defined('V_VERSION')) {
	die('Invalid request!');
}

require_once __DIR__ . '/../system/solution-presenter.php';
require_once __DIR__ . '/../system/solution-repository.php';

class Solutions extends ComponentBase {
	public static $defaultOptions = [
		'kind'          => 'url',
		'categorie'     => 'url',
		'alternative_a' => 'url',
		'limit'         => 12,
		'mode'          => 'listing',
		'embed'         => '',
		'slug'          => 'url',
		'language_id'   => null,
		'site_id'       => null,
	];

	public $cacheExpire = 0;

	function results() {
		$config = require __DIR__ . '/../config.php';
		SolutionPresenter::configure($config);
		$repository = new SolutionRepository($config);
		$languageId = (int) ($this->options['language_id'] ?? 0);
		$siteId = (int) ($this->options['site_id'] ?? 0);

		if (($this->options['mode'] ?? 'listing') === 'registration') {
			$categories = $repository->terms($config['taxonomies']['categorie'], $languageId, $siteId);
			$alternatives = $repository->terms($config['taxonomies']['alternative_a'], $languageId, $siteId);

			return ['html' => SolutionPresenter::registrationForm($categories, $alternatives, $config)];
		}

		if (($this->options['mode'] ?? 'listing') === 'detail') {
			$solution = $repository->published([
				'slug' => $this->options['slug'] ?? '',
				'language_id' => $languageId,
				'site_id' => $siteId,
			], 1)[0] ?? [];
			return ['html' => $solution ? SolutionPresenter::detail($solution, $repository->alternatives($solution, 5, $siteId)) : ''];
		}

		$filters = array_filter([
			'kind'          => $this->options['kind'] ?? null,
			'categorie'     => $this->options['categorie'] ?? null,
			'alternative_a' => $this->options['alternative_a'] ?? null,
		]);
		$filters['language_id'] = $languageId;
		$filters['site_id'] = $siteId;
		// An embedded block (a guide page, the homepage teaser) is a compact
		// listing inside a page that already has its own <h1> and its own
		// heading structure: no term header, no filter form.
		$embed = ! in_array((string) ($this->options['embed'] ?? ''), ['', '0', 'false'], true);
		$context = [];
		foreach ($embed ? [] : ['categorie', 'alternative_a'] as $filter) {
			// A multi-term filter is an array (guide embeds pass a JSON array);
			// there is no single term whose name and intro could head the page,
			// so only a scalar filter looks one up.
			if (empty($filters[$filter]) || ! is_scalar($filters[$filter])) {
				continue;
			}
			$taxonomy = $config['taxonomies'][$filter];
			$term = $repository->term($taxonomy, (string) $filters[$filter], $languageId, $siteId);
			if ($term) {
				$context = ['taxonomy' => $taxonomy, 'term_name' => $term['name'], 'term_intro' => $term['content']];
			}
			break;
		}
		$context['filtered'] = (bool) array_filter([
			$filters['kind'] ?? null,
			$filters['categorie'] ?? null,
			$filters['alternative_a'] ?? null,
		]);
		$context['show_filters'] = ! $embed;
		if (! $embed) {
			$context['category_terms'] = $repository->terms($config['taxonomies']['categorie'], $languageId, $siteId);
			$context['selected_kind'] = $filters['kind'] ?? '';
			$context['selected_categorie'] = $filters['categorie'] ?? '';
		}

		$rows = $repository->published($filters, (int) ($this->options['limit'] ?? 12));

		return ['html' => SolutionPresenter::listing($rows, $context)];
	}
}
