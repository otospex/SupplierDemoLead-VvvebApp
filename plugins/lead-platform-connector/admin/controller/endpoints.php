<?php

namespace Vvveb\Plugins\LeadPlatformConnector\Controller;

use Vvveb\Controller\Listing;
use function Vvveb\url;

class Endpoints extends Listing {

	protected $type = 'lead_endpoint';

	protected $modelName = 'Plugins\LeadPlatformConnector\LeadEndpoint';

	protected $list = 'lead_endpoint';

	protected $listController = 'endpoints';

	protected $controller = 'endpoint';

	protected $module = 'plugins/lead-platform-connector';

	function index() {
		parent::index();

		// Build edit/delete URLs into each row.
		if (isset($this->view->lead_endpoint) && is_array($this->view->lead_endpoint)) {
			foreach ($this->view->lead_endpoint as &$row) {
				$row['edit-url']   = url(['module' => $this->module . '/endpoint', 'lead_endpoint_id' => $row['lead_endpoint_id']]);
				$row['delete-url'] = url(['module' => $this->module . '/endpoints', 'action' => 'delete', 'lead_endpoint_id[]' => $row['lead_endpoint_id']]);
			}
		}
	}
}
