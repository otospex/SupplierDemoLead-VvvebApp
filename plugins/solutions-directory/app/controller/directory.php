<?php

namespace Vvveb\Plugins\SolutionsDirectory\Controller;

use Vvveb\Controller\Base;

class Directory extends Base {
	function index() {
		$this->view->template('content/annuaire.fr.html');
	}
}
