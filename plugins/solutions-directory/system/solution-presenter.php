<?php

namespace Vvveb\Plugins\SolutionsDirectory\System;

if (! defined('V_VERSION')) {
	die('Invalid request!');
}

final class SolutionPresenter {
	/**
	 * Public paths and template names come from plugins/solutions-directory/config.php
	 * so a deployment can move a URL without editing this renderer. The defaults
	 * below are the values the seed and the theme ship with, which also keeps the
	 * presenter usable from tests without a config file.
	 */
	private const URL_DEFAULTS = [
		'directory_url'    => '/annuaire',
		'solution_url'     => '/solution/',
		'registration_url' => '/annuaire/referencer-une-solution',
		'contact_url'      => '/page/contact',
		'privacy_url'      => '/page/confidentialite',
	];

	private static array $config = [];

	public static function configure(array $config): void {
		self::$config = $config;
	}

	private static function url(string $key): string {
		$value = (string) (self::$config[$key] ?? '');

		return $value !== '' ? $value : (string) (self::URL_DEFAULTS[$key] ?? '');
	}

	/** Path of a taxonomy term page, e.g. /annuaire/alternative-a/microsoft-365. */
	private static function termUrl(string $path, string $slug): string {
		return rtrim(self::url('directory_url'), '/') . '/' . $path . '/' . rawurlencode($slug);
	}

	private static function solutionUrl(string $slug): string {
		return rtrim(self::url('solution_url'), '/') . '/' . rawurlencode($slug);
	}

	private static function e($value): string {
		return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}

	private static function reviewedDate(string $date): string {
		$parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);

		return $parsed ? $parsed->format('d/m/Y') : self::e($date);
	}

	private static function kindLabel(string $kind): string {
		return [
			'logiciel'            => 'Logiciel',
			'hebergeur'           => 'Hébergement et infrastructure',
			'integrateur'         => 'Intégration et accompagnement',
			'ressource-publique'  => 'Ressource publique',
		][$kind] ?? ucfirst(str_replace('-', ' ', $kind));
	}

	private static function pricingLabel(string $pricing): string {
		return [
			'public'          => 'Tarifs publics',
			'sur-devis'       => 'Sur devis',
			'gratuit'         => 'Gratuit',
			'mixte'           => 'Modèle mixte',
			'non-communique'  => 'Non communiqué',
		][$pricing] ?? 'Non communiqué';
	}

	private static function badge(array $solution): string {
		if (($solution['verification_status'] ?? 'declare') === 'verifie') {
			$date = self::reviewedDate((string) ($solution['reviewed_at'] ?? ''));

			return '<span class="sd-solution-badge is-verified">Vérifié par Indépendant Digital le ' . $date . '</span>';
		}

		return '<span class="sd-solution-badge">Déclaré par l&rsquo;éditeur</span>';
	}

	/** The single gate for the declared website: http(s) only, or nothing. */
	private static function websiteUrl(array $solution): string {
		$url = (string) ($solution['website'] ?? '');
		if (! filter_var($url, FILTER_VALIDATE_URL) || ! in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
			return '';
		}

		return $url;
	}

	private static function website(array $solution, string $label = 'Visiter le site'): string {
		$url = self::websiteUrl($solution);
		if ($url === '') {
			return '';
		}

		$rel = ($solution['verification_status'] ?? 'declare') === 'verifie' ? 'noopener' : 'nofollow noopener';

		return '<a class="sd-link-arrow" href="' . self::e($url) . '" rel="' . $rel . '" target="_blank">' . self::e($label) . '</a>';
	}

	private static function normalizeTerms($terms): array {
		if (is_string($terms)) {
			$decoded = json_decode($terms, true);
			$terms = is_array($decoded) ? $decoded : [];
		}

		return is_array($terms) ? $terms : [];
	}

	private static function selected(string $value, string $current): string {
		return $value === $current ? ' selected' : '';
	}

	private static function filters(array $context): string {
		$categories = self::normalizeTerms($context['category_terms'] ?? []);
		$html = '<form class="sd-directory-filters" action="' . self::e(self::url('directory_url')) . '" method="get" aria-label="Filtrer l&rsquo;annuaire">'
			. '<label>Type de solution<select name="kind"><option value="">Tous les types</option>';
		foreach (['logiciel', 'hebergeur', 'integrateur', 'ressource-publique'] as $kind) {
			$html .= '<option value="' . $kind . '"' . self::selected($kind, (string) ($context['selected_kind'] ?? '')) . '>' . self::e(self::kindLabel($kind)) . '</option>';
		}
		$html .= '</select></label><label>Catégorie<select name="categorie"><option value="">Toutes les catégories</option>';
		foreach ($categories as $term) {
			$slug = (string) ($term['slug'] ?? '');
			$html .= '<option value="' . self::e($slug) . '"' . self::selected($slug, (string) ($context['selected_categorie'] ?? '')) . '>' . self::e($term['name'] ?? '') . '</option>';
		}

		return $html . '</select></label><button class="sd-btn sd-btn-primary" type="submit">Appliquer les filtres</button></form>';
	}

	public static function listing(array $rows, array $context = []): string {
		$rows = array_values(array_filter($rows, fn ($row) => ($row['status'] ?? '') === 'publish'));
		usort($rows, function ($left, $right) {
			$date = strcmp((string) ($right['reviewed_at'] ?? ''), (string) ($left['reviewed_at'] ?? ''));

			return $date !== 0 ? $date : strcasecmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''));
		});

		$heading = '';
		if (! empty($context['term_name'])) {
			$title = ($context['taxonomy'] ?? '') === 'alternative-a'
				? 'Alternatives à ' . $context['term_name']
				: $context['term_name'];
			$heading = '<header class="sd-directory-context"><p class="sd-eyebrow">Annuaire vérifié</p><h1>' . self::e($title) . '</h1>';
			if (! empty($context['term_intro'])) {
				$heading .= '<div class="sd-directory-context-copy">' . $context['term_intro'] . '</div>';
			}
			$heading .= '</header>';
		}

		$filters = ! empty($context['show_filters']) ? self::filters($context) : '';
		if (! $rows) {
			return $heading . $filters . '<div class="sd-directory-empty"><p>Aucune solution publiée ne correspond à ces critères.</p>'
				. '<a class="sd-btn sd-btn-primary" href="' . self::e(self::url('registration_url')) . '">Référencer une solution</a></div>';
		}

		$html = $heading . $filters . '<div class="sd-solutions-grid">';
		foreach ($rows as $solution) {
			$name = self::e($solution['name'] ?? '');
			$url  = self::e(self::solutionUrl((string) ($solution['slug'] ?? '')));
			$html .= '<article class="sd-solution-card">'
				. '<div class="sd-solution-card-top"><span class="sd-solution-kind">' . self::e(self::kindLabel((string) ($solution['kind'] ?? ''))) . '</span>'
				. self::badge($solution) . '</div>'
				. '<h2><a href="' . $url . '">' . $name . '</a></h2>'
				. '<p>' . self::e($solution['excerpt'] ?? '') . '</p>'
				. '<div class="sd-solution-card-links"><a class="sd-link-arrow" href="' . $url . '">Voir la fiche</a>'
				. self::website($solution) . '</div></article>';
		}

		return $html . '</div>';
	}

	public static function registrationForm(array $categories, array $alternatives, array $config): string {
		$endpoint = self::e($config['endpoint_slug'] ?? '');
		$html = '<form class="sd-solution-registration-form" method="post" action="" onsubmit="return false" data-v-endpoint="' . $endpoint . '">'
			. '<div class="visually-hidden" aria-hidden="true"><label>Site de votre entreprise<input type="text" name="company_website" tabindex="-1" autocomplete="off"></label></div>'
			. '<fieldset><legend>La solution</legend><div class="sd-form-grid">'
			. '<label>Type de solution<select name="kind" required><option value="">Choisissez</option><option value="logiciel">Logiciel ou SaaS</option><option value="hebergeur">Hébergement, cloud ou infrastructure</option><option value="integrateur">Intégration, conseil ou service managé</option></select></label>'
			. '<label>Nom de la solution<input name="solution_name" type="text" required></label>'
			. '<label>Site officiel<input name="website" type="url" required placeholder="https://"></label>'
			. '<label>Organisation<input name="organisation" type="text" required></label>'
			. '<label>Pays du siège<select name="hq_country" required><option value="">Choisissez</option><option value="FR">France</option>';
		foreach (['AT'=>'Autriche','BE'=>'Belgique','BG'=>'Bulgarie','HR'=>'Croatie','CY'=>'Chypre','CZ'=>'Tchéquie','DK'=>'Danemark','EE'=>'Estonie','FI'=>'Finlande','DE'=>'Allemagne','GR'=>'Grèce','HU'=>'Hongrie','IE'=>'Irlande','IT'=>'Italie','LV'=>'Lettonie','LT'=>'Lituanie','LU'=>'Luxembourg','MT'=>'Malte','NL'=>'Pays-Bas','PL'=>'Pologne','PT'=>'Portugal','RO'=>'Roumanie','SK'=>'Slovaquie','SI'=>'Slovénie','ES'=>'Espagne','SE'=>'Suède'] as $code => $country) {
			$html .= '<option value="' . $code . '">' . self::e($country) . '</option>';
		}
		$html .= '<option value="autre">Autre pays</option></select></label>'
			. '<label>Présentation en une phrase<input name="pitch" type="text" maxlength="160" required></label></div>'
			. '<div class="sd-form-options"><p>Catégories <span aria-hidden="true">*</span></p>';
		foreach ($categories as $term) {
			$html .= '<label><input name="categories[]" type="checkbox" value="' . self::e($term['slug'] ?? '') . '"> ' . self::e($term['name'] ?? '') . '</label>';
		}
		$html .= '</div><div class="sd-form-options"><p>Alternative à</p>';
		foreach ($alternatives as $term) {
			$html .= '<label><input name="alternative_to[]" type="checkbox" value="' . self::e($term['slug'] ?? '') . '"> ' . self::e($term['name'] ?? '') . '</label>';
		}
		$html .= '</div><label>Autre solution remplacée ou complétée<input name="alternative_to_other" type="text"></label></fieldset>'
			. '<fieldset><legend>Votre contact</legend><div class="sd-form-grid"><label>Nom et prénom<input name="contact_name" type="text" required></label><label>Adresse e-mail professionnelle<input name="email" type="email" required></label><label>Fonction<input name="contact_role" type="text"></label></div></fieldset>'
			. '<fieldset><legend>Détails à examiner</legend><label>Avantages et cas d&rsquo;usage<textarea name="advantages" rows="5" required></textarea></label><div class="sd-form-grid"><label>Pays d&rsquo;hébergement<input name="hosting_countries" type="text" placeholder="FR, DE ou non communiqué"></label><label>Qualifications, avec périmètre et date<textarea name="qualifications" rows="3"></textarea></label><label>Modèle tarifaire<select name="pricing_model"><option value="non-communique">Non communiqué</option><option value="public">Tarifs publics</option><option value="sur-devis">Sur devis</option><option value="gratuit">Gratuit</option><option value="mixte">Mixte</option></select></label></div>'
			. '<label class="sd-form-check"><input name="partner_interest" type="checkbox" value="1"> Je souhaite discuter d&rsquo;un partenariat commercial</label>'
			. '<label class="sd-form-check"><input name="accuracy_commitment" type="checkbox" value="1" required> Les informations sont exactes et je peux fournir des preuves</label>'
			. '<label class="sd-form-check"><input name="privacy_acknowledgement" type="checkbox" value="1" required> J&rsquo;ai lu la <a href="' . self::e(self::url('privacy_url')) . '">politique de confidentialité</a> et j&rsquo;accepte le traitement de ces informations pour examiner cette demande.</label></fieldset>'
			. '<div data-v-leadform-error class="alert alert-danger d-none" role="alert"></div><div data-v-leadform-success class="alert alert-success d-none" role="status"></div>'
			. '<button class="sd-btn sd-btn-primary" type="submit">Envoyer la fiche pour revue</button></form>';

		return $html;
	}

	private static function termLinks(array $solution, string $key, string $path, string $prefix = ''): string {
		$terms = self::normalizeTerms($solution[$key] ?? []);
		if (! $terms) {
			return '';
		}

		$html = '<div class="sd-solution-chips">';
		foreach ($terms as $term) {
			$html .= '<a href="' . self::e(self::termUrl($path, (string) ($term['slug'] ?? ''))) . '">'
				. self::e($prefix . ($term['name'] ?? '')) . '</a>';
		}

		return $html . '</div>';
	}

	public static function detail(array $solution, array $alternatives = []): string {
		if (($solution['status'] ?? '') !== 'publish') {
			return '';
		}

		$name = self::e($solution['name'] ?? '');
		$kind = (string) ($solution['kind'] ?? '');
		$reviewed = self::reviewedDate((string) ($solution['reviewed_at'] ?? ''));
		$relationship = ($solution['commercial_relationship'] ?? 'aucune') === 'partenaire-non-exclusif'
			? 'Partenaire commercial non exclusif'
			: 'Aucune relation commerciale déclarée';

		$html = '<article class="sd-solution-detail"><header class="sd-solution-hero"><nav class="sd-breadcrumb"><a href="/">Accueil</a><span>/</span><a href="' . self::e(self::url('directory_url')) . '">Annuaire</a><span>/</span>' . $name . '</nav>'
			. '<span class="sd-solution-kind">' . self::e(self::kindLabel($kind)) . '</span><h1>' . $name . '</h1>'
			. '<p class="sd-solution-pitch">' . self::e($solution['excerpt'] ?? '') . '</p>' . self::badge($solution)
			. self::termLinks($solution, 'categories', 'categorie')
			. self::termLinks($solution, 'alternative_a', 'alternative-a', 'Alternative à ') . '</header>';

		$facts = [
			'Site'                    => self::website($solution),
			'Siège'                   => self::e($solution['hq_country'] ?? 'Non communiqué'),
			'Hébergement'             => self::e($solution['hosting_countries'] ?? 'Non communiqué'),
			'Tarification'            => self::e(self::pricingLabel((string) ($solution['pricing_model'] ?? 'non-communique'))),
			'Qualifications déclarées'=> nl2br(self::e($solution['qualifications'] ?? 'Non communiquées')),
			'Relation commerciale'    => self::e($relationship),
		];
		$html .= '<dl class="sd-solution-facts">';
		foreach ($facts as $label => $value) {
			$html .= '<div><dt>' . self::e($label) . '</dt><dd>' . ($value ?: 'Non communiqué') . '</dd></div>';
		}
		$html .= '</dl><div class="sd-solution-body">' . ($solution['content'] ?? '') . '</div>';

		$html .= '<section class="sd-solution-alternatives" aria-labelledby="solution-alternatives"><h2 id="solution-alternatives">Alternatives</h2>';
		if ($alternatives) {
			$html .= self::listing($alternatives);
		} else {
			$html .= '<p>Aucune autre solution publiée ne partage encore ces catégories ou alternatives.</p>';
		}
		$html .= '</section>';

		// Wording fixed by the 2026-08-27 spec §6: the disclosure names the solution.
		if (($solution['commercial_relationship'] ?? '') === 'partenaire-non-exclusif') {
			$html .= '<p class="sd-disclosure">' . $name . ' est un partenaire commercial non exclusif d&rsquo;Indépendant Digital. '
				. 'Nous pouvons être rémunérés pour certaines mises en relation qualifiées. '
				. 'Ce partenariat n&rsquo;entraîne aucune recommandation automatique et ' . $name
				. ' est évalué selon la même méthode que les autres solutions.</p>';
		}

		// A fiche created from the registration queue has no reviewer and no
		// review date until an editor sets them, and the sentence has to stay
		// grammatical (and truthful) in that state.
		$reviewer = trim((string) ($solution['reviewer'] ?? '')) ?: 'Indépendant Digital';
		$html .= '<footer class="sd-solution-review"><p>Revue par ' . self::e($reviewer) . ($reviewed === '' ? '' : ' le ' . $reviewed) . '.</p>'
			. '<a href="' . self::e(self::url('contact_url')) . '">Signaler une erreur</a></footer></article>';

		// Same scheme-validated URL as the visible link; omitted when there is none,
		// so the markup can never advertise a URL the page refuses to link to.
		$jsonLd = [
			'@context'   => 'https://schema.org',
			'@type'      => $kind === 'logiciel' ? 'SoftwareApplication' : 'Organization',
			'name'       => (string) ($solution['name'] ?? ''),
			'areaServed' => 'FR',
		];
		if (($website = self::websiteUrl($solution)) !== '') {
			$jsonLd['url'] = $website;
		}

		return $html . '<script type="application/ld+json">' . json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) . '</script>';
	}
}
