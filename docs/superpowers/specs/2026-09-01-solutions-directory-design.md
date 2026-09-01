# Annuaire des solutions souveraines — directory design

**Date:** 2026-09-01
**Status:** Approved design
**Sequence:** spec 2 of 3 — after `2026-09-01-copy-platform-homepage-design.md`, before `2026-09-01-diagnostic-intake-design.md`
**Rules in force:** `docs/superpowers/specs/2026-08-27-independant-digital-growth-system-design.md` §5 (evaluation), §9 (editorial quality), `docs/editorial/provider-page-template.md`

## 1. Goal

A public, reviewed directory of solutions that help French organisations progress toward digital sovereignty. Solutions register themselves; nothing is published before a human review; every listing links from and to the guides that cover its use case. The directory is free, never ranked by commercial status, and is the site's growth and link-earning surface.

## 2. Decisions taken

- **Listing model:** review then publish. No auto-publication of any kind.
- **Who can list:** software/SaaS, hosting/cloud/infrastructure, integrators/consultancies/MSPs, public/institutional resources (the last kind is created editorially, not via the form).
- **Storage:** Vvveb posts of type `solution` with two taxonomies. No new database tables.

## 3. Content model

### 3.1 Post

- `post.type = 'solution'`, `post.template = 'content/solution.html'` (the language-suffixed `content/solution.fr.html` is resolved by the existing controller fallback).
- `post_content`: `name` (solution name), `slug`, `excerpt` (one-line pitch, ≤ 160 chars), `content` (reviewed body following `provider-page-template.md` headings translated to French), `meta_description`.
- `post.status`: `draft` on creation from a submission, `publish` after review.

### 3.2 Structured facts — `post_meta`, namespace `solution`

| key | values / format | shown publicly |
|---|---|---|
| `kind` | `logiciel` · `hebergeur` · `integrateur` · `ressource-publique` | yes |
| `website` | absolute URL | yes |
| `hq_country` | ISO 3166-1 alpha-2 | yes |
| `hosting_countries` | comma-separated ISO codes, or `non-communique` | yes |
| `pricing_model` | `public` · `sur-devis` · `gratuit` · `mixte` · `non-communique` | yes |
| `qualifications` | free text, each with scope and date, e.g. `SecNumCloud — IaaS uniquement — 2025-03` | yes |
| `commercial_relationship` | `aucune` · `partenaire-non-exclusif` | yes |
| `verification_status` | `declare` · `verifie` | yes (badge) |
| `reviewed_at` | `YYYY-MM-DD` | yes |
| `reviewer` | name | yes |
| `submitted_by_email` | email | admin only |
| `submission_id` | `lead_submission` id | admin only |

### 3.3 Taxonomies (both `post_type = 'solution'`)

- **`categorie`** — initial terms: visioconférence, messagerie, bureautique, fichiers et partage, identité et accès, hébergement et cloud, sauvegarde, cybersécurité, IA et agents, accompagnement et migration.
- **`alternative-a`** — curated incumbents: Microsoft 365, Microsoft Teams, Google Workspace, Google Meet, Zoom, Slack, Dropbox, OneDrive, SharePoint, AWS, Microsoft Azure, Google Cloud, ChatGPT / OpenAI, Salesforce, Notion. Extendable in admin. Slugs are stable and used in URLs and in guide-page blocks.

Every term has an editable intro paragraph (`taxonomy_item_content.content`) so term pages are never thin.

## 4. Public pages and URLs

| URL | Source | Content |
|---|---|---|
| `/annuaire` | page `annuaire` (type `page`, template `content/annuaire.fr.html`) | intro, filters (kind, catégorie), listing grid, link to registration |
| `/annuaire/categorie/<slug>` | Vvveb category controller for taxonomy `categorie` | term intro + listings |
| `/annuaire/alternative-a/<slug>` | Vvveb category controller for taxonomy `alternative-a` | term intro « Alternatives à X » + listings + link to the matching guide when one exists |
| `/solution/<slug>` | post type `solution`, template `content/solution.fr.html` | see §5 |
| `/annuaire/referencer-une-solution` | page with the registration form | see §6 |

Route mapping for `/annuaire/...` and `/solution/...` is added in `config/app.php` routes (or the theme `theme.php` route hooks — whichever the existing `/page/<slug>` pattern uses; the plan verifies this first).

Listing order everywhere: `reviewed_at` desc, then name. Commercial relationship never affects order or placement.

## 5. Listing page (`/solution/<slug>`)

1. Header: name, kind, catégories, « Alternative à … » chips, verification badge (« Déclaré par l'éditeur » or « Vérifié par Indépendant Digital le JJ/MM/AAAA »).
2. Facts block from `post_meta`: site, siège, hébergement, tarification, qualifications (with scope and date), relation commerciale.
3. Reviewed body (`post_content.content`).
4. **Alternatives** — mandatory: other published solutions sharing at least one `alternative-a` or `categorie` term, max 5, same ordering rule. If none, a sentence saying so.
5. Disclosure line when `commercial_relationship = partenaire-non-exclusif` (wording from the 2026-08-27 spec §6).
6. Review record: reviewer, reviewed date, « Signaler une erreur » link to `/page/contact`.
7. Outbound link to `website`: `rel="nofollow noopener"` while `verification_status = declare`; `rel="noopener"` once `verifie`.
8. JSON-LD `Organization` or `SoftwareApplication` (by kind) with `name`, `url`, `areaServed: FR`.

## 6. Registration

### 6.1 Page copy (`/annuaire/referencer-une-solution`)

States, in this order: listing is free; publication after human review; only verifiable claims are published; no ranking or placement for sale; a partner relationship is a separate conversation and is always disclosed; a link from the solution's site to its listing is welcome (courtesy, never a condition).

### 6.2 Form

Delivered through the existing `lead-platform-connector` (`data-v-endpoint="solution-registration"`), so CSRF, honeypot, minimum-time, rate limit, encryption and the admin queue are reused. Field names:

| name | type | required |
|---|---|---|
| `kind` | select: logiciel · hebergeur · integrateur | yes |
| `solution_name` | text | yes |
| `website` | url | yes |
| `organisation` | text | yes |
| `hq_country` | select (FR, EU members, autre) | yes |
| `contact_name` | text | yes |
| `email` | email (professional) | yes |
| `contact_role` | text | no |
| `categories[]` | checkboxes from taxonomy `categorie` | ≥ 1 |
| `alternative_to[]` | checkboxes from taxonomy `alternative-a` | no |
| `alternative_to_other` | text | no |
| `pitch` | text ≤ 160 chars | yes |
| `advantages` | textarea | yes |
| `hosting_countries` | text | no |
| `qualifications` | textarea (« avec périmètre et date ») | no |
| `pricing_model` | select | no |
| `partner_interest` | checkbox « Je souhaite discuter d'un partenariat commercial » | no |
| `accuracy_commitment` | checkbox « Les informations sont exactes et je peux fournir des preuves » | yes |
| `privacy_acknowledgement` | checkbox (existing rule) | yes |

Client-side: the stepper is not needed here — one page, grouped in three fieldsets (solution, contact, détails). Success message: « Merci. Votre solution sera examinée avant publication. »

### 6.3 Endpoint

`lead_endpoint` row `solution-registration` seeded in `seed.dokploy.sql` and the plugin install SQL, delivery mode local queue only (never forwarded to a provider platform).

### 6.4 Admin action — « Créer une fiche brouillon »

A button on submissions whose endpoint is `solution-registration`, shown in the connector's admin queue view. The button and its handler belong to the `solutions-directory` plugin and are injected into the queue view through Vvveb's event/hook system, so the connector stays generic. It creates a `solution` post in `draft` with `post_content` (name, slug from name, excerpt = pitch, content = advantages + qualifications pre-formatted under the template headings), `post_meta` from the fields (`verification_status = declare`, `commercial_relationship = aucune`, `submitted_by_email`, `submission_id`), and term links from `categories[]` / `alternative_to[]`. Idempotent: a second click opens the existing draft. Everything else — verification, editing, publishing — happens in the standard Vvveb post editor.

## 7. Reusable block — `sd-solutions-block`

A Vvveb component provided by a new small plugin `plugins/solutions-directory` (`component/solutions.php`, templates under its `app/template/`). The lead connector is not touched for rendering. The component takes `kind`, `categorie`, `alternative_a`, `limit` and renders published listings as compact cards. Used by:

- the homepage §3.3 block (`limit=6`);
- guide pages, e.g. `/page/choisir-visioconference-collaboration` with `alternative_a="google-meet,microsoft-teams,zoom"`, `/page/sortir-microsoft-365` with `alternative_a="microsoft-365"`;
- term pages and the annuaire index.

Empty state renders one sentence and the registration link; the block never fails a page render.

## 8. Internal linking rules

- Every listing links to its term pages and to its `Alternatives`.
- Every `alternative-a` term page links to the guide covering that use case when one exists (mapping maintained in the term intro, editable in admin).
- Every guide embeds the block for its use case (§7).
- Sitemap: `/annuaire`, term pages and published listings are included; drafts and the registration page are `noindex`.

## 9. Guards

- No auto-publish; drafts invisible to the public.
- `editorial-audit.php` rules (superlatives, unscoped certification words, environmental claims) run on `solution` bodies as part of `scripts/tests/content-contract-test.php`.
- Rate limit and honeypot inherited from the connector.
- `submitted_by_email` never rendered publicly; test asserts it.

## 10. Tests

- Plugin tests: block rendering (published only, ordering, empty state, badge text by `verification_status`, `rel` by status); draft creation action (field mapping, idempotence, term linking).
- Content tests: registration page contains the six expectation sentences; listing template contains the Alternatives section and disclosure hooks; no `submitted_by_email` in public templates.
- Route tests: `/annuaire`, a term page, a listing page and the registration page return 200 in the local preview; drafts return 404.

## 11. Out of scope

Paid or featured placements, self-service editing by solution owners, review workflow beyond the draft action, English listings, comparison tables.
