<?php

namespace Vvveb\Plugins\SolutionsDirectory\Controller;

use function Vvveb\adminPath;
use function Vvveb\model;
use Vvveb\Controller\Base;
use Vvveb\Plugins\LeadPlatformConnector\System\Crypto;
use Vvveb\Plugins\SolutionsDirectory\System\DraftCreator;
use Vvveb\Plugins\SolutionsDirectory\System\VvvebDraftStore;

class Draft extends Base {
	private function back(string $message): void {
		header('Location: ' . adminPath() . 'index.php?module=plugins/lead-platform-connector/submissions&errors=' . rawurlencode($message));
		exit;
	}

	function index() {
		$submissionId = (int) ($this->request->get['lead_submission_id'] ?? 0);
		$config = require dirname(__DIR__, 2) . '/config.php';
		$csrf = (string) ($this->request->get['csrf'] ?? '');
		$sessionCsrf = (string) $this->session->get('csrf');
		if ($csrf === '' || $sessionCsrf === '' || ! hash_equals($sessionCsrf, $csrf)) {
			$this->back('Cette action a expiré. Rechargez la liste puis réessayez.');
		}
		if (! $submissionId) {
			$this->back('Soumission introuvable.');
		}

		$submission = model('Plugins\LeadPlatformConnector\LeadSubmission')->get(['lead_submission_id' => $submissionId]);
		if (! $submission || ($submission['endpoint_slug'] ?? '') !== $config['endpoint_slug']) {
			$this->back('Cette soumission ne correspond pas au formulaire de référencement.');
		}

		try {
			$payload = json_decode(Crypto::decrypt((string) ($submission['payload_enc'] ?? '')), true);
			if (! is_array($payload)) {
				throw new \RuntimeException('Invalid registration payload.');
			}
			$config += [
				'language_id' => (int) ($this->global['language_id'] ?? 0),
				'site_id' => (int) ($this->global['site_id'] ?? 0),
				'admin_id' => (int) ($this->global['admin_id'] ?? 0),
			];
			$creator = new DraftCreator(new VvvebDraftStore($config), $config);
			$result = $creator->createOrFind($submissionId, $payload);
		} catch (\Throwable $e) {
			$this->back('La fiche brouillon n&rsquo;a pas pu être créée.');
		}

		header('Location: ' . adminPath() . 'index.php?module=content/post&post_id=' . (int) $result['post_id'] . '&type=' . rawurlencode($config['post_type']));
		exit;
	}
}
