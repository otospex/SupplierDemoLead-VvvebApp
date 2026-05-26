# Souverainete-Digitale Multi-Page Conversion Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the existing Contact, About, Services pages and the Blog render in the `souverainete-digitale` theme style, visible and live-editable in the Vvveb admin, with the shared nav converted to real page links.

**Architecture:** The pages already exist as DB records (post_ids 7=contact, 11=about, 12=services; posts 1–6 = blog) but 404 because the theme has no content templates. We add styled `content/*.html` templates to the theme (cloned from `index.html`'s head/nav/footer + Vvveb `data-v-post-*` bindings), repoint the page records' `template` field to those templates, seed styled page bodies, rewrite the shared `.sd-nav`/footer to real page links, then clear caches and verify each route returns HTTP 200.

**Tech Stack:** PHP (Vvveb CMS), MySQL (Docker service `db`), Bootstrap 5, the theme's `css/custom.css`. Stack runs at `http://localhost:8090` (PHP), `:8091` (phpMyAdmin).

---

## File Structure

**Theme templates (create):**
- `public/themes/souverainete-digitale/content/page.html` — generic styled CMS page (About, Services). Head + `.sd-nav` + styled page header + `<main data-v-post-content>` + `.sd-footer`.
- `public/themes/souverainete-digitale/content/contact.html` — styled Contact page carrying the two-column lead-form section from the homepage `#contact`.
- `public/themes/souverainete-digitale/content/post.html` — styled single blog post.
- `public/themes/souverainete-digitale/content/index.html` — styled blog listing (`/blog` route).

**Theme nav/footer (modify):**
- `public/themes/souverainete-digitale/index.html:36-84` — `.sd-nav` links.
- `public/themes/souverainete-digitale/index.html:872-959` — `.sd-footer` links.

**Database (modify, reversible):**
- `post.template` for post_ids 7, 11, 12 (and 1–6 for blog posts).
- `post_content.content` + `meta_description` for post_ids 7, 11, 12 (language_id 1).

**Helper scripts (create, throwaway — committed under docs for reproducibility):**
- `docs/superpowers/plans/scripts/db-snapshot.sh` — dumps target rows before edits.
- `docs/superpowers/plans/scripts/db-apply.sql` — the repoint + content seed.

**Reference (read-only, do not edit):**
- `public/themes/souverainete-digitale/index.html` — source of head/nav/footer/sections.
- `public/themes/landing/content/page.html` — reference for Vvveb `data-v-post-*` bindings.
- `app/controller/content/post.php:133-141` — template resolution behavior.
- `system/core/view.php:83-88` — theme path resolution.

---

## Conventions used in every template

The theme `<head>` (clone verbatim from `public/themes/souverainete-digitale/index.html:1-28`), keeping:
- `<base href="../">` for content templates in the `content/` subfolder (so `css/custom.css` resolves to the theme root, matching how `landing/content/page.html` uses `<base href="../">`).
- `<head data-v-save-global="index.html,head">` so head stays a shared global.

The nav: clone `.sd-nav` from `index.html:36-84` with `data-v-save-global="index.html,.sd-nav"` so it is the SAME shared global region as the homepage (edits propagate everywhere).

The footer: clone `.sd-footer` from `index.html:872-959` with `data-v-save-global="index.html,.sd-footer"`.

The editable content region (from `landing/content/page.html:465-475` pattern):
```html
<main id="site-content" data-v-component-post data-v-id="">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-lg-10">
        <img class="img-fluid my-2" src="" alt="" loading="lazy" data-v-post-image data-v-if="post.image">
        <div class="post-content" data-v-post-content></div>
      </div>
    </div>
  </div>
</main>
```

---

## Task 1: Snapshot the database rows we will change

**Files:**
- Create: `docs/superpowers/plans/scripts/db-snapshot.sh`

- [ ] **Step 1: Write the snapshot script**

```bash
#!/usr/bin/env bash
# Dump the rows we are about to modify so the change is reversible.
set -euo pipefail
OUT="docs/superpowers/plans/scripts/db-backup-$(date +%Y%m%d-%H%M%S).sql"
docker exec db mysqldump -uvvveb -pvvveb vvveb \
  post --where="post_id IN (1,2,3,4,5,6,7,11,12)" > "$OUT.post" 2>/dev/null
docker exec db mysqldump -uvvveb -pvvveb vvveb \
  post_content --where="post_id IN (7,11,12)" > "$OUT.post_content" 2>/dev/null
echo "Backup written: $OUT.post  /  $OUT.post_content"
```

- [ ] **Step 2: Run it and confirm backups exist**

Run:
```bash
chmod +x docs/superpowers/plans/scripts/db-snapshot.sh && ./docs/superpowers/plans/scripts/db-snapshot.sh && ls -la docs/superpowers/plans/scripts/db-backup-*
```
Expected: prints "Backup written: ..." and lists two non-empty files (`*.post`, `*.post_content`).

- [ ] **Step 3: Commit**

```bash
git add docs/superpowers/plans/scripts/db-snapshot.sh
git commit -m "chore: db snapshot script for souverainete multipage"
```

---

## Task 2: Confirm the current 404 (baseline)

**Files:** none (verification only).

- [ ] **Step 1: Record the failing baseline**

Run:
```bash
for u in /page/contact /page/about /page/services /blog; do \
  printf "%s -> " "$u"; curl -s -o /dev/null -w "HTTP %{http_code}\n" "http://localhost:8090$u"; done
```
Expected (current state): each prints `HTTP 404`. This is the baseline we will fix.

- [ ] **Step 2: Confirm homepage still 200 (must stay working)**

Run:
```bash
curl -s -o /dev/null -w "home HTTP %{http_code}\n" http://localhost:8090/
```
Expected: `home HTTP 200`.

---

## Task 3: Create the generic styled page template (`content/page.html`)

**Files:**
- Create: `public/themes/souverainete-digitale/content/page.html`

- [ ] **Step 1: Build the file**

Assemble the file from these parts, in order:
1. `<head>` cloned verbatim from `public/themes/souverainete-digitale/index.html:1-28`, BUT change `<base href="">` to `<base href="../">` (content templates live one level deep).
2. `<body class="page">`.
3. The announce bar `<div class="sd-announce">…</div>` from `index.html:31-33` (keeps visual consistency).
4. The `.sd-nav` block from `index.html:36-84` verbatim (keeps `data-v-save-global="index.html,.sd-nav"`).
5. A styled page header section:
```html
<section class="sd-section" style="padding-top:7rem;">
  <div class="container">
    <span class="sd-eyebrow"><span class="sd-eyebrow-dot"></span>Digital Sovereignty</span>
    <h1 class="sd-gradient-text" data-v-post-title style="font-size:clamp(2rem,4vw,3rem);font-weight:800;letter-spacing:-0.02em;margin:1rem 0;"></h1>
  </div>
</section>
```
6. The editable content region (the `<main>` block from the "Conventions" section above).
7. The `.sd-footer` block from `index.html:872-959` verbatim.
8. The closing scripts from `index.html:957-960` (Bootstrap bundle + the `sd-year` script), then `</body></html>`.

- [ ] **Step 2: Validate HTML structure (balanced tags, bindings present)**

Run:
```bash
F=public/themes/souverainete-digitale/content/page.html
grep -c 'data-v-post-content' "$F"; grep -c 'data-v-save-global="index.html,.sd-nav"' "$F"; \
grep -c 'data-v-save-global="index.html,.sd-footer"' "$F"; grep -c '<base href="../">' "$F"
```
Expected: prints `1`, `1`, `1`, `1` (one of each).

- [ ] **Step 3: Commit**

```bash
git add public/themes/souverainete-digitale/content/page.html
git commit -m "feat(theme): add styled content/page.html for souverainete-digitale"
```

---

## Task 4: Create the styled Contact template (`content/contact.html`)

**Files:**
- Create: `public/themes/souverainete-digitale/content/contact.html`

- [ ] **Step 1: Build the file**

Same shell as Task 3 (head with `<base href="../">`, announce bar, `.sd-nav`, `.sd-footer`, closing scripts), but the body between nav and footer is the homepage contact section cloned from `index.html:809-870` (the `<section class="sd-form-section sd-section" id="contact">` two-column block with the lead form), with these changes:
- Remove the `data-v-save-global="index.html,#contact"` attribute from the section (this is a standalone page, not the shared homepage region) and keep `id="contact"`.
- Keep the form exactly as-is, including `action="" method="POST" data-v-endpoint="digital-sovereignty" data-v-component-plugin-lead-platform-connector-leadform="1"` and all fields — so lead submissions keep working.
- Above the section, add an editable intro region so the page is still CMS-editable:
```html
<main id="site-content" data-v-component-post data-v-id="">
  <div class="post-content" data-v-post-content></div>
</main>
```
  Place this `<main>` immediately AFTER the nav and BEFORE the contact section.

- [ ] **Step 2: Validate**

Run:
```bash
F=public/themes/souverainete-digitale/content/contact.html
grep -c 'data-v-endpoint="digital-sovereignty"' "$F"; \
grep -c 'data-v-component-plugin-lead-platform-connector-leadform' "$F"; \
grep -c 'data-v-post-content' "$F"; grep -c 'data-v-save-global="index.html,#contact"' "$F"
```
Expected: `1`, `1`, `1`, `0` (the form connector present; the global-save attribute removed → 0).

- [ ] **Step 3: Commit**

```bash
git add public/themes/souverainete-digitale/content/contact.html
git commit -m "feat(theme): add styled content/contact.html with lead form"
```

---

## Task 5: Create the styled single-post template (`content/post.html`)

**Files:**
- Create: `public/themes/souverainete-digitale/content/post.html`

- [ ] **Step 1: Build the file**

Same shell as Task 3, with the content region extended for a blog post:
```html
<main id="site-content" data-v-component-post data-v-id="">
  <div class="container" style="padding-top:7rem;">
    <div class="row justify-content-center">
      <article class="col-lg-9">
        <span class="sd-eyebrow"><span class="sd-eyebrow-dot"></span>Blog</span>
        <h1 data-v-post-title style="font-size:clamp(1.8rem,3.5vw,2.6rem);font-weight:800;letter-spacing:-0.02em;margin:1rem 0;"></h1>
        <p class="text-muted" style="margin-bottom:1.5rem;">
          <span data-v-post-author></span> &middot; <span data-v-post-date></span>
        </p>
        <img class="img-fluid rounded my-3" src="" alt="" loading="lazy" data-v-post-image data-v-if="post.image">
        <div class="post-content" data-v-post-content></div>
      </article>
    </div>
  </div>
</main>
```

NOTE on bindings: `data-v-post-author`, `data-v-post-date`, `data-v-post-image`, `data-v-post-title`, `data-v-post-content` are the expected Vvveb post-field conventions. Before relying on them, confirm the exact names against `public/themes/landing/content/post.html` (run `grep -oE 'data-v-post-[a-z-]+' public/themes/landing/content/post.html | sort -u`) and use landing's names verbatim where they differ. Do not invent binding names.

- [ ] **Step 2: Validate**

Run:
```bash
F=public/themes/souverainete-digitale/content/post.html
grep -c 'data-v-post-content' "$F"; grep -c '<article' "$F"; grep -c '<base href="../">' "$F"
```
Expected: `1`, `1`, `1`.

- [ ] **Step 3: Commit**

```bash
git add public/themes/souverainete-digitale/content/post.html
git commit -m "feat(theme): add styled content/post.html for blog posts"
```

---

## Task 6: Create the styled blog-listing template (`content/index.html`)

**Files:**
- Create: `public/themes/souverainete-digitale/content/index.html`

- [ ] **Step 1: Build the file**

Same shell as Task 3 (head `<base href="../">`, announce, `.sd-nav`, `.sd-footer`, scripts). The body is a responsive card grid that loops over posts using Vvveb's post-list bindings (mirrors how `landing/content/index.html` lists posts). Use:
```html
<section class="sd-section" style="padding-top:7rem;">
  <div class="container">
    <span class="sd-eyebrow"><span class="sd-eyebrow-dot"></span>Insights</span>
    <h1 class="sd-gradient-text" style="font-size:clamp(2rem,4vw,3rem);font-weight:800;margin:1rem 0 2rem;">Blog</h1>
    <div class="row g-4" data-v-component-posts>
      <div class="col-md-6 col-lg-4" data-v-posts-item>
        <a class="sd-card d-block h-100 text-decoration-none" href="" data-v-post-url>
          <img class="img-fluid rounded mb-3" src="" alt="" loading="lazy" data-v-post-image data-v-if="post.image">
          <h3 style="font-size:1.15rem;font-weight:700;" data-v-post-title></h3>
          <p class="text-muted" data-v-post-excerpt></p>
        </a>
      </div>
    </div>
  </div>
</section>
```

NOTE on bindings: open `public/themes/landing/content/index.html` and copy the EXACT attribute names that theme uses for the post loop (component name, item repeater, url/title/excerpt/image bindings). The names above are the expected Vvveb conventions; if `landing/content/index.html` differs, use landing's names verbatim so the loop renders. Do not invent binding names.

- [ ] **Step 2: Verify binding names against landing**

Run:
```bash
grep -oE 'data-v-[a-z-]+' public/themes/landing/content/index.html | sort -u | head -40
```
Expected: a list of `data-v-*` attributes; reconcile the loop in our `content/index.html` to match the post-list ones (e.g. the component attr and the per-item repeat attr). Fix our file if names differ.

- [ ] **Step 3: Validate**

Run:
```bash
F=public/themes/souverainete-digitale/content/index.html
grep -c '<base href="../">' "$F"; grep -c 'data-v-save-global="index.html,.sd-nav"' "$F"
```
Expected: `1`, `1`.

- [ ] **Step 4: Commit**

```bash
git add public/themes/souverainete-digitale/content/index.html
git commit -m "feat(theme): add styled content/index.html blog listing"
```

---

## Task 7: Repoint page/post records to the new templates + seed content

**Files:**
- Create: `docs/superpowers/plans/scripts/db-apply.sql`

- [ ] **Step 1: Write the SQL**

```sql
-- Repoint existing pages to templates that exist in the active theme.
UPDATE post SET template = 'content/contact.html' WHERE post_id = 7;  -- Contact
UPDATE post SET template = 'content/page.html'    WHERE post_id = 11; -- About
UPDATE post SET template = 'content/page.html'    WHERE post_id = 12; -- Services
-- Blog posts use the styled single-post template.
UPDATE post SET template = 'content/post.html' WHERE post_id IN (1,2,3,4,5,6);

-- Seed Services body (currently 8 chars) with a styled solutions grid (English).
UPDATE post_content SET
  content = '<div class="row g-4">\
    <div class="col-md-6 col-lg-4"><div class="sd-card h-100"><h3>☁️ Sovereign Cloud</h3><p>European-hosted, fully reversible cloud infrastructure with no egress fees.</p></div></div>\
    <div class="col-md-6 col-lg-4"><div class="sd-card h-100"><h3>🔒 Data Protection</h3><p>Client-side encryption and GDPR-by-design data governance.</p></div></div>\
    <div class="col-md-6 col-lg-4"><div class="sd-card h-100"><h3>🛡️ Cybersecurity &amp; SOC</h3><p>24/7 monitoring, incident response and managed detection.</p></div></div>\
    <div class="col-md-6 col-lg-4"><div class="sd-card h-100"><h3>📋 Compliance &amp; Audit</h3><p>HDS, ACPR, ANSSI and sector-specific audit readiness.</p></div></div>\
    <div class="col-md-6 col-lg-4"><div class="sd-card h-100"><h3>🎯 Strategy &amp; Consulting</h3><p>Roadmaps to digital sovereignty tailored to your CIO.</p></div></div>\
    <div class="col-md-6 col-lg-4"><div class="sd-card h-100"><h3>🎓 Training</h3><p>Upskill your teams on sovereign tooling and secure practices.</p></div></div>\
  </div>',
  meta_description = 'Sovereign cloud, data protection, cybersecurity, compliance, consulting and training for European organizations.'
WHERE post_id = 12 AND language_id = 1;

-- Add an SEO meta description for Contact and About (content already present).
UPDATE post_content SET
  meta_description = 'Talk to our digital sovereignty experts. Free, confidential consultation with a reply within 24 business hours.'
WHERE post_id = 7 AND language_id = 1;

UPDATE post_content SET
  meta_description = 'Consulting and solutions helping European organizations master their digital assets and achieve sovereignty.'
WHERE post_id = 11 AND language_id = 1;
```

- [ ] **Step 2: Apply the SQL**

Run:
```bash
docker exec -i db mysql -uvvveb -pvvveb vvveb < docs/superpowers/plans/scripts/db-apply.sql && echo "APPLIED"
```
Expected: prints `APPLIED` with no SQL error.

- [ ] **Step 3: Verify the rows changed**

Run:
```bash
docker exec db mysql -uvvveb -pvvveb vvveb -e \
"SELECT post_id,template FROM post WHERE post_id IN (7,11,12); \
SELECT post_id,CHAR_LENGTH(content) clen, meta_description FROM post_content WHERE post_id IN (7,11,12) AND language_id=1;"
```
Expected: post 7 → `content/contact.html`, 11/12 → `content/page.html`; post 12 `clen` now large (hundreds, not 8); all three have non-empty `meta_description`.

- [ ] **Step 4: Commit**

```bash
git add docs/superpowers/plans/scripts/db-apply.sql
git commit -m "feat(content): repoint pages to theme templates and seed Services/SEO"
```

---

## Task 8: Clear caches so new templates/pages appear

**Files:** none (cache clearing).

- [ ] **Step 1: Clear compiled templates and the theme template-list cache**

Run:
```bash
rm -f storage/compiled-templates/*souverainete-digitale* 2>/dev/null; \
rm -f storage/cache/cache/admin.template-list-souverainete-digitale 2>/dev/null; \
rm -f storage/cache/cache/admin.template-list- 2>/dev/null; \
echo "cache cleared"
```
Expected: prints `cache cleared`.

- [ ] **Step 2: Commit (no-op if nothing tracked changed)**

No commit needed — cache dirs are gitignored. Skip if `git status` shows nothing.

---

## Task 9: Verify pages render in theme style (HTTP 200 + styling)

**Files:** none (verification only).

- [ ] **Step 1: All routes return 200**

Run:
```bash
for u in /page/contact /page/about /page/services /blog /; do \
  printf "%s -> " "$u"; curl -s -o /dev/null -w "HTTP %{http_code}\n" "http://localhost:8090$u"; done
```
Expected: every line `HTTP 200` (was 404 for the first four in Task 2).

- [ ] **Step 2: Pages carry the theme styling (sd-nav + custom.css)**

Run:
```bash
for u in /page/contact /page/about /page/services; do \
  printf "%s: " "$u"; \
  curl -s "http://localhost:8090$u" | grep -oE 'sd-nav|css/custom.css' | sort -u | tr '\n' ' '; echo; done
```
Expected: each line lists both `css/custom.css` and `sd-nav`.

- [ ] **Step 3: Contact form connector present on /page/contact**

Run:
```bash
curl -s http://localhost:8090/page/contact | grep -c 'data-v-endpoint="digital-sovereignty"\|lpc-lead-form'
```
Expected: ≥ 1.

- [ ] **Step 4: Blog listing shows posts**

Run:
```bash
curl -s http://localhost:8090/blog | grep -oE 'sd-card|post-content|data-v-post' | sort -u | head
```
Expected: non-empty (cards/post bindings rendered). If empty, reconcile the loop bindings per Task 6 Step 2 and re-clear cache (Task 8).

---

## Task 10: Convert the shared nav and footer to real page links

**Files:**
- Modify: `public/themes/souverainete-digitale/index.html` (`.sd-nav` block `:36-84`, `.sd-footer` block `:872-959`)

- [ ] **Step 1: Rewrite the nav links**

In the `.sd-nav` block, change these `href` values (leave structure/classes intact):
- The "Solutions" dropdown toggle `href="#solutions"` → `href="/page/services"`.
- Each Solutions dropdown item `href="#solutions"` → `href="/page/services"`.
- `href="#process"` (Method) → `href="/#process"`.
- `href="#temoignages"` (Testimonials) → `href="/#temoignages"`.
- `href="#certifications"` → `href="/#certifications"`.
- `href="#faq"` → `href="/#faq"`.
- The CTA button "Book a meeting" `href="#contact"` → `href="/page/contact"`.
- Add a new nav item before the CTA: `<li class="nav-item"><a class="nav-link" href="/blog">Blog</a></li>` and `<li class="nav-item"><a class="nav-link" href="/page/about">About</a></li>`.

- [ ] **Step 2: Rewrite the footer links**

In the `.sd-footer` block:
- "Solutions" column items `href="#solutions"` → `href="/page/services"`.
- "Company" column: `About` → `/page/about` (already `/page/about`), `Contact` `href="#contact"` → `/page/contact`, `Blog` → `/blog` (already).
- Bottom-bar contact anchor, if any `#contact` → `/page/contact`.

- [ ] **Step 3: Verify the homepage nav now uses real links and still renders**

Run:
```bash
curl -s http://localhost:8090/ | grep -oE '/page/services|/page/contact|/page/about|/blog' | sort | uniq -c; \
curl -s -o /dev/null -w "home HTTP %{http_code}\n" http://localhost:8090/
```
Expected: counts for `/page/services`, `/page/contact`, `/page/about`, `/blog` ≥ 1 each; `home HTTP 200`.

- [ ] **Step 4: Verify nav propagated to a sub-page (shared global)**

Run:
```bash
curl -s http://localhost:8090/page/about | grep -oE '/page/services|/page/contact|/blog' | sort -u
```
Expected: lists the real page links (proves the shared `.sd-nav` renders identically on sub-pages).

- [ ] **Step 5: Commit**

```bash
git add public/themes/souverainete-digitale/index.html
git commit -m "feat(theme): convert shared nav/footer to real page links"
```

---

## Task 11: Verify admin visibility and live-editability

**Files:** none (verification only).

- [ ] **Step 1: Pages appear in the editor template/page list**

The editor lists DB pages via `admin/controller/editor/editor.php` `pages()` (merged into the page list). Confirm the pages are queryable (the admin reads the same data):
```bash
docker exec db mysql -uvvveb -pvvveb vvveb -e \
"SELECT p.post_id,c.name,c.slug,p.type,p.status,p.template FROM post p \
JOIN post_content c ON c.post_id=p.post_id AND c.language_id=1 \
WHERE p.post_id IN (7,11,12) ;"
```
Expected: three rows, status `publish`, templates pointing at `content/contact.html` / `content/page.html`.

- [ ] **Step 2: Manual editor check (human-in-the-loop)**

Open `http://localhost:8090/admin` → Content → Pages. Confirm **Contact**, **About**, **Services** are listed and each opens in the live editor showing the styled layout. Open Posts → confirm the 6 blog posts list and open styled. (This step is a visual confirmation; record the result.)

---

## Task 12: Final regression + wrap-up

**Files:** none.

- [ ] **Step 1: Full route sweep**

Run:
```bash
for u in / /page/contact /page/about /page/services /blog; do \
  printf "%s -> " "$u"; curl -s -o /dev/null -w "HTTP %{http_code}\n" "http://localhost:8090$u"; done
```
Expected: all `HTTP 200`.

- [ ] **Step 2: Confirm legacy/out-of-scope pages untouched**

Run:
```bash
docker exec db mysql -uvvveb -pvvveb vvveb -e \
"SELECT post_id,template FROM post WHERE post_id IN (8,9,10,13,14,15,16);"
```
Expected: unchanged from the Task 1 snapshot (we did not modify these).

- [ ] **Step 3: Final commit / clean status**

Run:
```bash
git status --short
```
Expected: clean (all changes committed). If anything is pending, commit with an appropriate message.

---

## Notes for the implementer

- **Do not invent Vvveb binding names.** For the blog loop (Task 6) and post fields, mirror the exact `data-v-*` attribute names found in `public/themes/landing/content/index.html` and `content/post.html`. If a binding renders empty, that's the first thing to reconcile.
- **`<base href="../">`** matters for content templates (they live in `content/`). The homepage `index.html` uses `<base href="">` because it's at theme root — do not copy that into the content templates.
- **Cache:** after any template add/change or DB template repoint, re-run Task 8 before re-testing, or the editor list / compiled template can be stale (7-day cache).
- **Rollback:** the DB backups from Task 1 (`*.post`, `*.post_content`) restore the original rows via `docker exec -i db mysql -uvvveb -pvvveb vvveb < <backup-file>`.
- **Lead form:** the Contact template keeps `data-v-endpoint="digital-sovereignty"`; do not change the endpoint or field names.
