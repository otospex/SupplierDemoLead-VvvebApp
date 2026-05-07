<?php

namespace Vvveb\Plugins\LeadPlatformConnector\Controller;

use Vvveb\Controller\Listing;

class Submissions extends Listing {

	protected $type = 'lead_submission';

	protected $modelName = 'Plugins\LeadPlatformConnector\LeadSubmission';

	protected $list = 'lead_submission';

	protected $listController = 'submissions';

	protected $controller = 'submissions';

	protected $module = 'plugins/lead-platform-connector';

	function index() {
		parent::index();
	}
}
