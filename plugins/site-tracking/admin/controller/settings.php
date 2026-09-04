<?php

namespace Vvveb\Plugins\SiteTracking\Controller;

use function Vvveb\__;
use Vvveb\Controller\Base;
use Vvveb\Plugins\SiteTracking\System\TrackingSettings;
use Vvveb\System\CacheManager;

class Settings extends Base {
	function index() {
		require_once dirname(__DIR__, 2) . '/system/tracking.php';
		require_once dirname(__DIR__, 2) . '/system/tracking-settings.php';

		if ($this->request->getMethod() === 'post' && ($this->request->post['action'] ?? '') === 'save') {
			if ($this->checkCsrf() === false) {
				return;
			}
			TrackingSettings::save($this->request->post['tracking'] ?? []);
			// Pages are cached with the tags inlined: purge so the change shows now.
			if (class_exists(CacheManager::class) && method_exists(CacheManager::class, 'clearPageCache')) {
				try {
					CacheManager::clearPageCache();
				} catch (\Throwable $e) {
				}
			}
			$this->view->success['save'] = __('Tracking settings saved.');
		}

		$this->view->tracking = TrackingSettings::load(null) + ['edit-url' => \Vvveb\adminPath() . 'index.php?module=plugins/site-tracking/settings'];
	}
}
