# Solutions Directory Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the reviewed French “Annuaire des solutions souveraines,” its registration intake, reusable listings, and the admin draft-creation bridge without homepage wiring.

**Architecture:** A self-contained `solutions-directory` plugin registers the `solution` post type, reads portable configuration, queries existing Vvveb post/taxonomy tables, renders listing/detail HTML, and owns the draft action. Explicit routes precede Vvveb’s generic post routes; the two nested taxonomy URLs use a tiny plugin controller because the stock category route cannot distinguish two custom taxonomies or select the annuaire template. A Vvveb view-compile event substitutes an additive queue template owned by this plugin and a controller event adds action URLs to matching rows. The lead connector runtime gains two generic DOM events because its former serializer collapsed repeated checkbox values and its fixed success copy could not satisfy an endpoint-specific contract.

**Tech Stack:** PHP 8, Vvveb CMS events/components/SQL models, MySQL/PostgreSQL/SQLite install SQL, static/plain-PHP tests, French HTML/CSS.

**Spec:** `docs/superpowers/specs/2026-09-01-solutions-directory-design.md`

## Global Constraints

- Work only on `feat/solutions-directory` in `.claude/worktrees/solutions-directory`; never push.
- Do not modify `public/themes/souverainete-digitale/index.fr.html`.
- Drafts are never public; commercial status never changes ordering or badges.
- Plugin PHP reads the post type, endpoint, namespace, template, and taxonomy slugs from plugin configuration.
- French copy uses vouvoiement, `&rsquo;`, and `&nbsp;` before `: ? !`.
- Raw colors stay in `hallmark-tokens.css`; any `souverainete.css` change requires a fresh content hash and matching `?v=` in every French template.
- Every commit message ends with the required Claude co-author and session trailers.

---

### Task 1: Public directory domain and component

**Files:**
- Create: `plugins/solutions-directory/config.php`
- Create: `plugins/solutions-directory/plugin.php`
- Create: `plugins/solutions-directory/component/solutions.php`
- Create: `plugins/solutions-directory/system/solution-presenter.php`
- Create: `plugins/solutions-directory/system/solution-repository.php`
- Create: `plugins/solutions-directory/app/template/solutions.tpl`
- Create: `plugins/solutions-directory/app/controller/directory.php`
- Create: `plugins/solutions-directory/tests/solutions-component-test.php`
- Modify: `config/plugins.php`
- Modify: `config/app-routes.php`

**Interfaces:**
- `SolutionPresenter::listing(array $rows, array $context = []): string` renders only rows already selected by the public repository, orders by `reviewed_at DESC, name ASC`, emits status badges/links, and has a registration empty state.
- `SolutionPresenter::detail(array $solution, array $alternatives): string` renders facts, body, alternatives, disclosures, review record, and JSON-LD without exposing private meta.
- `SolutionRepository::published(array $filters, int $limit): array` selects `post.type = configured post type` and `post.status = publish`, resolving meta and taxonomy links.
- The `Solutions` component accepts `kind`, `categorie`, `alternative_a`, `limit`, `mode`, and `slug`.

- [x] Write fixture-based tests that fail because presenter/component files do not exist; assert draft exclusion, reviewed ordering, empty state, both badge strings, and the two `rel` policies.
- [x] Run `php plugins/solutions-directory/tests/solutions-component-test.php` and confirm the missing implementation failure.
- [x] Implement the presenter, repository, component template, post-type event, plugin registration, and explicit routes.
- [x] Re-run the test and confirm `solutions-component tests: PASS`.
- [ ] Commit the public plugin foundation with the required trailers (blocked by read-only worktree Git metadata in this execution sandbox).

### Task 2: Draft creation and queue integration

**Files:**
- Create: `plugins/solutions-directory/system/draft-mapper.php`
- Create: `plugins/solutions-directory/system/draft-creator.php`
- Create: `plugins/solutions-directory/system/vvveb-draft-store.php`
- Create: `plugins/solutions-directory/admin/controller/draft.php`
- Create: `plugins/solutions-directory/public/admin/submissions.html`
- Create: `plugins/solutions-directory/tests/draft-action-test.php`
- Modify: `plugins/solutions-directory/plugin.php`

**Interfaces:**
- `DraftMapper::map(array $fields, array $config, int $submissionId): array` returns draft post, localized content, public/admin meta, and submitted term slugs.
- `DraftCreator::createOrFind(int $submissionId, array $fields): array` returns `['post_id' => int, 'created' => bool]`; it consults the store before creating anything.
- `VvvebDraftStore` adapts Vvveb’s post model and existing post/taxonomy tables; it never publishes.

- [x] Write an in-memory-store test that fails because the mapper/creator are missing; assert all specified field mappings, draft status/template, term links, private email meta, and second-click idempotence.
- [x] Run `php plugins/solutions-directory/tests/draft-action-test.php` and verify the expected failure.
- [x] Implement mapping, persistence adapter, decrypting admin handler, compile-time queue-template substitution, and action-URL row enrichment through Vvveb events.
- [x] Re-run the draft test and the lead connector tests.
- [ ] Commit the admin bridge with the required trailers (blocked by read-only worktree Git metadata in this execution sandbox).

### Task 3: Templates, registration content, styles, and install/seed data

**Files:**
- Create: `public/themes/souverainete-digitale/content/annuaire.fr.html`
- Create: `public/themes/souverainete-digitale/content/solution.fr.html`
- Create: `public/themes/souverainete-digitale/content/solution-registration.fr.html`
- Preserve: `public/themes/souverainete-digitale/css/souverainete.css` and its current hash (changing it would require changing the expressly forbidden `index.fr.html`)
- Create: `plugins/solutions-directory/install.php`
- Create: `plugins/solutions-directory/install/sql/{mysqli,pgsql,sqlite}/data/solutions-directory.sql`
- Modify: `seed.dokploy.sql`
- Create: `scripts/tests/solutions-directory-content-test.php`

**Interfaces:**
- `annuaire.fr.html` embeds the public component and keeps the exact `page.fr.html` nav/footer chrome.
- `solution.fr.html` embeds detail mode and contains a mandatory Alternatives section/hook.
- `solution-registration.fr.html` is `noindex,follow` and renders the connector form supplied by the seeded page body.
- Install SQL idempotently creates both taxonomies, all initial terms/intros, and a queue-only `solution-registration` endpoint.

- [x] Write the static test first; require exact chrome, stylesheet chain, the six registration expectations, all form fields, Alternatives, route declarations, seed delimiter at EOF, and absence of `submitted_by_email` from public templates.
- [x] Run `php scripts/tests/solutions-directory-content-test.php` and confirm it fails on missing artifacts.
- [x] Create the three localized templates by copying chrome from `page.fr.html` and reuse the existing theme/Bootstrap classes.
- [x] Verify the untouched stylesheet marker and all French `?v=` references still match `f07f4201`.
- [x] Add idempotent per-driver install data and append the single required MySQL seed section at EOF using `@lang_fr` and doubled quote escaping.
- [x] Re-run the static test, homepage contract, editorial audit, and language policy tests.
- [ ] Commit templates/data with the required trailers (blocked by read-only worktree Git metadata in this execution sandbox).

### Task 4: Full verification and handoff

**Files:**
- Create: `/private/tmp/claude-501/-Users-houssamr-Projects-independant-digital/852c0dc1-d24b-4760-bd0c-32b1e9c5aa70/scratchpad/codex-directory-report.md`

- [x] Run `for t in scripts/tests/*.php; do php "$t"; done` and retain the tail.
- [x] Run `for t in scripts/tests/*.sh; do bash "$t"; done` and retain the tail.
- [x] Run `for t in plugins/*/tests/*.php; do php "$t"; done` and retain the tail.
- [x] Inspect `git diff --check`, `git status`, the route order, the seed EOF, CSS hash/version parity, and confirm `index.fr.html` is untouched.
- [x] Fix failures using fresh red/green cycles and rerun all three loops.
- [ ] Commit verification corrections with the required trailers (blocked by read-only worktree Git metadata in this execution sandbox).
- [x] Write the requested report with file map, routing rationale/deviations, command evidence, SQL review notes, and open items.
- [x] Print the requested short terminal summary without pushing.
