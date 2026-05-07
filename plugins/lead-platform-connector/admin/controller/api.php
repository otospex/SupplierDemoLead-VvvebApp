<?php

namespace Vvveb\Plugins\LeadPlatformConnector\Controller;

use Vvveb\Plugins\LeadPlatformConnector\System\Repo;

#[\AllowDynamicProperties]
class Api {

	function __construct() {}

	private function json(int $http, $body): void {
		http_response_code($http);
		header('Content-Type: application/json; charset=utf-8');
		header('Cache-Control: private, max-age=10');
		header('X-Robots-Tag: noindex, nofollow');
		echo json_encode($body);
		exit;
	}

	/**
	 * Lists active endpoints with their field maps for editor consumption.
	 * URL: /admin/index.php?module=plugins/lead-platform-connector/api&action=endpoints
	 */
	function endpoints() {
		try {
			$rows = Repo::many(
				'SELECT slug, label, campaign, field_map, active
				 FROM lead_endpoint WHERE active = 1 ORDER BY slug ASC'
			);
		} catch (\Throwable $e) {
			$this->json(500, ['ok' => false, 'message' => 'DB error']);
		}

		$out = [];
		foreach ($rows as $r) {
			$fieldMap = null;
			if (! empty($r['field_map'])) {
				$decoded = json_decode($r['field_map'], true);
				if (is_array($decoded)) $fieldMap = $decoded;
			}
			$out[] = [
				'slug'      => $r['slug'],
				'label'     => $r['label'] ?: $r['slug'],
				'campaign'  => $r['campaign'],
				'field_map' => $fieldMap,
			];
		}

		$this->json(200, ['ok' => true, 'endpoints' => $out]);
	}

	function index() {
		$this->json(404, ['ok' => false, 'message' => 'Specify ?action=endpoints']);
	}
}
