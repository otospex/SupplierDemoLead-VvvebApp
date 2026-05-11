/* Lead Platform Connector — editor extension
 *
 * Extends the native Vvveb `html/form` block with a "Lead Form" accordion in
 * the Content tab containing a single Endpoint select dropdown, populated from
 * the plugin's admin Endpoints page. Picking an endpoint:
 *   - sets data-v-endpoint="<slug>" on the form (the only LPC-related attr
 *     ever written to saved HTML),
 *   - sets a data-v-component-* marker the runtime component scanner needs,
 *   - regenerates the form's user-facing fields from the endpoint's field_map.
 *
 * URL, API key, and field map are configured in the plugin's admin Endpoints
 * page (admin/?module=plugins/lead-platform-connector/endpoints), NOT here.
 */

(function () {
    'use strict';

    // ---- Endpoint cache --------------------------------------------------

    let LPC_ENDPOINTS = null;
    let LPC_ENDPOINTS_BY_SLUG = {};

    function fetchEndpoints(cb) {
        if (LPC_ENDPOINTS !== null) { cb(LPC_ENDPOINTS); return; }
        fetch('/admin/index.php?module=plugins/lead-platform-connector/api&action=endpoints', {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' },
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            LPC_ENDPOINTS = (data && data.ok && data.endpoints) ? data.endpoints : [];
            LPC_ENDPOINTS_BY_SLUG = {};
            LPC_ENDPOINTS.forEach(function (e) { LPC_ENDPOINTS_BY_SLUG[e.slug] = e; });
            cb(LPC_ENDPOINTS);
        })
        .catch(function () { LPC_ENDPOINTS = []; cb([]); });
    }

    function buildOptions(endpoints, current) {
        const opts = [{ value: '', text: '— pick an endpoint —' }];
        endpoints.forEach(function (e) {
            opts.push({ value: e.slug, text: e.label + ' (' + e.slug + ')' });
        });
        // If the saved slug isn't in the active list, keep it visible.
        if (current && !endpoints.some(function (e) { return e.slug === current; })) {
            opts.push({ value: current, text: current + ' (inactive or removed)' });
        }
        return opts;
    }

    // ---- Field generation from endpoint field_map -----------------------

    function escapeHtml(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
        });
    }

    function humanize(s) {
        return String(s).replace(/[_-]+/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); });
    }

    function inputTypeFor(sourceKey, targetPath) {
        const k = String(sourceKey).toLowerCase();
        const t = String(targetPath || '').toLowerCase();
        if (k.indexOf('email') >= 0 || t === 'email') return 'email';
        if (k.indexOf('phone') >= 0 || k.indexOf('tel') >= 0 || t === 'phone') return 'tel';
        return 'text';
    }

    function isRequired(value) {
        return typeof value === 'string' && value.trim().toLowerCase() === 'required';
    }

    function fieldMapToFormHtml(fieldMap) {
        if (!fieldMap || typeof fieldMap !== 'object') return '';
        const keys = Object.keys(fieldMap);
        if (!keys.length) return '';

        let html = '<div class="row g-3">';
        keys.forEach(function (k) {
            const target   = fieldMap[k];
            const type     = inputTypeFor(k, target);
            const required = isRequired(target);
            const req      = required ? ' required' : '';
            const star     = required ? ' <span class="text-danger" aria-hidden="true">*</span>' : '';
            const label    = humanize(k);
            const safeKey  = escapeHtml(k);
            html +=
                '<div class="col-md-6">' +
                    '<label class="form-label">' + escapeHtml(label) + star + '</label>' +
                    '<input type="' + type + '" class="form-control" name="' + safeKey + '"' + req + '>' +
                '</div>';
        });
        html += '</div>';
        return html;
    }

    /**
     * Replace form's user-facing fields with ones generated from the field map.
     * Skip when:
     *  - form has data-lpc-keep-design (opt-out for hand-crafted designs),
     *  - or every field-map key already has a matching input (avoids clobber
     *    on the spurious onChange that fires when the editor first loads
     *    with a saved endpoint value).
     */
    function regenerateFormFields(form, fieldMap) {
        if (!form || !fieldMap) return;
        if (form.hasAttribute('data-lpc-keep-design')) return;

        const keys = Object.keys(fieldMap);
        if (!keys.length) return;

        const allPresent = keys.every(function (k) {
            return form.querySelector('[name="' + (window.CSS && CSS.escape ? CSS.escape(k) : k) + '"]') !== null;
        });
        if (allPresent) return;

        const newHtml = fieldMapToFormHtml(fieldMap);
        if (!newHtml) return;

        const existingRow = form.querySelector(':scope > .row.g-3, :scope > div > .row.g-3');
        const wrapper = document.createElement('div');
        wrapper.innerHTML = newHtml;
        const newRow = wrapper.firstElementChild;

        if (existingRow && newRow) {
            existingRow.parentNode.replaceChild(newRow, existingRow);
        } else if (newRow) {
            const submit = form.querySelector('button[type=submit], input[type=submit]');
            if (submit) form.insertBefore(newRow, submit);
            else form.insertBefore(newRow, form.firstChild);
        }
    }

    // ---- Component marker for runtime discovery -------------------------
    //
    // The server-side component scanner only matches elements carrying a
    // data-v-component-* attribute (system/component/component.php:441). When
    // a slug is set we mark the form so the runtime template fires and
    // injects CSRF token + submit URL.

    const COMPONENT_MARKER = 'data-v-component-plugin-lead-platform-connector-leadform';

    function setMarker(form, on) {
        if (!form) return;
        if (on) form.setAttribute(COMPONENT_MARKER, '1');
        else form.removeAttribute(COMPONENT_MARKER);
    }

    // ---- Property registration on native html/form ----------------------
    //
    // Sort values 100..101 keep our properties AFTER the native Action /
    // Method / Encoding type (sort default 0) and BEFORE the _base "General"
    // accordion (sort 1000+). That keeps each accordion holding the right
    // properties: Form → Action/Method/Encoding, Lead Form → Endpoint,
    // General → Id/Title/Class.

    function getNativeForm() {
        const reg = (Vvveb.Components && Vvveb.Components._components) || {};
        return reg['html/form'];
    }

    function extendForm() {
        if (!getNativeForm()) {
            return setTimeout(extendForm, 50);
        }

        const props = [
            {
                name: 'Lead Form',
                key: 'lpc_section',
                inputtype: SectionInput,
                section: 'content',
                sort: 100,
                data: { header: 'Lead Form' },
            },
            {
                name: 'Endpoint',
                key: 'lpc_endpoint',
                htmlAttr: 'data-v-endpoint',
                section: 'lpc_section',
                sort: 101,
                inputtype: SelectInput,
                data: { options: [{ value: '', text: 'Loading…' }] },

                beforeInit: function (form) {
                    const property = this;
                    const current = (form && form.getAttribute) ? (form.getAttribute('data-v-endpoint') || '') : '';

                    if (LPC_ENDPOINTS) {
                        property.data = property.data || {};
                        property.data.options = buildOptions(LPC_ENDPOINTS, current);
                    }

                    fetchEndpoints(function (endpoints) {
                        property.data = property.data || {};
                        property.data.options = buildOptions(endpoints, current);

                        // If the right panel select is already on screen, repopulate it now.
                        const panel = document.querySelector('#right-panel') || document;
                        const sel = panel.querySelector('select[name="lpc_endpoint"]');
                        if (sel) {
                            sel.innerHTML = property.data.options.map(function (o) {
                                const isSel = (o.value === current) ? ' selected' : '';
                                return '<option value="' + escapeHtml(o.value) + '"' + isSel + '>' + escapeHtml(o.text) + '</option>';
                            }).join('');
                        }
                    });
                },

                onChange: function (form, value) {
                    if (!form || form.tagName !== 'FORM') return form;
                    if (!value) {
                        form.removeAttribute('data-v-endpoint');
                        setMarker(form, false);
                        return form;
                    }
                    form.setAttribute('data-v-endpoint', value);
                    setMarker(form, true);

                    const ep = LPC_ENDPOINTS_BY_SLUG[value];
                    if (ep && ep.field_map && Object.keys(ep.field_map).length) {
                        regenerateFormFields(form, ep.field_map);
                    }
                    return form;
                },
            },
        ];

        Vvveb.Components.extend('html/form', 'html/form', { properties: props });
    }

    if (typeof Vvveb !== 'undefined' && Vvveb.Components) {
        extendForm();
    } else {
        window.addEventListener('load', extendForm);
    }

    // Warm the cache.
    fetchEndpoints(function () {});
})();
