# Diagnostic de souveraineté — multi-step intake design

**Date:** 2026-09-01
**Status:** Approved design
**Sequence:** spec 3 of 3 — after the homepage and directory specs
**Rules in force:** 2026-08-27 growth spec §10 (diagnostic fields, routing states), `plugins/lead-platform-connector` consent and privacy rules

## 1. Goal

Replace the three inconsistent forms (homepage 12-field form, `/page/contact` form, `/page/diagnostic-souverainete` 5-field form) with one three-step intake that captures contact details first, so an abandoned diagnostic still yields a usable lead, and that collects the qualification fields the 2026-08-27 spec defines.

## 2. Placement

- **Step 1** rendered inline on the homepage (§3.5 of the homepage spec) and as the first panel on `/page/diagnostic-souverainete`.
- **Steps 2–3** rendered only on `/page/diagnostic-souverainete`. Submitting step 1 on the homepage redirects to `/page/diagnostic-souverainete?lead=<token>#etape-2`.
- `/page/contact` embeds the same component (all three steps) with a short intro; the separate contact form is removed.

One markup source: `plugins/lead-platform-connector/app/template/diagnostic-form.tpl`, included by the three templates through the existing component mechanism (`data-v-component-plugin-lead-platform-connector-leadform` with a `variant="diagnostic"` option). The UI test asserts the three placements share it.

## 3. Steps and fields

Field names already accepted by `submit.php` are kept; new names are added (the controller serialises every field).

### Step 1 — Contact (saved immediately)

| name | type | required |
|---|---|---|
| `email` | email, professional (client-side hint, not enforced) | yes |
| `full_name` | text | yes |
| `company` | text (organisation) | yes |
| `job_title` | select: DSI · RSSI · CTO · DG / DGS · Achats · Transformation · Autre | yes |
| `privacy_acknowledgement` | checkbox | yes |

### Step 2 — Situation

| name | type |
|---|---|
| `org_type` | select: PME · ETI · Grand compte · Collectivité · Établissement public · Santé · Association · Autre |
| `company_size` | select: < 50 · 50–249 · 250–999 · 1 000–4 999 · ≥ 5 000 |
| `current_tools[]` | checkboxes: Microsoft 365 · Teams · Google Workspace · Zoom · Slack · AWS · Azure · Google Cloud · Autre |
| `use_cases[]` | checkboxes: Visioconférence et collaboration · Messagerie · Bureautique · Fichiers et partage · Identité et accès · Hébergement et cloud · Sauvegarde · Cybersécurité · IA et agents · Autre |
| `constraints[]` | checkboxes: Données sensibles / RGPD · HDS · NIS2 · SecNumCloud · Commande publique · Aucune contrainte identifiée |
| `trigger` | select: Renouvellement de contrat · Incident de sécurité · Audit ou contrôle · Programme cloud · Exigence de tutelle ou de direction · Autre |
| `timeline` | select: < 3 mois · 3–6 mois · 6–12 mois · > 12 mois · Pas de date |
| `budget` | select (optional): < 10 k€ · 10–50 k€ · 50–200 k€ · > 200 k€ · Non défini |
| `financing_interest` | checkbox: « Je souhaite étudier les financements et subventions disponibles (partenaires, dispositifs publics) » |
| `ai_platform_interest` | checkbox: « Accès anticipé à la plateforme d'agents IA » |

### Step 3 — Suite souhaitée

| name | type |
|---|---|
| `next_step` | radio: Résultat indépendant seulement · Échange de 30 minutes · Accompagnement · Mise en relation nominative |
| provider consent block | shown only when `next_step = mise-en-relation`; existing fields `provider_introduction_requested`, `provider_slug`, `consent_text_version` unchanged |
| `message` | textarea |
| `phone` | tel, optional |

Every step has « Continuer » / « Retour »; step 3 has « Envoyer ». Progress indicator « Étape n sur 3 ». Keyboard-accessible; each step is a `<fieldset>`; the active step is the only one not `hidden`.

## 4. Partial save

- Step 1 submit POSTs `{ endpoint, csrf, fields, stage: 1 }` to the existing submit URL. The controller inserts the `lead_submission` row with `stage = 1` and returns `{ ok, lead_token }`.
- `lead_token`: 32 random bytes, hex, stored hashed (`sha256`) in a new column `lead_submission.lead_token_hash`; expires 24 h after creation (`lead_token_expires_at`).
- Steps 2 and 3 POST `{ endpoint, csrf, lead_token, fields, stage: 2|3 }`. The controller looks up the row by token hash and endpoint, rejects expired or unknown tokens with 410, merges the fields into the encrypted payload, updates `stage`, and re-runs privacy and consent validation on the merged payload.
- Forwarding to an external lead platform (`DeliveryMode::FORWARD`) happens only at `stage = 3` or when the row is 24 h old with `stage < 3` (scheduled job reuse: `scripts/publish-scheduled-content.php` pattern → new `scripts/flush-partial-leads.php`). Until then the lead stays in the local queue.
- The admin queue shows `stage` and marks `stage < 3` rows as « Diagnostic incomplet ».
- A returning visitor with a valid `?lead=<token>` resumes at the stored stage; without a token the form starts at step 1.

## 5. Backend changes

- Migration: `scripts/migrate-lead-schema.php` adds `stage TINYINT DEFAULT 3`, `lead_token_hash CHAR(64) NULL`, `lead_token_expires_at DATETIME NULL` (mysqli, pgsql, sqlite variants in `plugins/lead-platform-connector/sql/*`).
- `submit.php`: accept `stage` and `lead_token`; branch insert vs update; return `lead_token` on stage-1 insert; keep the existing full-submission path (no `stage`) working for the registration form of the directory spec.
- `lead-form.js` → `diagnostic-form.js`: stepper, per-step native validation, token storage in `sessionStorage`, redirect after homepage step 1.
- `privacy_acknowledgement` validated at stage 1 (it is collected there); provider consent validated at the stage where it is submitted.

## 6. Copy

- Step 1 heading « Diagnostic de souveraineté » and one sentence: « Commencez par vos coordonnées ; vous pourrez compléter la situation ensuite. »
- Step-1 success text before redirect: « Coordonnées enregistrées. Précisez maintenant votre situation. »
- Final success: « Merci. Votre diagnostic est enregistré et sera relu avant toute orientation. Aucune coordonnée n'est transmise à un fournisseur sans votre consentement nominatif. »
- Privacy notice (`/page/confidentialite`) gains one line: partial submissions are kept for follow-up and purged with the same retention as complete ones.

## 7. Tests

- Connector PHP tests: stage-1 insert returns token; stage-2 update merges fields; expired/unknown token → 410; consent validation on merged payload; full-submission path unchanged; `submitted` flush job forwards only stage-3 or aged rows.
- UI test: homepage and diagnostic page contain the same `diagnostic-form` markup; no `phone` marked required; step 2 and 3 fieldsets `hidden` at load; « Étape 1 sur 3 » present.
- Rendered check: keyboard navigation through the three steps at 375 px and 1440 px; error and success alerts announced (`role="alert"`).

## 8. Out of scope

Scoring or automatic routing of the diagnostic result, e-mail notifications to the visitor, CRM integration, the directory's registration form (own spec).
