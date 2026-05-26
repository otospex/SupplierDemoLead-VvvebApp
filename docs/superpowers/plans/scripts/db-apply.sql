-- Souverainete-digitale multi-page: repoint existing pages/posts to templates
-- that exist in the active theme, and seed Services body + SEO meta.
-- Reversible: see db-backup-*.sql.post / .post_content from db-snapshot.sh.

-- Repoint existing pages to templates that exist in the active theme.
UPDATE post SET template = 'content/contact.html' WHERE post_id = 7;  -- Contact
UPDATE post SET template = 'content/page.html'    WHERE post_id = 11; -- About
UPDATE post SET template = 'content/page.html'    WHERE post_id = 12; -- Services

-- Blog posts use the styled single-post template.
UPDATE post SET template = 'content/post.html' WHERE post_id IN (1,2,3,4,5,6);

-- Seed Services body (currently 8 chars) with a styled solutions grid (English, language_id 1).
UPDATE post_content SET
  content = '<div class="row g-4"><div class="col-md-6 col-lg-4"><div class="sd-card h-100"><h3>&#9729;&#65039; Sovereign Cloud</h3><p>European-hosted, fully reversible cloud infrastructure with no egress fees.</p></div></div><div class="col-md-6 col-lg-4"><div class="sd-card h-100"><h3>&#128274; Data Protection</h3><p>Client-side encryption and GDPR-by-design data governance.</p></div></div><div class="col-md-6 col-lg-4"><div class="sd-card h-100"><h3>&#128737;&#65039; Cybersecurity &amp; SOC</h3><p>24/7 monitoring, incident response and managed detection.</p></div></div><div class="col-md-6 col-lg-4"><div class="sd-card h-100"><h3>&#128203; Compliance &amp; Audit</h3><p>HDS, ACPR, ANSSI and sector-specific audit readiness.</p></div></div><div class="col-md-6 col-lg-4"><div class="sd-card h-100"><h3>&#127919; Strategy &amp; Consulting</h3><p>Roadmaps to digital sovereignty tailored to your CIO.</p></div></div><div class="col-md-6 col-lg-4"><div class="sd-card h-100"><h3>&#127891; Training</h3><p>Upskill your teams on sovereign tooling and secure practices.</p></div></div></div>',
  meta_description = 'Sovereign cloud, data protection, cybersecurity, compliance, consulting and training for European organizations.'
WHERE post_id = 12 AND language_id = 1;

-- Add SEO meta descriptions for Contact and About (their content already exists).
UPDATE post_content SET
  meta_description = 'Talk to our digital sovereignty experts. Free, confidential consultation with a reply within 24 business hours.'
WHERE post_id = 7 AND language_id = 1;

UPDATE post_content SET
  meta_description = 'Consulting and solutions helping European organizations master their digital assets and achieve sovereignty.'
WHERE post_id = 11 AND language_id = 1;
