/*
 * Lead Platform Connector — diagnostic stepper runtime
 *
 * Superset of lead-form.20260827.js: it keeps the whole fetch/CSRF/honeypot
 * plumbing for ordinary one-shot forms, and adds the three-step diagnostic
 * intake on top of it.
 *
 * A form opts into the stepper simply by containing `<fieldset data-v-stage="N">`
 * children. Everything else behaves exactly like the one-shot runtime, so a
 * template may load this file instead of lead-form.20260827.js without
 * changing any other form on the page.
 *
 * Stepper contract (see plugins/lead-platform-connector/app/controller/submit.php):
 *   stage 1, no token  -> { ok, lead_token }
 *   stage 2 / 3, token -> { ok }
 *   410 = the resume session expired (server supplies the message)
 *   400 = the token is missing or malformed
 * Only the active step's own fields are posted: inactive fieldsets carry both
 * `hidden` and `disabled`, so they are excluded from FormData and from native
 * constraint validation (a `required` control inside a hidden fieldset would
 * otherwise make the form unvalidatable and unfocusable).
 */
(function () {
	'use strict';

	const TOKEN_URL  = '/index.php?module=plugins/lead-platform-connector/submit&action=token';
	const HONEYPOT   = 'company_website';
	const MIN_TIMEMS = 1500;
	const TOTAL_STEPS = 3;
	const STORAGE_PREFIX = 'lpc:lead:';

	const COPY = {
		unavailable: 'Le formulaire est temporairement indisponible. Rechargez la page puis réessayez.',
		tooFast:     'Le formulaire vient de charger. Merci de réessayer dans un instant.',
		received:    'Merci — votre demande a bien été reçue.',
		generic:     'Une erreur est survenue. Merci de réessayer.',
		network:     'Erreur réseau. Merci de réessayer.',
		stage1Saved: 'Coordonnées enregistrées. Précisez maintenant votre situation.',
		complete:    'Merci. Votre diagnostic est enregistré et sera relu avant toute orientation. Aucune coordonnée n’est transmise à un fournisseur sans votre consentement nominatif.',
	};

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

	function clearAlerts(form) {
		['success', 'error'].forEach(function (kind) {
			const box = form.parentNode ? form.parentNode.querySelector('[data-v-leadform-' + kind + ']') : null;
			if (box) box.classList.add('d-none');
		});
	}

	function serialize(form, honeypotName) {
		const fd = new FormData(form);
		let out = {};
		fd.forEach(function (value, key) {
			if (key === honeypotName) return;
			// Repeated checkbox groups are declared as `name="x[]"`; the
			// controller stores them as a JSON list under the bare name.
			if (key.slice(-2) === '[]') {
				const name = key.slice(0, -2);
				if (!Array.isArray(out[name])) out[name] = [];
				out[name].push(value);
				return;
			}
			out[key] = value;
		});
		// Additive extension point for endpoint-specific field shapes. The
		// connector remains generic.
		const fieldsEvent = new CustomEvent('lead-platform-connector:fields', {
			bubbles: true,
			detail: { form: form, fields: out }
		});
		form.dispatchEvent(fieldsEvent);
		out = fieldsEvent.detail.fields || out;
		if (out.provider_introduction_requested === '1') {
			out.consent_timestamp = new Date().toISOString();
		} else {
			delete out.provider_introduction_requested;
			delete out.provider_slug;
			delete out.consent_text_version;
			delete out.consent_timestamp;
		}
		return out;
	}

	/* ---------------------------------------------------------------- stepper */

	function readSteps(form) {
		const nodes = Array.prototype.slice.call(form.querySelectorAll('[data-v-stage]'));
		return nodes
			.map(function (el) { return { stage: parseInt(el.getAttribute('data-v-stage'), 10), el: el }; })
			.filter(function (s) { return s.stage >= 1 && s.stage <= TOTAL_STEPS; })
			.sort(function (a, b) { return a.stage - b.stage; });
	}

	function storageKey(slug) { return STORAGE_PREFIX + slug; }

	function readToken(slug) {
		try { return window.sessionStorage.getItem(storageKey(slug)) || ''; }
		catch (e) { return ''; }
	}

	function writeToken(slug, token) {
		try {
			if (token) window.sessionStorage.setItem(storageKey(slug), token);
			else window.sessionStorage.removeItem(storageKey(slug));
		} catch (e) { /* private mode — the stepper still works within one page */ }
	}

	function tokenFromUrl() {
		const params = new URLSearchParams(window.location.search || '');
		const raw = (params.get('lead') || '').trim();
		return /^[0-9a-f]{64}$/.test(raw) ? raw : '';
	}

	/**
	 * Drop `?lead=` from the address bar without navigating.
	 *
	 * A token that is dead (410) or spent (stage 3 settled) must not survive in
	 * the URL: a reload would re-adopt it, resume at step 2 and announce
	 * « Coordonnées enregistrées » for a row that can no longer be written.
	 * Only the parameter is removed; the path and the #etape-N fragment are
	 * left alone.
	 */
	function stripLeadParam() {
		try {
			if (!window.history || typeof window.history.replaceState !== 'function') return;
			const url = new URL(window.location.href);
			if (!url.searchParams.has('lead')) return;
			url.searchParams.delete('lead');
			window.history.replaceState(null, '', url.pathname + url.search + url.hash);
		} catch (e) { /* older browser — the 410 path still works, it just re-asks */ }
	}

	/** Forget a token everywhere it is held: config, session storage, URL. */
	function invalidateToken(cfg) {
		cfg.leadToken = '';
		cfg.stage1Signature = null;
		writeToken(cfg.endpoint, '');
		stripLeadParam();
	}

	/**
	 * A stable fingerprint of the step-1 answers.
	 *
	 * Step 1 is the only step that can be revisited with « Retour » after it has
	 * already been written to the server. Comparing this against the value the
	 * live token stands for tells the difference between "nothing changed, just
	 * move forward" and "the visitor corrected something, merge it into the row".
	 */
	function stepOneSignature(form) {
		const step = form.querySelector('[data-v-stage="1"]');
		if (!step) return '';
		return Array.prototype.slice.call(step.querySelectorAll('input, select, textarea'))
			.filter(function (el) { return el.name && el.type !== 'hidden'; })
			.map(function (el) {
				const value = (el.type === 'checkbox' || el.type === 'radio') ? (el.checked ? '1' : '') : el.value;
				return el.name + '=' + value;
			})
			.join('&');
	}

	function setStageVisible(step, active) {
		step.el.hidden = !active;
		// `disabled` keeps the inactive steps out of FormData and out of native
		// constraint validation.
		step.el.disabled = !active;
	}

	function focusFirstControl(step) {
		const control = step.el.querySelector('input:not([type=hidden]), select, textarea, button');
		if (control && typeof control.focus === 'function') control.focus();
	}

	function updateProgress(form, stage) {
		const node = form.querySelector('[data-v-stepper-progress]');
		if (node) node.textContent = 'Étape ' + stage + ' sur ' + TOTAL_STEPS;
	}

	function activate(state, stage, moveFocus) {
		let resolved = null;
		state.steps.forEach(function (step) {
			const active = step.stage === stage;
			setStageVisible(step, active);
			if (active) resolved = step;
		});
		if (!resolved) return false;
		state.stage = stage;
		updateProgress(state.form, stage);
		if (moveFocus) focusFirstControl(resolved);
		return true;
	}

	function bindConsentToggle(form) {
		const block = form.querySelector('[data-v-consent-block]');
		if (!block) return;
		const radios = form.querySelectorAll('input[name="next_step"]');
		if (!radios.length) return;
		const checkbox = block.querySelector('input[name="provider_introduction_requested"]');
		function sync() {
			let named = false;
			radios.forEach(function (r) { if (r.checked && r.value === 'mise-en-relation') named = true; });
			block.hidden = !named;
			// Withdrawing the named-introduction choice withdraws the consent
			// with it: a checkbox the visitor can no longer see must never stay
			// checked.
			if (!named && checkbox) checkbox.checked = false;
		}
		radios.forEach(function (r) { r.addEventListener('change', sync); });
		sync();
	}

	function bindBackButtons(state) {
		state.form.querySelectorAll('[data-v-step-back]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				clearAlerts(state.form);
				activate(state, Math.max(1, state.stage - 1), true);
			});
		});
	}

	/* -------------------------------------------------------------- submission */

	function attach(form, cfg) {
		if (!form || form.__lpcBound) return;
		form.__lpcBound = true;

		const steps = readSteps(form);
		const state = { form: form, steps: steps, stage: steps.length ? steps[0].stage : 0 };

		// Fail closed on every step: no submit button is live until the CSRF
		// token has been acquired.
		form.querySelectorAll('button[type=submit], input[type=submit]').forEach(function (button) {
			button.disabled = true;
		});

		// The form may carry novalidate from the editor; on the live page we
		// want native HTML5 validation on `required` inputs.
		form.removeAttribute('novalidate');

		if (steps.length) {
			bindConsentToggle(form);
			bindBackButtons(state);

			const urlToken = tokenFromUrl();
			if (urlToken) {
				cfg.leadToken = urlToken;
				writeToken(cfg.endpoint, urlToken);
			} else {
				cfg.leadToken = readToken(cfg.endpoint);
			}

			// A live token means step 1 already settled: resume at step 2 when
			// this placement renders it, otherwise stay on step 1.
			const resumeStage = cfg.leadToken && steps.length > 1 ? 2 : steps[0].stage;
			activate(state, resumeStage, false);
			if (cfg.leadToken && resumeStage === 2) {
				showAlert(form, 'success', COPY.stage1Saved);
				// The browser could not honour `#etape-2` at parse time: the
				// fieldset was still `hidden`. Bring it into view now.
				if (urlToken && typeof form.scrollIntoView === 'function') {
					form.scrollIntoView({ block: 'start' });
				}
			}
		}

		form.addEventListener('submit', function (ev) {
			ev.preventDefault();
			if (!cfg.ready || !cfg.csrf || !cfg.submitUrl) {
				showAlert(form, 'error', COPY.unavailable);
				return;
			}

			if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
				if (typeof form.reportValidity === 'function') form.reportValidity();
				return;
			}

			const hp = form.querySelector('[name="' + HONEYPOT + '"]');
			if (hp && hp.value) {
				showAlert(form, 'success', COPY.received);
				return;
			}

			const staged = steps.length > 0;

			// Which stage does this POST claim? Decided before the timing guard,
			// because a request that carries a resume token is by definition the
			// continuation of a session that already cleared that guard.
			let postStage = state.stage;
			if (staged && state.stage === 1 && cfg.leadToken) {
				if (stepOneSignature(form) === cfg.stage1Signature) {
					// « Retour » then « Continuer » with nothing edited. Posting
					// stage 1 again would insert a SECOND row and orphan the one
					// the live token points at, so make no request at all.
					onStageSuccess(state, cfg, {});
					return;
				}
				// The visitor corrected a step-1 answer. Stage 2 is the update
				// branch, so the correction merges into the row we already own
				// instead of starting a new one; the controller's monotonic
				// stage rule leaves a further-advanced row where it is.
				postStage = 2;
			}
			const carriesToken = staged && postStage > 1 && !!cfg.leadToken;

			if (!carriesToken) {
				const elapsed = Date.now() - cfg.renderTs;
				if (cfg.renderTs && elapsed < MIN_TIMEMS) {
					showAlert(form, 'error', COPY.tooFast);
					return;
				}
			}

			const btn = form.querySelector('[data-v-stage]:not([hidden]) button[type=submit]')
				|| form.querySelector('button[type=submit], input[type=submit]');
			if (btn) {
				btn.disabled = true;
				btn.dataset.origLabel = btn.textContent || btn.value || 'Envoyer';
				if ('textContent' in btn) btn.textContent = 'Envoi…';
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
			if (staged) {
				payload.stage = postStage;
				if (postStage > 1) payload.lead_token = cfg.leadToken || '';
			}

			fetch(cfg.submitUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
				body: JSON.stringify(payload),
			})
			.then(function (r) { return r.json().then(function (j) { return { http: r.status, body: j }; }); })
			.then(function (res) {
				restoreButton(btn);

				if (res.body && res.body.ok) {
					if (!staged) {
						onPlainSuccess(form, cfg);
						return;
					}
					onStageSuccess(state, cfg, res.body);
					return;
				}

				const msg = (res.body && res.body.message) ? res.body.message : COPY.generic;
				showAlert(form, 'error', msg);
				if (res.http === 410) {
					// The resume session is gone. Drop the dead token — config,
					// session storage AND the ?lead= parameter, or a reload
					// would resurrect it — and send the visitor back to step 1
					// rather than looping on a POST that can never succeed.
					invalidateToken(cfg);
					if (staged) activate(state, 1, true);
				}
				if (res.http === 419) {
					refreshToken(cfg);
				}
			})
			.catch(function () {
				restoreButton(btn);
				showAlert(form, 'error', COPY.network);
			});
		});

		function restoreButton(btn) {
			if (!btn) return;
			btn.disabled = false;
			if ('textContent' in btn) btn.textContent = btn.dataset.origLabel || 'Envoyer';
		}

		function onPlainSuccess(theForm, theCfg) {
			const successEvent = new CustomEvent('lead-platform-connector:success', {
				bubbles: true,
				detail: { form: theForm, message: COPY.received }
			});
			theForm.dispatchEvent(successEvent);
			showAlert(theForm, 'success', successEvent.detail.message);
			theForm.reset();
			refreshToken(theCfg);
		}

		function onStageSuccess(theState, theCfg, body) {
			const theForm = theState.form;
			if (theState.stage === 1) {
				// Only a stage-1 INSERT hands back a token. A corrected step 1
				// posts stage 2 and answers {ok:true}, and the "nothing changed"
				// short-circuit posts nothing at all — in both cases the token
				// we already hold is the right one and must not be wiped.
				if (body && body.lead_token) {
					theCfg.leadToken = String(body.lead_token);
					writeToken(theCfg.endpoint, theCfg.leadToken);
				}
				theCfg.stage1Signature = stepOneSignature(theForm);
				const redirect = theForm.getAttribute('data-v-stage-redirect');
				if (redirect) {
					// This placement renders step 1 only (the homepage). Hand
					// the visitor to the full diagnostic with a resume token.
					showAlert(theForm, 'success', COPY.stage1Saved);
					window.location.assign(redirect + '?lead=' + encodeURIComponent(theCfg.leadToken) + '#etape-2');
					return;
				}
				showAlert(theForm, 'success', COPY.stage1Saved);
				activate(theState, 2, true);
				refreshToken(theCfg);
				return;
			}

			if (theState.stage === 2) {
				clearAlerts(theForm);
				activate(theState, 3, true);
				refreshToken(theCfg);
				return;
			}

			// Stage 3 settles the lead: the token is burned server-side, so the
			// form must not be re-submittable against it.
			const successEvent = new CustomEvent('lead-platform-connector:success', {
				bubbles: true,
				detail: { form: theForm, message: COPY.complete }
			});
			theForm.dispatchEvent(successEvent);
			invalidateToken(theCfg);
			theState.steps.forEach(function (step) { setStageVisible(step, false); });
			const progress = theForm.querySelector('[data-v-stepper-progress]');
			if (progress) progress.textContent = 'Diagnostic transmis.';
			showAlert(theForm, 'success', successEvent.detail.message);
			theForm.reset();
		}
	}

	function refreshToken(cfg) {
		return fetchToken(cfg.endpoint).then(function (next) {
			if (!next) return;
			cfg.csrf = next.csrf;
			cfg.submitUrl = next.submitUrl;
			// The minimum-time guard is re-armed with the new token, exactly as
			// the one-shot runtime does after a successful submit: a form that
			// has just been reset for a fresh entry gets a fresh clock. Staged
			// posts are unaffected — they carry a resume token, which is
			// stronger proof of a real prior round trip than elapsed time, and
			// the submit handler exempts them from the guard.
			cfg.renderTs = next.renderTs;
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
			const cfg = { endpoint: slug, csrf: '', submitUrl: '', renderTs: Date.now(), ready: false, leadToken: '', stage1Signature: null };
			attach(form, cfg);
			fetchToken(slug).then(function (tok) {
				if (!tok) {
					console.warn('[LPC] could not acquire token for slug', slug);
					showAlert(form, 'error', COPY.unavailable);
					return;
				}
				cfg.csrf = tok.csrf;
				cfg.submitUrl = tok.submitUrl;
				cfg.renderTs = tok.renderTs;
				cfg.ready = true;
				form.querySelectorAll('button[type=submit], input[type=submit]').forEach(function (button) {
					button.disabled = false;
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
