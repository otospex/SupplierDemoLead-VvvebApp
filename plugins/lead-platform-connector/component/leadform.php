<?php

namespace Vvveb\Plugins\LeadPlatformConnector\Component;

use function Vvveb\__;
use Vvveb\System\Component\ComponentBase;
use Vvveb\System\Event;
use Vvveb\Plugins\LeadPlatformConnector\System\CsrfToken;

if (! defined('V_VERSION')) {
	die('Invalid request!');
}

class LeadForm extends ComponentBase {

	public static $defaultOptions = [
		'endpoint'      => '',
		'success_url'   => '',
		'success_msg'   => 'Thanks — your request was received.',
		'error_msg'     => 'Sorry, something went wrong. Please try again.',
		'honeypot'      => 'company_website',
		'min_time_ms'   => 1500,
	];

	public $cacheExpire = 0;

	function results() {
		$results = [
			'csrf'        => '',
			'endpoint'    => $this->options['endpoint'] ?? '',
			'submit_url'  => '/index.php?module=plugins/lead-platform-connector/submit',
			'honeypot'    => $this->options['honeypot'] ?? 'company_website',
			'success_url' => $this->options['success_url'] ?? '',
			'success_msg' => $this->options['success_msg'] ?? '',
			'error_msg'   => $this->options['error_msg'] ?? '',
			'render_ts'   => (int) (microtime(true) * 1000),
		];

		if (! empty($results['endpoint'])) {
			$results['csrf'] = CsrfToken::issue($results['endpoint']);
		}

		[$results] = Event::trigger(__CLASS__, __FUNCTION__, $results);

		return $results;
	}
}
