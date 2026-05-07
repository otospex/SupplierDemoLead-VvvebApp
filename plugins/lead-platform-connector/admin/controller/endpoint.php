<?php

namespace Vvveb\Plugins\LeadPlatformConnector\Controller;

use function Vvveb\__;
use function Vvveb\model;
use Vvveb\Controller\Crud;
use Vvveb\System\Core\Request;
use Vvveb\Plugins\LeadPlatformConnector\System\Crypto;

class Endpoint extends Crud {

	protected $type = 'lead_endpoint';

	protected $modelName = 'Plugins\LeadPlatformConnector\LeadEndpoint';

	protected $module = 'plugins/lead-platform-connector';

	function index() {
		$req = Request::getInstance();

		// Custom save: encrypt API key, normalise JSON fields, then delegate to model.
		if (! empty($req->post['save']) || ($req->post['action'] ?? '') === 'save') {
			$this->customSave($req->post);
			$id = (int) ($req->post['lead_endpoint_id'] ?? 0);
			$adminPath = \Vvveb\adminPath();
			header('Location: ' . $adminPath . 'index.php?module=' . $this->module . '/endpoints');
			exit;
		}

		parent::index();

		// Massage record for display (decode allowed_origins JSON, present masked key placeholder)
		if (isset($this->view->lead_endpoint) && is_array($this->view->lead_endpoint)) {
			$ep =& $this->view->lead_endpoint;

			$decoded = json_decode($ep['allowed_origins'] ?? '', true);
			$ep['allowed_origins_text'] = is_array($decoded) ? implode("\n", $decoded) : ($ep['allowed_origins'] ?? '');

			$ep['api_key_placeholder'] = ! empty($ep['api_key_enc'])
				? __('Leave blank to keep current key')
				: 'lp_...';

			// Bind edit/save back to the same form module.
			$ep['edit-url'] = \Vvveb\adminPath() . 'index.php?module=' . $this->module . '/endpoint';
		} else {
			// New record defaults
			$this->view->lead_endpoint = [
				'lead_endpoint_id'      => 0,
				'slug'                  => '',
				'label'                 => '',
				'platform_url'          => '',
				'campaign'              => '',
				'field_map'             => '',
				'allowed_origins'       => '',
				'allowed_origins_text'  => '',
				'rate_limit'            => 30,
				'active'                => 1,
				'api_key_placeholder'   => 'lp_...',
			];
		}
	}

	private function customSave(array $post): void {
		$id           = (int) ($post['lead_endpoint_id'] ?? 0);
		$slug         = preg_replace('/[^a-z0-9_-]+/i', '-', strtolower(trim((string) ($post['slug'] ?? ''))));
		$label        = trim((string) ($post['label'] ?? ''));
		$platform_url = trim((string) ($post['platform_url'] ?? ''));
		$campaign     = trim((string) ($post['campaign'] ?? ''));
		$field_map    = trim((string) ($post['field_map'] ?? ''));
		$allowed      = trim((string) ($post['allowed_origins'] ?? ''));
		$rate_limit   = max(0, (int) ($post['rate_limit'] ?? 30));
		$active       = ! empty($post['active']) ? 1 : 0;
		$apiKeyPlain  = (string) ($post['api_key'] ?? '');

		if ($slug === '' || $platform_url === '' || $campaign === '') {
			return;
		}

		$field_map_json = '';
		if ($field_map !== '') {
			$decoded = json_decode($field_map, true);
			$field_map_json = is_array($decoded) ? json_encode($decoded) : '';
		}
		$allowed_json = '';
		if ($allowed !== '') {
			$list = array_filter(array_map('trim', preg_split('/[\s,]+/', $allowed)));
			$allowed_json = $list ? json_encode(array_values($list)) : '';
		}

		$record = [
			'slug'            => $slug,
			'label'           => $label,
			'platform_url'    => $platform_url,
			'campaign'        => $campaign,
			'field_map'       => $field_map_json,
			'allowed_origins' => $allowed_json,
			'rate_limit'      => $rate_limit,
			'active'          => $active,
		];

		$model = model($this->modelName);

		if ($id > 0) {
			if ($apiKeyPlain !== '') {
				$record['api_key_enc'] = Crypto::encrypt($apiKeyPlain);
			}
			$model->edit(['lead_endpoint' => $record, 'lead_endpoint_id' => $id]);
		} else {
			$record['api_key_enc'] = $apiKeyPlain !== '' ? Crypto::encrypt($apiKeyPlain) : '';
			$model->add(['lead_endpoint' => $record]);
		}
	}
}
