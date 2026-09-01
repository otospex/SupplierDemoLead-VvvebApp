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

if (! defined('V_VERSION')) {
	die('Invalid request!');
}

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
		}

		if (APP === 'admin') {
			$this->adminHooks();
		}
	}

	private function isQueueTemplate(string $template): bool {
		return str_replace('\\', '/', $template) === 'plugins/lead-platform-connector/admin/submissions.html';
	}

	private function adminHooks(): void {
		Event::on('Vvveb\System\Core\View', 'template', __CLASS__, function ($filename, $compiledFilename, $view) {
			if ($this->isQueueTemplate($filename)) {
				$compiledFilename .= '-solutions-directory-v1';
			}

			return [$filename, $compiledFilename, $view];
		});

		Event::on('Vvveb\System\Core\View', 'compile', __CLASS__, function ($template, $filename, $tplFile, $engine, $view) {
			if ($this->isQueueTemplate($template)) {
				$filename = __DIR__ . '/public/admin/submissions.html';
			}

			return [$template, $filename, $tplFile, $engine, $view];
		});

		Event::on('Vvveb\System\Core\FrontController', 'call', __CLASS__, function ($template, $controller, $actionName) {
			if (get_class($controller) !== 'Vvveb\Plugins\LeadPlatformConnector\Controller\Submissions') {
				return [$template, $controller, $actionName];
			}
			$rows =& $controller->view->lead_submission;
			$csrf = rawurlencode((string) $controller->session->get('csrf'));
			if (is_array($rows)) {
				foreach ($rows as &$row) {
					$row['solution_action_url'] = '';
					if (($row['endpoint_slug'] ?? '') === $this->config['endpoint_slug']) {
						$row['solution_action_url'] = \Vvveb\adminPath() . 'index.php?module=plugins/solutions-directory/draft&lead_submission_id=' . (int) $row['lead_submission_id'] . '&csrf=' . $csrf;
					}
				}
			}

			return [$template, $controller, $actionName];
		});
	}
}

$solutionsDirectoryPlugin = new SolutionsDirectoryPlugin();
