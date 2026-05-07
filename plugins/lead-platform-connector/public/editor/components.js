/* Lead Platform Connector — editor block
 * Adds a "Lead Form (Platform)" component under the Plugins group.
 * Endpoint slug is a dropdown populated from /admin/?module=plugins/lead-platform-connector/api&action=endpoints
 * Picking an endpoint regenerates the form fields from its Field map.
 */

(function () {
    'use strict';

    // Cache for endpoint list, populated on first dropdown render.
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
        // SelectInput expects an array of { value, text }.
        const opts = [{ value: '', text: '— pick an endpoint —' }];
        endpoints.forEach(function (e) {
            opts.push({ value: e.slug, text: e.label + ' (' + e.slug + ')' });
        });
        // If the saved value isn't in the list, keep it so users see what was set.
        if (current && !endpoints.some(function (e) { return e.slug === current; })) {
            opts.push({ value: current, text: current + ' (inactive or removed)' });
        }
        return opts;
    }

    /**
     * Convert a field map JSON to an HTML form-fields fragment.
     * Source key (LHS) becomes the input `name` attribute.
     * Heuristics decide input type and label.
     */
    function fieldMapToFormHtml(fieldMap) {
        if (!fieldMap || typeof fieldMap !== 'object') return '';

        const keys = Object.keys(fieldMap);
        if (!keys.length) return '';

        const humanize = function (s) {
            return s.replace(/[_-]+/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); });
        };
        const inputTypeFor = function (sourceKey, targetPath) {
            const k = sourceKey.toLowerCase();
            const t = (targetPath || '').toLowerCase();
            if (k.indexOf('email') >= 0 || t === 'email') return 'email';
            if (k.indexOf('phone') >= 0 || k.indexOf('tel') >= 0 || t === 'phone') return 'tel';
            if (k.indexOf('zip') >= 0 || k.indexOf('postal') >= 0) return 'text';
            return 'text';
        };
        const isRequired = function (k, t) {
            const lk = (k + ' ' + (t || '')).toLowerCase();
            return /name|phone|tel|email/.test(lk);
        };

        let html = '<div class="row g-3">';
        keys.forEach(function (k) {
            const target = fieldMap[k];
            const type   = inputTypeFor(k, target);
            const req    = isRequired(k, target) ? ' required' : '';
            const label  = humanize(k);
            html +=
                '<div class="col-md-6">' +
                    '<label class="form-label">' + label + '</label>' +
                    '<input type="' + type + '" class="form-control" name="' + k + '"' + req + '>' +
                '</div>';
        });
        html += '</div>';
        return html;
    }

    /**
     * Replace the current form's fields (everything inside <form> except hidden honeypot
     * and the submit button) with newly-generated fields from the endpoint's field map.
     */
    function regenerateFormFields(rootElement, fieldMap) {
        if (!rootElement) return;
        const form = rootElement.querySelector('form[data-v-leadform-config]');
        if (!form) return;

        const newFieldsHtml = fieldMapToFormHtml(fieldMap);
        if (!newFieldsHtml) return;

        // Find the existing top-level fields wrapper (.row.g-3) and replace it.
        const existingRow = form.querySelector(':scope > .row.g-3, :scope > div > .row.g-3');
        const wrapper = document.createElement('div');
        wrapper.innerHTML = newFieldsHtml;
        const newRow = wrapper.firstElementChild;

        if (existingRow && newRow) {
            existingRow.parentNode.replaceChild(newRow, existingRow);
        } else if (newRow) {
            // Insert at the top of the form (before honeypot/submit).
            form.insertBefore(newRow, form.firstChild);
        }
    }

    // Warm the endpoints cache as soon as the editor loads.
    fetchEndpoints(function () {});

    Vvveb.ComponentsGroup['Plugins'] = Vvveb.ComponentsGroup['Plugins'] ?? [];
    Vvveb.ComponentsGroup['Plugins'].push("lead-platform-connector/lead-form");

    Vvveb.Components.extend("_base", "lead-platform-connector/lead-form", {
        image: "icons/cloud-upload.svg",
        name: "Lead Form (Platform)",
        attributes: ["data-v-component-plugin-lead-platform-connector-leadform"],
        html: `<div data-v-component-plugin-lead-platform-connector-leadform
                data-v-endpoint=""
                data-v-success_url=""
                data-v-success_msg="Thanks — your request was received."
                data-v-error_msg="Sorry, something went wrong. Please try again."
                data-v-honeypot="company_website"
                data-v-min_time_ms="1500"
                class="lpc-lead-form-wrap">

            <div class="alert alert-success d-none" data-v-leadform-success role="alert">
                Thanks — your request was received.
            </div>
            <div class="alert alert-danger d-none" data-v-leadform-error role="alert">
                Sorry, something went wrong. Please try again.
            </div>

            <form data-v-leadform-config
                data-endpoint=""
                data-csrf=""
                data-submit-url=""
                data-honeypot="company_website"
                data-success-url=""
                data-success-msg=""
                data-error-msg=""
                data-render-ts=""
                method="post"
                novalidate>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Full name</label>
                        <input type="text" class="form-control" name="full_name" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Phone</label>
                        <input type="tel" class="form-control" name="telephone" required>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email_address">
                    </div>
                </div>

                <div aria-hidden="true" style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden;">
                    <label>Do not fill this field</label>
                    <input type="text" name="company_website" tabindex="-1" autocomplete="off">
                </div>

                <div class="mt-3">
                    <button type="submit" class="btn btn-primary" data-v-leadform-submit>
                        Submit
                    </button>
                </div>
            </form>
        </div>`,
        properties: [{
            name: "Endpoint",
            key: "endpoint",
            htmlAttr: "data-v-endpoint",
            inputtype: SelectInput,
            data: { options: [{ value: '', text: 'Loading…' }] },

            // Vvveb calls beforeInit when rendering each property in the right panel.
            // Update property.data.options synchronously from cache when possible,
            // and re-fetch in the background so the next render is fresh.
            beforeInit: function (node) {
                const property = this;
                const current = node && node.getAttribute ? (node.getAttribute('data-v-endpoint') || '') : '';

                if (LPC_ENDPOINTS) {
                    property.data = property.data || {};
                    property.data.options = buildOptions(LPC_ENDPOINTS, current);
                }

                fetchEndpoints(function (endpoints) {
                    property.data = property.data || {};
                    property.data.options = buildOptions(endpoints, current);

                    // If the right panel select is already on screen, repopulate it now.
                    const panel = document.querySelector('#right-panel') || document;
                    const sel = panel.querySelector('select[name="endpoint"]');
                    if (sel) {
                        const prev = sel.value;
                        sel.innerHTML = property.data.options.map(function (o) {
                            const isSel = (o.value === current || o.value === prev) ? ' selected' : '';
                            return '<option value="' + o.value + '"' + isSel + '>' + o.text + '</option>';
                        }).join('');
                    }
                });
            },

            onChange: function (node, value /*, input, component, origEvent */) {
                // Always regenerate the form fields when the endpoint changes.
                if (!value) {
                    node.setAttribute('data-v-endpoint', '');
                    return node;
                }
                node.setAttribute('data-v-endpoint', value);

                const ep = LPC_ENDPOINTS_BY_SLUG[value];
                if (ep && ep.field_map && Object.keys(ep.field_map).length) {
                    regenerateFormFields(node, ep.field_map);
                }
                return node;
            },
        }, {
            name: "",
            key: "endpoint_warning",
            inline: false,
            col: 12,
            inputtype: NoticeInput,
            data: {
                type: 'info',
                title: '',
                text: 'Pick an active endpoint. Form fields auto-regenerate from the endpoint\'s Field Map. The browser never sees the API key.'
            }
        }, {
            name: "Success redirect URL",
            key: "success_url",
            htmlAttr: "data-v-success_url",
            inputtype: TextInput
        }, {
            name: "Success message",
            key: "success_msg",
            htmlAttr: "data-v-success_msg",
            inputtype: TextInput
        }, {
            name: "Error message",
            key: "error_msg",
            htmlAttr: "data-v-error_msg",
            inputtype: TextInput
        }, {
            name: "Honeypot field name",
            key: "honeypot",
            htmlAttr: "data-v-honeypot",
            inputtype: TextInput
        }, {
            name: "Minimum submit delay (ms)",
            key: "min_time_ms",
            htmlAttr: "data-v-min_time_ms",
            inputtype: NumberInput
        }]
    });
})();
