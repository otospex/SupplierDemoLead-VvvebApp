<?php

/*
Name: Solutions Directory
Slug: solutions-directory
Category: content
Description: Reviewed directory of sovereign digital solutions.
Author: Indépendant Digital
Version: 0.1.0
*/

use Vvveb\System\Event;
use Vvveb\Plugins\SolutionsDirectory\Install;
use Vvveb\Plugins\SolutionsDirectory\System\ContentEmbed;
use Vvveb\Plugins\SolutionsDirectory\System\QueueIntegration;

if (! defined('V_VERSION')) {
	die('Invalid request!');
}

require_once __DIR__ . '/system/content-embed.php';
require_once __DIR__ . '/system/queue-integration.php';

#[\AllowDynamicProperties]
class SolutionsDirectoryPlugin {
	private array $config;

	function __construct() {
		$this->config = require __DIR__ . '/config.php';

		Event::on('Vvveb\postTypes', 'customPost', __CLASS__, function ($types) {
			$type = $this->config['post_type'];
			$types[$type] = [
				'type'     => $type,
				'plural'   => 'solutions',
				'icon'     => 'icon-layers-outline',
				'comments' => false,
			];

			return [$types];
		});

		Event::on('Vvveb\System\Extensions\Plugins', 'setup', __CLASS__, function ($pluginName, $siteId) {
			if ($pluginName === 'solutions-directory') {
				(require_once __DIR__ . '/install.php');
				(new Install())->run();
			}

			return [$pluginName, $siteId];
		});

		if (APP === 'app') {
			Event::on('Vvveb\Component\Posts', 'results', __CLASS__, function ($results) {
				$rss = (string) ($_GET['rss'] ?? '');
				if ($rss !== 'page-sitemap.xml' || empty($results['post'])) {
					return [$results];
				}

				$registrationSlug = basename((string) $this->config['registration_url']);
				$results['post'] = array_filter(
					$results['post'],
					static fn (array $post): bool => ($post['slug'] ?? '') !== $registrationSlug
				);
				if (isset($results['count'])) {
					$results['count'] = count($results['post']);
				}

				return [$results];
			});

			$this->renderContentEmbeds();
		}

		if (APP === 'admin') {
			$this->adminHooks();
		}
	}

	/**
	 * Renders directory blocks that guide pages store in their body copy.
	 *
	 * Vvveb instantiates components only from theme template files, so a block
	 * written into post_content is never rendered and its fallback ("no
	 * published solution matches") would be shown even when solutions do match.
	 * The rewrite happens on the post component's results, before the page
	 * cache captures anything, so a cached page carries the rendered block.
	 * Blocks vtpl already rendered carry data-sd-rendered and are skipped.
	 */
	private function renderContentEmbeds(): void {
		Event::on('Vvveb\Component\Post', 'results', __CLASS__, function ($results) {
			if (! is_array($results) || empty($results['content']) || ! is_string($results['content'])) {
				return [$results];
			}

			$results['content'] = ContentEmbed::render($results['content'], static function (array $options): string {
				require_once __DIR__ . '/component/solutions.php';

				$component = new \Vvveb\Plugins\SolutionsDirectory\Component\Solutions($options);
				$rendered  = $component->results();

				return (string) ($rendered['html'] ?? '');
			});

			return [$results];
		});
	}

	private function adminHooks(): void {
		Event::on('Vvveb\System\Core\View', 'template', __CLASS__, function ($filename, $compiledFilename, $view) {
			if (QueueIntegration::isQueueTemplate((string) $filename)) {
				$compiledFilename .= QueueIntegration::compiledSuffix();
			}

			return [$filename, $compiledFilename, $view];
		});

		Event::on('Vvveb\System\Core\View', 'compile', __CLASS__, function ($template, $filename, $tplFile, $engine, $view) {
			if (QueueIntegration::isQueueTemplate((string) $template)) {
				$filename = QueueIntegration::queueTemplateFile();
			}

			return [$template, $filename, $tplFile, $engine, $view];
		});

		Event::on('Vvveb\System\Core\FrontController', 'call', __CLASS__, function ($template, $controller, $actionName) {
			if (get_class($controller) !== QueueIntegration::QUEUE_CONTROLLER) {
				return [$template, $controller, $actionName];
			}
			$rows =& $controller->view->lead_submission;
			if (is_array($rows)) {
				$rows = QueueIntegration::decorateRows(
					$rows,
					$this->config,
					QueueIntegration::draftActionUrl($this->config, \Vvveb\adminPath())
				);
			}

			return [$template, $controller, $actionName];
		});
	}
}

$solutionsDirectoryPlugin = new SolutionsDirectoryPlugin();
