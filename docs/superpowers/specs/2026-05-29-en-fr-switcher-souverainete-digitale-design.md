# EN/FR Language Switcher + French Translation — `souverainete-digitale` theme

**Date:** 2026-05-29
**Theme:** `public/themes/souverainete-digitale` (Digital Sovereignty, B2B lead site)
**Goal:** A dynamic EN/FR language switcher in the navbar (after the "Book a meeting" button) and full French translation of the site, using Vvveb's native multilanguage mechanisms.

## Background — how Vvveb multilanguage works

Four native mechanisms, all already present in the codebase:

1. **`language` table** — defines active languages. The switcher reads from it via
   `Locale::availableLanguages()` (cached 3 days in `storage/cache/app.languages`).
   Managed through admin Localization → Languages. Currently: English (id=1, default).
   French (`fr_FR`, slug `fr`) added on the deployed site; must also be added locally.
2. **Language-prefixed URLs** — `config/app-routes.php` already routes `/{language{2,3}}/...`.
   Default language (English) has no prefix (`/page/about`); French uses `/fr/page/...`, `/fr/`.
   The `[data-v-component-language]` component auto-builds these `hreflang` URLs and omits the
   prefix for the default language.
3. **Per-language homepage template** — `app/controller/index.php` supports
   `$site['template'][$language_id]`, so the static homepage can have an English `index.html`
   and a French `index.fr.html`, chosen automatically by active language.
4. **`post_content` per language** — each page/post has one row per language (same `post_id`,
   different `language_id` + translated `name`/`slug`/`content`). French = new rows.

## Key facts established

- The theme is hand-built **static HTML** (`index.html`, ~7 `data-v-` markers only). The hero,
  nav labels, sections, and footer are literal English text — NOT DB-driven like the `landing` theme.
- The `[data-v-component-language]` switcher is **not present** in this theme yet. Adding a language
  to the DB does nothing until the widget markup exists in the theme HTML — this is why the switcher
  did not appear after French was activated.
- Flag assets exist: `/img/flags/en.png`, `/img/flags/fr.png`.
- The reference `landing` theme uses the same component at
  `public/themes/landing/index.html:376` (bundled with a currency switcher we will omit).
- Sub-pages already exist as DB posts (English, language_id=1):

  | post_id | EN slug | FR slug | FR name |
  |---|---|---|---|
  | 7  | contact            | contact            | Nous contacter |
  | 11 | about              | a-propos           | À propos |
  | 12 | services           | solutions          | Solutions |
  | 17 | method             | certifications     | Méthode & Certifications |
  | 18 | sovereign-cloud    | cloud-souverain    | Cloud Souverain |
  | 19 | data-protection    | protection-donnees | Protection des Données |
  | 20 | cybersecurity-soc  | cybersecurite-soc  | Cybersécurité & SOC |
  | 21 | compliance-audit   | conformite-audit   | Conformité & Audit |
  | 22 | strategy-consulting| strategie-conseil  | Stratégie & Conseil |
  | 23 | training           | formation          | Formation |

## Decisions

- **Approach:** Native Vvveb (Approach A) — switcher driven by the `language` table; per-language
  homepage template; `post_content` rows for sub-pages.
- **Switcher placement:** new `<li>` in the navbar `<ul>` immediately after the "Book a meeting"
  `<li>` (currently `index.html:74–78`).
- **Toggle style:** flag + code, e.g. 🇫🇷 EN ▾.
- **Single-language behavior:** switcher hidden when fewer than 2 active languages exist.
- **Where changes live:** theme HTML in this repo (deploys with the repo) + French added to
  `seed.dokploy.sql` so deployments get it. French also added to the LOCAL DB for localhost testing.
- **Verification:** localhost.

## Implementation — three increments

### Increment 1 — Dynamic switcher (build + verify first)
- Add `fr_FR` (slug `fr`, status 1) to the **local** `language` table; clear `storage/cache/app.languages`.
- Add the same row idempotently to `seed.dokploy.sql`.
- Add the native `[data-v-component-language]` widget as a new `<li>` after "Book a meeting" in
  `index.html`, styled to the `sd-` nav, flag+code toggle, currency block omitted.
- Render the `<li>` only when ≥2 active languages exist (hide-on-single-language).
- **Checkpoint:** user verifies the EN/FR dropdown appears on localhost.

### Increment 2 — French homepage template
- Create `index.fr.html` (French twin: nav labels, hero, all sections, footer translated; French
  nav slugs; same switcher widget).
- Configure `config/sites.php` `template` to be per-language: `[1 => 'index.html', <frId> => 'index.fr.html']`.
- Verify `/fr/` serves the French homepage via `app/controller/index.php`.

### Increment 3 — French sub-pages
- Append French `post_content` rows (language_id = French) for all 10 pages to `seed.dokploy.sql`,
  using an idempotent `@pid`/`@fr_done` pattern keyed on the English slug, with a `@lang_fr`
  variable; translated `name`, `slug`, `content`, `meta_*`. Replaces the leftover Romanian
  `language_id=2` demo rows (deleted).
- Insert the same rows into the local DB for testing.
- Ensure nav `href`s in `index.fr.html` use French slugs (`/fr/page/cloud-souverain`, etc.).

#### Sub-page chrome localization (added during implementation)
Sub-pages render inside the static `content/page.html` / `content/contact.html` templates, whose
nav/footer/head are injected at render from `index.html` via `data-v-save-global`. That reference
is a compile-time literal, so French sub-pages otherwise show English chrome. Resolution:
- Create French sub-page templates `content/page.fr.html` and `content/contact.fr.html` whose
  three `data-v-save-global` references point to `index.fr.html` (French chrome) and whose static
  text is translated.
- `post.template` is a single column shared across languages, so a general fallback was added in
  `app/controller/content/post.php`: when the active language slug differs from the default, prefer
  a language-suffixed template (`content/<name>.<slug>.html`) if it exists on disk; otherwise use
  the base template. Keyed on the language **slug** (`fr`), not the locale code (`fr_FR`).

## Outcome
All 15 URLs (EN + FR homepages and 10 sub-pages each) return 200. Switcher renders on both
languages, hides when only one is active, links EN↔FR. French side fully translated in body and
chrome. English side unchanged. Note: the switcher's cross-language link for content pages uses
Vvveb's id-based fallback URL (`/fr/page/p-18`), which resolves correctly; primary nav uses clean
French slugs.

## Risks / notes
- Verify `/fr/` (homepage) and `/fr/page/{fr-slug}` routing resolve correctly (routes exist in
  `config/app-routes.php`).
- Language cache must be cleared after any `language` table change.
- French copy will be done in a professional B2B sovereignty register; user can refine wording.
- Romanian demo rows (language_id=2) in `post_content` are leftover Vvveb seed data and are out of
  scope — Romanian is not in the `language` table, so it will not appear in the switcher.
