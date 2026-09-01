# Copy Platform + Homepage Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Recentre the French site on « souveraineté numérique » with three routes (conseil / solutions référencées / financement) and rebuild the homepage as a five-section page on a single stylesheet.

**Architecture:** Vvveb templates stay; `hallmark-tokens.css` remains the only place brand values live; `hallmark-redesign.css` absorbs the still-needed rules from `custom.css` and becomes `souverainete.css`, the single theme stylesheet. Homepage markup is rebuilt; page copy in the DB is updated via SQL applied both to the local DB and `seed.dokploy.sql`.

**Tech Stack:** Vvveb CMS (PHP), Bootstrap 5.3.2, plain CSS with OKLCH tokens, PHP CLI regression tests, Docker preview on `http://127.0.0.1:8090`.

**Spec:** `docs/superpowers/specs/2026-09-01-copy-platform-homepage-design.md` (copy platform §2, homepage §3, stylesheet §4, sub-pages §5, tests §6).

## Global Constraints

- Pivot term: **souveraineté numérique** is the outcome everywhere; « indépendance / réduire les dépendances » describes the path (spec §2.1).
- Say **« solutions étrangères »** or **« fournisseurs soumis à des lois extraterritoriales »** — never US-only framing; examples name products, not countries (spec §2.2).
- The three routes appear in fixed order with the exact copy of spec §2.3.
- Partner rule verbatim wherever partners are mentioned: « Certaines solutions référencées sont des partenaires commerciaux. Le partenariat est toujours affiché ; il ne détermine jamais la recommandation, seule l'adéquation au cas d'usage compte. »
- The intake is named **« Diagnostic de souveraineté »** everywhere (spec §2.6).
- No invented metrics, testimonials, logos, or counts. No `sd-gradient-text`, no icon-tile card grids, no stat bands, no inline `style=""`, no hex/rgb literals outside `hallmark-tokens.css`.
- **Template portability (user requirement):** all brand values (colors, fonts, spacing) live in `hallmark-tokens.css`; component CSS uses generic `sd-*` classes and token references only; no French copy inside CSS; sections are self-contained components an unrelated directory site could reuse by swapping tokens and template content.
- French typography: curly apostrophes (`’` / `&rsquo;`), `&nbsp;` before `:` `?` `!`, sentence-case headings.
- Buttons and nav links stay one line from 320 px to desktop; existing tests stay green; every task ends with the full test list passing: `for t in scripts/tests/*.php; do php "$t"; done; for t in scripts/tests/*.sh; do bash "$t"; done`.
- Local preview: `docker compose up -d php` → `http://127.0.0.1:8090`. Apply DB copy changes with `docker compose exec -T db mysql -uvvveb -pvvveb vvveb` **and** mirror them in `seed.dokploy.sql`.

## Dispatch map (orchestrator note)

- Task 1, 5, 6: mechanical/test work → **sonnet** subagent.
- Task 2, 3, 4: design-sensitive CSS/markup → **opus** subagent.
- After Tasks 3–4 and at the end: **codex** review pass (`codex:codex-rescue`) on the diff, plus spot-check screenshots.

---

### Task 1: Homepage contract test (failing first)

**Files:**
- Create: `scripts/tests/homepage-contract-test.php`
- Modify: `scripts/tests/ui-polish-test.php` (delete — superseded; its still-valid assertions move here)
- Modify: `scripts/tests/french-homepage-content-test.sh` (update expected strings)

**Interfaces:**
- Consumes: `public/themes/souverainete-digitale/index.fr.html`, `content/*.fr.html`, `css/souverainete.css` (created in Task 2).
- Produces: exit 0 only when the spec-§6 contracts hold. Later tasks run it as their acceptance gate.

- [ ] **Step 1: Write the failing test**

`scripts/tests/homepage-contract-test.php`, modeled on the existing `ui-polish-test.php` (plain PHP, `fail($msg)` collects errors, exit 1 if any). Assertions on `index.fr.html` source:

```php
<?php
$root = dirname(__DIR__, 2);
$theme = "$root/public/themes/souverainete-digitale";
$home = file_get_contents("$theme/index.fr.html");
$errors = [];
$fail = function ($m) use (&$errors) { $errors[] = $m; };

// Stylesheet chain: bootstrap, then exactly one souverainete.css, no custom.css, no hallmark-redesign.css
if (substr_count($home, 'souverainete.css') !== 1) $fail('homepage must link souverainete.css exactly once');
if (str_contains($home, 'custom.css')) $fail('homepage must not link custom.css');
if (str_contains($home, 'hallmark-redesign.css')) $fail('homepage must not link hallmark-redesign.css');

// Banned patterns
foreach (['sd-gradient-text', 'sd-stats', 'sd-cert-card', 'sd-step-icon', 'sd-decision-rule', 'sd-announce'] as $cls) {
    if (str_contains($home, $cls)) $fail("banned class present: $cls");
}
if (preg_match('/<[^>]+ style="/', $home)) $fail('inline style attribute present');

// Structure: exactly one h1, <= 50 chars text; five sections
if (preg_match_all('/<h1[^>]*>(.*?)<\/h1>/s', $home, $m) !== 1) $fail('exactly one h1 required');
else {
    $h1 = trim(html_entity_decode(strip_tags($m[1][0]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if (mb_strlen($h1) > 50) $fail('h1 longer than 50 chars: ' . $h1);
}
if (substr_count($home, '<section') > 5) $fail('more than 5 sections');

// Copy platform
foreach (['souveraineté numérique', 'Lancer le diagnostic', 'Voir la méthode', 'Voir l’annuaire',
          'Conseil et accompagnement', 'Solutions référencées', 'Financement',
          'Diagnostic de souveraineté'] as $needle) {
    if (!str_contains($home, $needle)) $fail("missing copy: $needle");
}
$rule = 'Le partenariat est toujours affiché';
if (!str_contains($home, $rule)) $fail('partner rule sentence missing');

// Nav contract
if (!str_contains($home, '>Annuaire<')) $fail('nav must contain Annuaire');
if (preg_match('/nav-link[^>]*>Accueil</', $home)) $fail('nav must not contain Accueil');

// Step-1 intake fields present, phone not required
foreach (['name="email"', 'name="full_name"', 'name="company"', 'name="job_title"', 'name="privacy_acknowledgement"'] as $f) {
    if (!str_contains($home, $f)) $fail("intake step-1 field missing: $f");
}
if (preg_match('/name="phone"[^>]*required/', $home)) $fail('phone must not be required');

// French templates use the single stylesheet
foreach (['content/index.fr.html', 'content/page.fr.html', 'content/post.fr.html', 'content/contact.fr.html'] as $t) {
    $tpl = file_get_contents("$theme/$t");
    if (substr_count($tpl, 'souverainete.css') !== 1) $fail("$t must link souverainete.css exactly once");
    if (str_contains($tpl, 'custom.css') || str_contains($tpl, 'hallmark-redesign.css')) $fail("$t links a retired stylesheet");
}

// CSS discipline: no raw color literals outside tokens file
$css = file_get_contents("$theme/css/souverainete.css");
if (preg_match('/#[0-9a-fA-F]{3,8}\b|rgb\(|oklch\(/', preg_replace('/\/\*.*?\*\//s', '', $css)))
    $fail('souverainete.css contains raw color literals (must reference tokens)');
if (str_contains($css, 'transition: all') || str_contains($css, 'transition:all'))
    $fail('souverainete.css uses transition: all');

if ($errors) { foreach ($errors as $e) echo "FAIL: $e\n"; echo "homepage-contract tests: FAIL\n"; exit(1); }
echo "homepage-contract tests: PASS\n";
```

Delete `scripts/tests/ui-polish-test.php` (assertions it carried that remain valid — TOC selector, button padding — are re-asserted here by keeping its two relevant checks: copy them in verbatim from the old file before deleting: the `.sd-toc a:not(.sd-btn)` selector check against the CSS and the `min-height: 44px` button check, pointed at `souverainete.css`).

In `french-homepage-content-test.sh`, update expected strings: H1 becomes `Vers la souveraineté numérique`, keep the checks for absence of testimonials/metrics, drop assertions tied to removed sections (grep the script for section names removed by spec §3.6 and delete those lines).

- [ ] **Step 2: Run to verify it fails**

Run: `php scripts/tests/homepage-contract-test.php`
Expected: FAIL (souverainete.css missing, old classes present).

- [ ] **Step 3: Commit**

```bash
git add scripts/tests/homepage-contract-test.php scripts/tests/french-homepage-content-test.sh
git rm scripts/tests/ui-polish-test.php
git commit -m "test: homepage contract for sovereignty copy platform and single stylesheet"
```

---

### Task 2: Single stylesheet — create `souverainete.css`, retire `custom.css` from French chain

**Files:**
- Create: `public/themes/souverainete-digitale/css/souverainete.css`
- Modify: `public/themes/souverainete-digitale/css/hallmark-tokens.css` (only if a needed value is missing — add tokens, never inline values)
- Delete: `public/themes/souverainete-digitale/css/hallmark-redesign.css` (its content is the base of the new file)
- Keep: `public/themes/souverainete-digitale/css/custom.css` (still referenced by English templates `content/*.html` and `index.html`; do NOT delete, do NOT edit)
- Modify: `public/themes/souverainete-digitale/content/index.fr.html`, `content/page.fr.html`, `content/post.fr.html`, `content/contact.fr.html`, `generated/*.fr.html` (stylesheet links only)

**Interfaces:**
- Consumes: current `hallmark-redesign.css` rules; `custom.css` rules listed below.
- Produces: `css/souverainete.css` — the only stylesheet French templates load after Bootstrap, versioned `?v=20260901-sv1`. Class names (`sd-btn`, `sd-nav`, `sd-footer`, `sd-form-card`, `sd-page-hero`, `sd-toc`, `sd-faq-item`, `sd-decision-item`) unchanged so DB content keeps rendering.

- [ ] **Step 1: Build the file**

1. Start from `hallmark-redesign.css` content (keep its stamp comment line — it is rewritten in Task 6).
2. Port from `custom.css` — **rewriting every color/size literal to a token reference** — only the rules the French pages still need. Identify them by grepping the French templates, `generated/*.fr.html` and DB content for class names, then port these groups (current `custom.css` line areas given as of commit `c74ffe0`):
   - form controls (`.form-control`, `.form-label`, `.form-check`, focus states),
   - nav dropdown (`.dropdown-menu`, `.dropdown-item`, toggler),
   - content-page chrome (`.sd-page-hero`, `.sd-toc`, `.post-content` typography, breadcrumbs),
   - generated-page components still used by `generated/*.fr.html` (grep those files for `sd-` classes; port exactly the classes found),
   - utility classes Bootstrap doesn't cover that appear in DB content (`.sd-card`, `.sd-card-link`, `.sd-decision-list`, `.sd-decision-item`, `.sd-decision-number`).
3. Delete from the merged result the rules for classes banned by Task 1 (`.sd-stats*`, `.sd-cert*`, `.sd-step-icon`, `.sd-path-index*`, `.sd-decision-rule`, `.sd-gradient-text`, `.sd-announce*`, hero orb/radial background blocks).
4. Missing values become tokens: e.g. if a ported rule needs a hover surface that has no token, add `--color-panel-hover: oklch(97% 0.012 250);` to `hallmark-tokens.css` and reference it.
5. Add the new section components used by Task 3 (code in Task 3 Step 1 — the two tasks may be done by the same worker in sequence; if split, Task 3's CSS block is appended in Task 3).

- [ ] **Step 2: Update stylesheet links**

In the four `content/*.fr.html` templates and every `generated/*.fr.html`: replace the two links (`custom.css?...` and `hallmark-redesign.css?...`) with one:

```html
<link id="theme-css" href="css/souverainete.css?v=20260901-sv1" rel="stylesheet" media="screen">
```

(`generated/*.fr.html` use the same relative `css/` prefix as today — mirror whatever prefix each file currently uses.)

- [ ] **Step 3: Verify rendering did not break sub-pages**

Run: `docker compose up -d php`, then `curl -s http://127.0.0.1:8090/page/methode-evaluation | grep -c souverainete.css` → `1`, and open `http://127.0.0.1:8090/page/methode-evaluation`, `/page/contact`, one `generated` route if routed. Confirm styled (not unstyled HTML) via screenshot or `curl -sI http://127.0.0.1:8090/themes/souverainete-digitale/css/souverainete.css` → `200`.

- [ ] **Step 4: Run tests**

Run: `php scripts/tests/homepage-contract-test.php`
Expected: template-link assertions PASS; homepage assertions still FAIL (homepage rebuilt in Task 3). Run the full suite; pre-existing tests must PASS.

- [ ] **Step 5: Commit**

```bash
git add -A public/themes/souverainete-digitale
git commit -m "feat: consolidate French theme into single souverainete.css stylesheet"
```

---

### Task 3: Rebuild the homepage (`index.fr.html`)

**Files:**
- Modify: `public/themes/souverainete-digitale/index.fr.html` (full body rewrite between `<body>` and the scripts; `<head>` metas updated)
- Modify: `public/themes/souverainete-digitale/css/souverainete.css` (append section CSS below)

**Interfaces:**
- Consumes: `souverainete.css` from Task 2; existing lead-form runtime (`data-v-endpoint="independant-digital-intake"`, `lead-form.20260827.js`).
- Produces: five sections with ids `#hero`, `#voies`, `#solutions`, `#methode`, `#diagnostic`; class hooks `sd-routes`, `sd-solutions-block`, `sd-method-list`, `sd-ai-band` that spec 2/3 plans reuse.

- [ ] **Step 1: Write the new markup**

Head changes: `<title>Indépendant Digital — Souveraineté numérique par étapes</title>`; meta description « Diagnostic, solutions référencées et financement pour aider les organisations françaises à progresser vers la souveraineté numérique, par étapes. » ; og tags to match; JSON-LD description updated the same way; keep canonical/manifest/favicon lines.

Body (structure below is normative; the worker may refine spacing/classes but not sections, order, copy, or links):

```html
<body class="home">
<nav class="sd-nav navbar navbar-expand-lg" data-v-save-global="index.fr.html,.sd-nav">
  <div class="container">
    <a class="navbar-brand" href="/" data-v-site-url aria-label="Indépendant Digital — Accueil">
      <img class="sd-brand-logo" src="/media/independant-digital-logo.png" alt="" width="760" height="65" decoding="async" aria-hidden="true">
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbar" aria-controls="navbar" aria-expanded="false" aria-label="Ouvrir la navigation"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="navbar">
      <ul class="navbar-nav ms-auto align-items-lg-center">
        <li class="nav-item"><a class="nav-link" href="/annuaire">Annuaire</a></li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="/page/independance-numerique" role="button" data-bs-toggle="dropdown">Guides</a>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="/page/independance-numerique">Indépendance numérique</a></li>
            <li><a class="dropdown-item" href="/page/sortir-microsoft-365">Sortir de Microsoft 365</a></li>
            <li><a class="dropdown-item" href="/page/choisir-visioconference-collaboration">Visioconférence et collaboration</a></li>
          </ul>
        </li>
        <li class="nav-item"><a class="nav-link" href="/page/methode-evaluation">Méthode</a></li>
        <li class="nav-item"><a class="nav-link" href="/page/a-propos">À propos</a></li>
        <li class="nav-item ms-lg-2"><a class="sd-btn sd-btn-primary" href="/page/diagnostic-souverainete">Diagnostic de souveraineté</a></li>
      </ul>
    </div>
  </div>
</nav>

<section class="sd-hero" id="hero">
  <div class="container">
    <div class="row g-5 align-items-center">
      <div class="col-lg-7">
        <h1>Vers la souveraineté numérique, par étapes.</h1>
        <p class="lead">Pour les DSI, RSSI, dirigeants et acteurs publics&nbsp;: cartographiez vos dépendances aux solutions étrangères, choisissez des alternatives adaptées et construisez une trajectoire finançable, sans interrompre les usages critiques.</p>
        <div class="sd-hero-actions">
          <a href="/page/diagnostic-souverainete" class="sd-btn sd-btn-primary sd-btn-lg">Lancer le diagnostic</a>
          <a href="/page/methode-evaluation" class="sd-link-arrow">Voir la méthode</a>
        </div>
      </div>
      <div class="col-lg-5">
        <figure class="sd-hero-journey">
          <img src="/media/editorial/independence-trajectory.svg" alt="Trajectoire progressive d’un système dépendant vers un socle numérique réversible" width="1200" height="480" fetchpriority="high" decoding="async">
          <figcaption>Une trajectoire se décide par couche&nbsp;: identité, données, collaboration, hébergement et réversibilité.</figcaption>
        </figure>
      </div>
    </div>
  </div>
</section>

<section class="sd-routes sd-section" id="voies">
  <div class="container">
    <h2>Trois voies, un même objectif</h2>
    <div class="sd-routes-grid">
      <article class="sd-route sd-route-wide">
        <span class="sd-route-index">1</span>
        <h3>Conseil et accompagnement</h3>
        <p>Diagnostic, feuille de route, cadrage et pilotage de la trajectoire, du premier inventaire au test de sortie.</p>
        <a class="sd-link-arrow" href="/page/diagnostic-souverainete">Lancer le diagnostic</a>
      </article>
      <article class="sd-route">
        <span class="sd-route-index">2</span>
        <h3>Solutions référencées</h3>
        <p>Des solutions françaises et européennes évaluées sur les mêmes critères publics. Partenaire ou non, seule l’adéquation au cas d’usage décide.</p>
        <p class="sd-route-note">Certaines solutions référencées sont des partenaires commerciaux. Le partenariat est toujours affiché&nbsp;; il ne détermine jamais la recommandation, seule l’adéquation au cas d’usage compte.</p>
        <a class="sd-link-arrow" href="/annuaire">Parcourir l’annuaire</a>
      </article>
      <article class="sd-route">
        <span class="sd-route-index">3</span>
        <h3>Financement</h3>
        <p>Des partenaires et des dispositifs publics peuvent cofinancer ou subventionner le projet. Toute rémunération est affichée.</p>
        <a class="sd-link-arrow" href="/page/diagnostic-souverainete">Étudier le financement</a>
      </article>
    </div>
  </div>
</section>

<section class="sd-solutions sd-section" id="solutions">
  <div class="container">
    <h2>Solutions référencées</h2>
    <div class="sd-solutions-block" data-solutions-limit="6">
      <!-- spec 2 replaces this empty state with the live listing component -->
      <p class="sd-solutions-empty">L’annuaire des solutions souveraines ouvre bientôt. Vous proposez une solution qui aide les organisations françaises à gagner en autonomie&nbsp;? <a href="/page/contact">Contactez-nous pour être référencé</a>.</p>
    </div>
    <a class="sd-link-arrow" href="/annuaire">Voir l’annuaire</a>
  </div>
</section>

<section class="sd-method sd-section" id="methode">
  <div class="container">
    <h2>Quatre décisions avant de recommander</h2>
    <ol class="sd-method-list">
      <li><strong>Cartographier l’existant.</strong> Repérer les outils, contrats, intégrations et données qui rendent la sortie difficile.</li>
      <li><strong>Construire les scénarios.</strong> Décider ce qui peut rester, coexister ou migrer selon vos contraintes et vos échéances.</li>
      <li><strong>Comparer les options.</strong> Examiner les solutions sur les mêmes critères&nbsp;: exploitation, preuves, coûts et réversibilité.</li>
      <li><strong>Documenter la décision.</strong> Consigner les critères retenus, les limites connues et les vérifications à mener avant de signer.</li>
    </ol>
    <a class="sd-link-arrow" href="/page/methode-evaluation">Lire la méthode complète</a>
  </div>
</section>

<section class="sd-diagnostic sd-section" id="diagnostic">
  <div class="container">
    <div class="sd-ai-band">
      <h3>Plateforme d’agents IA — en construction</h3>
      <p>Nous construisons une plateforme de création, d’entraînement et de gestion d’agents IA, hébergée en Europe. L’accès anticipé se demande dans le diagnostic.</p>
    </div>
    <div class="row g-5 align-items-start">
      <div class="col-lg-5">
        <h2>Diagnostic de souveraineté</h2>
        <p>Commencez par vos coordonnées&nbsp;; vous pourrez préciser votre situation ensuite. Aucune coordonnée n’est transmise à un fournisseur sans votre consentement nominatif.</p>
      </div>
      <div class="col-lg-7">
        <form class="sd-form-card" action="" method="POST" onsubmit="return false" data-v-endpoint="independant-digital-intake" data-v-component-plugin-lead-platform-connector-leadform="1">
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label" for="dg-email">E-mail professionnel <span class="text-danger" aria-hidden="true">*</span></label><input id="dg-email" type="email" class="form-control" name="email" required></div>
            <div class="col-md-6"><label class="form-label" for="dg-name">Nom complet <span class="text-danger" aria-hidden="true">*</span></label><input id="dg-name" type="text" class="form-control" name="full_name" required></div>
            <div class="col-md-6"><label class="form-label" for="dg-company">Organisation <span class="text-danger" aria-hidden="true">*</span></label><input id="dg-company" type="text" class="form-control" name="company" required></div>
            <div class="col-md-6"><label class="form-label" for="dg-role">Fonction <span class="text-danger" aria-hidden="true">*</span></label>
              <select id="dg-role" class="form-select" name="job_title" required>
                <option value="">Choisir…</option><option>DSI</option><option>RSSI</option><option>CTO</option><option>DG / DGS</option><option>Achats</option><option>Transformation</option><option>Autre</option>
              </select></div>
          </div>
          <div class="mt-3 form-check">
            <input class="form-check-input" type="checkbox" name="privacy_acknowledgement" value="1" id="dg-privacy" required>
            <label class="form-check-label" for="dg-privacy">J’ai lu la <a href="/page/confidentialite">notice de confidentialité</a> et j’accepte que ces informations soient utilisées pour répondre à ma demande.</label>
          </div>
          <div class="mt-3"><button type="submit" class="sd-btn sd-btn-primary">Commencer le diagnostic</button></div>
        </form>
      </div>
    </div>
  </div>
</section>

<footer class="sd-footer" data-v-save-global="index.fr.html,.sd-footer">
  <div class="container">
    <p class="sd-footer-statement">Indépendant Digital aide les organisations françaises à progresser vers la souveraineté numérique — diagnostic, solutions référencées et financement, sans masquer les compromis.</p>
    <p class="sd-footer-rule">Certaines solutions référencées sont des partenaires commerciaux. Le partenariat est toujours affiché&nbsp;; il ne détermine jamais la recommandation, seule l’adéquation au cas d’usage compte.</p>
    <ul class="sd-footer-links">
      <li><a href="/annuaire">Annuaire</a></li>
      <li><a href="/page/methode-evaluation">Méthode</a></li>
      <li><a href="/page/diagnostic-souverainete">Diagnostic</a></li>
      <li><a href="/page/transparence-partenariats">Transparence</a></li>
      <li><a href="/page/confidentialite">Confidentialité</a></li>
      <li><a href="/page/contact">Contact</a></li>
    </ul>
    <div class="sd-footer-bottom"><span>&copy; 2026 Indépendant Digital</span><span>Marché français &middot; Sources et dates de revue publiées</span></div>
  </div>
</footer>
```

Keep the existing script block at the end of the file (app.js, bootstrap bundle, nav-active script — update its `SERVICE_SLUGS` only if slugs changed: they did not — and the lead-form script include). Remove the announcement bar. The FAQ markup being removed is preserved by git history; Task 5 re-uses it.

Append to `souverainete.css` (all values via tokens; add missing tokens to `hallmark-tokens.css`):

```css
/* Routes — asymmetric editorial grid */
.sd-routes-grid { display: grid; gap: var(--space-xl); grid-template-columns: minmax(0, 7fr) minmax(0, 5fr); }
.sd-route-wide { grid-row: 1 / span 2; }
.sd-route { border-top: var(--rule-hair) solid var(--color-rule-strong); padding-top: var(--space-lg); }
.sd-route-index { font-family: var(--font-mono); font-size: 0.8rem; font-weight: 600; color: var(--color-accent-strong); }
.sd-route h3 { font-family: var(--font-display); margin: var(--space-xs) 0 var(--space-sm); }
.sd-route-note { font-size: 0.9rem; color: var(--color-muted); }
@media (max-width: 991px) { .sd-routes-grid { grid-template-columns: minmax(0, 1fr); } .sd-route-wide { grid-row: auto; } }

/* Link-arrow — text CTA */
.sd-link-arrow { font-weight: 600; color: var(--color-accent); text-decoration: none; white-space: nowrap; }
.sd-link-arrow::after { content: " \2192"; }
.sd-link-arrow:hover, .sd-link-arrow:focus-visible { text-decoration: underline; }

/* Solutions block */
.sd-solutions-empty { color: var(--color-muted); max-width: 46rem; }

/* Method list */
.sd-method-list { max-width: 46rem; display: grid; gap: var(--space-md); padding-left: 1.25rem; }
.sd-method-list li::marker { font-family: var(--font-mono); font-weight: 600; color: var(--color-accent-strong); }

/* AI band */
.sd-ai-band { border: var(--rule-hair) solid var(--color-rule); border-left: none; border-right: none; padding: var(--space-lg) 0; margin-bottom: var(--space-2xl); }
.sd-ai-band h3 { font-family: var(--font-display); font-size: 1.1rem; margin-bottom: var(--space-xs); }
.sd-ai-band p { margin: 0; color: var(--color-ink-soft); max-width: 52rem; }

/* Statement footer */
.sd-footer-statement { font-family: var(--font-display); font-size: 1.25rem; max-width: 46rem; }
.sd-footer-rule { color: var(--color-footer-muted); font-size: 0.9rem; max-width: 46rem; }
.sd-footer-links { display: flex; flex-wrap: wrap; gap: var(--space-md) var(--space-lg); list-style: none; padding: 0; margin: var(--space-xl) 0; }
```

Vary section padding (spec: no uniform rhythm): `#voies` and `#methode` use `--space-4xl` top, `#solutions` uses `--space-2xl`, `#diagnostic` `--space-3xl`.

- [ ] **Step 2: Run the contract test**

Run: `php scripts/tests/homepage-contract-test.php`
Expected: PASS.

- [ ] **Step 3: Rendered check**

`docker compose up -d php`; verify at `http://127.0.0.1:8090/`: five sections, no horizontal scroll at 375 px (browser tools or Playwright), CTA labels one line at 320/375/768/1440 px, form submits to a success alert (fill valid values, wait > 1.5 s after load).

- [ ] **Step 4: Full test suite**

Run: `for t in scripts/tests/*.php; do php "$t"; done; for t in scripts/tests/*.sh; do bash "$t"; done`
Expected: all PASS.

- [ ] **Step 5: Commit**

```bash
git add public/themes/souverainete-digitale
git commit -m "feat: rebuild French homepage around souveraineté copy platform"
```

---

### Task 4: Propagate chrome (nav/footer) to all French templates

**Files:**
- Modify: `public/themes/souverainete-digitale/content/index.fr.html`, `content/page.fr.html`, `content/post.fr.html`, `content/contact.fr.html`
- Consult: `scripts/sync-french-chrome.php` (use it if it copies `.sd-nav` / `.sd-footer` blocks from `index.fr.html`; read it first — if it already does this, run it instead of hand-editing)

**Interfaces:**
- Consumes: the `.sd-nav` and `.sd-footer` blocks from Task 3 (marked `data-v-save-global`).
- Produces: identical nav/footer markup in all four French content templates; announcement bar removed everywhere.

- [ ] **Step 1: Sync**

Run `php scripts/sync-french-chrome.php` if it performs the copy; otherwise replace each template's `<nav class="sd-nav">…</nav>` and `<footer class="sd-footer">…</footer>` blocks with the Task 3 versions and delete each `<div class="sd-announce">…</div>`.

- [ ] **Step 2: Verify**

`php scripts/tests/homepage-contract-test.php` PASS; `curl -s http://127.0.0.1:8090/page/methode-evaluation | grep -c '>Annuaire<'` → ≥ 1; `grep -c sd-announce public/themes/souverainete-digitale/content/*.fr.html` → 0 matches.

- [ ] **Step 3: Full suite + commit**

```bash
git add public/themes/souverainete-digitale/content
git commit -m "feat: sync sovereignty nav and statement footer across French templates"
```

---

### Task 5: Sub-page copy — diagnostic rename, FAQ move, à-propos and transparence updates, seed parity

**Files:**
- Modify: local DB (via `docker compose exec -T db mysql -uvvveb -pvvveb vvveb`)
- Modify: `seed.dokploy.sql` (mirror every DB change)
- Modify: `scripts/tests/transparency-content-test.sh` (new sentence asserted)

**Interfaces:**
- Consumes: FAQ markup from git history (`git show c74ffe0:public/themes/souverainete-digitale/index.fr.html`, the six `<details class="sd-faq-item">` blocks).
- Produces: renamed diagnostic page, methode page with FAQ section, à-propos and transparence with the three-routes copy.

- [ ] **Step 1: Diagnostic page rename**

SQL (run against local DB AND append the equivalent UPDATEs in the French section of `seed.dokploy.sql`, following the file's existing `@lang_fr` pattern):

```sql
UPDATE post_content SET
  name = 'Diagnostic de souveraineté',
  meta_description = 'Diagnostic de souveraineté numérique : décrivez vos outils, contraintes et échéances. Demande relue avant toute orientation.'
WHERE slug = 'diagnostic-souverainete';
```

In the page content HTML (same row), replace the eyebrow text « Demande de cadrage » with « Diagnostic de souveraineté » and the H1 « Décrire vos dépendances avant de choisir une solution » with « Diagnostic de souveraineté » plus lead sentence « Décrivez vos dépendances avant de choisir une solution. » (use `REPLACE(content, '…', '…')` UPDATEs so the rest of the page is untouched).

- [ ] **Step 2: FAQ onto methode-evaluation**

Extract the six `sd-faq-item` blocks from `git show c74ffe0:…/index.fr.html`, wrap as:

```html
<section class="sd-faq"><h2>Questions fréquentes</h2> …six details blocks… </section>
```

Append to the `methode-evaluation` French `post_content.content` via `UPDATE post_content SET content = CONCAT(content, '<escaped html>') WHERE slug='methode-evaluation'` (single-quote-escape the HTML). Mirror in `seed.dokploy.sql`.

- [ ] **Step 3: À-propos and transparence**

À-propos: in « Notre travail », replace the first sentence with: « Nous aidons les DSI, RSSI, directions générales et acheteurs publics à progresser vers la souveraineté numérique par trois voies&nbsp;: le conseil, des solutions référencées évaluées sur des critères publics, et la recherche de financement. » (REPLACE update). In « Comment le site est financé », append: « Certains partenaires peuvent également cofinancer ou subventionner les projets accompagnés&nbsp;; cette participation est toujours affichée. »

Transparence: append to « Notre modèle »: « Un partenaire peut aussi participer au financement ou à la subvention d’un projet accompagné&nbsp;; cette participation est affichée au même titre qu’une rémunération de mise en relation. Le référencement dans l’annuaire est gratuit et n’est jamais classé selon la relation commerciale. »

Mirror both in `seed.dokploy.sql`. Add to `transparency-content-test.sh` an assertion that the served `/page/transparence-partenariats` contains « référencement dans l’annuaire est gratuit ».

- [ ] **Step 4: Verify + full suite + commit**

`curl -s http://127.0.0.1:8090/page/diagnostic-souverainete | grep -c 'Diagnostic de souveraineté'` ≥ 2; `curl -s http://127.0.0.1:8090/page/methode-evaluation | grep -c 'Questions fréquentes'` = 1; full test list PASS.

```bash
git add seed.dokploy.sql scripts/tests/transparency-content-test.sh
git commit -m "feat: rename diagnostic, move FAQ to methode, disclose partner financing"
```

---

### Task 6: Stamp, hallmark log, final verification

**Files:**
- Modify: `public/themes/souverainete-digitale/css/souverainete.css` (first line stamp)
- Modify: `.hallmark/log.json` (prepend entry)

**Interfaces:** none downstream.

- [ ] **Step 1: Stamp and log**

First line of `souverainete.css`:

```css
/* Hallmark · macrostructure: Workbench · genre: modern-minimal · theme: Cobalt · nav: project header · footer: Ft5 Statement · enrichment: E5 Tier-B hand-built SVG (reused) · audience: French DSI/RSSI/public leaders · use: diagnostic + directory · tone: technical · pre-emit critique: P5 H4 E4 S4 R5 V4 */
```

Prepend to `.hallmark/log.json`:

```json
{ "date": "2026-09-01", "scope": "public/themes/souverainete-digitale/index.fr.html", "brief": "Sovereignty copy platform — 5-section homepage, three routes, statement footer", "macrostructure": "Workbench", "genre": "modern-minimal", "theme": "Cobalt", "enrichment": "E5 Tier-B hand-built SVG (reused)", "nav": "project header", "footer": "Ft5 Statement", "tone": "technical", "notes": "Single stylesheet souverainete.css; custom.css retained for English templates only." }
```

- [ ] **Step 2: Full verification**

Run every test; render `/`, `/page/diagnostic-souverainete`, `/page/methode-evaluation`, `/page/contact`, `/page/a-propos`, `/page/transparence-partenariats` at 375 px and 1440 px — no horizontal scroll, no unstyled page, no two-line buttons.

- [ ] **Step 3: Commit**

```bash
git add public/themes/souverainete-digitale/css/souverainete.css .hallmark/log.json
git commit -m "chore: stamp homepage redesign and record hallmark rotation"
```
