-- Portable "Method & Certifications" page creator (/page/method).
-- Safe for ANY database (local or live): auto-picks the next post_id and
-- skips entirely if a page with slug 'method' already exists (idempotent).
-- Run with:  docker exec -i <db-container> mysql -u<user> -p'<pass>' <db> < this.sql
-- Content is IDENTICAL to db-create-method-page.sql (rich sd-step + sd-cert-card + stats).

-- Abort cleanly if the method page already exists (avoids duplicates on re-run).
SET @exists := (SELECT COUNT(*) FROM post_content WHERE slug = 'method');

-- Next free post_id.
SET @pid := (SELECT IFNULL(MAX(post_id),0) + 1 FROM post);

-- Insert the post row (only if not already present).
INSERT INTO post (post_id, admin_id, status, image, comment_status, password, parent, sort_order, type, template, comment_count, views, created_at, updated_at)
SELECT @pid, 1, 'publish', '', 'open', '', 0, 0, 'page', 'content/page.html', 0, 0, NOW(), NOW()
WHERE @exists = 0;

-- Link to site 1 (only if we inserted).
INSERT INTO post_to_site (post_id, site_id)
SELECT @pid, 1 WHERE @exists = 0;

-- Insert the content. The CONCAT(...) body below is copied verbatim from
-- db-create-method-page.sql.
INSERT INTO post_content (post_id, language_id, name, slug, content, excerpt, meta_keywords, meta_description)
SELECT @pid, 1, 'Method & Certifications', 'method',
  CONCAT(
    -- Method (4 steps with icons)
    '<div class="sd-section-header"><span class="sd-eyebrow"><span class="sd-eyebrow-dot"></span>Our method</span><h2>A clear <span class="sd-gradient-text">4-step</span> journey</h2><p class="section-lead">From the first analysis to production rollout, we follow a proven method used with 250+ European organizations.</p></div>',
    '<div class="row g-4">',
    '<div class="col-md-6 col-lg-3"><div class="sd-step"><div class="sd-step-number">01</div><div class="sd-step-icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2"/><path d="M21 21l-4.35-4.35" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></div><h3>Audit &amp; Mapping</h3><p>We map your data, workloads and regulatory exposure to find what truly needs sovereignty.</p></div></div>',
    '<div class="col-md-6 col-lg-3"><div class="sd-step"><div class="sd-step-number">02</div><div class="sd-step-icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M14 2v6h6" stroke="currentColor" stroke-width="2"/></svg></div><h3>Tailored Strategy</h3><p>A roadmap aligned with your industry constraints, budget and existing tooling.</p></div></div>',
    '<div class="col-md-6 col-lg-3"><div class="sd-step"><div class="sd-step-number">03</div><div class="sd-step-icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M12 2v4M12 18v4M2 12h4M18 12h4M5 5l3 3M16 16l3 3M19 5l-3 3M8 16l-3 3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2"/></svg></div><h3>Implementation</h3><p>Migration and integration with reversibility guaranteed and no hidden lock-in.</p></div></div>',
    '<div class="col-md-6 col-lg-3"><div class="sd-step"><div class="sd-step-number">04</div><div class="sd-step-icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none"><path d="M3 12a9 9 0 1015.5-6.3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M18 3v4h-4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></div><h3>Run &amp; Improve</h3><p>Ongoing monitoring, optimization and 24/7 support from our European teams.</p></div></div>',
    '</div>',
    -- Certifications (badges)
    '<div class="sd-section-header" style="margin-top:4rem;"><span class="sd-eyebrow"><span class="sd-eyebrow-dot"></span>Certifications</span><h2>Recognized, audited, <span class="sd-gradient-text">verifiable</span> standards</h2><p class="section-lead">We operate to the most demanding frameworks. Every service we offer is certified by accredited independent auditors.</p></div>',
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
  ),
  '',
  '',
  'Our proven 4-step method and the recognized certifications (SecNumCloud, ISO 27001, HDS, GDPR, NIS2, SOC 2) behind our sovereign solutions.'
WHERE @exists = 0;

SELECT IF(@exists = 0, CONCAT('Created /page/method as post_id ', @pid), 'Skipped: slug "method" already exists') AS result;
