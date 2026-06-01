# Make French the default frontend language (French at /, English at /en/)

**Date:** 2026-06-01
**Theme:** `public/themes/souverainete-digitale`

## Problem
Changing the default language in admin (Localization → Languages) sets the DB
`language.default` flag, but the **frontend ignores it**. `app/controller/base.php:233`
resolves the default from `$site['language']` (site config), which is unset and falls
back to `'en'`:

```php
$language = $languages[$site['language'] ?? 'en'] ?? ['slug' => 'en', ...];
```

So `/` always renders English regardless of the admin toggle.

## Goal
French becomes the site default: `/` serves the French homepage; English moves to
`/en/`. The switcher (`app/component/language.php:103`) automatically drops the URL
prefix for `default_language_id` and adds the prefix for the other language, so the
flip is driven by which language is the configured default.

## Decision
- **Correction (verified):** the default is NOT read from `config/sites.php`. It comes
  from `siteSettings()` → the `site.settings` JSON `language` key (admin Settings →
  General/Web). `base.php:233` reads `$site = siteSettings()` then
  `$languages[$site['language'] ?? 'en']`. Setting `site.settings.language = 'fr'` (and
  `language_id`) + busting the `site.{id}` cache flips `/` to French. (The admin
  Languages → default toggle sets `language.default`, which the frontend ignores.)
- URL behaviour: flip accepted — French prefix-free (`/`, `/page/...`), English at
  `/en/...`. Existing `/fr/...` URLs still resolve (redundant prefix) but are no longer
  the canonical French ones.
- Production default: set the site language to French in admin Settings (admin-managed);
  code handles the URL prefixes.
- FR links: cleaned to prefix-free.

## Phase 1 results (verified locally)
With `site.settings.language='fr'`:
- `/` → French homepage ✓; switcher active=fr, links English→`/en/`, French→`/` ✓
- `/en/` → English homepage renders ✓ BUT its nav/footer links are hardcoded `/page/...`
  (no prefix) → now resolve in French context (BROKEN).
- `/page/{slug}` → French; `/en/page/{slug}` → English ✓.
- FR homepage `/fr/page/...` links still resolve to French (redundant, harmless).

→ Phase 2 must rewrite link prefixes: EN templates `/page → /en/page`, `/ → /en/`,
`/blog → /en/blog`; FR templates `/fr/... → prefix-free`.

## Implementation — Phase 1 (config only)
1. Add `'language' => 'fr'` to the site config in `config/sites.php`.
   The per-language homepage `template` map already keys by slug (`'fr' =>
   index.fr.html`, `'en' => index.html`), so `/` → French and `/en/` → English work.
2. Deploy/test locally and enumerate what breaks:
   - Does `/` render the French homepage?
   - Does `/en/` render English?
   - Does the switcher link French → `/` and English → `/en/...`?
   - Which hardcoded `/fr/...` (in index.fr.html etc.) and `/page/...` (English) links
     now point at the wrong language?

## Implementation — Phase 2 (only if Phase 1 shows breakage)
Swap hardcoded URL prefixes in the theme files that need it:
- `index.fr.html`, `content/{page,contact,post}.fr.html`: `/fr/...` → prefix-free.
- `index.html`, `content/{page,contact,post}.html`: `/page/...`, `/blog`, `/` →
  `/en/...`.
- The active-nav JS slug lists in each file.
- Seed nav slugs if applicable.

No DB or controller changes: the same `post_content` rows serve under flipped prefixes
(`/page/{slug}` resolves in the current/default language = French; `/en/page/{slug}` in
English).

## Deploy
`config/sites.php` is already in the Docker overlay (`Dockerfile.dokploy`) and
re-applies every start, so the change ships on redeploy. No seed marker bump needed
(no DB change).

## Verification
`/` French homepage; `/en/` English; switcher cross-links correct; sub-pages and blog
resolve in both languages; no link points at the wrong language.
