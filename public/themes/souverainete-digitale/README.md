# Théme souveraineté-digitale

## Stylesheet chain

The **French** templates (`*.fr.html`, both hand-authored and `generated/`) load
exactly two stylesheets, in this order:

1. Bootstrap 5.3.2 (CDN)
2. `css/souverainete.css?v=<cache-buster>`

`css/hallmark-tokens.css` is imported by `souverainete.css` and is the **only**
place brand values (color, type, spacing tokens) should be defined. Never hardcode
a hex color, font stack, or spacing value directly in a template or in
`souverainete.css` itself — add or change a token instead. This keeps the design
portable: swapping the brand later means editing one file.

`css/custom.css` and `css/hallmark-redesign.css` are **retired** from the French
chain. `custom.css` is still referenced by:

- the parked English templates (`index.html`, `content/*.html`) — not linked from
  any live French route, kept only until/unless the English site is relaunched;
- the admin page-builder editor (`admin/controller/editor/*`), which writes
  vvveb.js inline-style edits to `css/custom.css` regardless of theme language.

Do not delete `custom.css`. Do not link it from any `*.fr.html` template —
`scripts/tests/homepage-contract-test.php` fails the build if it detects a
`custom.css` (or `hallmark-redesign.css`) link on a French page.

## Cache-buster version bump rule

Every French template that links `souverainete.css` pins it with a `?v=...`
query string (19 templates as of this writing: `index.fr.html`, the 4
`content/*.fr.html` templates, and the 14 `generated/*.fr.html` pages).

**Any change to `souverainete.css` must bump that `?v=` value in all 19 linking
templates in the same commit.** Browsers and any CDN/edge cache in front of the
site key on the full URL including the query string, so a CSS edit with no
version bump can serve stale styles to visitors who already cached the old file.

This is not theoretical: commit `6d2a132` ("fix: bump theme stylesheet version
and correct diagnostic heading order") had to bump `sv1` to `sv2` across all 19
templates after a CSS change shipped without the corresponding bump, following
the stylesheet consolidation in `7d4b49e`. Treat a missing bump as a shipped bug,
not a nitpick.

## Chrome sync (nav + footer)

`index.fr.html` is the source of truth for the site chrome — the `<nav
class="sd-nav">…</nav>` and `<footer class="sd-footer">…</footer>` blocks.
`scripts/sync-french-chrome.php` copies those two blocks verbatim into the four
`content/*.fr.html` templates (`content/index.fr.html`, `content/page.fr.html`,
`content/post.fr.html`, `content/contact.fr.html`).

After editing the nav or footer in `index.fr.html`, run:

```
php scripts/sync-french-chrome.php
```

and confirm with `git diff --stat` that only the nav/footer blocks changed in
the four target templates (the script exits non-zero if it can't find or
replace a block, so a clean run with no unexpected diff is the pass condition).
It uses `preg_replace_callback` rather than `preg_replace` so a literal `$` or
`\` in future chrome markup is never misread as a regex backreference.

Anything outside the nav/footer blocks (page body, per-template `<script>`
blocks such as the `syncActiveNav` nav-highlighting script) is **not** touched
by the sync script and must be kept in step by hand across all five French
templates when it changes.
