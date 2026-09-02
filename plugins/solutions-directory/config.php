<?php

// Every public path and template name the directory renders lives here so a
// deployment can move a URL without editing the presenter or the controllers.
// The defaults are the values the seed and the theme templates ship with.
return [
	'post_type'       => 'solution',
	'post_template'   => 'content/solution.html',
	'meta_namespace'  => 'solution',
	'endpoint_slug'   => 'solution-registration',

	// Language used for seeded terms and for drafts created from the admin
	// queue. Resolved against `language.slug` / `language.code`, never against
	// the admin session, so an English admin still files French drafts.
	'language_slug'   => 'fr',

	// Public URLs.
	'directory_url'   => '/annuaire',
	'solution_url'    => '/solution/',
	'registration_url'=> '/annuaire/referencer-une-solution',
	'contact_url'     => '/contact',
	'privacy_url'     => '/confidentialite',

	// Theme template rendered by the term routes.
	'directory_template' => 'content/annuaire.fr.html',

	// Admin module that turns a queued registration into a draft listing.
	'draft_module'    => 'plugins/solutions-directory/draft',

	'taxonomies'      => [
		'categorie'     => 'categorie',
		'alternative_a' => 'alternative-a',
	],
];
