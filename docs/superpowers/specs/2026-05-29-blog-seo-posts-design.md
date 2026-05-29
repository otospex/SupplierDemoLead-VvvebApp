# Replace demo blog posts with 4 SEO-optimized bilingual posts

**Date:** 2026-05-29
**Theme:** `public/themes/souverainete-digitale`
**Goal:** Remove the leftover Vvveb demo blog posts and create 4 long-form, SEO-optimized blog
posts (EN + FR), each ~1200–1800 words, assigned to clean sovereignty categories.

## Background
- Blog posts are `post` rows with `type='post'`, body text in `post_content` per language.
  `post_content.content` is `longtext`; `excerpt` is `text`; `name`, `slug`, `meta_keywords`,
  `meta_description` are `varchar(191)` (hard limit — must stay ≤191 chars).
- Current blog = 6 demo posts (`post_id` 1–6: "hello-world", Marcus Aurelius quotes, lorem ipsum),
  tied to junk demo categories (Tablets, Games, Windows, "category 12–25").
- Categories live in the `categories` taxonomy (taxonomy_id 1): `taxonomy_item` +
  `taxonomy_item_content` (per language); posts link via `post_to_taxonomy_item`.
- Routes: posts at `/post/{slug}` and `/fr/post/{slug}`; categories at `/cat/{slug}` and
  `/fr/cat/{slug}`. French chrome on posts uses the language-suffixed template fallback in
  `app/controller/content/post.php` (built previously) → needs `content/post.fr.html`.
- Post template shows: title, body, author display name, formatted date, optional featured image
  (`data-v-if="post.image"` so empty image is fine).

## Decisions
- **Bilingual:** each post + category in EN (language_id 1) and FR (language_id 2).
- **Length:** 1200–1800 words per post, SEO-structured (H1/H2/H3, lists, FAQ, CTA).
- **Categories:** create 3 new sovereignty categories; clean up demo posts' junk category links.
  (Do not mass-delete the wider demo taxonomy — out of scope.)
- **Where:** apply to local DB now + append idempotent block to `seed.dokploy.sql`.
- **Images:** `image=''` (theme handles empty gracefully).

## Section 1 — Cleanup
Delete for `post_id` IN (1..6): `post_content`, `post_to_site`, `post_to_taxonomy_item`,
`post_meta`, then `post`. Idempotent (guarded so re-running is harmless).

## Section 2 — Categories (taxonomy_id 1, type=categories)
| EN name | EN slug | FR name | FR slug |
|---|---|---|---|
| Sovereign Cloud        | sovereign-cloud-blog | Cloud Souverain              | cloud-souverain-blog |
| Compliance & Regulation| compliance           | Conformité & Réglementation  | conformite |
| Cybersecurity          | cybersecurity        | Cybersécurité                | cybersecurite |

(`-blog` suffix on cloud slug avoids colliding with the `/page/sovereign-cloud` service page.)

## Section 3 — The 4 posts
| # | EN title | Category | Intent |
|---|---|---|---|
| 1 | SecNumCloud Explained: What ANSSI Qualification Means for Your Cloud in 2026 | Sovereign Cloud | "what is SecNumCloud" |
| 2 | The US CLOUD Act & European Data: Why Your Cloud Provider's Jurisdiction Matters | Compliance | CLOUD Act risk |
| 3 | Sovereign Cloud Migration: A Step-by-Step Guide for European CIOs | Sovereign Cloud | migration how-to |
| 4 | NIS2 Compliance Checklist: Are You Ready for the Directive? | Cybersecurity | NIS2 readiness |

Each: keyword-front-loaded title, H2/H3 sections, scannable lists, FAQ block (snippet bait),
closing CTA to `/page/contact` (FR → `/fr/page/contact`). Theme classes: `sd-section-header`,
`sd-card`, `sd-eyebrow`, `sd-btn`.

## Section 4 — Implementation
- New posts: `post_id = MAX(post_id)+1`, `type='post'`, `template='content/post.html'`,
  `status='publish'`, `admin_id=1`, a `created_at`, `post_to_site` (site 1).
- Per post: EN + FR `post_content` rows; assign to its category via `post_to_taxonomy_item`.
- Create `content/post.fr.html` (French chrome, mirroring `page.fr.html`); the controller fallback
  selects it for the French language automatically.
- Idempotency: slug-keyed `@exists` guards; safe to re-run. `@lang`/`@lang_fr` resolved as in the
  existing seed.
- Apply to local DB; append the same idempotent block to `seed.dokploy.sql`.

## Verification
`/blog` and `/fr/blog` list the 4 posts; each `/post/{slug}` and `/fr/post/{slug}` returns 200 with
correct-language body + chrome; `/cat/{slug}` and `/fr/cat/{slug}` list their posts. Old demo posts
(1–6) gone. meta fields ≤191 chars (asserted in the build script).
