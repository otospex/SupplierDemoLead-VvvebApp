# Copy platform and homepage redesign

**Date:** 2026-09-01
**Status:** Approved design (brainstormed 2026-08-31/09-01)
**Depends on:** `docs/superpowers/specs/2026-08-27-independant-digital-growth-system-design.md` (disclosure, consent, evidence rules stay in force)
**Audit input:** `docs/launch/ui-audit-2026-08-31.md`
**Sequence:** spec 1 of 3 — followed by `2026-09-01-solutions-directory-design.md` and `2026-09-01-diagnostic-intake-design.md`

## 1. Goal

Make the French site say one thing — *help French organisations move, step by step, toward digital sovereignty* — through three routes (conseil, solutions référencées, financement), and replace the nine-section AI-template homepage with a five-section page built on a single stylesheet.

## 2. Copy platform (site-wide)

### 2.1 Pivot term

- **Souveraineté numérique** is the outcome and the headline term: `<title>`, H1, nav CTA, directory name, meta descriptions.
- **Indépendance / réduire les dépendances** describes the path. Pattern: *« progresser vers la souveraineté numérique, par étapes »*.
- Brand name stays *Indépendant Digital*; the tagline carries *souveraineté*.

### 2.2 What organisations move away from

- Say **solutions étrangères** or **fournisseurs soumis à des lois extraterritoriales**. Never frame it as US-only.
- When an example is needed, name the product (Microsoft 365, Google Workspace, Zoom, AWS…), not a country.

### 2.3 Three routes — fixed order and wording

| # | Route | One-line definition | Link |
|---|---|---|---|
| 1 | **Conseil et accompagnement** | Diagnostic, feuille de route, cadrage et pilotage de la trajectoire. | `/page/diagnostic-souverainete` |
| 2 | **Solutions référencées** | Solutions françaises et européennes évaluées sur les mêmes critères publics ; partenaire ou non, seule l'adéquation au cas d'usage décide. | `/annuaire` |
| 3 | **Financement** | Des partenaires et des dispositifs publics peuvent cofinancer ou subventionner le projet ; toute rémunération est affichée. | `/page/diagnostic-souverainete` (step 2 has the financing interest field) |

### 2.4 Partner rule (homepage + every page that names a partner)

> Certaines solutions référencées sont des partenaires commerciaux. Le partenariat est toujours affiché ; il ne détermine jamais la recommandation, seule l'adéquation au cas d'usage compte.

### 2.5 AI-agent platform

- One short section on the homepage, present tense, no claims of availability:
  > Nous construisons une plateforme de création, d'entraînement et de gestion d'agents IA, hébergée en Europe. Accès anticipé sur demande.
- Not in the hero, not a CTA button. The only action is the intake's step-2 checkbox *« Accès anticipé à la plateforme d'agents IA »*.

### 2.6 One name for the intake

*Diagnostic de souveraineté* — used in the nav CTA, page title, H1, footer link and form heading. Replaces « Décrire mon besoin », « Demande de cadrage », « Formulaire de cadrage », « Décrire vos dépendances ».

### 2.7 Kept unchanged

Named consent before any introduction; review dates on every page; no invented metrics, testimonials, logos or customer counts; French only; the transparence and confidentialité pages remain authoritative.

### 2.8 Typography of copy

Curly apostrophes (`’`) everywhere; `&nbsp;` before `:` `?` `!` `;` in French; sentence case for headings; no em-dash chains.

## 3. Homepage

File: `public/themes/souverainete-digitale/index.fr.html`. Five sections, in this order.

### 3.1 Hero

- Left-biased layout (copy column ~7/12, visual ~5/12 on desktop; stacked on mobile).
- Eyebrow removed. H1 ≤ 50 characters, solid ink, no gradient span. Working copy: **« Vers la souveraineté numérique, par étapes. »**
- Lead paragraph (≤ 2 sentences): who it is for (DSI, RSSI, directions, collectivités et établissements publics) and what happens (cartographier, choisir, financer, migrer sans rupture).
- One primary CTA **« Lancer le diagnostic »** → `/page/diagnostic-souverainete`; one text link **« Voir la méthode »** → `/page/methode-evaluation`.
- Visual: the existing `/media/editorial/independence-trajectory.svg` with its three-step legend. `fetchpriority="high"`, never `loading="lazy"`.

### 3.2 Trois voies

- Asymmetric typographic list, not three equal cards: route 1 spans wider, routes 2–3 stack beside it on desktop; single column on mobile.
- Each route: numeral, title, the one-line definition from §2.3, one link.
- Partner rule (§2.4) as a short paragraph under route 2.

### 3.3 Solutions référencées

- Component `sd-solutions-block` (defined in the directory spec) rendering up to 6 published listings: name, catégorie, « alternative à … », verification badge, partner badge if any.
- Link « Voir l'annuaire » → `/annuaire`.
- Empty state (before the directory ships): a one-sentence explanation plus link « Référencer une solution » → `/annuaire/referencer-une-solution`. The homepage must render correctly with zero listings.

### 3.4 Méthode en quatre décisions

- Numbered list (`<ol>`), existing four steps and copy, no icons, no cards, no eyebrow.
- Link « Lire la méthode complète » → `/page/methode-evaluation`.

### 3.5 Agents IA + Diagnostic (step 1)

- Short band for the platform (§2.5).
- Then the intake's step 1 embedded inline (email professionnel, nom, organisation, fonction), heading « Diagnostic de souveraineté », one sentence on what happens next, privacy acknowledgement. Steps 2–3 continue on `/page/diagnostic-souverainete`. Until the intake spec ships, the existing lead form with the four step-1 fields is used, wired to the same endpoint `independant-digital-intake`.

### 3.6 Removed from the homepage

Stat band (`.sd-stats`), six-card path index, icon step cards, split feature section, qualification-scope cards, decision list, CTA banner, FAQ, hidden star-rating markup, all `.sd-gradient-text` spans, all inline `style=""` attributes. The FAQ content moves to `/page/methode-evaluation` as a final section so it is not lost.

### 3.7 Nav and footer (shared via `data-v-save-global`)

- Nav: wordmark, **Annuaire**, **Guides** (dropdown), **Méthode**, **À propos**, CTA « Diagnostic de souveraineté ». « Accueil » removed. Announcement bar removed.
- Footer: statement style — one sentence of purpose, the partner rule, then a single row of six links (Annuaire · Méthode · Diagnostic · Transparence · Confidentialité · Contact), then © line. No link columns.
- Same nav/footer applied to `content/page.fr.html`, `content/post.fr.html`, `content/contact.fr.html` and `content/index.fr.html` via `scripts/sync-french-chrome.php`.

## 4. Stylesheet consolidation

- `hallmark-tokens.css` stays the single token source. `hallmark-redesign.css` becomes the only theme stylesheet, renamed to `souverainete.css` and loaded once after Bootstrap.
- `custom.css` is retired from the French templates. Rules still needed (form controls, nav dropdown, sub-page hero, TOC, generated pages) are moved into `souverainete.css`, rewritten on tokens, then `custom.css` is deleted from the theme once `generated/*.fr.html` and the English templates are re-pointed or confirmed unused. If the English templates still need it, keep `custom.css` for them only and document that in the theme README.
- No hex/rgb/oklch literals outside `hallmark-tokens.css`; no `transition: all`; no gradients on text; `:focus-visible` rings unanimated.
- Hallmark stamp updated to the macrostructure actually built (chosen at build time from the catalog, must differ from *Map / Diagram* and *Split Studio* per `.hallmark/log.json`), and `.hallmark/log.json` gets a new entry.

## 5. Sub-pages touched

- `/page/diagnostic-souverainete`: title, H1 and eyebrow renamed per §2.6 (form itself changes in the intake spec).
- `/page/methode-evaluation`: gains the FAQ section moved from the homepage.
- `/page/a-propos`: « Notre travail » paragraph rewritten around the three routes; « Comment le site est financé » mentions financing partners.
- `/page/transparence-partenariats`: adds the sentence on partner co-financing and the directory rule (listing is free, never ranked by commercial status).
- `/page/confidentialite`: unchanged here (legal placeholders are a launch blocker tracked in `docs/launch/open-items.md`).
- Seed: `seed.dokploy.sql` updated for the renamed page fields so a fresh deploy matches.

## 6. Tests

`scripts/tests/ui-polish-test.php` extended (or replaced by `homepage-contract-test.php`) to assert on the homepage source:

- exactly one theme stylesheet link after Bootstrap; no `custom.css` link;
- no `sd-gradient-text`, no `sd-stats`, no `sd-cert-card`, no `sd-step-icon`, no `hidden` star SVGs, no inline `style=` attributes;
- ≤ 5 `<section>` elements in `<main>`/body order, exactly one `h1`, H1 length ≤ 50 characters;
- CTA labels present: « Lancer le diagnostic », « Voir la méthode », « Voir l'annuaire »;
- partner rule sentence present verbatim;
- nav contains « Annuaire » and not « Accueil ».

`scripts/tests/french-homepage-content-test.sh` updated for the new copy; all existing tests stay green. Rendered check at 320 / 375 / 414 / 768 / 1440 px: no horizontal scroll, no two-line CTA labels.

## 7. Out of scope

The directory itself, the multi-step intake, legal placeholders, English templates, Dokploy provisioning.
