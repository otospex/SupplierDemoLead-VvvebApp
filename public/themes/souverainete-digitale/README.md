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

**The `?v=` is the first 8 characters of `souverainete.css`'s own content
hash** — the same value as the `/* content-hash: XXXXXXXX */` marker
maintained at the end of the file (sha1 of the file with that marker line
excluded, first 8 hex chars). It is derived from content, not a hand-picked
date or version string, precisely so a forgotten bump becomes impossible to
ship silently: whenever you edit `souverainete.css`, recompute the hash,
update the trailing marker to match, and update every linking template's
`?v=` to the same value — then run:

```
php scripts/tests/homepage-contract-test.php
```

which fails the build if the marker is stale, or if the homepage's or any of
these four templates' `?v=` doesn't match the recomputed hash:
`content/index.fr.html`, `content/page.fr.html`, `content/post.fr.html`,
`content/contact.fr.html`. It does **not** check the other 20 linking files
(the six directory templates `content/annuaire{,.fr}.html`,
`content/solution{,.fr}.html`, `content/solution-registration{,.fr}.html`, and
the 14 `generated/*.fr.html` pages); keep those in step by hand and verify the
whole set — 25 files today — with
`grep -rho 'souverainete.css?v=[a-z0-9]*' public/ | sort | uniq -c`
after any bump: one line, one hash, or you have stragglers.

Browsers and any CDN/edge cache in front of the site key on the full URL including the query string, so a CSS edit with no
matching version bump serves stale styles to any visitor who already cached
the old file.

This is not theoretical — it has shipped twice: commit `6d2a132` ("fix: bump
theme stylesheet version and correct diagnostic heading order") had to bump a
hand-picked `sv1` to `sv2` across all 19 templates of the day after a CSS
change shipped
without the corresponding bump, following the stylesheet consolidation in
`7d4b49e`; a later fix-wave commit made the same mistake again by adding CSS
changes and a content-hash marker but leaving every template's `?v=` on the
stale `sv2` string. The content-hash-derived scheme above and the
strengthened `homepage-contract-test.php` assertions exist specifically to
make that class of bug fail CI instead of shipping. Treat a missing or
mismatched bump as a shipped bug, not a nitpick.

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
