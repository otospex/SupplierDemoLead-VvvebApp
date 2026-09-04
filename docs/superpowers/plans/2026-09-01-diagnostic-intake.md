# Diagnostic de souveraineté — Multi-step Intake Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the three inconsistent lead forms with one three-step intake that saves a usable contact lead at step 1 and collects the qualification fields at steps 2–3.

**Architecture:** The existing `lead-platform-connector` plugin gains a `stage`/`lead_token` partial-save path in `submit.php` and a stepper front-end (`diagnostic-form.js` + one shared form markup). The three placements (homepage step 1, diagnostic page steps 1–3, contact page steps 1–3) share one markup source. Page content changes go to the live DB and `seed.dokploy.sql` in parity.

**Tech Stack:** PHP (Vvveb plugin), vanilla JS, MySQL/pgsql/sqlite DDL, plain-PHP CLI tests.

**Spec:** `docs/superpowers/specs/2026-09-01-diagnostic-intake-design.md` (fields §3, partial save §4, backend §5, copy §6, tests §7).

## Global Constraints

- Existing field names keep working: `full_name`, `email`, `company`, `job_title`, `phone`, `message`, `privacy_acknowledgement`, `provider_introduction_requested`, `provider_slug`, `consent_text_version`.
- The full-submission path (no `stage` in payload) must behave exactly as today — the directory's `solution-registration` form and any legacy form depend on it.
- `lead_token` is 32 random bytes hex, stored only as `sha256` hash, expires 24 h; unknown/expired token → HTTP 410.
- Privacy acknowledgement validated at stage 1; provider consent validated at the stage where its fields are submitted; both re-validated on the merged payload at stage 3.
- Forwarding to an external platform (`DeliveryMode::FORWARD`) only at stage 3 or when a partial row is ≥ 24 h old (flush job).
- Phone is optional everywhere. French copy: vouvoiement, curly apostrophes/`&rsquo;` per surrounding convention, `&nbsp;` before `: ? !`.
- Every DB content change mirrored in `seed.dokploy.sql` (`@lang_fr` idiom, `''` escaping). Cache recipe after template/DB edits: `rm -f storage/compiled-templates/app_1_*; rm -rf public/page-cache/*; docker compose restart php` (wait ~8 s); preview http://127.0.0.1:8090.
- `css/souverainete.css` cache-buster is content-derived: if the CSS changes, recompute the trailing `/* content-hash: XXXXXXXX */` marker and set `?v=<hash8>` on all 19 linking templates (see theme README; `homepage-contract-test.php` enforces it for 5 templates).
- All existing suites stay green: `for t in scripts/tests/*.php; do php "$t"; done; for t in scripts/tests/*.sh; do bash "$t"; done; for t in plugins/lead-platform-connector/tests/*.php; do php "$t"; done` (plus `plugins/solutions-directory/tests/*` once the directory is merged).
- Do not commit `config/admin.php` / `config/app.php` mode-change noise.

## Dispatch map (orchestrator note)

- Tasks 1, 2, 4: mechanical/tests/DDL → sonnet. Task 3: backend judgment → opus. Task 5: front-end + copy → opus. Task 6: verification → sonnet.

---

### Task 1: Failing connector tests for the partial-save contract

**Files:**
- Create: `plugins/lead-platform-connector/tests/partial-lead-test.php`

**Interfaces:**
- Consumes: `plugins/lead-platform-connector/system/repo.php`, `system/crypto.php`, test idioms from `tests/consent-test.php` (pure-function tests, no HTTP; echo `partial-lead tests: PASS`).
- Produces: assertions against two new pure helpers Task 3 will create in `plugins/lead-platform-connector/system/partial-lead.php`:
  - `PartialLead::issueToken(): array{token:string, hash:string, expires_at:string}` — token 64 hex chars; hash = `hash('sha256', token)`; expires_at = now +24 h UTC `Y-m-d H:i:s`.
  - `PartialLead::validate(array $payload, callable $findByHash): array{ok:bool, mode:'insert'|'update'|'full', row?:array, error?:string, http?:int}` — no `stage` key ⇒ `mode=full`; `stage===1` without token ⇒ `insert`; `stage` 2|3 with `lead_token` ⇒ look up via `$findByHash(hash)`; unknown row or `expires_at < now` ⇒ `ok=false, http=410`.
  - `PartialLead::merge(array $existingFields, array $newFields): array` — new keys win; `privacy_acknowledgement` never downgraded from '1'; provider-consent fields only present if `provider_introduction_requested === '1'` in the merged result.

- [ ] **Step 1: Write the failing tests**

Cover: token shape and 24 h expiry; full mode when `stage` absent; insert mode at stage 1; update mode at stages 2/3 with valid token; 410 on unknown hash; 410 on expired row; merge semantics (new keys win; ack persistence; consent-field stripping when not requested).

- [ ] **Step 2: Run to verify failure** — `php plugins/lead-platform-connector/tests/partial-lead-test.php` → FAIL (class missing).

- [ ] **Step 3: Commit** — `test: partial-lead contract for staged diagnostic intake`

---

### Task 2: Schema — stage + token columns, three drivers

**Files:**
- Modify: `plugins/lead-platform-connector/install/sql/mysqli/*.sql`, `.../pgsql/*.sql`, `.../sqlite/*.sql` (the `lead_submission` CREATE)
- Modify: `scripts/migrate-lead-schema.php` (idempotent ALTERs for existing installs)

**Interfaces:**
- Produces columns: `stage TINYINT NOT NULL DEFAULT 3` (pgsql `SMALLINT`, sqlite `INTEGER`), `lead_token_hash CHAR(64) NULL` + unique index, `lead_token_expires_at DATETIME NULL` (pgsql `TIMESTAMP`).
- Existing rows read as stage 3 (complete) via the DEFAULT.

- [ ] **Step 1: Add columns to the three CREATE files and idempotent ALTERs to the migrate script** (follow the script's existing column-exists guard pattern).
- [ ] **Step 2: Run** `php scripts/migrate-lead-schema.php` against the local DB; verify with `docker compose exec -T db mysql -uvvveb -pvvveb vvveb -e "SHOW COLUMNS FROM lead_submission LIKE 'stage'"`.
- [ ] **Step 3: Full plugin tests still PASS; commit** — `feat: add stage and lead-token columns to lead_submission`

---

### Task 3: Backend — `PartialLead` helper + `submit.php` staged path

**Files:**
- Create: `plugins/lead-platform-connector/system/partial-lead.php`
- Modify: `plugins/lead-platform-connector/app/controller/submit.php`

**Interfaces:**
- Consumes: Task 1's contract (implement exactly those signatures), Task 2's columns, existing `CsrfToken`, `PrivacyAcknowledgement`, `ProviderConsent`, `DeliveryMode`, crypto/repo helpers.
- Produces JSON: stage-1 success `{ok:true, lead_token:"<64hex>"}`; stage-2 `{ok:true}`; stage-3 same as today's full success; 410 `{ok:false, message:"Votre session de diagnostic a expiré. Recommencez, vos informations n’ont pas été perdues côté serveur."}` (wording final).

- [ ] **Step 1: Implement `PartialLead` to make Task 1 tests pass**; run them → PASS.
- [ ] **Step 2: Wire into `submit.php`:** after CSRF/origin/rate-limit, branch on `PartialLead::validate`: `full` → existing code path untouched; `insert` → validate privacy ack, encrypt fields, INSERT with `stage=1`, token hash + expiry, return token (do NOT run provider-consent validation at stage 1 — those fields can't exist yet); `update` → decrypt existing payload, `merge`, validate privacy on merged, validate provider consent when `provider_introduction_requested` present in merged, re-encrypt, UPDATE row + `stage`; forwarding only when merged stage becomes 3 and `DeliveryMode::FORWARD`.
- [ ] **Step 3: Re-run all connector tests + full suite** → PASS. The existing tests prove the full path unchanged.
- [ ] **Step 4: Commit** — `feat: staged partial-save path for diagnostic intake`

---

### Task 4: Flush job for aged partial leads

**Files:**
- Create: `scripts/flush-partial-leads.php` (CLI, modeled on `scripts/publish-scheduled-content.php` bootstrap)
- Create: `plugins/lead-platform-connector/tests/flush-partial-test.php`

**Interfaces:**
- Behavior: rows with `stage < 3` and `created_at <= now −24 h` are marked `stage = 3` (final) and, when `DeliveryMode::FORWARD`, forwarded via the existing client; local mode just finalizes. Prints a one-line summary `flushed N partial leads`. Test covers: aged row flushed, fresh partial untouched, complete row untouched (pure-function extraction like the other tests — put the row-selection predicate in `PartialLead::isFlushable(array $row, string $now): bool` and test that).

- [ ] Step 1: failing test → Step 2: implement → Step 3: tests PASS → Step 4: commit `feat: flush job finalizes aged partial diagnostic leads`.

---

### Task 5: Front-end — one stepper form, three placements, copy + seed

**Files:**
- Create: `plugins/lead-platform-connector/public/js/diagnostic-form.20260901.js` (stepper; extends the fetch/CSRF plumbing of `lead-form.20260827.js` — copy the file and add the stepper; do not modify the old file, other forms still use it)
- Modify: `public/themes/souverainete-digitale/index.fr.html` (homepage `#diagnostic` step-1 form: add `data-v-stage="1"` behavior — on success, redirect to `/page/diagnostic-souverainete?lead=<token>#etape-2`; script include swapped to the new JS for this form)
- Modify: live DB + `seed.dokploy.sql` — `diagnostic-souverainete` and `contact` page content: replace their current single-card forms with the shared 3-step markup below
- Modify: `public/themes/souverainete-digitale/css/souverainete.css` (stepper styles, token-only; recompute content-hash marker and `?v=` across 19 templates per the README rule)
- Modify: `scripts/tests/homepage-contract-test.php` (assert homepage form has the 4 step-1 fields + `data-v-stage="1"`; assert no `required` phone anywhere in the file — keep existing assertions)

**The three steps (markup normative; ids suffixed per placement):**

Step 1 fieldset (visible): `email` (email, required), `full_name` (required), `company` (required), `job_title` select required — options: `DSI · RSSI · CTO · DG / DGS · Achats · Transformation · Autre`; `privacy_acknowledgement` checkbox required (existing label + link to `/page/confidentialite`); button « Continuer ».

Step 2 fieldset (`hidden` at load): `org_type` select — PME · ETI · Grand compte · Collectivité · Établissement public · Santé · Association · Autre; `company_size` select — `< 50` · `50–249` · `250–999` · `1 000–4 999` · `≥ 5 000`; `current_tools[]` checkboxes — Microsoft 365 · Teams · Google Workspace · Zoom · Slack · AWS · Azure · Google Cloud · Autre; `use_cases[]` checkboxes — Visioconférence et collaboration · Messagerie · Bureautique · Fichiers et partage · Identité et accès · Hébergement et cloud · Sauvegarde · Cybersécurité · IA et agents · Autre; `constraints[]` checkboxes — Données sensibles / RGPD · HDS · NIS2 · SecNumCloud · Commande publique · Aucune contrainte identifiée; `trigger` select — Renouvellement de contrat · Incident de sécurité · Audit ou contrôle · Programme cloud · Exigence de tutelle ou de direction · Autre; `timeline` select — `< 3 mois` · `3–6 mois` · `6–12 mois` · `> 12 mois` · Pas de date; `budget` select optional — `< 10 k€` · `10–50 k€` · `50–200 k€` · `> 200 k€` · Non défini; `financing_interest` checkbox — « Je souhaite étudier les financements et subventions disponibles (partenaires, dispositifs publics) »; `ai_platform_interest` checkbox — « Accès anticipé à la plateforme d'agents IA »; buttons « Retour » / « Continuer ».

Step 3 fieldset (`hidden`): `next_step` radios — `resultat-independant` « Résultat indépendant seulement » · `echange-30min` « Échange de 30 minutes » · `accompagnement` « Accompagnement » · `mise-en-relation` « Mise en relation nominative »; the existing provider-consent block shown only when `mise-en-relation` selected (existing field names, unchecked by default); `message` textarea; `phone` tel optional; buttons « Retour » / « Envoyer ».

Progress indicator « Étape N sur 3 » (`aria-live="polite"`); each fieldset a `<fieldset>` with `<legend>`; per-step native validation before advancing; token from stage-1 response kept in `sessionStorage` and re-sent with stages 2–3; `?lead=<token>` in URL resumes at step 2.

**Copy (spec §6, verbatim):** step-1 heading « Diagnostic de souveraineté » + « Commencez par vos coordonnées ; vous pourrez compléter la situation ensuite. »; step-1 saved note « Coordonnées enregistrées. Précisez maintenant votre situation. »; final success « Merci. Votre diagnostic est enregistré et sera relu avant toute orientation. Aucune coordonnée n'est transmise à un fournisseur sans votre consentement nominatif. » Privacy page (`confidentialite`, DB+seed) gains one sentence: « Les demandes partiellement complétées sont conservées pour assurer le suivi et purgées selon la même durée que les demandes complètes. »

- [ ] Step 1: build JS + markup; Step 2: wire the three placements (homepage keeps only step 1); Step 3: DB + seed parity; Step 4: CSS + hash/`?v=` recompute; Step 5: full suite PASS + rendered check (three placements share markup, keyboard walk through steps at 375/1440, submit stage 1 → row with stage=1 in DB, complete steps 2–3 → same row stage=3); Step 6: commit `feat: three-step diagnostic intake with partial save`.

---

### Task 6: End-to-end verification + admin queue stage visibility

**Files:**
- Modify: `plugins/lead-platform-connector/admin/template/*` (queue list: show `stage`, label `Diagnostic incomplet` when < 3 — find the listing template; if the admin lists via SQL model files, add the column there)
- Verify only otherwise.

- [ ] Step 1: admin queue shows stage. Step 2: end-to-end on preview: stage-1 only → queue shows incomplete; full run → complete; abandoned partial older than 24 h → `php scripts/flush-partial-leads.php` finalizes it. Step 3: run every suite (incl. directory plugin tests) → PASS. Step 4: commit `feat: expose intake stage in lead queue`.
