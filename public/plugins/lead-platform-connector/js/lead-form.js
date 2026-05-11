/*
 * Lead Platform Connector — published-page runtime
 *
 * Finds every <form data-v-endpoint="..."> on the page, fetches a fresh CSRF
 * token + submit URL from the plugin's token endpoint, and intercepts submit
 * to POST the lead JSON to the plugin's submit controller.
 *
 * Doesn't depend on the template engine writing any data attributes onto the
 * form — the form just needs `data-v-endpoint=<slug>`, which the editor sets.
 */
(function () {
	'use strict';

	const TOKEN_URL  = '/index.php?module=plugins/lead-platform-connector/submit&action=token';
	const HONEYPOT   = 'company_website';
	const MIN_TIMEMS = 1500;

	function readUtmFromUrl() {
		const params = new URLSearchParams(window.location.search || '');
		const utm = {};
		['utm_source','utm_medium','utm_campaign','utm_term','utm_content','gclid','fbclid','msclkid'].forEach(function (k) {
			const v = params.get(k);
			if (v) utm[k] = v;
		});
		return utm;
	}

	function ensureAlertBox(form, kind) {
		const attr = 'data-v-leadform-' + kind;
		const existing = form.parentNode ? form.parentNode.querySelector('[' + attr + ']') : null;
		if (existing) return existing;
		const box = document.createElement('div');
		box.setAttribute(attr, '');
		box.setAttribute('role', 'alert');
		box.className = 'alert ' + (kind === 'success' ? 'alert-success' : 'alert-danger') + ' d-none';
		if (form.parentNode) form.parentNode.insertBefore(box, form);
		return box;
	}

	function showAlert(form, type, message) {
		const box = ensureAlertBox(form, type);
		if (message) box.textContent = message;
		box.classList.remove('d-none');
		const otherKind = type === 'success' ? 'error' : 'success';
		const other = form.parentNode ? form.parentNode.querySelector('[data-v-leadform-' + otherKind + ']') : null;
		if (other) other.classList.add('d-none');
	}

	function serialize(form, honeypotName) {
		const fd = new FormData(form);
		const out = {};
		fd.forEach(function (value, key) {
			if (key === honeypotName) return;
			out[key] = value;
		});
		return out;
	}

	function attach(form, cfg) {
		if (!form || form.__lpcBound) return;
		form.__lpcBound = true;

		// The form may carry novalidate from the editor; on the live page we
		// want native HTML5 validation on `required` inputs.
		form.removeAttribute('novalidate');

		form.addEventListener('submit', function (ev) {
			ev.preventDefault();

			if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
				if (typeof form.reportValidity === 'function') form.reportValidity();
				return;
			}

			const hp = form.querySelector('[name="' + HONEYPOT + '"]');
			if (hp && hp.value) {
				showAlert(form, 'success', 'Thanks — your request was received.');
				return;
			}

			const elapsed = Date.now() - cfg.renderTs;
			if (cfg.renderTs && elapsed < MIN_TIMEMS) {
				showAlert(form, 'error', 'One moment — the form just loaded. Please try again.');
				return;
			}

			const btn = form.querySelector('button[type=submit], input[type=submit]');
			if (btn) {
				btn.disabled = true;
				btn.dataset.origLabel = btn.textContent || btn.value || 'Submit';
				if ('textContent' in btn) btn.textContent = 'Sending…';
			}

			const fields = serialize(form, HONEYPOT);
			const payload = {
				endpoint:    cfg.endpoint,
				csrf:        cfg.csrf,
				fields:      fields,
				utm:         readUtmFromUrl(),
				source_page: window.location.pathname + window.location.search,
				referrer:    document.referrer || '',
			};

			fetch(cfg.submitUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
				body: JSON.stringify(payload),
			})
			.then(function (r) { return r.json().then(function (j) { return { http: r.status, body: j }; }); })
			.then(function (res) {
				if (btn) {
					btn.disabled = false;
					if ('textContent' in btn) btn.textContent = btn.dataset.origLabel || 'Submit';
				}

				if (res.body && res.body.ok) {
					showAlert(form, 'success', 'Thanks — your request was received.');
					form.reset();
					// Refresh token after a successful submit (single-use semantics in practice).
					fetchToken(cfg.endpoint).then(function (next) { if (next) cfg.csrf = next.csrf; cfg.renderTs = next ? next.renderTs : cfg.renderTs; });
				} else {
					const msg = (res.body && res.body.message) ? res.body.message : 'Sorry, something went wrong. Please try again.';
					showAlert(form, 'error', msg);
					if (res.http === 419) {
						// Token expired — refresh it.
						fetchToken(cfg.endpoint).then(function (next) { if (next) { cfg.csrf = next.csrf; cfg.renderTs = next.renderTs; } });
					}
				}
			})
			.catch(function () {
				if (btn) {
					btn.disabled = false;
					if ('textContent' in btn) btn.textContent = btn.dataset.origLabel || 'Submit';
				}
				showAlert(form, 'error', 'Network error. Please try again.');
			});
		});
	}

	function fetchToken(slug) {
		return fetch(TOKEN_URL + '&slug=' + encodeURIComponent(slug), {
			credentials: 'same-origin',
			headers: { 'Accept': 'application/json' },
		})
		.then(function (r) { return r.json(); })
		.then(function (j) {
			if (!j || !j.ok || !j.csrf || !j.submit_url) return null;
			return { csrf: j.csrf, submitUrl: j.submit_url, renderTs: j.render_ts || Date.now() };
		})
		.catch(function () { return null; });
	}

	function init() {
		const forms = document.querySelectorAll('form[data-v-endpoint]');
		forms.forEach(function (form) {
			const slug = form.getAttribute('data-v-endpoint');
			if (!slug) return;
			fetchToken(slug).then(function (tok) {
				if (!tok) {
					console.warn('[LPC] could not acquire token for slug', slug);
					return;
				}
				attach(form, {
					endpoint:  slug,
					csrf:      tok.csrf,
					submitUrl: tok.submitUrl,
					renderTs:  tok.renderTs,
				});
			});
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
