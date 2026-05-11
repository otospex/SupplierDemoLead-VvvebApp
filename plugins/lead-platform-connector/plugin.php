<?php

/*
Name: Lead Platform Connector
Slug: lead-platform-connector
Category: integrations
Url: https://github.com/otospex/leadmanagementplatform
Description: Send form submissions from Vvveb published pages to the Lead Management Platform (Laravel) via a secure server-side proxy. Per-endpoint API keys, encrypted at rest. Full audit log + retry.
Thumb: lead-platform-connector.svg
Author: otospex
Version: 0.1
Settings: /admin/index.php?module=plugins/lead-platform-connector/endpoints
*/

use function Vvveb\__;
use function Vvveb\arrayInsertArrayAfter;
use function Vvveb\model;
use Vvveb\Plugins\LeadPlatformConnector\Install;
use Vvveb\System\Core\View;
use Vvveb\System\Db;
use Vvveb\System\Event;

if (! defined('V_VERSION')) {
	die('Invalid request!');
}

#[\AllowDynamicProperties]
class LeadPlatformConnectorPlugin {

	function admin() {
		$admin_path = \Vvveb\adminPath();

		// Self-heal: make sure our tables exist. The framework's setup event only
		// fires on truly-first activation; this guard handles re-installs, fresh
		// volumes, and cases where activation didn't trigger the hook.
		$this->ensureInstalled();

		Event::on('Vvveb\Controller\Base', 'init-menu', __CLASS__, function ($menu) use ($admin_path) {
			$menu['plugins']['items']['lead-platform-connector'] = [
				'name'   => __('Lead Platform'),
				'url'    => $admin_path . 'index.php?module=plugins/lead-platform-connector/endpoints',
				'icon'   => 'icon-cloud-upload-outline',
				'module' => 'plugins/lead-platform-connector/endpoints',
				'action' => 'index',
			];

			$menuEntry = [
				'name'   => __('Lead Platform'),
				'url'    => $admin_path . 'index.php?module=plugins/lead-platform-connector/endpoints',
				'icon'   => 'icon-cloud-upload-outline',
				'module' => 'plugins/lead-platform-connector/endpoints',
				'action' => 'index',
				'items'  => [
					'endpoints' => [
						'name'   => __('Endpoints'),
						'url'    => $admin_path . 'index.php?module=plugins/lead-platform-connector/endpoints',
						'icon'   => 'icon-cog-outline',
						'module' => 'plugins/lead-platform-connector/endpoints',
						'action' => 'index',
					],
					'submissions' => [
						'name'   => __('Submissions'),
						'url'    => $admin_path . 'index.php?module=plugins/lead-platform-connector/submissions',
						'icon'   => 'icon-list-outline',
						'module' => 'plugins/lead-platform-connector/submissions',
						'action' => 'index',
					],
				],
			];

			$menu = arrayInsertArrayAfter('users', $menu, ['lead-platform' => $menuEntry]);

			return [$menu];
		});

		Event::on('Vvveb\Controller\Editor\Editor', 'loadThemeAssets', __CLASS__, function ($assets) {
			$assets['components']['lead-platform-connector'] =
				'../../plugins/lead-platform-connector/editor/components.js';

			return [$assets];
		});

		Event::on('Vvveb\System\Extensions\Plugins', 'setup', __CLASS__, function ($pluginName, $siteId) {
			if ($pluginName == 'lead-platform-connector') {
				$this->install();
			}

			return [$pluginName, $siteId];
		});
	}

	function install() {
		$install = new Install();
		$install->run();

		// Bust any stale generated-model files so they regenerate against the
		// now-existing tables. Without this, models cached when the tables were
		// missing keep an empty column whitelist → INSERTs with no fields.
		$this->clearGeneratedModelCache();
	}

	/**
	 * Delete cached SQL models for this plugin. The framework regenerates them
	 * on next use by introspecting live columns.
	 */
	private function clearGeneratedModelCache() {
		$dirs = [
			DIR_ROOT . 'storage/model/admin/plugins/leadplatformconnector',
			DIR_ROOT . 'storage/model/app/plugins/leadplatformconnector',
		];
		foreach ($dirs as $dir) {
			if (! is_dir($dir)) {
				continue;
			}
			foreach ((array) glob($dir . '/*.php') as $file) {
				@unlink($file);
			}
		}
	}

	/**
	 * Run install if our tables are missing. Cached via filesystem flag so we
	 * don't hit the DB on every admin request.
	 */
	function ensureInstalled() {
		// Use a flag file so we don't hit the DB on every admin request.
		// Delete it (or drop the tables) to re-trigger install.
		$flag = DIR_ROOT . 'storage/cache/lpc-installed';
		if (is_file($flag)) {
			return;
		}

		try {
			$exists = $this->leadEndpointTableExists();

			if (! $exists) {
				$this->install();
				// Re-check: only set the flag if install actually succeeded.
				// If install silently failed (perms, DB not ready), leave the
				// flag absent so we retry on the next admin request.
				$exists = $this->leadEndpointTableExists();
			}

			if ($exists) {
				@touch($flag);
			}
		} catch (\Throwable $e) {
			// Don't break the admin if the DB isn't ready yet (e.g. during install).
		}
	}

	private function leadEndpointTableExists(): bool {
		$db = Db::getInstance();
		$stmt = $db->execute('SHOW TABLES LIKE :name', ['name' => 'lead_endpoint']);
		if (! $stmt) {
			return false;
		}
		if (method_exists($stmt, 'get_result')) {
			$res = $stmt->get_result();
			return (bool) ($res && $res->num_rows > 0);
		}
		$rows = $db->fetchAll($stmt);
		return is_array($rows) && count($rows) > 0;
	}

	function app() {
		// Inject <script src=".../lead-form.js"> into every visitor-facing page
		// via output buffering. We can't rely on the vtpl-based template that
		// used to live in app/template/lead-form.tpl because vtpl doesn't
		// recompile when only a plugin .tpl changes (view::checkNeedRecompile
		// only inspects theme/tpl mtimes), and the @leadform|attr selector
		// pattern was not reliably matching on native html/form blocks. The
		// runtime form discovers its CSRF token via a fetch() to the plugin's
		// public token endpoint instead of reading it from a server-written
		// data-csrf attribute, so we don't need the template engine at all
		// here — just need the JS on the page.
		ob_start(function ($html) {
			if (! is_string($html) || $html === '') return $html;
			// Only inject when a Lead Form is present on the page.
			if (strpos($html, 'data-v-endpoint=') === false) return $html;
			if (strpos($html, 'plugins/lead-platform-connector/js/lead-form.js') !== false) return $html;

			$src = (defined('PUBLIC_PATH') ? PUBLIC_PATH : '/') . 'plugins/lead-platform-connector/js/lead-form.js';
			$tag = '<script src="' . htmlspecialchars($src, ENT_QUOTES) . '" defer></script>';

			// Inject before </body> if present, otherwise append.
			$pos = stripos($html, '</body>');
			if ($pos !== false) {
				return substr($html, 0, $pos) . $tag . substr($html, $pos);
			}
			return $html . $tag;
		});
	}

	function __construct() {
		if (APP == 'admin') {
			$this->admin();
		} else {
			if (APP == 'app') {
				$this->app();
			}
		}
	}
}

$leadPlatformConnectorPlugin = new LeadPlatformConnectorPlugin();
