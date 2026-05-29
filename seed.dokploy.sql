-- =====================================================================
-- Dokploy auto-seed for the souverainete-digitale multi-page site.
-- Applied ONCE on first deploy by seed.dokploy.php (guarded by a marker
-- file in the persistent volume, so live admin edits are never clobbered
-- on later redeploys).
--
-- Keyed on SLUG / type rather than hardcoded post_id, so it is robust to
-- id drift between databases. English content (language_id resolved from
-- the 'en'/'en_US' language row). Idempotent: re-running is harmless.
-- =====================================================================

-- Resolve the primary (English) language_id once.
SET @lang := (SELECT language_id FROM language
              WHERE slug = 'en' OR code LIKE 'en%' ORDER BY language_id LIMIT 1);
SET @lang := IFNULL(@lang, 1);

-- ---------------------------------------------------------------------
-- 0) Ensure the French language exists and is active (drives the EN/FR
--    navbar switcher and /fr/ routing). Idempotent: only inserted if a
--    French row is not already present.
-- ---------------------------------------------------------------------
SET @fr_exists := (SELECT COUNT(*) FROM language WHERE slug = 'fr' OR code LIKE 'fr%');
INSERT INTO language (name, code, locale, slug, rtl, sort_order, status, `default`)
  SELECT 'Français', 'fr_FR', 'fr-fr', 'fr', 0, 2, 1, 0 WHERE @fr_exists = 0;
SET @lang_fr := (SELECT language_id FROM language
                 WHERE slug = 'fr' OR code LIKE 'fr%' ORDER BY language_id LIMIT 1);

-- ---------------------------------------------------------------------
-- 1) Repoint existing pages/posts to templates that exist in this theme.
--    Matched by slug (pages) and by type (blog posts).
-- ---------------------------------------------------------------------
UPDATE post p
  JOIN post_content c ON c.post_id = p.post_id
  SET p.template = 'content/contact.html'
  WHERE p.type = 'page' AND c.slug = 'contact';

UPDATE post p
  JOIN post_content c ON c.post_id = p.post_id
  SET p.template = 'content/page.html'
  WHERE p.type = 'page' AND c.slug IN ('about','services');

UPDATE post SET template = 'content/post.html'
  WHERE type = 'post' AND (template IS NULL OR template = '' OR template LIKE 'content/post-%');

-- ---------------------------------------------------------------------
-- 2) Rich styled content for Services (slug 'services').
-- ---------------------------------------------------------------------
UPDATE post_content SET content = CONCAT(
'<div class="sd-section-header"><span class="sd-eyebrow"><span class="sd-eyebrow-dot"></span>Our solutions</span>',
'<h2>A complete approach to <span class="sd-gradient-text">sovereignty</span></h2>',
'<p class="section-lead">From initial assessment to operational rollout, our experts cover the full lifecycle of your digital sovereignty strategy.</p></div>',
'<div class="row g-4">',
'<div class="col-md-6 col-lg-4"><div class="sd-card"><div class="sd-card-icon"><svg width="26" height="26" viewBox="0 0 24 24" fill="none"><path d="M18 10h-1.26A8 8 0 109 20h9a5 5 0 000-10z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg></div><h3>Sovereign Cloud</h3><p>Migration and hosting on certified European infrastructure, with guaranteed protection from extraterritorial law.</p><a href="/page/contact" class="sd-card-link">Learn more <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M5 12h14M13 5l7 7-7 7" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></a></div></div>',
'<div class="col-md-6 col-lg-4"><div class="sd-card"><div class="sd-card-icon sd-card-icon-accent"><svg width="26" height="26" viewBox="0 0 24 24" fill="none"><rect x="3" y="11" width="18" height="11" rx="2" stroke="currentColor" stroke-width="2"/><path d="M7 11V7a5 5 0 0110 0v4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></div><h3>Data Protection</h3><p>End-to-end encryption, sovereign key management (HSM, KMS) and GDPR-compliant retention policies.</p><a href="/page/contact" class="sd-card-link">Learn more <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M5 12h14M13 5l7 7-7 7" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></a></div></div>',
'<div class="col-md-6 col-lg-4"><div class="sd-card"><div class="sd-card-icon sd-card-icon-orange"><svg width="26" height="26" viewBox="0 0 24 24" fill="none"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M9 12l2 2 4-4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></div><h3>Compliance &amp; Audit</h3><p>SecNumCloud, ISO 27001, HDS and NIS2 audits. Full support across national and European frameworks.</p><a href="/page/contact" class="sd-card-link">Learn more <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M5 12h14M13 5l7 7-7 7" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></a></div></div>',
'<div class="col-md-6 col-lg-4"><div class="sd-card"><div class="sd-card-icon"><svg width="26" height="26" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/><path d="M2 12h20M12 2a14 14 0 010 20M12 2a14 14 0 000 20" stroke="currentColor" stroke-width="2"/></svg></div><h3>Cybersecurity &amp; SOC</h3><p>24/7 threat detection, managed sovereign SOC and penetration testing tailored to your critical workloads.</p><a href="/page/contact" class="sd-card-link">Learn more <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M5 12h14M13 5l7 7-7 7" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></a></div></div>',
'<div class="col-md-6 col-lg-4"><div class="sd-card"><div class="sd-card-icon sd-card-icon-accent"><svg width="26" height="26" viewBox="0 0 24 24" fill="none"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M14 2v6h6M9 13h6M9 17h6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></div><h3>Strategy &amp; Consulting</h3><p>Dependency mapping, risk analysis and a multi-year sovereignty roadmap tailored to your CIO.</p><a href="/page/contact" class="sd-card-link">Learn more <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M5 12h14M13 5l7 7-7 7" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></a></div></div>',
'<div class="col-md-6 col-lg-4"><div class="sd-card"><div class="sd-card-icon sd-card-icon-orange"><svg width="26" height="26" viewBox="0 0 24 24" fill="none"><path d="M22 10v6M2 10l10-5 10 5-10 5z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M6 12v5c3 3 9 3 12 0v-5" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg></div><h3>Team Training</h3><p>Awareness sessions, workshops and certifications to anchor sovereignty in your culture.</p><a href="/page/contact" class="sd-card-link">Learn more <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M5 12h14M13 5l7 7-7 7" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></a></div></div>',
'</div>'
), meta_description = 'Sovereign cloud, data protection, cybersecurity, compliance, consulting and training for European organizations.'
WHERE language_id = @lang AND post_id IN (SELECT post_id FROM (SELECT post_id FROM post_content WHERE slug='services') x);

-- ---------------------------------------------------------------------
-- 3) Rich styled content for Contact (slug 'contact') — intro + icon cards.
-- ---------------------------------------------------------------------
UPDATE post_content SET content = CONCAT(
'<p style="color:var(--sd-muted);font-size:1.08rem;line-height:1.7;max-width:640px;">Whether you are scoping a sovereign cloud migration, preparing an audit, or simply weighing your options, our experts are here to help. Reach out and we will get back to you within 24 business hours.</p>',
'<div class="row g-4 mt-1">',
'<div class="col-md-4"><div class="sd-card h-100"><div class="sd-card-icon"><svg width="26" height="26" viewBox="0 0 24 24" fill="none"><rect x="3" y="5" width="18" height="14" rx="2" stroke="currentColor" stroke-width="2"/><path d="M4 7l8 6 8-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></div><h3>Email</h3><p><a href="mailto:contact@sovereignty.example" class="sd-card-link">contact@sovereignty.example</a></p></div></div>',
'<div class="col-md-4"><div class="sd-card h-100"><div class="sd-card-icon sd-card-icon-accent"><svg width="26" height="26" viewBox="0 0 24 24" fill="none"><path d="M5 4h4l2 5-3 2a12 12 0 005 5l2-3 5 2v4a2 2 0 01-2 2A16 16 0 013 6a2 2 0 012-2z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg></div><h3>Phone</h3><p><a href="tel:+442012345678" class="sd-card-link">+44 20 1234 5678</a></p></div></div>',
'<div class="col-md-4"><div class="sd-card h-100"><div class="sd-card-icon sd-card-icon-orange"><svg width="26" height="26" viewBox="0 0 24 24" fill="none"><path d="M12 22s8-7 8-12a8 8 0 10-16 0c0 5 8 12 8 12z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><circle cx="12" cy="10" r="3" stroke="currentColor" stroke-width="2"/></svg></div><h3>Offices</h3><p>London &middot; Paris &middot; Brussels &middot; Luxembourg</p></div></div>',
'</div>'
), meta_description = 'Talk to our digital sovereignty experts. Free, confidential consultation with a reply within 24 business hours.'
WHERE language_id = @lang AND post_id IN (SELECT post_id FROM (SELECT post_id FROM post_content WHERE slug='contact') x);

-- ---------------------------------------------------------------------
-- 4) Rich styled content for About (slug 'about') — feature split + method + why-us + certs + stats.
-- ---------------------------------------------------------------------
UPDATE post_content SET content = CONCAT(
'<div class="row g-5 align-items-center sd-feature-split"><div class="col-lg-6"><div class="sd-feature-img-wrap">',
'<svg viewBox="0 0 480 360" xmlns="http://www.w3.org/2000/svg" style="width:100%;"><defs>',
'<linearGradient id="ab-grad" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#0B5FFF"/><stop offset="100%" stop-color="#7C3AED"/></linearGradient>',
'<linearGradient id="ab-grad2" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#00D9B2"/><stop offset="100%" stop-color="#0B5FFF"/></linearGradient></defs>',
'<ellipse cx="240" cy="180" rx="120" ry="80" fill="url(#ab-grad)" opacity="0.10"/>',
'<path d="M240 70 L180 96 v60 c0 56 40 92 60 104 c20-12 60-48 60-104 V96 z" fill="url(#ab-grad)" opacity="0.15"/>',
'<path d="M240 70 L180 96 v60 c0 56 40 92 60 104 c20-12 60-48 60-104 V96 z" stroke="url(#ab-grad)" stroke-width="2.5" fill="none"/>',
'<path d="M218 168 l16 16 30-34" stroke="url(#ab-grad)" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" fill="none"/>',
'<g fill="#0B5FFF" opacity="0.55"><circle cx="240" cy="50" r="3"/><circle cx="300" cy="66" r="3"/><circle cx="344" cy="110" r="3"/><circle cx="360" cy="170" r="3"/><circle cx="344" cy="230" r="3"/><circle cx="136" cy="110" r="3" fill="#7C3AED"/><circle cx="120" cy="170" r="3" fill="#7C3AED"/><circle cx="136" cy="230" r="3" fill="#7C3AED"/><circle cx="180" cy="66" r="3" fill="#7C3AED"/></g>',
'<g><rect x="40" y="250" width="96" height="56" rx="10" fill="#fff" stroke="#00D9B2" stroke-width="1.5"/><circle cx="64" cy="278" r="12" fill="url(#ab-grad2)" opacity="0.2"/><path d="M58 278 l4 4 8-8" stroke="#00A88A" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/><rect x="84" y="272" width="40" height="5" rx="2.5" fill="#0B5FFF" opacity="0.3"/><rect x="84" y="284" width="28" height="5" rx="2.5" fill="#0B5FFF" opacity="0.2"/></g>',
'<g><rect x="344" y="250" width="96" height="56" rx="10" fill="#fff" stroke="#7C3AED" stroke-width="1.5"/><rect x="360" y="266" width="64" height="6" rx="3" fill="#7C3AED" opacity="0.3"/><rect x="360" y="280" width="44" height="6" rx="3" fill="#7C3AED" opacity="0.2"/></g>',
'<circle cx="40" cy="60" r="4" fill="#7C3AED" opacity="0.6"/><circle cx="440" cy="60" r="4" fill="#0B5FFF" opacity="0.6"/></svg>',
'</div></div>',
'<div class="col-lg-6"><span class="sd-eyebrow"><span class="sd-eyebrow-dot"></span>Who we are</span>',
'<h2 style="font-size:clamp(1.6rem,3vw,2.4rem);font-weight:800;letter-spacing:-.02em;margin:1rem 0;">Helping Europe stay <span class="sd-gradient-text">in control</span></h2>',
'<p style="color:var(--sd-muted);font-size:1.08rem;line-height:1.7;">We help European organizations regain control of their digital assets &mdash; combining sovereign cloud, data protection and pragmatic strategy so you stay compliant, secure and independent, without sacrificing performance.</p>',
'<ul class="sd-feature-list"><li><span class="sd-feature-list-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg></span><div><strong>European by design</strong><span>Data hosted and processed in Europe, GDPR-compliant end to end.</span></div></li>',
'<li><span class="sd-feature-list-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg></span><div><strong>Reversible &amp; open</strong><span>Open standards and portable formats &mdash; you are never locked in.</span></div></li>',
'<li><span class="sd-feature-list-icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg></span><div><strong>Sector expertise</strong><span>Public sector, healthcare, finance, defense, energy and industry.</span></div></li></ul></div></div>',
'<div class="sd-section-header" style="margin-top:4rem;"><span class="sd-eyebrow"><span class="sd-eyebrow-dot"></span>Our method</span><h2>A clear <span class="sd-gradient-text">4-step</span> journey</h2><p class="section-lead">From the first analysis to production rollout, we follow a proven method used with 250+ European organizations.</p></div>',
'<div class="row g-4">',
'<div class="col-md-6 col-lg-3"><div class="sd-step"><div class="sd-step-number">01</div><div class="sd-step-icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"/><path d="M21 21l-4.35-4.35" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></div><h3>Audit &amp; Mapping</h3><p>We map your data, workloads and regulatory exposure to find what truly needs sovereignty.</p></div></div>',
'<div class="col-md-6 col-lg-3"><div class="sd-step"><div class="sd-step-number">02</div><div class="sd-step-icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M14 2v6h6" stroke="currentColor" stroke-width="2"/></svg></div><h3>Tailored Strategy</h3><p>A roadmap aligned with your industry constraints, budget and existing tooling.</p></div></div>',
'<div class="col-md-6 col-lg-3"><div class="sd-step"><div class="sd-step-number">03</div><div class="sd-step-icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M12 2v4M12 18v4M2 12h4M18 12h4M5 5l3 3M16 16l3 3M19 5l-3 3M8 16l-3 3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/></svg></div><h3>Implementation</h3><p>Migration and integration with reversibility guaranteed and no hidden lock-in.</p></div></div>',
'<div class="col-md-6 col-lg-3"><div class="sd-step"><div class="sd-step-number">04</div><div class="sd-step-icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M3 12a9 9 0 1015.5-6.3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M18 3v4h-4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></div><h3>Run &amp; Improve</h3><p>Ongoing monitoring, optimization and 24/7 support from our European teams.</p></div></div>',
'</div>',
'<div class="sd-section-header" style="margin-top:4rem;"><span class="sd-eyebrow"><span class="sd-eyebrow-dot"></span>Certifications</span><h2>Recognized, audited, <span class="sd-gradient-text">verifiable</span> standards</h2></div>',
'<div class="row g-3 g-md-4">',
'<div class="col-6 col-md-4 col-lg-2"><div class="sd-cert-card"><div class="sd-cert-badge"><svg width="34" height="34" viewBox="0 0 24 24" fill="none"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M9 12l2 2 4-4" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></div><h4>SecNumCloud</h4><p>ANSSI 3.2</p></div></div>',
'<div class="col-6 col-md-4 col-lg-2"><div class="sd-cert-card"><div class="sd-cert-badge"><svg width="34" height="34" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"/><text x="12" y="15" text-anchor="middle" font-size="6" font-weight="700" fill="currentColor">ISO</text></svg></div><h4>ISO 27001</h4><p>InfoSec mgmt</p></div></div>',
'<div class="col-6 col-md-4 col-lg-2"><div class="sd-cert-card"><div class="sd-cert-badge"><svg width="34" height="34" viewBox="0 0 24 24" fill="none"><path d="M12 3l2.5 5 5.5.8-4 3.9 1 5.5-5-2.6-5 2.6 1-5.5-4-3.9 5.5-.8z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg></div><h4>HDS</h4><p>Healthcare data</p></div></div>',
'<div class="col-6 col-md-4 col-lg-2"><div class="sd-cert-card"><div class="sd-cert-badge"><svg width="34" height="34" viewBox="0 0 24 24" fill="none"><rect x="4" y="9" width="16" height="12" rx="2" stroke="currentColor" stroke-width="2"/><path d="M8 9V6a4 4 0 018 0v3" stroke="currentColor" stroke-width="2"/></svg></div><h4>GDPR</h4><p>EU compliance</p></div></div>',
'<div class="col-6 col-md-4 col-lg-2"><div class="sd-cert-card"><div class="sd-cert-badge"><svg width="34" height="34" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"/><path d="M3 12h18M12 3a14 14 0 010 18M12 3a14 14 0 000 18" stroke="currentColor" stroke-width="1.5"/></svg></div><h4>NIS2</h4><p>EU directive</p></div></div>',
'<div class="col-6 col-md-4 col-lg-2"><div class="sd-cert-card"><div class="sd-cert-badge"><svg width="34" height="34" viewBox="0 0 24 24" fill="none"><path d="M5 7l7-4 7 4v6c0 5-3.5 7.5-7 9-3.5-1.5-7-4-7-9z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M12 8v4l2 2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></div><h4>SOC 2</h4><p>Type II</p></div></div>',
'</div>',
'<div class="row g-4" style="margin-top:3.5rem;">',
'<div class="col-6 col-md-3"><div class="sd-stat"><div class="sd-stat-number">250+</div><div class="sd-stat-label">Organizations served</div></div></div>',
'<div class="col-6 col-md-3"><div class="sd-stat"><div class="sd-stat-number">99.99%</div><div class="sd-stat-label">Average SLA uptime</div></div></div>',
'<div class="col-6 col-md-3"><div class="sd-stat"><div class="sd-stat-number">14</div><div class="sd-stat-label">European countries</div></div></div>',
'<div class="col-6 col-md-3"><div class="sd-stat"><div class="sd-stat-number">24/7</div><div class="sd-stat-label">Sovereign SOC</div></div></div>',
'</div>'
), meta_description = 'Consulting and solutions helping European organizations master their digital assets and achieve sovereignty.'
WHERE language_id = @lang AND post_id IN (SELECT post_id FROM (SELECT post_id FROM post_content WHERE slug='about') x);

-- ---------------------------------------------------------------------
-- 5) Create the combined Method & Certifications page (/page/method),
--    id-agnostic and idempotent (skips if slug 'method' already exists).
-- ---------------------------------------------------------------------
SET @exists := (SELECT COUNT(*) FROM post_content WHERE slug = 'method');
SET @pid := (SELECT IFNULL(MAX(post_id),0) + 1 FROM post);

INSERT INTO post (post_id, admin_id, status, image, comment_status, password, parent, sort_order, type, template, comment_count, views, created_at, updated_at)
SELECT @pid, 1, 'publish', '', 'open', '', 0, 0, 'page', 'content/page.html', 0, 0, NOW(), NOW()
WHERE @exists = 0;

INSERT INTO post_to_site (post_id, site_id)
SELECT @pid, 1 WHERE @exists = 0;

INSERT INTO post_content (post_id, language_id, name, slug, content, excerpt, meta_keywords, meta_description)
SELECT @pid, @lang, 'Method & Certifications', 'method',
CONCAT(
'<div class="sd-section-header"><span class="sd-eyebrow"><span class="sd-eyebrow-dot"></span>Our method</span><h2>A clear <span class="sd-gradient-text">4-step</span> journey</h2><p class="section-lead">From the first analysis to production rollout, we follow a proven method used with 250+ European organizations.</p></div>',
'<div class="row g-4">',
'<div class="col-md-6 col-lg-3"><div class="sd-step"><div class="sd-step-number">01</div><div class="sd-step-icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"/><path d="M21 21l-4.35-4.35" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></div><h3>Audit &amp; Mapping</h3><p>We map your data, workloads and regulatory exposure to find what truly needs sovereignty.</p></div></div>',
'<div class="col-md-6 col-lg-3"><div class="sd-step"><div class="sd-step-number">02</div><div class="sd-step-icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M14 2v6h6" stroke="currentColor" stroke-width="2"/></svg></div><h3>Tailored Strategy</h3><p>A roadmap aligned with your industry constraints, budget and existing tooling.</p></div></div>',
'<div class="col-md-6 col-lg-3"><div class="sd-step"><div class="sd-step-number">03</div><div class="sd-step-icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M12 2v4M12 18v4M2 12h4M18 12h4M5 5l3 3M16 16l3 3M19 5l-3 3M8 16l-3 3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/></svg></div><h3>Implementation</h3><p>Migration and integration with reversibility guaranteed and no hidden lock-in.</p></div></div>',
'<div class="col-md-6 col-lg-3"><div class="sd-step"><div class="sd-step-number">04</div><div class="sd-step-icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M3 12a9 9 0 1015.5-6.3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M18 3v4h-4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></div><h3>Run &amp; Improve</h3><p>Ongoing monitoring, optimization and 24/7 support from our European teams.</p></div></div>',
'</div>',
'<div class="sd-section-header" style="margin-top:4rem;"><span class="sd-eyebrow"><span class="sd-eyebrow-dot"></span>Certifications</span><h2>Recognized, audited, <span class="sd-gradient-text">verifiable</span> standards</h2><p class="section-lead">We operate to the most demanding frameworks. Every service we offer is certified by accredited independent auditors.</p></div>',
'<div class="row g-3 g-md-4">',
'<div class="col-6 col-md-4 col-lg-2"><div class="sd-cert-card"><div class="sd-cert-badge"><svg width="34" height="34" viewBox="0 0 24 24" fill="none"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M9 12l2 2 4-4" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></div><h4>SecNumCloud</h4><p>ANSSI 3.2</p></div></div>',
'<div class="col-6 col-md-4 col-lg-2"><div class="sd-cert-card"><div class="sd-cert-badge"><svg width="34" height="34" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"/><text x="12" y="15" text-anchor="middle" font-size="6" font-weight="700" fill="currentColor">ISO</text></svg></div><h4>ISO 27001</h4><p>InfoSec mgmt</p></div></div>',
'<div class="col-6 col-md-4 col-lg-2"><div class="sd-cert-card"><div class="sd-cert-badge"><svg width="34" height="34" viewBox="0 0 24 24" fill="none"><path d="M12 3l2.5 5 5.5.8-4 3.9 1 5.5-5-2.6-5 2.6 1-5.5-4-3.9 5.5-.8z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg></div><h4>HDS</h4><p>Healthcare data</p></div></div>',
'<div class="col-6 col-md-4 col-lg-2"><div class="sd-cert-card"><div class="sd-cert-badge"><svg width="34" height="34" viewBox="0 0 24 24" fill="none"><rect x="4" y="9" width="16" height="12" rx="2" stroke="currentColor" stroke-width="2"/><path d="M8 9V6a4 4 0 018 0v3" stroke="currentColor" stroke-width="2"/></svg></div><h4>GDPR</h4><p>EU compliance</p></div></div>',
'<div class="col-6 col-md-4 col-lg-2"><div class="sd-cert-card"><div class="sd-cert-badge"><svg width="34" height="34" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"/><path d="M3 12h18M12 3a14 14 0 010 18M12 3a14 14 0 000 18" stroke="currentColor" stroke-width="1.5"/></svg></div><h4>NIS2</h4><p>EU directive</p></div></div>',
'<div class="col-6 col-md-4 col-lg-2"><div class="sd-cert-card"><div class="sd-cert-badge"><svg width="34" height="34" viewBox="0 0 24 24" fill="none"><path d="M5 7l7-4 7 4v6c0 5-3.5 7.5-7 9-3.5-1.5-7-4-7-9z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M12 8v4l2 2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></div><h4>SOC 2</h4><p>Type II</p></div></div>',
'</div>',
'<div class="row g-4" style="margin-top:3.5rem;">',
'<div class="col-6 col-md-3"><div class="sd-stat"><div class="sd-stat-number">250+</div><div class="sd-stat-label">Organizations served</div></div></div>',
'<div class="col-6 col-md-3"><div class="sd-stat"><div class="sd-stat-number">99.99%</div><div class="sd-stat-label">Average SLA uptime</div></div></div>',
'<div class="col-6 col-md-3"><div class="sd-stat"><div class="sd-stat-number">14</div><div class="sd-stat-label">European countries</div></div></div>',
'<div class="col-6 col-md-3"><div class="sd-stat"><div class="sd-stat-number">24/7</div><div class="sd-stat-label">Sovereign SOC</div></div></div>',
'</div>'
),
'', '',
'Our proven 4-step method and the recognized certifications (SecNumCloud, ISO 27001, HDS, GDPR, NIS2, SOC 2) behind our sovereign solutions.'
WHERE @exists = 0;

-- =====================================================================
-- 6) Dedicated, SEO-friendly service pages — one per Solutions item.
--    Each gets its own descriptive slug (/page/sovereign-cloud, etc.),
--    expanded keyword-rich content, an H1 (post name), meta_description
--    and meta_keywords. All are real DB pages on the content/page.html
--    template, so they are fully editable in the admin live editor.
--
--    id-agnostic + idempotent: a page is created only when its slug does
--    not already exist, so re-running never duplicates or clobbers edits.
-- =====================================================================

-- 6.1) Sovereign Cloud --------------------------------------------------
SET @exists := (SELECT COUNT(*) FROM post_content WHERE slug = 'sovereign-cloud');
SET @pid := (SELECT IFNULL(MAX(post_id),0) + 1 FROM post);
INSERT INTO post (post_id, admin_id, status, image, comment_status, password, parent, sort_order, type, template, comment_count, views, created_at, updated_at)
SELECT @pid, 1, 'publish', '', 'open', '', 0, 0, 'page', 'content/page.html', 0, 0, NOW(), NOW() WHERE @exists = 0;
INSERT INTO post_to_site (post_id, site_id) SELECT @pid, 1 WHERE @exists = 0;
INSERT INTO post_content (post_id, language_id, name, slug, content, excerpt, meta_keywords, meta_description)
SELECT @pid, @lang, 'Sovereign Cloud', 'sovereign-cloud', CONCAT(
'<div class="sd-section-header"><span class="sd-eyebrow"><span class="sd-eyebrow-dot"></span>Sovereign Cloud</span><h2>European cloud hosting, <span class="sd-gradient-text">immune to extraterritorial law</span></h2><p class="section-lead">Migrate and run your workloads on certified European infrastructure (SecNumCloud, ISO 27001, HDS). Keep full control over where your data lives, who can access it, and how it is operated &mdash; with no exposure to the US CLOUD Act or other foreign jurisdictions.</p></div>',
'<div class="row g-4">',
'<div class="col-md-6 col-lg-4"><div class="sd-card"><h3>Certified EU infrastructure</h3><p>Data centres operated in the European Union under SecNumCloud 3.2 and ISO 27001, with contractual guarantees against extraterritorial access.</p></div></div>',
'<div class="col-md-6 col-lg-4"><div class="sd-card"><h3>Seamless migration</h3><p>Lift-and-shift or re-platform your VMs, containers and databases with zero-downtime cutover plans and full rollback safety.</p></div></div>',
'<div class="col-md-6 col-lg-4"><div class="sd-card"><h3>Guaranteed reversibility</h3><p>Open standards and portable formats mean you can leave at any time &mdash; no proprietary lock-in, no hidden egress traps.</p></div></div>',
'<div class="col-md-6 col-lg-4"><div class="sd-card"><h3>Elastic &amp; performant</h3><p>Auto-scaling compute, sovereign object storage and managed Kubernetes that match hyperscaler performance without the jurisdiction risk.</p></div></div>',
'<div class="col-md-6 col-lg-4"><div class="sd-card"><h3>99.99% SLA</h3><p>Multi-zone resilience, automated backups and 24/7 monitoring from European teams, backed by a contractual uptime commitment.</p></div></div>',
'<div class="col-md-6 col-lg-4"><div class="sd-card"><h3>Transparent pricing</h3><p>Predictable, euro-denominated billing with no surprise egress fees &mdash; budget your sovereign cloud with confidence.</p></div></div>',
'</div>',
'<div class="sd-section-header" style="margin-top:3rem;"><p class="section-lead">Public sector, healthcare, finance and defence organisations across 14 European countries trust our sovereign cloud to host their most sensitive workloads. Ready to assess your migration?</p><a href="/page/contact" class="sd-btn sd-btn-primary">Talk to a sovereign cloud expert <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M5 12h14M13 5l7 7-7 7" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></a></div>'
),
'Sovereign European cloud hosting with SecNumCloud and ISO 27001 certification, full reversibility and protection from extraterritorial law.',
'sovereign cloud, european cloud, secnumcloud, gdpr cloud hosting, data sovereignty, cloud souverain, iso 27001 cloud',
'Sovereign cloud hosting on certified European infrastructure (SecNumCloud, ISO 27001). Full control of your data, protection from extraterritorial law, and guaranteed reversibility.'
WHERE @exists = 0;

-- 6.2) Data Protection --------------------------------------------------
SET @exists := (SELECT COUNT(*) FROM post_content WHERE slug = 'data-protection');
SET @pid := (SELECT IFNULL(MAX(post_id),0) + 1 FROM post);
INSERT INTO post (post_id, admin_id, status, image, comment_status, password, parent, sort_order, type, template, comment_count, views, created_at, updated_at)
SELECT @pid, 1, 'publish', '', 'open', '', 0, 0, 'page', 'content/page.html', 0, 0, NOW(), NOW() WHERE @exists = 0;
INSERT INTO post_to_site (post_id, site_id) SELECT @pid, 1 WHERE @exists = 0;
INSERT INTO post_content (post_id, language_id, name, slug, content, excerpt, meta_keywords, meta_description)
SELECT @pid, @lang, 'Data Protection', 'data-protection', CONCAT(
'<div class="sd-section-header"><span class="sd-eyebrow"><span class="sd-eyebrow-dot"></span>Data Protection</span><h2>Encryption, key sovereignty and <span class="sd-gradient-text">GDPR by design</span></h2><p class="section-lead">Protect personal and strategic data end to end with sovereign encryption, hardware key management and retention policies built for GDPR. Your keys, your control &mdash; even your cloud provider cannot read your data.</p></div>',
'<div class="row g-4">',
'<div class="col-md-6 col-lg-4"><div class="sd-card"><h3>End-to-end encryption</h3><p>Data encrypted in transit and at rest with strong, audited ciphers &mdash; protecting you against breaches and unauthorised access.</p></div></div>',
'<div class="col-md-6 col-lg-4"><div class="sd-card"><h3>Sovereign key management</h3><p>Hold and rotate your own keys with HSM and KMS hosted in Europe. Bring-your-own-key and hold-your-own-key supported.</p></div></div>',
'<div class="col-md-6 col-lg-4"><div class="sd-card"><h3>GDPR-ready retention</h3><p>Automated retention, minimisation and right-to-erasure workflows that keep you compliant without manual effort.</p></div></div>',
'<div class="col-md-6 col-lg-4"><div class="sd-card"><h3>Data classification</h3><p>Discover, tag and govern sensitive data across your estate so the right controls follow the data automatically.</p></div></div>',
'<div class="col-md-6 col-lg-4"><div class="sd-card"><h3>Backup &amp; recovery</h3><p>Immutable, geo-redundant backups in Europe with tested restore procedures and ransomware-resistant snapshots.</p></div></div>',
'<div class="col-md-6 col-lg-4"><div class="sd-card"><h3>Access governance</h3><p>Least-privilege access, full audit trails and just-in-time elevation to prove who touched what, and when.</p></div></div>',
'</div>',
'<div class="sd-section-header" style="margin-top:3rem;"><p class="section-lead">Make GDPR a competitive advantage instead of a burden. Let us assess your data-protection posture and close the gaps.</p><a href="/page/contact" class="sd-btn sd-btn-primary">Request a data protection review <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M5 12h14M13 5l7 7-7 7" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></a></div>'
),
'Sovereign data protection: end-to-end encryption, European key management (HSM/KMS) and GDPR-compliant retention and access governance.',
'data protection, gdpr compliance, encryption, key management, hsm, kms, data sovereignty, rgpd',
'End-to-end encryption, sovereign key management (HSM/KMS) and GDPR-compliant retention. Protect personal and strategic data with keys you control, hosted in Europe.'
WHERE @exists = 0;

-- 6.3) Cybersecurity & SOC ---------------------------------------------
SET @exists := (SELECT COUNT(*) FROM post_content WHERE slug = 'cybersecurity-soc');
SET @pid := (SELECT IFNULL(MAX(post_id),0) + 1 FROM post);
INSERT INTO post (post_id, admin_id, status, image, comment_status, password, parent, sort_order, type, template, comment_count, views, created_at, updated_at)
SELECT @pid, 1, 'publish', '', 'open', '', 0, 0, 'page', 'content/page.html', 0, 0, NOW(), NOW() WHERE @exists = 0;
INSERT INTO post_to_site (post_id, site_id) SELECT @pid, 1 WHERE @exists = 0;
INSERT INTO post_content (post_id, language_id, name, slug, content, excerpt, meta_keywords, meta_description)
SELECT @pid, @lang, 'Cybersecurity & SOC', 'cybersecurity-soc', CONCAT(
'<div class="sd-section-header"><span class="sd-eyebrow"><span class="sd-eyebrow-dot"></span>Cybersecurity &amp; SOC</span><h2>24/7 threat detection from a <span class="sd-gradient-text">sovereign SOC</span></h2><p class="section-lead">A managed European Security Operations Centre that watches your critical workloads around the clock. Detect, investigate and respond to threats faster &mdash; with analysts, tooling and data all kept in Europe.</p></div>',
'<div class="row g-4">',
'<div class="col-md-6 col-lg-4"><div class="sd-card"><h3>Managed SOC 24/7</h3><p>European analysts monitor your environment day and night, triaging alerts and escalating real incidents within minutes.</p></div></div>',
'<div class="col-md-6 col-lg-4"><div class="sd-card"><h3>Threat detection &amp; response</h3><p>SIEM, EDR and threat intelligence combined into managed detection and response (MDR) tuned to your risk profile.</p></div></div>',
'<div class="col-md-6 col-lg-4"><div class="sd-card"><h3>Penetration testing</h3><p>Offensive security engagements and red-team exercises that find weaknesses before attackers do.</p></div></div>',
'<div class="col-md-6 col-lg-4"><div class="sd-card"><h3>Incident response</h3><p>A rehearsed playbook and on-call experts to contain, eradicate and recover from incidents with minimal impact.</p></div></div>',
'<div class="col-md-6 col-lg-4"><div class="sd-card"><h3>Vulnerability management</h3><p>Continuous scanning, prioritisation and remediation tracking across your applications and infrastructure.</p></div></div>',
'<div class="col-md-6 col-lg-4"><div class="sd-card"><h3>NIS2 readiness</h3><p>Controls, reporting and governance aligned to the NIS2 directive for essential and important entities.</p></div></div>',
'</div>',
'<div class="sd-section-header" style="margin-top:3rem;"><p class="section-lead">Attackers do not keep office hours &mdash; neither do we. See how a sovereign SOC strengthens your defence.</p><a href="/page/contact" class="sd-btn sd-btn-primary">Book a SOC consultation <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M5 12h14M13 5l7 7-7 7" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></a></div>'
),
'Managed sovereign SOC with 24/7 threat detection, MDR, penetration testing, incident response and NIS2-aligned cybersecurity.',
'cybersecurity, managed soc, mdr, threat detection, penetration testing, incident response, nis2, soc souverain',
'24/7 managed sovereign SOC: threat detection and response, penetration testing, incident response and NIS2 readiness for your critical European workloads.'
WHERE @exists = 0;

-- 6.4) Compliance & Audit ----------------------------------------------
SET @exists := (SELECT COUNT(*) FROM post_content WHERE slug = 'compliance-audit');
SET @pid := (SELECT IFNULL(MAX(post_id),0) + 1 FROM post);
INSERT INTO post (post_id, admin_id, status, image, comment_status, password, parent, sort_order, type, template, comment_count, views, created_at, updated_at)
SELECT @pid, 1, 'publish', '', 'open', '', 0, 0, 'page', 'content/page.html', 0, 0, NOW(), NOW() WHERE @exists = 0;
INSERT INTO post_to_site (post_id, site_id) SELECT @pid, 1 WHERE @exists = 0;
INSERT INTO post_content (post_id, language_id, name, slug, content, excerpt, meta_keywords, meta_description)
SELECT @pid, @lang, 'Compliance & Audit', 'compliance-audit', CONCAT(
'<div class="sd-section-header"><span class="sd-eyebrow"><span class="sd-eyebrow-dot"></span>Compliance &amp; Audit</span><h2>Pass every audit, across <span class="sd-gradient-text">every framework</span></h2><p class="section-lead">From SecNumCloud and ISO 27001 to HDS, GDPR and NIS2, we guide you through readiness, evidence collection and certification &mdash; turning compliance from a recurring scramble into a repeatable, audit-ready process.</p></div>',
'<div class="row g-4">',
'<div class="col-md-6 col-lg-4"><div class="sd-card"><h3>SecNumCloud</h3><p>ANSSI 3.2 readiness and qualification support for the highest French and European cloud-trust standard.</p></div></div>',
'<div class="col-md-6 col-lg-4"><div class="sd-card"><h3>ISO 27001</h3><p>Build and certify an information security management system that auditors and customers trust.</p></div></div>',
'<div class="col-md-6 col-lg-4"><div class="sd-card"><h3>HDS</h3><p>Health Data Hosting certification for organisations handling sensitive patient and medical data.</p></div></div>',
'<div class="col-md-6 col-lg-4"><div class="sd-card"><h3>GDPR audit</h3><p>Gap analysis, records of processing and DPIA support to demonstrate accountability under the GDPR.</p></div></div>',
'<div class="col-md-6 col-lg-4"><div class="sd-card"><h3>NIS2 directive</h3><p>Map obligations, close gaps and prepare the governance and reporting NIS2 requires.</p></div></div>',
'<div class="col-md-6 col-lg-4"><div class="sd-card"><h3>Continuous compliance</h3><p>Automated evidence collection and control monitoring so you stay audit-ready all year round.</p></div></div>',
'</div>',
'<div class="sd-section-header" style="margin-top:3rem;"><p class="section-lead">Stop dreading audit season. Let our accredited experts make compliance a continuous, low-effort discipline.</p><a href="/page/contact" class="sd-btn sd-btn-primary">Schedule a compliance assessment <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M5 12h14M13 5l7 7-7 7" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></a></div>'
),
'Compliance and audit support for SecNumCloud, ISO 27001, HDS, GDPR and NIS2 — readiness, evidence and continuous compliance.',
'compliance, audit, secnumcloud, iso 27001, hds, gdpr, nis2, conformite, certification',
'Audit-readiness and certification support across SecNumCloud, ISO 27001, HDS, GDPR and NIS2. Turn compliance into a repeatable, continuous process.'
WHERE @exists = 0;

-- 6.5) Strategy & Consulting -------------------------------------------
SET @exists := (SELECT COUNT(*) FROM post_content WHERE slug = 'strategy-consulting');
SET @pid := (SELECT IFNULL(MAX(post_id),0) + 1 FROM post);
INSERT INTO post (post_id, admin_id, status, image, comment_status, password, parent, sort_order, type, template, comment_count, views, created_at, updated_at)
SELECT @pid, 1, 'publish', '', 'open', '', 0, 0, 'page', 'content/page.html', 0, 0, NOW(), NOW() WHERE @exists = 0;
INSERT INTO post_to_site (post_id, site_id) SELECT @pid, 1 WHERE @exists = 0;
INSERT INTO post_content (post_id, language_id, name, slug, content, excerpt, meta_keywords, meta_description)
SELECT @pid, @lang, 'Strategy & Consulting', 'strategy-consulting', CONCAT(
'<div class="sd-section-header"><span class="sd-eyebrow"><span class="sd-eyebrow-dot"></span>Strategy &amp; Consulting</span><h2>A pragmatic <span class="sd-gradient-text">sovereignty roadmap</span> for your CIO</h2><p class="section-lead">We map your digital dependencies, quantify the risk of foreign lock-in, and build a multi-year roadmap that balances sovereignty, cost and performance &mdash; aligned to your industry, your budget and your existing tooling.</p></div>',
'<div class="row g-4">',
'<div class="col-md-6 col-lg-4"><div class="sd-card"><h3>Dependency mapping</h3><p>A clear picture of which vendors, data flows and workloads expose you to extraterritorial risk.</p></div></div>',
'<div class="col-md-6 col-lg-4"><div class="sd-card"><h3>Risk analysis</h3><p>Quantified assessment of lock-in, regulatory and continuity risk, prioritised by business impact.</p></div></div>',
'<div class="col-md-6 col-lg-4"><div class="sd-card"><h3>Sovereignty roadmap</h3><p>A realistic multi-year plan with milestones, budgets and quick wins your board can get behind.</p></div></div>',
'<div class="col-md-6 col-lg-4"><div class="sd-card"><h3>Vendor &amp; tool review</h3><p>Independent evaluation of sovereign alternatives that fit your stack, without ripping everything out.</p></div></div>',
'<div class="col-md-6 col-lg-4"><div class="sd-card"><h3>Business case &amp; TCO</h3><p>A defensible cost and value model so sovereignty investments are easy to justify and fund.</p></div></div>',
'<div class="col-md-6 col-lg-4"><div class="sd-card"><h3>Change &amp; governance</h3><p>Operating model, governance and KPIs to keep sovereignty on track long after the project ends.</p></div></div>',
'</div>',
'<div class="sd-section-header" style="margin-top:3rem;"><p class="section-lead">Sovereignty is a journey, not a switch. Start with a clear, costed roadmap built around your reality.</p><a href="/page/contact" class="sd-btn sd-btn-primary">Plan your sovereignty roadmap <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M5 12h14M13 5l7 7-7 7" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></a></div>'
),
'Digital sovereignty strategy and consulting: dependency mapping, risk analysis and a pragmatic multi-year roadmap for your CIO.',
'digital sovereignty strategy, it consulting, dependency mapping, risk analysis, cloud exit strategy, cio roadmap',
'Digital sovereignty consulting: dependency mapping, risk analysis and a pragmatic multi-year roadmap aligned to your industry, budget and existing tooling.'
WHERE @exists = 0;

-- 6.6) Training ---------------------------------------------------------
SET @exists := (SELECT COUNT(*) FROM post_content WHERE slug = 'training');
SET @pid := (SELECT IFNULL(MAX(post_id),0) + 1 FROM post);
INSERT INTO post (post_id, admin_id, status, image, comment_status, password, parent, sort_order, type, template, comment_count, views, created_at, updated_at)
SELECT @pid, 1, 'publish', '', 'open', '', 0, 0, 'page', 'content/page.html', 0, 0, NOW(), NOW() WHERE @exists = 0;
INSERT INTO post_to_site (post_id, site_id) SELECT @pid, 1 WHERE @exists = 0;
INSERT INTO post_content (post_id, language_id, name, slug, content, excerpt, meta_keywords, meta_description)
SELECT @pid, @lang, 'Training', 'training', CONCAT(
'<div class="sd-section-header"><span class="sd-eyebrow"><span class="sd-eyebrow-dot"></span>Training</span><h2>Anchor sovereignty in your <span class="sd-gradient-text">culture and skills</span></h2><p class="section-lead">Technology alone does not make an organisation sovereign &mdash; people do. Our awareness sessions, hands-on workshops and certification paths give your teams the skills and reflexes to protect data and stay independent.</p></div>',
'<div class="row g-4">',
'<div class="col-md-6 col-lg-4"><div class="sd-card"><h3>Security awareness</h3><p>Engaging sessions that turn every employee into a first line of defence against phishing and data leaks.</p></div></div>',
'<div class="col-md-6 col-lg-4"><div class="sd-card"><h3>Hands-on workshops</h3><p>Practical, role-based workshops for IT, DevOps and data teams on sovereign tools and secure practices.</p></div></div>',
'<div class="col-md-6 col-lg-4"><div class="sd-card"><h3>Certification paths</h3><p>Structured tracks toward recognised security and cloud certifications for your technical staff.</p></div></div>',
'<div class="col-md-6 col-lg-4"><div class="sd-card"><h3>Executive briefings</h3><p>Board-level sessions on sovereignty risk, regulation and strategy in clear, non-technical language.</p></div></div>',
'<div class="col-md-6 col-lg-4"><div class="sd-card"><h3>Tailored curricula</h3><p>Programmes built around your stack, your maturity and your industry &mdash; delivered on-site or remotely.</p></div></div>',
'<div class="col-md-6 col-lg-4"><div class="sd-card"><h3>Ongoing enablement</h3><p>Refreshers, simulations and measurable progress so new habits stick long after the training day.</p></div></div>',
'</div>',
'<div class="sd-section-header" style="margin-top:3rem;"><p class="section-lead">Build a sovereignty-aware culture that lasts. Let us design a training programme for your teams.</p><a href="/page/contact" class="sd-btn sd-btn-primary">Design a training programme <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M5 12h14M13 5l7 7-7 7" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></a></div>'
),
'Digital sovereignty and cybersecurity training: awareness sessions, hands-on workshops, certification paths and executive briefings.',
'cybersecurity training, security awareness, sovereignty training, certifications, workshops, formation cybersecurite',
'Sovereignty and cybersecurity training: awareness sessions, hands-on workshops, certification paths and executive briefings tailored to your teams.'
WHERE @exists = 0;
