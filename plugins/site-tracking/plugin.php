<?php
/*
Name: Site Tracking
Slug: site-tracking
Category: integrations
Description: Audience measurement (cookieless Matomo) and consent-gated marketing tags, configured from the admin.
Author: Indépendant Digital
Version: 0.1.0
Settings: /admin/index.php?module=plugins/site-tracking/settings
*/

use function Vvveb\__;
use Vvveb\System\Event;

if (! defined('V_VERSION')) {
	die('Invalid request!');
}

require_once __DIR__ . '/system/tracking.php';
require_once __DIR__ . '/system/tracking-settings.php';

#[\AllowDynamicProperties]
class SiteTrackingPlugin {
	function __construct() {
		if (APP === 'admin') {
			$adminPath = \Vvveb\adminPath();
			Event::on('Vvveb\Controller\Base', 'init-menu', __CLASS__, function ($menu) use ($adminPath) {
				$menu['plugins']['items']['site-tracking'] = [
					'name'   => __('Suivi et consentement'),
					'url'    => $adminPath . 'index.php?module=plugins/site-tracking/settings',
					'icon'   => 'icon-analytics-outline',
					'module' => 'plugins/site-tracking/settings',
					'action' => 'index',
				];
				if (isset($menu['settings']['items']) && is_array($menu['settings']['items'])) {
					$menu['settings']['items']['site-tracking'] = $menu['plugins']['items']['site-tracking'];
				}

				return [$menu];
			});
		}
	}
}

$siteTrackingPlugin = new SiteTrackingPlugin();
