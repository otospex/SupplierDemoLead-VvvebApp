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
- Approach: set `'language' => 'fr'` in `config/sites.php` (the value `base.php` reads).
- URL behaviour: flip accepted — French prefix-free (`/`, `/page/...`), English at
  `/en/...`. Existing `/fr/...` URLs are no longer the canonical French ones.
- Rollout: **config-first**, then verify which hardcoded theme links actually break
  before editing theme files (avoid over-editing).

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
