<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$files = [
	$root . '/public/themes/souverainete-digitale/index.fr.html',
	$root . '/public/themes/souverainete-digitale/content/index.fr.html',
	$root . '/public/themes/souverainete-digitale/content/page.fr.html',
	$root . '/public/themes/souverainete-digitale/content/post.fr.html',
	$root . '/public/themes/souverainete-digitale/content/contact.fr.html',
];

$announcement = <<<'HTML'
<div class="sd-announce">
  <strong>Méthode publique</strong> &middot; Besoin, preuves et conditions de sortie avant toute recommandation. <a href="/page/transparence-partenariats">Voir nos règles &rarr;</a>
</div>
HTML;

$navigation = <<<'HTML'
<nav class="sd-nav navbar navbar-expand-lg" data-v-save-global="index.fr.html,.sd-nav">
  <div class="container">
    <a class="navbar-brand" href="/" data-v-site-url aria-label="Indépendant Digital — Accueil">
      <svg width="32" height="32" viewbox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><defs><lineargradient id="sd-logo-grad" x1="0" y1="0" x2="32" y2="32"><stop offset="0%" stop-color="#0B5FFF"></stop><stop offset="100%" stop-color="#7C3AED"></stop></lineargradient></defs><path d="M16 2L4 7v8c0 7.5 5 14 12 16 7-2 12-8.5 12-16V7L16 2z" fill="url(#sd-logo-grad)" opacity="0.15"></path><path d="M16 2L4 7v8c0 7.5 5 14 12 16 7-2 12-8.5 12-16V7L16 2z" stroke="url(#sd-logo-grad)" stroke-width="2" stroke-linejoin="round"></path><path d="M11 16l3.5 3.5L21 13" stroke="url(#sd-logo-grad)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"></path></svg>
      <span>Indépendant<span style="color: var(--sd-primary)">.</span>Digital</span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbar" aria-controls="navbar" aria-expanded="false" aria-label="Ouvrir la navigation"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="navbar">
      <ul class="navbar-nav ms-auto align-items-lg-center">
        <li class="nav-item"><a class="nav-link" href="/">Accueil</a></li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="/page/independance-numerique" role="button" data-bs-toggle="dropdown">Guides</a>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="/page/independance-numerique">Indépendance numérique</a></li>
            <li><a class="dropdown-item" href="/page/sortir-microsoft-365">Sortir de Microsoft 365</a></li>
            <li><a class="dropdown-item" href="/page/choisir-visioconference-collaboration">Visioconférence et collaboration</a></li>
          </ul>
        </li>
        <li class="nav-item"><a class="nav-link" href="/page/methode-evaluation">Méthode</a></li>
        <li class="nav-item"><a class="nav-link" href="/page/transparence-partenariats">Transparence</a></li>
        <li class="nav-item"><a class="nav-link" href="/page/a-propos">À propos</a></li>
        <li class="nav-item ms-lg-2"><a class="sd-btn sd-btn-primary" href="/page/diagnostic-souverainete">Décrire mon besoin</a></li>
      </ul>
    </div>
  </div>
</nav>
HTML;

$footer = <<<'HTML'
<footer class="sd-footer" data-v-save-global="index.fr.html,.sd-footer">
  <div class="container">
    <div class="row g-4 g-lg-5">
      <div class="col-lg-5 col-md-6">
        <div class="sd-footer-brand"><strong>Indépendant Digital</strong></div>
        <p style="max-width: 420px; margin: 0;">Guides, diagnostics et orientations pour réduire les dépendances numériques des organisations françaises sans masquer les compromis.</p>
      </div>
      <div class="col-6 col-md-3"><h4>Décider</h4><ul><li><a href="/page/independance-numerique">Guide de cadrage</a></li><li><a href="/page/methode-evaluation">Méthode d&rsquo;évaluation</a></li><li><a href="/page/diagnostic-souverainete">Diagnostic</a></li></ul></div>
      <div class="col-6 col-md-3"><h4>Le projet</h4><ul><li><a href="/page/a-propos">À propos</a></li><li><a href="/page/transparence-partenariats">Transparence</a></li><li><a href="/page/confidentialite">Confidentialité</a></li><li><a href="/page/contact">Contact</a></li></ul></div>
    </div>
    <div class="sd-footer-bottom"><span>&copy; 2026 Indépendant Digital</span><span>Marché français &middot; Sources et dates de revue publiées</span></div>
  </div>
</footer>
HTML;

$patterns = [
	'#<div class="sd-announce">.*?</div>#s' => $announcement,
	'#<nav class="sd-nav navbar navbar-expand-lg".*?</nav>#s' => $navigation,
	'#<footer class="sd-footer".*?</footer>#s' => $footer,
];

foreach ($files as $file) {
	$html = (string) file_get_contents($file);
	foreach ($patterns as $pattern => $replacement) {
		$count = 0;
		$html = (string) preg_replace($pattern, $replacement, $html, 1, $count);
		if ($count !== 1) {
			fwrite(STDERR, "Could not replace chrome block in {$file}: {$pattern}\n");
			exit(1);
		}
	}
	file_put_contents($file, $html);
}

fwrite(STDOUT, "French chrome synchronized across " . count($files) . " templates.\n");
