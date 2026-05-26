# Multi-page conversion for the `souverainete-digitale` theme

**Date:** 2026-05-26
**Author:** tools@otospex.com (with Claude)
**Status:** Draft for review

## Goal

Turn the single-page `souverainete-digitale` Vvveb theme into a multi-page site.
Add **Contact**, **About**, **Services/Solutions**, and a **Blog** that are:

- Visible in the Vvveb admin (Content > Pages / Posts).
- Live-editable in the Vvveb editor.
- Styled to match the existing Digital Sovereignty homepage (`index.html`).
- SEO-optimized (titles, meta description, canonical, OpenGraph, semantic
  headings, descriptive alt text) and 100% responsive (Bootstrap 5 grid,
  the theme's existing responsive CSS in `css/custom.css`).
- Reachable from a shared top navigation converted to real page links.

## Key findings (what's actually in the system)

These came from inspecting the running stack (PHP on `:8090`, MySQL service `db`,
phpMyAdmin on `:8091`) and the codebase.

1. **The pages already exist in the database.** The `post` / `post_content`
   tables (site_id 1) already contain page records from a previous (landing)
   theme, bilingual (language_id 1 = English, 2 = Romanian):
   - `contact` (post_id 7) — template `contact.html`
   - `about` (post_id 11) — template `about.html`
   - `services` (post_id 12) — template `services.html`
   - plus `pricing`, `portfolio`, `shipping-delivery`, `terms-conditions`,
     `privacy-policy`, `marketplace`, `subscription-policy`.
   - **6 blog posts** (post_id 1–6, type `post`, status `publish`).

2. **They currently 404.** `GET /page/contact` and `GET /blog` both return
   **HTTP 404**, while `/` (homepage) renders correctly in the theme style.

3. **Root cause.** A Vvveb page/post renders through a template resolved against
   the **active theme folder** `public/themes/souverainete-digitale/`
   (`system/core/view.php` lines 83–88, 165–175). The page records point to
   `contact.html` / `about.html` / `services.html`, which **do not exist** in
   this theme. The theme also has **no `content/` folder** (no `page.html`,
   `post.html`, or `index.html` blog-listing template). With no resolvable
   template, the controller (`app/controller/content/post.php` lines 133–141)
   falls through to "not found".

4. **Templates are `.html` only.** The engine compiles `.html` → `.tpl`
   automatically (`view.php` `template()` / `saveCompiledTemplate`), so we only
   author `.html` files. No manual `.tpl` work.

5. **Nav and footer are shared/global.** In `index.html` the nav carries
   `data-v-save-global="index.html,.sd-nav"` and the footer
   `data-v-save-global="index.html,.sd-footer"`. Editing them in the editor
   saves back to `index.html` and propagates to every page that includes the
   same global region. The current nav uses on-page anchors
   (`#solutions`, `#process`, `#contact`, ...).

6. **Editor page list is cache-backed.** `admin/controller/editor/editor.php`
   `loadTemplateList()` caches the template/page list for 7 days; DB pages are
   merged in via `pages()`. Cache may need clearing for new templates to show.

**Consequence:** This is mostly a *rendering/wiring* task, not a *create-pages*
task. The pages exist; we must make the theme able to render them in its style,
repoint their templates, fix the 404, and wire the navigation.

## Approach

### Component 1 — Theme content templates (the foundation)

Create a `content/` folder inside `public/themes/souverainete-digitale/` with:

- **`content/page.html`** — styled CMS page template. Built from `index.html`:
  same `<head>` block (Bootstrap 5 CDN + `css/custom.css`), the same `.sd-nav`
  (shared global) and `.sd-footer` (shared global), and a content region
  `<main id="site-content" ... data-v-component-post ...>` containing
  `data-v-post-content` where the page body is injected, plus
  `data-v-post-title` / `data-v-post-image` bindings. Includes a lightweight
  styled page header (eyebrow + H1) consistent with the theme's section styling.
- **`content/post.html`** — styled single blog-post template (same shell as
  `page.html`, with post meta: date/author, plus `data-v-post-content`).
- **`content/index.html`** — styled blog **listing** template (the `/blog`
  route renders this) with a responsive card grid of posts, reusing the theme's
  card styling.

SEO + responsive requirements baked into each template:
- `<title>`, `<meta name="description">`, `<link rel="canonical">`, OpenGraph
  tags bound to the post fields (Vvveb already injects title/description from
  `post.php` lines 143–165; templates expose the binding hooks).
- One `<h1>` per page, semantic `<section>`/`<article>`/`<main>`, `loading="lazy"`
  + descriptive `alt` on images.
- Bootstrap 5 responsive grid and the theme's existing breakpoints; verify at
  mobile / tablet / desktop widths.

### Component 2 — Repoint existing page records to theme templates

Update the `template` field on the existing page records so they resolve in
this theme. Two sub-options, decided per page:

- Pages that should use the generic styled CMS layout (Contact, About) →
  set `template = content/page.html`.
- Services/Solutions → set `template = content/page.html` as well (content
  carries the richer layout), unless we choose to ship a dedicated
  `content/solutions.html`; default is `content/page.html` to keep it simple
  (YAGNI).

This is a small, reversible data change on `post.template` for post_ids 7
(contact), 11 (about), 12 (services). Blog posts (1–6) get
`content/post.html` (or rely on the default post template once it exists).

We will **not** touch the unrelated legacy pages (pricing, portfolio, terms,
privacy, marketplace, subscription) in this pass — out of scope.

### Component 3 — Seed/refresh page content (styled, SEO, responsive)

For Contact, About, Services we set the page **body content** (stored in
`post_content.content`) to styled markup matching the theme:

- **Contact** — reuse the homepage `#contact` section: the two-column layout
  (benefits list + stats on the left; the lead form on the right). The form
  keeps the existing lead-platform connector
  (`data-v-component-plugin-lead-platform-connector-leadform`,
  `data-v-endpoint="digital-sovereignty"`) so submissions keep working. Add
  contact details (email, phone, locations) from the footer.
- **About** — company mission, the method/process, certifications — lifted and
  expanded from the homepage sections, in the theme's section styling.
- **Services/Solutions** — expanded version of the homepage solutions grid
  (Sovereign Cloud, Data Protection, Cybersecurity & SOC, Compliance & Audit,
  Strategy & Consulting, Training), each as a styled card with a short
  description.

Each gets a `meta_description` and a clean `name`/`slug`
(`contact`, `about`, `services`).

### Component 4 — Convert navigation to real page links

Edit the shared `.sd-nav` in `index.html` (it propagates globally) and the
relevant footer links:

- Solutions → `/page/services`
- Method / Testimonials / Certifications / FAQ stay as homepage section anchors,
  rewritten as root-relative (`/#process`, `/#temoignages`, `/#certifications`,
  `/#faq`) so they jump back to the homepage section from any sub-page. No
  homepage content is moved into About.
- Blog → `/blog`
- About → `/page/about`
- Contact and the "Book a meeting" CTA → `/page/contact`

Because the same nav markup is shared, all pages get consistent navigation.
The homepage's in-page anchors that still target on-page sections remain valid
(prefixed with `/` so they work from sub-pages, e.g. `/#solutions`).

### Component 5 — Cache clear + verification

- Clear the editor/template cache so new templates and repointed pages show in
  the admin Pages list (`loadTemplateList` 7-day cache).
- Verify each route returns **HTTP 200** and renders in theme style:
  `/page/contact`, `/page/about`, `/page/services`, `/blog`, and a single post.
- Verify the pages appear in the admin and open in the editor.
- Verify responsiveness (mobile/tablet/desktop) and that the Contact form still
  posts to its endpoint.

## Out of scope

- Legacy pages not requested (pricing, portfolio, terms, privacy, marketplace,
  subscription) — left untouched.
- The Romanian (language_id 2) translations of new content — English
  (language_id 1) is the primary; Romanian rows are left as-is unless trivial.
- Any redesign of the homepage itself beyond the nav/footer link changes.
- New plugins or backend/controller code changes (we work within existing Vvveb
  page/post rendering).

## Risks & mitigations

- **Wrong template path → still 404.** Mitigation: confirm the exact resolved
  path against `view.php` and test the route returns 200 before moving on.
- **Editor cache hides changes.** Mitigation: clear compiled-template / list
  cache and hard-reload.
- **Editing shared nav could affect homepage.** Expected and desired (single
  shared nav); we verify the homepage still looks correct after.
- **DB edits.** Changes are limited to `post.template` and `post_content.content`
  for 3 known post_ids; reversible. Take a quick DB snapshot of those rows first.

## Success criteria

1. `/page/contact`, `/page/about`, `/page/services` and `/blog` all return
   HTTP 200 and render in the Digital Sovereignty style.
2. All four show up in the Vvveb admin and open in the live editor.
3. Top nav (shared) links to the real pages; works from every page.
4. Pages are responsive at mobile/tablet/desktop and carry proper SEO tags.
5. The Contact form still submits to its lead endpoint.
6. The homepage is unchanged except for the nav/footer link updates.
