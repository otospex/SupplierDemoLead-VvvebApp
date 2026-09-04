<?php

namespace Vvveb\Plugins\SolutionsDirectory\System;

if (! defined('V_VERSION')) {
	die('Invalid request!');
}

require_once __DIR__ . '/draft-mapper.php';

final class DraftCreator {
	private $store;
	private array $config;

	function __construct($store, array $config) {
		$this->store = $store;
		$this->config = $config;
	}

	public function createOrFind(int $submissionId, array $fields): array {
		$existing = $this->store->findBySubmission($submissionId);
		if ($existing) {
			return ['post_id' => (int) $existing, 'created' => false];
		}

		$draft = DraftMapper::map($fields, $this->config, $submissionId);
		$postId = $this->store->create($draft);

		return ['post_id' => (int) $postId, 'created' => true];
	}
}
