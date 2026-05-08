(function () {
	'use strict';

	function readUtmFromUrl() {
		var params = new URLSearchParams(window.location.search || '');
		var utm = {};
		var keys = ['utm_source','utm_medium','utm_campaign','utm_term','utm_content','gclid','fbclid','msclkid'];
		keys.forEach(function (k) {
			var v = params.get(k);
			if (v) utm[k] = v;
		});
		return utm;
	}

	function showAlert(wrap, type, message) {
		var el = wrap.querySelector('[data-v-leadform-' + type + ']');
		if (!el) return;
		if (message) el.textContent = message;
		el.classList.remove('d-none');
		var other = wrap.querySelector('[data-v-leadform-' + (type === 'success' ? 'error' : 'success') + ']');
		if (other) other.classList.add('d-none');
	}

	function serialize(form, honeypotName) {
		var fd = new FormData(form);
		var out = {};
		fd.forEach(function (value, key) {
			if (key === honeypotName) return;
			out[key] = value;
		});
		return out;
	}

	function attach(wrap) {
		var form = wrap.querySelector('form[data-v-leadform-config]');
		if (!form || form.__lpcBound) return;
		form.__lpcBound = true;

		// Let the browser run HTML5 validation on the `required` inputs the
		// editor generates. The original template carried `novalidate` to keep
		// the editor experience clean; on the live page we want native checks.
		form.removeAttribute('novalidate');

		var cfg = {
			endpoint:    form.getAttribute('data-endpoint') || '',
			csrf:        form.getAttribute('data-csrf') || '',
			submitUrl:   form.getAttribute('data-submit-url') || '',
			honeypot:    form.getAttribute('data-honeypot') || 'company_website',
			successUrl:  form.getAttribute('data-success-url') || '',
			successMsg:  form.getAttribute('data-success-msg') || '',
			errorMsg:    form.getAttribute('data-error-msg') || '',
			renderTs:    parseInt(form.getAttribute('data-render-ts') || '0', 10),
			minTimeMs:   parseInt(wrap.getAttribute('data-v-min_time_ms') || '1500', 10),
		};

		form.addEventListener('submit', function (ev) {
			ev.preventDefault();

			// HTML5 validation: surface the native tooltip on empty required
			// fields and stop here. We only do this client-side; the platform
			// is still authoritative.
			if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
				if (typeof form.reportValidity === 'function') form.reportValidity();
				return;
			}

			if (!cfg.endpoint || !cfg.csrf || !cfg.submitUrl) {
				showAlert(wrap, 'error', 'Form is not configured.');
				return;
			}

			// Honeypot
			var hp = form.querySelector('[name="' + cfg.honeypot + '"]');
			if (hp && hp.value) {
				showAlert(wrap, 'success', cfg.successMsg);
				return;
			}

			// Time gate (defeat bot autofill). Only triggers right after a
			// fresh page load — humans normally take more than a second.
			var elapsed = Date.now() - cfg.renderTs;
			if (cfg.renderTs && elapsed < cfg.minTimeMs) {
				showAlert(wrap, 'error', 'One moment — the form just loaded. Please try again.');
				return;
			}

			var btn = form.querySelector('[data-v-leadform-submit]');
			if (btn) { btn.disabled = true; btn.dataset.origLabel = btn.textContent; btn.textContent = 'Sending…'; }

			var fields = serialize(form, cfg.honeypot);
			var payload = {
				endpoint:     cfg.endpoint,
				csrf:         cfg.csrf,
				fields:       fields,
				utm:          readUtmFromUrl(),
				source_page:  window.location.pathname + window.location.search,
				referrer:     document.referrer || '',
			};

			fetch(cfg.submitUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
				body: JSON.stringify(payload),
			})
			.then(function (r) { return r.json().then(function (j) { return { http: r.status, body: j }; }); })
			.then(function (res) {
				if (btn) { btn.disabled = false; btn.textContent = btn.dataset.origLabel || 'Submit'; }

				if (res.body && res.body.ok) {
					if (cfg.successUrl) {
						window.location.href = cfg.successUrl;
						return;
					}
					showAlert(wrap, 'success', cfg.successMsg);
					form.reset();
				} else {
					var msg = (res.body && res.body.message) ? res.body.message : cfg.errorMsg;
					showAlert(wrap, 'error', msg);
				}
			})
			.catch(function () {
				if (btn) { btn.disabled = false; btn.textContent = btn.dataset.origLabel || 'Submit'; }
				showAlert(wrap, 'error', cfg.errorMsg);
			});
		});
	}

	function init() {
		var sel = '[data-v-component-plugin-lead-platform-connector-leadform],'
				+ '[data-v-component-plugin-lead-platform-connector-lead-form]';
		var wraps = document.querySelectorAll(sel);
		wraps.forEach(attach);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
