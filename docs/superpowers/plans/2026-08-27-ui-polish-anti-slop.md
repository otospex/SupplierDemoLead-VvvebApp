# UI Polish and Anti-Slop Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remove the measured UI inconsistencies and the strongest AI-template signals from the live French site without changing its information architecture or editorial position.

**Architecture:** Keep the existing Vvveb templates and Bootstrap layout, but make the Hallmark token layer the effective visual source of truth. Add a small static regression audit for rendered-source contracts, then update the French templates and CSS so the live browser uses one stylesheet chain, one CTA hierarchy, and purpose-specific homepage structures.

**Tech Stack:** PHP CLI regression tests, shell launch tests, Vvveb HTML templates, Bootstrap 5, project CSS, Docker-based local preview.

**Spec:** `docs/superpowers/specs/2026-08-27-independant-digital-growth-system-design.md` plus the approved UI audit in the current task.

## Global Constraints

- French remains the only promoted language; English assets stay preserved and hidden from the live acquisition path.
- Partner recommendations remain non-exclusive and evidence-led.
- Do not alter lead form field names, endpoints, privacy acknowledgement, or delivery behavior.
- Primary CTA: dependency diagnostic. Secondary CTA: methodology. Contact is tertiary.
- No fabricated testimonials, metrics, ratings, or supplier claims.
- Buttons and clickable labels remain one line from 320px through desktop widths.
- Existing launch and policy tests must remain green.

---

### Task 1: UI regression audit

**Files:**
- Create: `scripts/tests/ui-polish-test.php`
- Test: `scripts/tests/ui-polish-test.php`

**Interfaces:**
- Consumes: French homepage and theme CSS files.
- Produces: exit code `0` only when the live source contracts for CTA padding, stylesheet loading, decision prompts, and token use are satisfied.

- [x] **Step 1: Write the failing test**

Create assertions that require one `custom.css` reference, forbid testimonial stars/avatars on the homepage, require a decision-list structure, require TOC link rules to exclude `.sd-btn`, and require the content-page eyebrow and hero spacing to use Hallmark tokens.

- [x] **Step 2: Run test to verify it fails**

Run: `php scripts/tests/ui-polish-test.php`

Expected: FAIL on the current duplicate stylesheets, testimonial markup, TOC selector, and legacy colour/spacing declarations.

- [x] **Step 3: Keep the test fixed while implementing Tasks 2–4**

Do not weaken assertions to match implementation. The production changes must satisfy the user-visible contracts.

### Task 2: Normalize CSS, buttons, colours, and page heroes

**Files:**
- Modify: `public/themes/souverainete-digitale/css/custom.css`
- Modify: `public/themes/souverainete-digitale/css/hallmark-redesign.css`
- Modify: `public/themes/souverainete-digitale/css/hallmark-tokens.css`

**Interfaces:**
- Consumes: existing `.sd-btn`, `.sd-page-hero`, `.sd-toc`, form, nav, and homepage component classes.
- Produces: a 44px minimum button system, deliberate 52px large controls, 24px horizontal CTA padding, tokenised active colours, and property-specific motion.

- [x] **Step 1: Repair the TOC selector and button sizing**

Change `.sd-toc a` to `.sd-toc a:not(.sd-btn)` and explicitly preserve CTA padding. Match form submit height to form controls.

- [x] **Step 2: Consolidate active colours and content hero spacing**

Alias legacy variables to Hallmark tokens, replace effective mint/orange/purple accents, and set the content hero to `64px` top and `88px` bottom padding with a restrained navy surface.

- [x] **Step 3: Replace broad transitions in active components**

Use explicit colour, border, box-shadow, and transform transitions; focus rings remain immediate.

- [x] **Step 4: Run the UI regression audit**

Run: `php scripts/tests/ui-polish-test.php`

Expected: only homepage/template structure assertions remain failing.

### Task 3: De-template the French homepage

**Files:**
- Modify: `public/themes/souverainete-digitale/index.fr.html`
- Modify: `public/themes/souverainete-digitale/css/hallmark-redesign.css`

**Interfaces:**
- Consumes: the six current decision destinations and the three existing decision questions.
- Produces: an asymmetric decision index, a flat evidence visual, and a numbered decision checklist without testimonial signals.

- [x] **Step 1: Remove duplicate stylesheet links**

Keep one `custom.css` reference followed by one `hallmark-redesign.css` reference.

- [x] **Step 2: Replace the equal icon-card grid**

Preserve all six destinations and their copy in an asymmetric two-track decision index with text-first links.

- [x] **Step 3: Replace testimonial framing**

Retain the three questions, remove stars, quote marks, avatars, and initials, and render them as numbered decision prompts.

- [x] **Step 4: Reduce eyebrow and generic-copy repetition**

Keep an eyebrow only in the hero and one genuinely ordinal method section. Replace generic CTA headings and apply French sentence casing.

- [x] **Step 5: Run the UI regression audit**

Run: `php scripts/tests/ui-polish-test.php`

Expected: PASS.

### Task 4: Make French template CSS loading deterministic

**Files:**
- Modify: `public/themes/souverainete-digitale/content/index.fr.html`
- Modify: `public/themes/souverainete-digitale/content/page.fr.html`
- Modify: `public/themes/souverainete-digitale/content/post.fr.html`
- Modify: `public/themes/souverainete-digitale/content/contact.fr.html`

**Interfaces:**
- Consumes: `custom.css` and `hallmark-redesign.css`.
- Produces: exactly one link to each stylesheet in every French template.

- [x] **Step 1: Add the single ordered stylesheet chain**

Ensure Bootstrap loads first, then one `custom.css`, then one `hallmark-redesign.css` in each French template.

- [x] **Step 2: Run UI and launch tests**

Run: `php scripts/tests/ui-polish-test.php && bash scripts/tests/launch-policy-test.sh && bash scripts/tests/french-homepage-content-test.sh`

Expected: PASS with zero failures.

### Task 5: Rendered verification

**Files:**
- Verify only: local rendered routes and forms.

**Interfaces:**
- Consumes: Docker preview at `http://127.0.0.1:8090`.
- Produces: browser evidence for layout, contrast, CTA sizing, form state, and absence of overflow.

- [x] **Step 1: Run the full project test suite**

Run every script under `scripts/tests/` and every PHP test under `plugins/lead-platform-connector/tests/`.

- [x] **Step 2: Inspect live French routes**

Check `/`, `/page/methode-evaluation`, `/page/diagnostic-souverainete`, `/page/transparence-partenariats`, `/page/choisir-visioconference-collaboration`, and `/page/contact`.

- [x] **Step 3: Verify responsive and interaction contracts**

Confirm no horizontal scroll, no wrapped CTA labels, visible focus styles, consistent controls, and working form validation at desktop and available mobile widths.

- [x] **Step 4: Leave the local preview running**

Navigate the in-app browser to the homepage for user review and report the local URL.
