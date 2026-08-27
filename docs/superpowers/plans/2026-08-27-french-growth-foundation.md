# French Growth Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Turn the existing French sovereignty demo into a credible, French-first Indépendant Digital launch foundation with enforceable editorial standards, no fabricated proof, parked English content, transparent partnerships, and a safe base for provider and alternatives acquisition pages.

**Architecture:** Keep Vvveb, the existing theme, database seed, and lead connector. Add a repository-level editorial audit and claim register, then remediate the visible French shell and seeded content before publishing new traffic pages. Provider and alternatives pages use a shared decision structure but remain manually written and reviewed.

**Tech Stack:** PHP 7.4+, Vvveb CMS, MySQL seed SQL, HTML/CSS, shell-based verification.

**Spec:** `docs/superpowers/specs/2026-08-27-independant-digital-growth-system-design.md`

## Global Constraints

- French is the only indexable acquisition language at launch; English source content is retained but parked with `noindex,follow` and removed from navigation.
- A provider recommendation must be based on a use case and published criteria, never commercial status alone.
- Every provider or solution page with credible substitutes has an alternatives section.
- A standalone alternatives page requires distinct intent, commercial relevance, original analysis, no cannibalization, and either 50 relevant French monthly searches or 100 Search Console impressions over 90 days, unless a documented high-value exception is approved.
- No invented customers, testimonials, metrics, qualifications, savings, results, research, or environmental claims.
- Certifications and qualifications always name the holder, service scope, status, source, and review date.
- AIFEL remains a non-exclusive commercial partner and receives data only after explicit named consent.
- No AIFEL “lowest carbon” or equivalent comparative claim ships without a scoped comparative lifecycle assessment.
- Production templates must render at 320, 375, 414, and 768 CSS pixels without horizontal scrolling or wrapped primary controls.

---

### Task 1: Establish the project language and approved strategy

**Files:**
- Create: `CONTEXT.md`
- Create: `docs/superpowers/specs/2026-08-27-independant-digital-growth-system-design.md`

**Interfaces:**
- Consumes: approved business, ICP, editorial, AIFEL, and alternatives decisions from the project owner.
- Produces: canonical terminology and requirements consumed by every later task.

- [x] **Step 1: Create the domain glossary**

Define Organization, Decision-maker, Use case, Trigger, Provider, Solution, Ecosystem recommendation, Targeted alternative, Commercial partner, Independent recommendation, Alternatives section, Alternatives page, Diagnostic, Qualified opportunity, Qualified introduction, Verified claim, Claim scope, and Manual editorial review.

- [x] **Step 2: Write the consolidated strategy specification**

Cover positioning, ICP, revenue, recommendation methodology, AIFEL, alternatives demand gates, French-first policy, editorial QA, lead routing, marketing, metrics, delivery phases, and launch gates.

- [x] **Step 3: Verify the documents contain no unresolved markers**

Run:

```bash
rg -n '\b(TBD|TODO|FIXME|placeholder)\b' CONTEXT.md docs/superpowers/specs/2026-08-27-independant-digital-growth-system-design.md
```

Expected: no output and exit code 1.

- [x] **Step 4: Commit**

```bash
git add CONTEXT.md docs/superpowers/specs/2026-08-27-independant-digital-growth-system-design.md
git commit -m "docs: define independent digital growth strategy"
```

### Task 2: Add an enforceable editorial claim audit

**Files:**
- Create: `scripts/editorial-audit.php`
- Create: `scripts/tests/editorial-audit-test.php`
- Create: `docs/editorial/claim-register.csv`
- Modify: `README.md`

**Interfaces:**
- Consumes: a file or directory path supplied on the command line.
- Produces: one `path:line [RULE] message` record per launch-blocking claim and exit code `1` when blockers exist; exit code `0` when none exist.

- [x] **Step 1: Write the failing test**

Create `scripts/tests/editorial-audit-test.php` with temporary clean and risky fixtures. The risky fixture contains `La confiance de plus de 250 organisations`, `ACMECORP`, `impact carbone plus bas que tout le monde`, and `certifié SecNumCloud` without a source marker. Assert that the audit returns non-zero and emits `FABRICATED_PROOF`, `PLACEHOLDER_BRAND`, `CARBON_SUPERLATIVE`, and `UNSCOPED_CERTIFICATION`. Assert that the clean fixture returns zero.

- [x] **Step 2: Run the test to verify it fails**

Run:

```bash
php scripts/tests/editorial-audit-test.php
```

Expected: FAIL because `scripts/editorial-audit.php` does not exist.

- [x] **Step 3: Implement the audit**

Create a PHP CLI script that recursively scans `.html`, `.sql`, and `.md` files while excluding `.git`, `vendor`, generated backups, and the audit fixtures. Implement these rule identifiers:

```php
$rules = [
    'FABRICATED_PROOF' => '/(?:plus de\s+250\s+organisations|250\+\s+organisations)/iu',
    'PLACEHOLDER_BRAND' => '/\b(?:ACMECORP|CUBIX|NEXUS|DELTA)\b/u',
    'CARBON_SUPERLATIVE' => '/(?:impact carbone|empreinte carbone).{0,40}(?:plus bas(?:se)? que tout le monde|le plus faible|la plus faible)/iu',
    'UNSCOPED_CERTIFICATION' => '/(?:nous|notre|nos|indépendant digital).{0,80}(?:certifi(?:é|ée|és|ées)|SecNumCloud|HDS|ISO\s*27001)/iu',
    'ABSOLUTE_EXTRATERRITORIAL' => '/(?:à l.abri|immun(?:e|isé|isée)|sans exposition|garanti(?:e)?).{0,50}(?:CLOUD Act|lois? extraterritoriales?)/iu',
];
```

Allow an individual line to carry `editorial-audit: allow RULE evidence=<URL-or-register-id>` only when a matching non-empty evidence value is present. The exception remains visible in output under `--report-allowances`.

- [x] **Step 4: Run the test to verify it passes**

Run:

```bash
php scripts/tests/editorial-audit-test.php
```

Expected: `editorial-audit tests: PASS`.

- [x] **Step 5: Create the claim register**

Create the CSV header and no fabricated rows:

```csv
claim_id,status,claim,scope,source_url,source_owner,checked_on,next_review,editorial_owner,notes
```

Allowed statuses are `proposed`, `verified`, `qualified`, `expired`, and `rejected`.

- [x] **Step 6: Document the checks**

Add a project-specific section to `README.md` explaining:

```bash
php scripts/editorial-audit.php public/themes/souverainete-digitale seed.dokploy.sql
php scripts/tests/editorial-audit-test.php
```

State that audit success does not replace manual review.

- [x] **Step 7: Run the audit against the current site**

Run:

```bash
php scripts/editorial-audit.php public/themes/souverainete-digitale seed.dokploy.sql
```

Expected before remediation: exit code 1 with the existing fabricated-proof and overbroad-claim locations listed. Save no generated report; the terminal output is the baseline.

- [x] **Step 8: Commit**

```bash
git add scripts/editorial-audit.php scripts/tests/editorial-audit-test.php docs/editorial/claim-register.csv README.md
git commit -m "test: block unsupported editorial claims"
```

### Task 3: Replace the demo identity and unsupported homepage proof

**Files:**
- Modify: `public/themes/souverainete-digitale/index.fr.html`
- Modify: `public/themes/souverainete-digitale/css/hallmark-redesign.css`
- Modify: `public/themes/souverainete-digitale/css/hallmark-tokens.css`
- Modify: `seed.dokploy.sql`
- Modify: `seed.dokploy.php`

**Interfaces:**
- Consumes: the brand promise and launch constraints from the spec.
- Produces: the indexable French homepage and deployment seed using the Indépendant Digital identity without unsupported proof.

- [x] **Step 1: Add a homepage assertion test**

Create a shell verification in `scripts/tests/french-homepage-content-test.sh` that fails unless the French homepage contains `Indépendant Digital`, `independantdigital.fr`, `Décisions numériques`, and a methodology link. It must also fail when it finds `Digital.Sovereignty`, `souverainete-digitale.fr`, `ACMECORP`, `250 organisations`, `Conformité vérifiée`, or `certifié SecNumCloud` used as an Indépendant Digital credential.

- [x] **Step 2: Run it to verify it fails**

Run:

```bash
bash scripts/tests/french-homepage-content-test.sh
```

Expected: FAIL on the current demo identity and proof.

- [x] **Step 3: Rewrite the visible French homepage**

Use the approved core copy:

```text
Title: Indépendant Digital — Décider et migrer sans dépendance aveugle
Hero: Réduisez vos dépendances numériques, cas d’usage par cas d’usage.
Lead: Guides, diagnostics et recommandations pour les DSI, RSSI et dirigeants qui veulent reprendre le contrôle de leurs outils, de leurs données et de leur capacité de sortie.
Primary CTA: Évaluer mes dépendances
Secondary CTA: Comprendre notre méthode
```

Replace the placeholder logo strip with a short methodology statement. Remove customer counts, invented logos, generic testimonials, fake savings, decorative certification badges, and unsupported “verified” labels. Keep useful navigation and working routes.

- [x] **Step 4: Update the deployment seed**

Replace the matching French homepage metadata and any seed block that would reintroduce removed proof. Bump the seed marker from `v9` to `v10` only after the SQL is idempotent and scoped.

- [x] **Step 5: Run focused tests**

Run:

```bash
bash scripts/tests/french-homepage-content-test.sh
php scripts/editorial-audit.php public/themes/souverainete-digitale/index.fr.html
php -l seed.dokploy.php
```

Expected: all pass. The full seed audit remains red until the broader seeded-content remediation in Tasks 5 and 9; this task verifies only that the homepage and its metadata no longer add blockers.

- [x] **Step 6: Render and inspect**

Run the existing Docker stack, load `/`, and inspect at 320, 375, 414, 768, and desktop widths. Confirm there is no horizontal scroll, no wrapped primary control, and no missing route.

- [x] **Step 7: Commit**

```bash
git add public/themes/souverainete-digitale/index.fr.html public/themes/souverainete-digitale/css/hallmark-redesign.css public/themes/souverainete-digitale/css/hallmark-tokens.css seed.dokploy.sql seed.dokploy.php scripts/tests/french-homepage-content-test.sh
git commit -m "feat: establish credible French homepage"
```

### Task 4: Park English without deleting it

**Files:**
- Modify: `public/themes/souverainete-digitale/index.html`
- Modify: `public/themes/souverainete-digitale/content/index.html`
- Modify: `public/themes/souverainete-digitale/content/page.html`
- Modify: `public/themes/souverainete-digitale/content/post.html`
- Modify: `public/themes/souverainete-digitale/content/contact.html`
- Modify: `public/themes/souverainete-digitale/index.fr.html`
- Modify: `public/themes/souverainete-digitale/content/index.fr.html`
- Modify: `public/themes/souverainete-digitale/content/page.fr.html`
- Modify: `public/themes/souverainete-digitale/content/post.fr.html`
- Modify: `public/themes/souverainete-digitale/content/contact.fr.html`

**Interfaces:**
- Consumes: existing `/en/` routes and source content.
- Produces: accessible but non-indexable English routes and a French interface with no language switcher.

- [x] **Step 1: Add language-policy assertions**

Create `scripts/tests/language-policy-test.sh`. Assert every English template contains `<meta name="robots" content="noindex,follow">`, every French template lacks that tag, and French templates contain no `data-v-component-language` block.

- [x] **Step 2: Run it to verify it fails**

Run:

```bash
bash scripts/tests/language-policy-test.sh
```

Expected: FAIL.

- [x] **Step 3: Apply the language policy**

Add the robots meta tag to English templates. Remove the switcher block from French shared chrome without deleting English templates or database rows. Ensure sitemap generation does not advertise English acquisition pages; if the CMS feed cannot filter by language safely, add `X-Robots-Tag: noindex, follow` for `/en/` routes at the web-server layer as a second guard.

- [x] **Step 4: Verify**

Run:

```bash
bash scripts/tests/language-policy-test.sh
```

Expected: PASS.

- [x] **Step 5: Commit**

```bash
git add public/themes/souverainete-digitale scripts/tests/language-policy-test.sh
git commit -m "seo: park English acquisition content"
```

### Task 5: Publish methodology and commercial-transparency pages

**Files:**
- Modify: `seed.dokploy.sql`
- Modify: `seed.dokploy.php`
- Modify: `public/themes/souverainete-digitale/index.fr.html`
- Create: `docs/editorial/provider-review-checklist.md`
- Create: `docs/editorial/manual-publication-checklist.md`

**Interfaces:**
- Consumes: provider evaluation fields and publication requirements from the spec.
- Produces: `/page/methode-evaluation`, `/page/transparence-partenariats`, and internal checklists.

- [x] **Step 1: Write route-content assertions**

Create `scripts/tests/transparency-content-test.sh` to assert the seed contains both slugs and the phrases `partenaire commercial non exclusif`, `aucune recommandation automatique`, `correction factuelle`, `date de dernière revue`, and `alternatives`.

- [x] **Step 2: Run it to verify it fails**

Run:

```bash
bash scripts/tests/transparency-content-test.sh
```

Expected: FAIL.

- [x] **Step 3: Seed both pages**

Use `content/page.html`, French language only, published status, unique slugs, concise metadata, primary-source links, and no claims about work not yet completed. Add the methodology and transparency links to the footer.

- [x] **Step 4: Add internal review checklists**

The provider checklist mirrors the evaluation dimensions in the spec. The publication checklist requires named owner/reviewer, intent, sources, disclosures, alternatives, original contribution, mobile QA, metadata, links, structured data, and scheduled review.

- [x] **Step 5: Verify**

Run:

```bash
bash scripts/tests/transparency-content-test.sh
php scripts/editorial-audit.php docs/editorial
php -l seed.dokploy.php
```

Expected: PASS. The full legacy seed remains a remediation target for Task 9.

- [x] **Step 6: Commit**

```bash
git add seed.dokploy.sql seed.dokploy.php public/themes/souverainete-digitale/index.fr.html docs/editorial scripts/tests/transparency-content-test.sh
git commit -m "feat: publish evaluation and partnership methodology"
```

### Task 6: Separate independent diagnosis from named provider consent

**Files:**
- Modify: `plugins/lead-platform-connector/app/controller/submit.php`
- Modify: `plugins/lead-platform-connector/public/js/lead-form.js`
- Modify: `plugins/lead-platform-connector/install/sql/mysqli/schema/lead_submission.sql`
- Modify: `plugins/lead-platform-connector/install/sql/pgsql/schema/lead_submission.sql`
- Modify: `plugins/lead-platform-connector/install/sql/sqlite/schema/lead_submission.sql`
- Modify: `plugins/lead-platform-connector/README.md`
- Create: `plugins/lead-platform-connector/tests/consent-test.php`

**Interfaces:**
- Consumes: `provider_introduction_requested`, `provider_slug`, `consent_text_version`, and `consent_timestamp` only from an explicit provider-introduction form.
- Produces: validated provider-introduction fields forwarded to the lead platform and a non-PII audit record of consent version, provider, and time.

- [x] **Step 1: Write failing consent tests**

Test that a general diagnostic can submit without a provider. Test that an AIFEL introduction is rejected unless `provider_introduction_requested` is exactly `1`, `provider_slug` is `aifel`, and consent version and timestamp are present. Test that the controller never infers provider consent from newsletter consent or endpoint slug.

- [x] **Step 2: Run the tests to verify they fail**

Run:

```bash
php plugins/lead-platform-connector/tests/consent-test.php
```

Expected: FAIL because the consent validator does not exist.

- [x] **Step 3: Add a focused validator**

Extract validation into a small pure function or class used by the controller. General diagnostics remain valid without provider fields. Named introductions require all four fields and an allowed provider slug configured server-side.

- [x] **Step 4: Store the audit fields**

Add nullable `provider_slug`, `consent_text_version`, and `consent_at` columns. Do not store the raw consent sentence or duplicate contact PII in the audit table.

- [x] **Step 5: Verify**

Run:

```bash
php plugins/lead-platform-connector/tests/consent-test.php
php -l plugins/lead-platform-connector/app/controller/submit.php
```

Expected: PASS.

- [x] **Step 6: Commit**

```bash
git add plugins/lead-platform-connector
git commit -m "feat: require named consent for provider introductions"
```

### Task 7: Add the reusable provider and alternatives content contract

**Files:**
- Create: `docs/editorial/provider-page-template.md`
- Create: `docs/editorial/alternatives-page-template.md`
- Create: `docs/editorial/keyword-opportunity-register.csv`
- Create: `scripts/validate-content-contract.php`
- Create: `scripts/tests/content-contract-test.php`

**Interfaces:**
- Consumes: authored provider or alternatives Markdown working copy before it is converted into Vvveb seed HTML.
- Produces: pass/fail validation for mandatory headings, disclosure, review metadata, sources, fit/not-fit, evidence gaps, and alternatives.

- [x] **Step 1: Write failing fixture tests**

Test a provider fixture missing `## Alternatives` and `## Ne convient pas si`; test an alternatives fixture missing keyword evidence and methodology. Both must fail. Complete fixtures must pass.

- [x] **Step 2: Implement templates and validator**

Provider headings are: Summary, Best fit, Does not fit, Capabilities, Hosting and jurisdiction, Security evidence, Integration and reversibility, Pricing, Evidence gaps, Commercial relationship, Alternatives, Sources, Review record.

Alternatives headings are: Search intent, Demand evidence, Selection method, Decision table, Detailed alternatives, Migration considerations, Commercial relationships, Sources, Review record.

- [x] **Step 3: Create the opportunity register**

Use this header:

```csv
query_cluster,country,language,monthly_volume,gsc_impressions_90d,intent,commercial_relevance,distinct_intent,original_analysis_ready,cannibalization_checked,decision,checked_on,source,owner,notes
```

- [x] **Step 4: Verify and commit**

Run:

```bash
php scripts/tests/content-contract-test.php
```

Expected: PASS.

Commit:

```bash
git add docs/editorial scripts/validate-content-contract.php scripts/tests/content-contract-test.php
git commit -m "feat: define provider and alternatives content contract"
```

### Task 8: Prepare the AIFEL evidence gate without publishing unsupported claims

**Files:**
- Create: `docs/providers/aifel/evidence-request.md`
- Create: `docs/providers/aifel/review.md`
- Create: `docs/providers/aifel/claim-register.csv`
- Create: `docs/providers/aifel/lead-qualification.md`

**Interfaces:**
- Consumes: source documents and product evidence supplied by AIFEL.
- Produces: an editorial go/no-go decision and a routing specification. It does not publish a provider page until the gate is passed.

- [x] **Step 1: Create the evidence request**

Request ownership, hosting, subprocessors, DPA, qualifications, encryption by module, identity and integration, export, SLA, continuity, accessibility, price bands, product demonstration, security testing summary, and permission for every logo or case claim.

- [x] **Step 2: Create the review document**

Record the defensible category, best-fit and poor-fit scenarios, verified capabilities, open questions, comparative claims prohibited pending evidence, and editorial decision.

- [x] **Step 3: Create the AIFEL claim register**

Use the same columns as the global claim register and leave unsupported claims in `proposed` or `rejected`, never `verified`.

- [x] **Step 4: Create the lead qualification contract**

Require secure collaboration or videoconferencing as the central use case, French operation as a material requirement, approximate scope, relevant decision role, timing, current tools, and explicit named consent.

- [x] **Step 5: Run the editorial audit and commit**

Run:

```bash
php scripts/editorial-audit.php docs/providers/aifel
```

Expected: PASS.

Commit:

```bash
git add docs/providers/aifel
git commit -m "docs: establish AIFEL evidence and qualification gate"
```

### Task 9: Build the French launch content and local lead queue

**Files:**
- Modify: `seed.dokploy.sql`
- Modify: `seed.dokploy.php`
- Modify: `public/themes/souverainete-digitale/index.fr.html`
- Modify: `public/themes/souverainete-digitale/content/contact.fr.html`
- Modify: `plugins/lead-platform-connector/app/controller/submit.php`
- Create: `plugins/lead-platform-connector/system/delivery-mode.php`
- Create: `plugins/lead-platform-connector/tests/delivery-mode-test.php`
- Create: `scripts/tests/launch-content-test.php`

**Interfaces:**
- Consumes: French positioning, editorial rules and lead payload contract.
- Produces: useful live pages, a diagnostic/contact journey and locally queued submissions that can be wired to the external distribution API without changing the forms.

- [x] **Step 1: Write failing launch-content and delivery-mode tests**

Assert that the seed contains live methodology, transparency, diagnostic, contact, about, independence hub, Microsoft 365 exit guide and collaboration-selection guide pages. Assert that each live form uses the launch endpoint and carries a privacy acknowledgement. Assert that a blank platform URL selects local queue mode.

- [x] **Step 2: Implement local queue mode**

Allow an active endpoint with an empty platform URL/API key to validate and store the submission as `pending` without external transmission. Preserve the same audit and response contract used when the distribution API is configured later.

- [x] **Step 3: Seed and link the live launch set**

Rewrite the diagnostic, contact and about content; publish the two decision guides; seed the queue-only endpoint; and make the French navigation expose only working, reviewed routes.

- [x] **Step 4: Verify and commit**

Run launch, consent, transparency and editorial checks, then commit as `feat: build French launch journey and lead queue`.

### Task 10: Add gated scheduled publishing

**Files:**
- Create: `scripts/lib/scheduled-publisher.php`
- Create: `scripts/publish-scheduled-content.php`
- Create: `scripts/tests/scheduled-publisher-test.php`
- Modify: `app/sql/mysqli/post.sql`
- Modify: `app/sql/pgsql/post.sql`
- Modify: `app/sql/sqlite/post.sql`
- Modify: `seed.dokploy.sql`
- Modify: `docs/editorial/keyword-opportunity-register.csv`

**Interfaces:**
- Consumes: posts with `status=scheduled`, a due `created_at`, and `post_meta.editorial_ready=1`.
- Produces: atomic publication of eligible posts; held drafts and future posts remain inaccessible.

- [x] **Step 1: Write the failing scheduler test**

Use an in-memory SQLite database to prove that only due, editorially approved records publish.

- [x] **Step 2: Implement the publisher and route status guard**

Add a CLI publisher suitable for a Dokploy cron job. Ensure direct page lookups respect `status=publish` so scheduled content cannot leak by slug.

- [x] **Step 3: Seed the content calendar**

Add complete French working drafts for Teams alternatives, a French Zoom alternative and French collaborative suites. Keep `editorial_ready=0` until keyword demand and manual QA are recorded.

- [x] **Step 4: Verify and commit**

Run scheduler tests and verify held routes return 404 in the seeded stack.

### Task 11: Remove unsafe legacy proof and finish technical launch policy

**Files:**
- Modify: `seed.dokploy.sql`
- Modify: `public/themes/souverainete-digitale/index.html`
- Modify: `public/themes/souverainete-digitale/generated/*.fr.html`
- Modify: `public/themes/souverainete-digitale/content/*.fr.html`
- Create: `docs/launch/open-items.md`
- Create: `scripts/tests/launch-policy-test.sh`

**Interfaces:**
- Consumes: the editorial baseline and live/scheduled content decisions.
- Produces: zero unsupported-claim blockers, no placeholder identities, correct indexation and an explicit list of external legal/credential inputs still required.

- [x] **Step 1: Write launch-policy assertions**

Test the final domain, French metadata, parked English policy, absence of demo identities and absence of publishable forms pointing at development endpoints.

- [x] **Step 2: Remediate legacy content**

Remove fabricated proof, narrow legal/security statements and retire content that cannot be sourced. Do not publish invented legal entity, hosting or contact details.

- [x] **Step 3: Clear the editorial audit**

Run the audit over theme, seed, editorial and provider documentation until it returns zero blockers.

- [x] **Step 4: Commit**

Commit as `fix: clear launch editorial and identity blockers`.

### Task 12: Full launch verification

**Files:** none.

**Interfaces:**
- Consumes: Tasks 1–11.
- Produces: reproducible evidence for the exact launch-ready boundary and any external configuration still required.

- [x] **Step 1: Run all automated checks**

```bash
php scripts/tests/editorial-audit-test.php
bash scripts/tests/french-homepage-content-test.sh
bash scripts/tests/language-policy-test.sh
bash scripts/tests/transparency-content-test.sh
php plugins/lead-platform-connector/tests/consent-test.php
php scripts/tests/content-contract-test.php
php plugins/lead-platform-connector/tests/delivery-mode-test.php
php scripts/tests/launch-content-test.php
php scripts/tests/scheduled-publisher-test.php
bash scripts/tests/launch-policy-test.sh
php scripts/editorial-audit.php public/themes/souverainete-digitale seed.dokploy.sql docs/editorial docs/providers
php -l seed.dokploy.php
```

Expected: every command passes.

- [x] **Step 2: Run route and form checks**

With the Docker stack running, verify `/`, methodology, transparency, contact, diagnostic, blog, and one resource page return 200. Verify `/en/` returns 200 with `noindex,follow`. Submit a general diagnostic without provider consent and verify no `provider_slug` is sent. Submit an AIFEL test introduction with explicit consent and verify the consent audit fields.

- [x] **Step 3: Complete manual QA**

Use `docs/editorial/manual-publication-checklist.md` on the homepage, methodology, transparency, and diagnostic. Record failures as issues; do not mark launch-ready while any launch gate is open.

- [x] **Step 4: Review the diff**

```bash
git status --short
git diff --check
git log --oneline master..HEAD
```

Expected: no whitespace errors, no unrelated files, and one focused commit per completed task.
