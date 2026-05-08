<?php

/*
Name: Souveraineté Digitale
Slug: souverainete-digitale
URI: https://vvveb.com
Author: Otospex
Author URI: https://vvveb.com
Description: Clean B2B landing page focused on Digital Sovereignty consultations.
Version: 1.0
License:  Apache 2.0
License URI: https://vvveb.com/licence/
Tags: landing, b2b, lead-generation, sovereignty
Text Domain: souverainete-digitale
*/
use function Vvveb\__;

return
	[
		'pages' => [
		],
		'components' => [
			['title' =>  __('Content'), 'name' =>  'content'],
			['title' => __('Bootstrap 5'), 'name' =>  'bootstrap5'],
		],
		'inputs' => [
		],
		'ignoreFolders' => ['backup'],
	];
