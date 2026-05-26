-- Rich UI redesign of Contact (7), About (11), Services (12) page bodies.
-- Uses the homepage's component vocabulary (sd-card/sd-card-icon/sd-card-link,
-- sd-step, sd-cert-card, sd-stat, sd-feature-split) plus new bespoke inline SVG
-- icons & illustrations. English (language_id = 1). No external image files.
-- Target DB: supplierdemolead-vvvebapp-db-1 (the app's MySQL).

-- =========================================================================
-- SERVICES (post_id 12): six icon cards in a styled section.
-- =========================================================================
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
'<div class="col-md-6 col-lg-4"><div class="sd-card"><div class="sd-card-icon sd-card-icon-orange"><svg width="26" height="26" viewBox="0 0 24 24" fill="none"><path d="M22 10v6M2 10l10-5 10 5-10 5z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M6 12v5c3 3 9 3 12 0v-5" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg></div><h3>Team Training</h3><p>Awareness sessions, workshops and certifications to anchor sovereignty in your organization''s culture.</p><a href="/page/contact" class="sd-card-link">Learn more <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M5 12h14M13 5l7 7-7 7" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></a></div></div>',
'</div>'
), meta_description = 'Sovereign cloud, data protection, cybersecurity, compliance, consulting and training for European organizations.'
WHERE post_id = 12 AND language_id = 1;

-- =========================================================================
-- ABOUT (post_id 11): feature-split with bespoke SVG, step method, why-us
-- icon cards, certification badges, and a stats row.
-- =========================================================================
UPDATE post_content SET content = CONCAT(
-- Intro / mission with a NEW bespoke SVG illustration (shield + EU stars + nodes)
'<div class="row g-5 align-items-center sd-feature-split"><div class="col-lg-6"><div class="sd-feature-img-wrap">',
'<svg viewBox="0 0 480 360" xmlns="http://www.w3.org/2000/svg" style="width:100%;"><defs>',
'<linearGradient id="ab-grad" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#0B5FFF"/><stop offset="100%" stop-color="#7C3AED"/></linearGradient>',
'<linearGradient id="ab-grad2" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#00D9B2"/><stop offset="100%" stop-color="#0B5FFF"/></linearGradient></defs>',
'<ellipse cx="240" cy="180" rx="120" ry="80" fill="url(#ab-grad)" opacity="0.10"/>',
-- central shield
'<path d="M240 70 L180 96 v60 c0 56 40 92 60 104 c20-12 60-48 60-104 V96 z" fill="url(#ab-grad)" opacity="0.15"/>',
'<path d="M240 70 L180 96 v60 c0 56 40 92 60 104 c20-12 60-48 60-104 V96 z" stroke="url(#ab-grad)" stroke-width="2.5" fill="none"/>',
'<path d="M218 168 l16 16 30-34" stroke="url(#ab-grad)" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" fill="none"/>',
-- EU-style ring of stars
'<g fill="#0B5FFF" opacity="0.55"><circle cx="240" cy="50" r="3"/><circle cx="300" cy="66" r="3"/><circle cx="344" cy="110" r="3"/><circle cx="360" cy="170" r="3"/><circle cx="344" cy="230" r="3"/><circle cx="136" cy="110" r="3" fill="#7C3AED"/><circle cx="120" cy="170" r="3" fill="#7C3AED"/><circle cx="136" cy="230" r="3" fill="#7C3AED"/><circle cx="180" cy="66" r="3" fill="#7C3AED"/></g>',
-- floating node cards
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
-- Method (4 steps with icons)
'<div class="sd-section-header" style="margin-top:4rem;"><span class="sd-eyebrow"><span class="sd-eyebrow-dot"></span>Our method</span><h2>A clear <span class="sd-gradient-text">4-step</span> journey</h2><p class="section-lead">From the first analysis to production rollout, we follow a proven method used with 250+ European organizations.</p></div>',
'<div class="row g-4">',
'<div class="col-md-6 col-lg-3"><div class="sd-step"><div class="sd-step-number">01</div><div class="sd-step-icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"/><path d="M21 21l-4.35-4.35" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></div><h3>Audit &amp; Mapping</h3><p>We map your data, workloads and regulatory exposure to find what truly needs sovereignty.</p></div></div>',
'<div class="col-md-6 col-lg-3"><div class="sd-step"><div class="sd-step-number">02</div><div class="sd-step-icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M14 2v6h6" stroke="currentColor" stroke-width="2"/></svg></div><h3>Tailored Strategy</h3><p>A roadmap aligned with your industry constraints, budget and existing tooling.</p></div></div>',
'<div class="col-md-6 col-lg-3"><div class="sd-step"><div class="sd-step-number">03</div><div class="sd-step-icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M12 2v4M12 18v4M2 12h4M18 12h4M5 5l3 3M16 16l3 3M19 5l-3 3M8 16l-3 3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/></svg></div><h3>Implementation</h3><p>Migration and integration with reversibility guaranteed and no hidden lock-in.</p></div></div>',
'<div class="col-md-6 col-lg-3"><div class="sd-step"><div class="sd-step-number">04</div><div class="sd-step-icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M3 12a9 9 0 1015.5-6.3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M18 3v4h-4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></div><h3>Run &amp; Improve</h3><p>Ongoing monitoring, optimization and 24/7 support from our European teams.</p></div></div>',
'</div>',
-- Certifications (badges)
'<div class="sd-section-header" style="margin-top:4rem;"><span class="sd-eyebrow"><span class="sd-eyebrow-dot"></span>Certifications</span><h2>Recognized, audited, <span class="sd-gradient-text">verifiable</span> standards</h2></div>',
'<div class="row g-3 g-md-4">',
'<div class="col-6 col-md-4 col-lg-2"><div class="sd-cert-card"><div class="sd-cert-badge"><svg width="34" height="34" viewBox="0 0 24 24" fill="none"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M9 12l2 2 4-4" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg></div><h4>SecNumCloud</h4><p>ANSSI 3.2</p></div></div>',
'<div class="col-6 col-md-4 col-lg-2"><div class="sd-cert-card"><div class="sd-cert-badge"><svg width="34" height="34" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"/><text x="12" y="15" text-anchor="middle" font-size="6" font-weight="700" fill="currentColor">ISO</text></svg></div><h4>ISO 27001</h4><p>InfoSec mgmt</p></div></div>',
'<div class="col-6 col-md-4 col-lg-2"><div class="sd-cert-card"><div class="sd-cert-badge"><svg width="34" height="34" viewBox="0 0 24 24" fill="none"><path d="M12 3l2.5 5 5.5.8-4 3.9 1 5.5-5-2.6-5 2.6 1-5.5-4-3.9 5.5-.8z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg></div><h4>HDS</h4><p>Healthcare data</p></div></div>',
'<div class="col-6 col-md-4 col-lg-2"><div class="sd-cert-card"><div class="sd-cert-badge"><svg width="34" height="34" viewBox="0 0 24 24" fill="none"><rect x="4" y="9" width="16" height="12" rx="2" stroke="currentColor" stroke-width="2"/><path d="M8 9V6a4 4 0 018 0v3" stroke="currentColor" stroke-width="2"/></svg></div><h4>GDPR</h4><p>EU compliance</p></div></div>',
'<div class="col-6 col-md-4 col-lg-2"><div class="sd-cert-card"><div class="sd-cert-badge"><svg width="34" height="34" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2"/><path d="M3 12h18M12 3a14 14 0 010 18M12 3a14 14 0 000 18" stroke="currentColor" stroke-width="1.5"/></svg></div><h4>NIS2</h4><p>EU directive</p></div></div>',
'<div class="col-6 col-md-4 col-lg-2"><div class="sd-cert-card"><div class="sd-cert-badge"><svg width="34" height="34" viewBox="0 0 24 24" fill="none"><path d="M5 7l7-4 7 4v6c0 5-3.5 7.5-7 9-3.5-1.5-7-4-7-9z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M12 8v4l2 2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></div><h4>SOC 2</h4><p>Type II</p></div></div>',
'</div>',
-- Stats row
'<div class="row g-4" style="margin-top:3.5rem;">',
'<div class="col-6 col-md-3"><div class="sd-stat"><div class="sd-stat-number">250+</div><div class="sd-stat-label">Organizations served</div></div></div>',
'<div class="col-6 col-md-3"><div class="sd-stat"><div class="sd-stat-number">99.99%</div><div class="sd-stat-label">Average SLA uptime</div></div></div>',
'<div class="col-6 col-md-3"><div class="sd-stat"><div class="sd-stat-number">14</div><div class="sd-stat-label">European countries</div></div></div>',
'<div class="col-6 col-md-3"><div class="sd-stat"><div class="sd-stat-number">24/7</div><div class="sd-stat-label">Sovereign SOC</div></div></div>',
'</div>'
), meta_description = 'Consulting and solutions helping European organizations master their digital assets and achieve sovereignty.'
WHERE post_id = 11 AND language_id = 1;

-- =========================================================================
-- CONTACT (post_id 7): intro + three icon cards (mail / phone / location SVGs).
-- =========================================================================
UPDATE post_content SET content = CONCAT(
'<p style="color:var(--sd-muted);font-size:1.08rem;line-height:1.7;max-width:640px;">Whether you are scoping a sovereign cloud migration, preparing an audit, or simply weighing your options, our experts are here to help. Reach out and we will get back to you within 24 business hours.</p>',
'<div class="row g-4 mt-1">',
'<div class="col-md-4"><div class="sd-card h-100"><div class="sd-card-icon"><svg width="26" height="26" viewBox="0 0 24 24" fill="none"><rect x="3" y="5" width="18" height="14" rx="2" stroke="currentColor" stroke-width="2"/><path d="M4 7l8 6 8-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></div><h3>Email</h3><p><a href="mailto:contact@sovereignty.example" class="sd-card-link">contact@sovereignty.example</a></p></div></div>',
'<div class="col-md-4"><div class="sd-card h-100"><div class="sd-card-icon sd-card-icon-accent"><svg width="26" height="26" viewBox="0 0 24 24" fill="none"><path d="M5 4h4l2 5-3 2a12 12 0 005 5l2-3 5 2v4a2 2 0 01-2 2A16 16 0 013 6a2 2 0 012-2z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/></svg></div><h3>Phone</h3><p><a href="tel:+442012345678" class="sd-card-link">+44 20 1234 5678</a></p></div></div>',
'<div class="col-md-4"><div class="sd-card h-100"><div class="sd-card-icon sd-card-icon-orange"><svg width="26" height="26" viewBox="0 0 24 24" fill="none"><path d="M12 22s8-7 8-12a8 8 0 10-16 0c0 5 8 12 8 12z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><circle cx="12" cy="10" r="3" stroke="currentColor" stroke-width="2"/></svg></div><h3>Offices</h3><p>London &middot; Paris &middot; Brussels &middot; Luxembourg</p></div></div>',
'</div>'
), meta_description = 'Talk to our digital sovereignty experts. Free, confidential consultation with a reply within 24 business hours.'
WHERE post_id = 7 AND language_id = 1;
