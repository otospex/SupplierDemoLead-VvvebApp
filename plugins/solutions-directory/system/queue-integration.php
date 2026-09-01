<?php

namespace Vvveb\Plugins\SolutionsDirectory\System;

if (! defined('V_VERSION')) {
	die('Invalid request!');
}

/**
 * Glue between the generic lead-platform-connector queue and this plugin.
 *
 * Kept out of plugin.php so the substitution rules can be exercised by tests
 * without booting Vvveb: plugin.php only wires these functions to events.
 */
final class QueueIntegration {
	/**
	 * Template names the connector's submissions view can be published under.
	 *
	 * Vvveb's FrontController derives the view template from the module name
	 * (`plugins/lead-platform-connector/submissions`), so the runtime value has
	 * no `admin/` segment even though the file lives in `public/admin/`. The
	 * `admin/` shape is accepted as well so a core change to that derivation
	 * cannot silently drop the draft button.
	 */
	public const QUEUE_TEMPLATES = [
		'plugins/lead-platform-connector/submissions.html',
		'plugins/lead-platform-connector/admin/submissions.html',
	];

	public const QUEUE_CONTROLLER = 'Vvveb\Plugins\LeadPlatformConnector\Controller\Submissions';

	public static function isQueueTemplate(string $template): bool {
		return in_array(str_replace('\\', '/', $template), self::QUEUE_TEMPLATES, true);
	}

	/** Forked queue markup that adds the « Créer une fiche brouillon » column. */
	public static function queueTemplateFile(): string {
		return dirname(__DIR__) . '/public/admin/submissions.html';
	}

	/** Suffix appended to the compiled template name so the fork gets its own cache entry. */
	public static function compiledSuffix(): string {
		return '-solutions-directory-v1';
	}

	/**
	 * Admin URL of the draft action. No submission id and no CSRF token: the
	 * queue template posts both, so neither ends up in a URL or an access log.
	 */
	public static function draftActionUrl(array $config, string $adminPath = '/admin/'): string {
		return $adminPath . 'index.php?module=' . ($config['draft_module'] ?? 'plugins/solutions-directory/draft');
	}

	/**
	 * Flags the queue rows this plugin can act on. Rows from another endpoint
	 * keep an empty string so the template's `data-v-if` hides the button.
	 */
	public static function decorateRows(array $rows, array $config, string $actionUrl): array {
		foreach ($rows as $index => $row) {
			if (! is_array($row)) {
				continue;
			}
			$rows[$index]['solution_action_url'] =
				(($row['endpoint_slug'] ?? '') === ($config['endpoint_slug'] ?? '')) ? $actionUrl : '';
		}

		return $rows;
	}
}
