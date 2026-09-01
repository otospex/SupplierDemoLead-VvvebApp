<?php

namespace Vvveb\Plugins\SolutionsDirectory\Controller;

use function Vvveb\adminPath;
use function Vvveb\model;
use Vvveb\Controller\Base;
use Vvveb\Plugins\LeadPlatformConnector\System\Crypto;
use Vvveb\Plugins\SolutionsDirectory\System\DraftCreator;
use Vvveb\Plugins\SolutionsDirectory\System\VvvebDraftStore;
use Vvveb\System\Db;

class Draft extends Base {
	private function back(string $message): void {
		header('Location: ' . adminPath() . 'index.php?module=plugins/lead-platform-connector/submissions&errors=' . rawurlencode($message));
		exit;
	}

	/**
	 * The listing language is a property of the site, not of whoever is logged
	 * in: an admin browsing in English must still file a French draft. Resolved
	 * exactly like the plugin installer does, with the session language kept
	 * only as a last resort. Returns [language_id, human-readable source].
	 */
	private function resolveLanguage(array $config): array {
		$slug = (string) ($config['language_slug'] ?? 'fr');

		try {
			$db   = Db::getInstance();
			$stmt = $db->execute(
				'SELECT language_id FROM language WHERE slug = :slug OR code LIKE :code ORDER BY language_id LIMIT 1',
				['slug' => $slug, 'code' => $slug . '%']
			);
			$row = $stmt ? $db->fetchArray($stmt) : [];
			$id  = (int) (($row['language_id'] ?? 0));

			if ($id) {
				return [$id, sprintf('langue « %s » du site', $slug)];
			}
		} catch (\Throwable $e) {
			// fall through to the session language
		}

		return [(int) ($this->global['language_id'] ?? 0), 'langue de la session d’administration'];
	}

	function index() {
		$config       = require dirname(__DIR__, 2) . '/config.php';
		$submissionId = (int) ($this->request->post['lead_submission_id'] ?? 0);
		$csrf         = (string) ($this->request->post['csrf'] ?? '');
		$sessionCsrf  = (string) $this->session->get('csrf');

		// State-changing action: POST only, so the token never reaches a URL,
		// a Referer header or an access log.
		if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
			$this->back('Cette action doit être envoyée depuis la file des soumissions.');
		}
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

		[$languageId, $languageSource] = $this->resolveLanguage($config);

		try {
			$payload = json_decode(Crypto::decrypt((string) ($submission['payload_enc'] ?? '')), true);
			if (! is_array($payload)) {
				throw new \RuntimeException('Invalid registration payload.');
			}
			$config += [
				'language_id' => $languageId,
				'site_id'     => (int) ($this->global['site_id'] ?? 0),
				'admin_id'    => (int) ($this->global['admin_id'] ?? 0),
			];
			$creator = new DraftCreator(new VvvebDraftStore($config), $config);
			$result  = $creator->createOrFind($submissionId, $payload);
		} catch (\Throwable $e) {
			$this->back('La fiche brouillon n’a pas pu être créée.');
		}

		$notice = $result['created']
			? sprintf('Fiche brouillon créée (%s).', $languageSource)
			: sprintf('Fiche brouillon déjà existante (%s).', $languageSource);

		header('Location: ' . adminPath() . 'index.php?module=content/post&post_id=' . (int) $result['post_id']
			. '&type=' . rawurlencode($config['post_type'])
			. '&success=' . rawurlencode($notice));
		exit;
	}
}
