# SEO launch infrastructure — design (2026-09-02)

Scope: the code-side launch blockers in `docs/launch/open-items.md`, an
SEO-clean URL structure, an automatic sitemap, a robots policy that admits
LLM crawlers, per-page canonicals, the legal identity of the operator, a
first set of real providers in the directory, and a technical + semantic
SEO audit of the result. Content beyond that stays with the editor.

## 1. Canonical host

The runtime has no notion of the public domain: `config/sites.php` keeps
the wildcard host `*.*.*`, `SITE_URL` resolves to it, and every absolute
URL the CMS builds carries the *request* host (or the wildcard verbatim, in
the directory sitemap). Sitemaps, canonicals and `og:url` all need one
authoritative origin.

- New constant `CANONICAL_URL` in `env.php`, read from the `CANONICAL_URL`
  environment variable and defaulting to `https://independantdigital.fr`.
  Local preview sets `CANONICAL_URL=http://127.0.0.1:8090` in the compose
  override so the sitemap is testable there.
- Helper `\Vvveb\canonicalUrl(string $path = '/'): string` in
  `system/functions.php` joins the origin and a path.
- `config/sites.php` is left alone: its host pattern drives site matching,
  not URL generation.

## 2. URL structure: no `/page/` prefix

Pages are the site's entity pages (méthode, diagnostic, guides, annuaire
chrome). They move from `/page/{slug}` to `/{slug}`; blog posts move from
`/{slug}` to `/blog/{slug}` so the two types cannot collide and the blog is
a self-describing section. English keeps its prefix: `/en/{slug}` and
`/en/blog/{slug}`.

Routes (`config/app-routes.php`), in match order:

| Route | Module | Note |
|---|---|---|
| `/blog/{slug}` | `content/post/index` | declared after `/blog/#page#` |
| `/{language{2,3}}/blog/{slug}` | `content/post/index` | |
| `/{slug}` | `content/page/index` | replaces the post route at this key |
| `/{language{2,3}}/{slug}` | `content/page/index` | |
| `/page/{slug}`, `/{language{2,3}}/page/{slug}` | `content/legacy-page/index` | 301 to the new shape |

The `p-#post_id#` routes for pages and posts are dropped: every row has a
slug, and they made non-default-language page URLs come out as
`/en/page/p-42`. The reverse map (`Routes::url`) then yields `/{slug}` for
`content/page/index` and `/blog/{slug}` for `content/post/index`, so
hreflang, feeds and menus follow without further change.

`content/legacy-page` is a new three-line controller: it builds the new URL
through the reverse map and issues a permanent redirect. It is its own
module so its fixed data keys never enter the `content/page/index` reverse
map (the hijack `plugins/solutions-directory/app/controller/page.php`
documents).

Link rewrite, done once with a script and verified by tests:

- theme templates: `href="/page/x"` → `href="/x"`, `/fr/page/x` → `/x`,
  `/en/page/x` → `/en/x`; the same in the inline `SERVICE_SLUGS` arrays and
  the `data-v-stage-redirect` attribute;
- `seed.dokploy.sql` bodies and taxonomy intros;
- the local database (same substitution over `post_content.content` and
  `taxonomy_item_content`), so the preview matches production.

Page cache: entries are keyed by request path, so `public/page-cache/*/page/*`
must be purged or nginx keeps serving the old bodies. The deploy already
wipes `public/page-cache` and `storage/cache/routes.app` on every start
(`init.dokploy.sh`, `scripts/lib/cache-invalidator.php`).

## 3. Per-page canonical, `og:url`, `og:title`

`app/template/common.tpl` runs after the global `<head>` replacement, so it
is the one place that can set head values per page. `Base::init()` computes
`$this->view->canonical` from `CANONICAL_URL` plus the request path with the
query string and any trailing slash removed. `common.tpl` then:

- removes any `link[rel=canonical]` inherited from the global head and
  appends one with the computed URL;
- sets `meta[property=og:url]` to the same value;
- `content/post.tpl` sets `og:title` and `og:description` from the row's
  title and meta description, next to the existing `<title>` binding.

The inert per-template canonical tags in `generated/*.fr.html` stay inert
and are not edited; the head propagation is the fix, as the open item asks.

## 4. Automatic sitemap and robots

A single controller `app/controller/sitemap.php` serves:

- `/sitemap.xml` — sitemap index listing the per-type sitemaps that have at
  least one URL;
- `/sitemap-pages.xml` — published pages in every language, one `<url>`
  per language with `xhtml:link rel="alternate" hreflang` pairs, `lastmod`
  from `updated_at`; the registration page and other `noindex` slugs are
  excluded by a small list in the controller;
- `/sitemap-posts.xml` — published posts, same shape;
- `/sitemap-solutions.xml` — the directory: `/annuaire`, term pages and
  published solutions. The plugin keeps ownership: its existing sitemap
  controller moves to this route and builds absolute URLs from
  `canonicalUrl()`; `/feed/solutions.xml` is dropped.

URLs come from the reverse route map (`url()`), so they track §2. The
default-theme `feed/*-sitemap.xml` templates and `/feed/index` are no longer
referenced; the `<link rel="sitemap">` in the theme head points at
`/sitemap.xml`.

nginx: the upstream image's `vvveb.conf` sends any `.xml` path to the
static-file location, which falls through to `error_page 404 /index.php` —
PHP runs but the status stays 404. The image conf is copied into the repo as
`nginx.dokploy.conf` with one addition placed before the static-extension
block:

```nginx
location ~ ^/sitemap(-[a-z0-9-]+)?\.xml$ {
    try_files /page-cache/$host$uri /index.php$is_args$args;
}
```

`Dockerfile.dokploy` copies it to `/etc/nginx/http.d/vvveb.conf` at build
time (outside the webroot, so the upstream bootstrap is unaffected). Sitemaps
are cached like pages (they do not start with `/feed`) and purged by the
same invalidator on publish.

`public/vrobots.txt` becomes:

```
User-agent: *
Disallow: /admin/
Disallow: /user/
Disallow: /search/
Disallow: /cart/
Disallow: /checkout/

User-agent: GPTBot
User-agent: ChatGPT-User
User-agent: OAI-SearchBot
User-agent: ClaudeBot
User-agent: Claude-User
User-agent: Claude-SearchBot
User-agent: anthropic-ai
User-agent: PerplexityBot
User-agent: Google-Extended
User-agent: Applebot-Extended
User-agent: CCBot
Allow: /

Sitemap: /sitemap.xml
```

`app/controller/feed/robots.php` always rewrites `Sitemap:` lines to
absolute URLs on `CANONICAL_URL` (today the rewrite only runs when the site
has a sub-path, which it never does).

## 5. Deploy overlay completeness

`Dockerfile.dokploy` overlays a hand-picked file list onto the upstream
image. It currently omits `config/app-routes.php`, the whole
`plugins/solutions-directory` plugin, `app/controller/feed/robots.php`,
`public/vrobots.txt`, the flush job and the template changes — so the
directory and its routes never reach production. The overlay is extended to
whole directories where the fork owns them (`plugins/`, `public/plugins/`,
`scripts/`, `app/template/`, `app/controller/`) plus the individual files it
changes, and a test (`scripts/tests/deploy-overlay-test.php`) asserts that
every file touched by a fork commit and still present is covered by a
`COPY` source. Excluded from the check: docs, tests, compose/nginx files
kept in the repo root, seed files (staged separately), and local tooling.

## 6. Lead retention purge and single-flight jobs

- `scripts/flush-partial-leads.php` takes a non-blocking `flock` on
  `storage/flush-partial-leads.lock` and exits 0 when another run holds it.
- `scripts/purge-leads.php` deletes `lead_submission` rows whose
  `updated_at` is older than `LEAD_RETENTION_DAYS`; dry run by default,
  `--apply` to delete, no default retention (the notice must state it
  first). Logic lives in `LeadRetention` with an SQLite test.

## 7. Legal identity

The operator is Otospex Solutions SARL, 17 avenue Bourguiba, 4180 Houmet
Souk, Tunisie (per otospexsolutions.com). The privacy notice names it as
controller and adds what a non-EU controller owes under GDPR art. 27 (an EU
representative) and chapter V (transfer safeguards for staff access from
Tunisia). A new `mentions-legales` page carries the LCEN notice. Fields the
public source does not give — RNE/matricule fiscal, capital, gérant,
hébergeur and its address, the dedicated privacy mailbox, the retention
duration — are left as explicit `[À compléter]` markers so a checklist can
find them, and `docs/launch/open-items.md` is updated accordingly.

## 8. Directory seed

Six real French providers, one per use case, appended to
`seed.dokploy.sql` as an idempotent `type='solution'` section and applied
to the local database. Every entry is `verification_status='declare'`,
qualifications carry holder, scope and date with a source URL or read
`Non communiquées`, and the entries share `categorie` / `alternative-a`
terms so the Alternatives sections populate. The audit script gates the
copy.

## 9. Audit

`docs/launch/seo-audit-2026-09-02.md` records the technical audit
(sitemap, robots, LLM access, canonicals, hreflang, internal links, orphan
and demo pages, structured data) and the semantic audit (per-page intent,
time to retrieval, goal completion) against the preview after the changes
above, with what was fixed and what remains editorial.

## Testing

Existing suites are updated for the new URL shape; new tests cover the
route table (page vs post, legacy redirect module), canonical computation,
the sitemap controller's URL list against a fixture database, robots
output, the overlay completeness, and retention purge. The integration
pass (`INTEGRATION=1`) checks live status codes for `/methode-evaluation`,
`/page/methode-evaluation` (301), `/sitemap.xml`, `/sitemap-pages.xml`,
`/sitemap-solutions.xml` and `/robots.txt`.
