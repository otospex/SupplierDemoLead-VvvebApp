<?php

namespace Vvveb\Plugins\LeadPlatformConnector;

use function Vvveb\__;
use Vvveb\System\Import\Sql;

if (! defined('V_VERSION')) {
	die('Invalid request!');
}

#[\AllowDynamicProperties]
class Install {
	function import() {
		try {
			$engine = DB_ENGINE;
			$import = new Sql();
			$import->setPath(__DIR__ . "/install/sql/$engine/schema/");
			$import->createTables();
		} catch (\Exception $e) {
			$this->view->errors[] = sprintf(__('Db error: "%s" Error code: "%s"'), $e->getMessage(), $e->getCode());
		}
	}

	function run() {
		$this->import();
	}
}
