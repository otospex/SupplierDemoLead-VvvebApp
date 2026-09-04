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
		'contact_url'      => '/contact',
		'privacy_url'      => '/confidentialite',
		'diagnostic_url'   => '/diagnostic-souverainete',
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

	private static function hubs(array $context): string {
		$directory = self::url('directory_url');
		$groups = [
			['Par cas d&rsquo;usage', 'categorie', self::normalizeTerms($context['category_terms'] ?? []), ''],
			['Par solution à remplacer', 'alternative-a', self::normalizeTerms($context['alternative_terms'] ?? []), 'Alternatives à '],
		];
		$html = '';
		foreach ($groups as [$title, $segment, $terms, $prefix]) {
			if (! $terms) {
				continue;
			}
			$html .= '<div class="sd-directory-hub"><h2>' . $title . '</h2><ul>';
			foreach ($terms as $term) {
				$slug = (string) ($term['slug'] ?? '');
				if ($slug === '') {
					continue;
				}
				$html .= '<li><a href="' . self::e($directory . '/' . $segment . '/' . $slug) . '">' . self::e($prefix . ($term['name'] ?? $slug)) . '</a></li>';
			}
			$html .= '</ul></div>';
		}

		return $html === '' ? '' : '<nav class="sd-directory-hubs" aria-label="Parcourir l&rsquo;annuaire">' . $html . '</nav>';
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

		// A term page (layout "term") owns its whole main: a full-bleed hero,
		// then the results beside the sidebar. Everything else (the hub, the
		// guide embeds) renders inside the container the template provides.
		$termLayout = ($context['layout'] ?? '') === 'term' && ! empty($context['term_name']);
		$heading = '';
		if (! empty($context['term_name'])) {
			$title = ($context['taxonomy'] ?? '') === 'alternative-a'
				? 'Alternatives à ' . $context['term_name']
				: $context['term_name'];
			$count = count($rows);
			$countLabel = $count === 0 ? '' : '<p class="sd-directory-count">' . $count . ' solution' . ($count > 1 ? 's' : '') . ' documentée' . ($count > 1 ? 's' : '') . '</p>';
			if ($termLayout) {
				$heading = '<section class="sd-directory-hero sd-page-hero"><div class="container">'
					. '<nav class="sd-breadcrumb"><a href="/">Accueil</a><span>/</span><a href="' . self::e(self::url('directory_url')) . '">Annuaire</a><span>/</span>' . self::e($title) . '</nav>'
					. '<p class="sd-eyebrow">' . (($context['taxonomy'] ?? '') === 'alternative-a' ? 'Solution à remplacer' : 'Cas d&rsquo;usage') . '</p><h1>' . self::e($title) . '</h1>'
					. (empty($context['term_intro']) ? '' : '<div class="sd-directory-context-copy">' . $context['term_intro'] . '</div>')
					. $countLabel . '</div></section>';
			} else {
				$heading = '<header class="sd-directory-context"><p class="sd-eyebrow">Annuaire vérifié</p><h1>' . self::e($title) . '</h1>';
				if (! empty($context['term_intro'])) {
					$heading .= '<div class="sd-directory-context-copy">' . $context['term_intro'] . '</div>';
				}
				$heading .= $countLabel . '</header>';
			}
		}

		$filters = ! empty($context['show_filters']) ? self::filters($context) : '';
		// The unfiltered directory is the hub: every category and every
		// "alternative à" page is linked from here, so none of them is an
		// orphan reachable only through a <select> or a solution's chips.
		if (! empty($context['show_filters']) && empty($context['filtered'])) {
			$filters = self::hubs($context) . $filters;
		}
		if (! $rows) {
			// Two different facts, two different sentences. "No match" blames
			// criteria the reader chose; before anything is published there are
			// no criteria to miss, and telling a launch-day visitor that their
			// filter failed would be simply untrue. A filtered view therefore
			// asks the caller whether the directory holds anything at all
			// ('directory_empty'); with no filter and no term, an empty result
			// already proves it.
			$narrowed = ! empty($context['filtered']) || ! empty($context['term_name']);
			$directoryEmpty = array_key_exists('directory_empty', $context)
				? (bool) $context['directory_empty']
				: ! $narrowed;
			$notice = $directoryEmpty
				? 'L&rsquo;annuaire ouvre ses premières fiches prochainement.'
				: 'Aucune solution publiée ne correspond à ces critères.';

			$empty = '<div class="sd-directory-empty"><p>' . $notice . '</p>'
				. '<a class="sd-btn sd-btn-primary" href="' . self::e(self::url('registration_url')) . '">Référencer une solution</a></div>';

			return $termLayout ? self::termLayout($heading, $filters . $empty, $context) : $heading . $filters . $empty;
		}

		$html = '<div class="sd-solutions-grid">';
		foreach ($rows as $solution) {
			$name = self::e($solution['name'] ?? '');
			$url  = self::e(self::solutionUrl((string) ($solution['slug'] ?? '')));
			$html .= '<article class="sd-solution-card">'
				. '<div class="sd-solution-card-logo-box">' . self::logo($solution, 'sd-solution-card-logo') . '</div>'
				. '<div class="sd-solution-card-top"><span class="sd-solution-kind">' . self::e(self::kindLabel((string) ($solution['kind'] ?? ''))) . '</span>'
				. self::badge($solution) . '</div>'
				. '<h3><a href="' . $url . '">' . $name . '</a></h3>'
				. '<p>' . self::e($solution['excerpt'] ?? '') . '</p>'
				. '<ul class="sd-solution-card-meta">'
				. '<li data-icon="hq">' . self::countryLabel((string) ($solution['hq_country'] ?? '')) . '</li>'
				. '<li data-icon="pricing">' . self::e(self::pricingLabel((string) ($solution['pricing_model'] ?? 'non-communique'))) . '</li></ul>'
				. '<div class="sd-solution-card-links"><a class="sd-link-arrow" href="' . $url . '">Voir la fiche</a>'
				. self::website($solution) . '</div></article>';
		}
		$html .= '</div>';

		return $termLayout ? self::termLayout($heading, $filters . $html, $context) : $heading . $filters . $html;
	}

	/** Hero above, results beside the sidebar below, inside the site container. */
	private static function termLayout(string $hero, string $results, array $context): string {
		return $hero . '<section class="sd-directory-results"><div class="container"><div class="sd-solution-layout"><div class="sd-directory-main">' . $results . '</div>'
			. '<aside class="sd-solution-aside" aria-label="Parcourir et contacter">' . self::browseCard($context) . self::asideWidgets() . '</aside></div></div></section>';
	}

	/**
	 * A public media path is local, under media/, and nothing else: no scheme,
	 * no protocol-relative host, no traversal. Returned rooted at "/".
	 */
	private static function mediaPath($value): string {
		$value = ltrim(trim((string) $value), '/');
		if (! preg_match('#^media/[A-Za-z0-9][A-Za-z0-9._/-]*\.(?:png|jpe?g|webp|svg|gif|avif)$#i', $value) || str_contains($value, '..')) {
			return '';
		}

		return '/' . $value;
	}

	/** Initials of the first two words, for a solution without a logo. */
	private static function monogram(string $name): string {
		$words = preg_split('/[\s\-_]+/u', trim($name)) ?: [];
		$initials = '';
		foreach (array_slice(array_filter($words), 0, 2) as $word) {
			$initials .= mb_strtoupper(mb_substr($word, 0, 1));
		}

		return '<span class="sd-solution-monogram" aria-hidden="true">' . self::e($initials) . '</span>';
	}

	private static function logo(array $solution, string $class): string {
		$path = self::mediaPath($solution['image'] ?? '');
		if ($path === '') {
			return self::monogram((string) ($solution['name'] ?? ''));
		}

		return '<img class="' . $class . '" src="' . self::e($path) . '" alt="" loading="lazy" decoding="async">';
	}

	/**
	 * The `screenshots` meta holds one image per line as "path | caption";
	 * blank lines and non-local paths are dropped.
	 *
	 * @return array<int, array{path: string, caption: string}>
	 */
	private static function screenshots(array $solution): array {
		$items = [];
		foreach (preg_split('/\R/u', (string) ($solution['screenshots'] ?? '')) ?: [] as $line) {
			$parts   = array_map('trim', explode('|', $line, 2));
			$path    = self::mediaPath($parts[0]);
			if ($path === '') {
				continue;
			}
			$items[] = ['path' => $path, 'caption' => (string) ($parts[1] ?? '')];
		}

		return array_slice($items, 0, 6);
	}

	private static function screensSection(string $name, array $screens): string {
		if (! $screens) {
			return '';
		}

		$html = '<section class="sd-solution-screens" aria-labelledby="solution-screens"><h2 id="solution-screens">Captures d&rsquo;écran</h2><div class="sd-solution-screens-grid">';
		foreach ($screens as $index => $screen) {
			$alt = $name . ' — ' . ($screen['caption'] !== '' ? self::e($screen['caption']) : 'capture d&rsquo;écran ' . ($index + 1));
			$html .= '<figure><a href="' . self::e($screen['path']) . '" target="_blank" rel="noopener"><img src="' . self::e($screen['path']) . '" alt="' . $alt . '" loading="lazy" decoding="async"></a>'
				. ($screen['caption'] !== '' ? '<figcaption>' . self::e($screen['caption']) . '</figcaption>' : '')
				. '</figure>';
		}

		return $html . '</div></section>';
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
			. '<fieldset><legend>Visuels</legend><p class="sd-form-help">Liens vers votre logo et jusqu&rsquo;à trois captures d&rsquo;écran (PNG, SVG ou WebP). Ils ne sont utilisés qu&rsquo;après vérification des droits d&rsquo;usage, par un membre de l&rsquo;équipe qui héberge les fichiers sur ce site.</p>'
			. '<div class="sd-form-grid"><label>Logo (lien)<input name="logo_url" type="url" placeholder="https://"></label>'
			. '<label>Capture d&rsquo;écran 1 (lien)<input name="screenshot_urls[]" type="url" placeholder="https://"></label>'
			. '<label>Capture d&rsquo;écran 2 (lien)<input name="screenshot_urls[]" type="url" placeholder="https://"></label>'
			. '<label>Capture d&rsquo;écran 3 (lien)<input name="screenshot_urls[]" type="url" placeholder="https://"></label></div></fieldset>'
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

	private static function countryLabel(string $code): string {
		$code = strtoupper(trim($code));

		return [
			'FR' => 'France',
			'DE' => 'Allemagne',
			'BE' => 'Belgique',
			'CH' => 'Suisse',
			'LU' => 'Luxembourg',
			'NL' => 'Pays-Bas',
			'ES' => 'Espagne',
			'IT' => 'Italie',
			'EU' => 'Union européenne',
		][$code] ?? ($code === '' ? 'Non communiqué' : self::e($code));
	}

	/**
	 * A declared fact arrives as one free-text field where the editor separated
	 * items with semicolons (hosting) or line breaks (qualifications). Each item
	 * keeps its own text, em-dashes included: they are prose, not sub-fields.
	 *
	 * @return string[] escaped items, empty when the field is blank
	 */
	private static function factItems(string $value, string $separator): array {
		$items = [];
		foreach (preg_split($separator, $value) ?: [] as $item) {
			$item = trim($item, " \t\r\n.");
			if ($item !== '') {
				$items[] = self::e($item);
			}
		}

		return $items;
	}

	private static function factList(string $value, string $separator, string $fact, string $placeholder): string {
		$items = self::factItems($value, $separator);
		if (! $items) {
			return self::e($placeholder);
		}

		return '<ul class="sd-solution-list" data-fact="' . $fact . '"><li>' . implode('</li><li>', $items) . '</li></ul>';
	}

	/** One row of the sidebar summary: icon name, label, already-escaped value. */
	private static function asideRow(string $icon, string $label, string $value): string {
		return '<li data-icon="' . $icon . '"><span class="sd-aside-label">' . $label . '</span><span class="sd-aside-value">' . $value . '</span></li>';
	}

	/** The two calls to action shared by solution pages and term pages. */
	private static function asideWidgets(): string {
		$cta = '<section class="sd-aside-card sd-aside-cta" aria-labelledby="solution-aside-cta">'
			. '<h2 class="sd-aside-title" id="solution-aside-cta">Besoin d&rsquo;aide pour choisir&nbsp;?</h2>'
			. '<p>Décrivez votre contexte et vos contraintes&nbsp;: nous vous indiquons quelle solution convient, et à quelles conditions.</p>'
			. '<a class="sd-btn sd-btn-primary" href="' . self::e(self::url('diagnostic_url')) . '">Lancer le diagnostic</a>'
			. '<a class="sd-aside-link" href="' . self::e(self::url('contact_url')) . '">Ou écrivez-nous directement</a>'
			. '</section>';

		$provider = '<section class="sd-aside-card sd-aside-provider" aria-labelledby="solution-aside-provider">'
			. '<h2 class="sd-aside-title" id="solution-aside-provider">Vous éditez une solution souveraine&nbsp;?</h2>'
			. '<p>Proposez-la à l&rsquo;annuaire. Chaque fiche est relue avant publication, sources et dates à l&rsquo;appui.</p>'
			. '<a class="sd-btn sd-btn-secondary" href="' . self::e(self::url('registration_url')) . '">Référencer une solution</a>'
			. '</section>';

		return $cta . $provider;
	}

	/** Sibling terms of the current taxonomy, so a term page is never a dead end. */
	private static function browseCard(array $context): string {
		$alternative = ($context['taxonomy'] ?? '') === 'alternative-a';
		$terms   = self::normalizeTerms($context[$alternative ? 'alternative_terms' : 'category_terms'] ?? []);
		$segment = $alternative ? 'alternative-a' : 'categorie';
		$current = (string) ($context['term_slug'] ?? '');
		$links   = '';
		foreach ($terms as $term) {
			$slug = (string) ($term['slug'] ?? '');
			if ($slug === '' || $slug === $current) {
				continue;
			}
			$links .= '<li><a href="' . self::e(self::termUrl($segment, $slug)) . '">' . self::e(($alternative ? 'Alternatives à ' : '') . ($term['name'] ?? $slug)) . '</a></li>';
		}
		if ($links === '') {
			return '';
		}

		return '<section class="sd-aside-card sd-aside-browse" aria-labelledby="directory-aside-browse">'
			. '<h2 class="sd-aside-title" id="directory-aside-browse">' . ($alternative ? 'Autres solutions à remplacer' : 'Autres cas d&rsquo;usage') . '</h2>'
			. '<ul class="sd-aside-links">' . $links . '</ul>'
			. '<a class="sd-aside-link" href="' . self::e(self::url('directory_url')) . '">Tout l&rsquo;annuaire</a></section>';
	}

	private static function aside(array $solution, string $reviewed): string {
		$verified = ($solution['verification_status'] ?? 'declare') === 'verifie';
		$status   = $verified
			? 'Vérifié par Indépendant Digital' . ($reviewed === '' ? '' : ' le ' . $reviewed)
			: 'Déclaré par l&rsquo;éditeur' . ($reviewed === '' ? '' : ', relu le ' . $reviewed);

		$rows = self::asideRow('kind', 'Type', self::e(self::kindLabel((string) ($solution['kind'] ?? ''))))
			. self::asideRow('hq', 'Siège', self::countryLabel((string) ($solution['hq_country'] ?? '')))
			. self::asideRow('pricing', 'Tarification', self::e(self::pricingLabel((string) ($solution['pricing_model'] ?? 'non-communique'))))
			. self::asideRow($verified ? 'verified' : 'declared', 'Statut', $status);

		$website = self::website($solution);
		$facts   = '<section class="sd-aside-card sd-aside-facts" aria-labelledby="solution-aside-facts">'
			. '<h2 class="sd-aside-title" id="solution-aside-facts">En bref</h2><ul class="sd-aside-list">' . $rows . '</ul>'
			. ($website === '' ? '' : '<p class="sd-aside-site">' . $website . '</p>')
			. '</section>';

		return '<aside class="sd-solution-aside" aria-label="Résumé et contact">' . $facts . self::asideWidgets() . '</aside>';
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

		$html = '<div class="sd-solution-layout"><article class="sd-solution-detail"><header class="sd-solution-hero"><nav class="sd-breadcrumb"><a href="/">Accueil</a><span>/</span><a href="' . self::e(self::url('directory_url')) . '">Annuaire</a><span>/</span>' . $name . '</nav>'
			. '<div class="sd-solution-identity"><div class="sd-solution-logo-box">' . self::logo($solution, 'sd-solution-logo') . '</div>'
			. '<div><span class="sd-solution-kind">' . self::e(self::kindLabel($kind)) . '</span><h1>' . $name . '</h1></div></div>'
			. '<p class="sd-solution-pitch">' . self::e($solution['excerpt'] ?? '') . '</p>' . self::badge($solution)
			. self::termLinks($solution, 'categories', 'categorie')
			. self::termLinks($solution, 'alternative_a', 'alternative-a', 'Alternative à ') . '</header>';

		// The long-form, checkable facts stay in the reading column; the short
		// ones (type, HQ, pricing, status, website) live in the sidebar summary.
		$facts = [
			'Hébergement'             => self::factList((string) ($solution['hosting_countries'] ?? ''), '/\s*;\s*/u', 'hosting', 'Non communiqué'),
			'Qualifications déclarées'=> self::factList((string) ($solution['qualifications'] ?? ''), '/\R+/u', 'qualifications', 'Non communiquées'),
			'Relation commerciale'    => self::e($relationship),
		];
		$html .= '<dl class="sd-solution-facts">';
		foreach ($facts as $label => $value) {
			$html .= '<div><dt>' . self::e($label) . '</dt><dd>' . $value . '</dd></div>';
		}
		$html .= '</dl><div class="sd-solution-body">' . ($solution['content'] ?? '') . '</div>';

		$screens = self::screenshots($solution);
		$html .= self::screensSection($name, $screens);

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
			. '<a href="' . self::e(self::url('contact_url')) . '">Signaler une erreur</a></footer></article>'
			. self::aside($solution, $reviewed) . '</div>';

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
		if (($logo = self::mediaPath($solution['image'] ?? '')) !== '') {
			$jsonLd['logo']  = $logo;
			$jsonLd['image'] = $logo;
		}
		if ($screens) {
			$jsonLd['screenshot'] = array_column($screens, 'path');
		}

		return $html . '<script type="application/ld+json">' . json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) . '</script>';
	}
}
