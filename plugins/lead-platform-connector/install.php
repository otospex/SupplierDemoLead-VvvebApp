<?php

namespace Vvveb\Plugins\LeadPlatformConnector;

use function Vvveb\__;
use Vvveb\System\Db;
use Vvveb\System\Import\Sql;

if (! defined('V_VERSION')) {
	die('Invalid request!');
}

#[\AllowDynamicProperties]
class Install {
	private function migrateLeadSubmission(string $engine): void {
		$definitions = [
			'provider_slug' => $engine === 'sqlite' ? 'TEXT DEFAULT NULL' : 'varchar(64) DEFAULT NULL',
			'consent_text_version' => $engine === 'sqlite' ? 'TEXT DEFAULT NULL' : 'varchar(64) DEFAULT NULL',
			'consent_at' => $engine === 'pgsql' ? 'timestamp(0) DEFAULT NULL' : 'datetime DEFAULT NULL',
			'payload_enc' => $engine === 'mysqli' ? 'longtext DEFAULT NULL' : 'TEXT DEFAULT NULL',
		];
		$db = Db::getInstance();
		foreach ($definitions as $column => $definition) {
			$ifMissing = $engine === 'pgsql' ? ' IF NOT EXISTS' : '';
			try {
				$db->execute("ALTER TABLE lead_submission ADD COLUMN$ifMissing $column $definition");
			} catch (\Throwable $ignored) {
				// MySQL and SQLite lack portable IF NOT EXISTS support here; a
				// duplicate-column error is expected on an already migrated site.
			}
		}
		$verified = $db->execute('SELECT provider_slug, consent_text_version, consent_at, payload_enc FROM lead_submission WHERE 1 = 0');
		if ($verified === false) {
			throw new \RuntimeException('lead_submission migration verification failed');
		}
	}

	function import() {
		try {
			$engine = DB_ENGINE;
			$import = new Sql();
			$import->setPath(__DIR__ . "/install/sql/$engine/schema/");
			$import->createTables();
			$this->migrateLeadSubmission($engine);
		} catch (\Exception $e) {
			$this->view->errors[] = sprintf(__('Db error: "%s" Error code: "%s"'), $e->getMessage(), $e->getCode());
		}
	}

	function run() {
		$this->import();
	}
}
