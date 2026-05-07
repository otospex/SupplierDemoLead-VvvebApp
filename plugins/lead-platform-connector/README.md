# Lead Platform Connector

Vvveb plugin that lets pages built with the visual editor send form submissions to the Lead Management Platform (Laravel `POST /api/v1/leads`) via a secure server-side proxy.

## Architecture

```
[Visitor browser]                  [This Vvveb site (PHP)]                 [Lead Platform (Laravel)]
                                                                            
  <form>                            POST /index.php?module=                  POST /api/v1/leads
   submit ───────────────────────►  plugins/lead-platform-connector/submit  ─────────────────────►
   (JSON: endpoint+csrf+fields)     - verify CSRF (HMAC, 30 min)             X-Api-Key: lp_...
                                    - check Origin allowlist
                                    - rate-limit per IP
                                    - decrypt API key (AES-256-GCM)
                                    - apply field map
                                    - log to `lead_submission`
```

The browser **never** sees the API key. It only sees a per-page CSRF token bound to an endpoint slug.

## What's included

| File | Purpose |
|---|---|
| `plugin.php` | Plugin bootstrap. Registers admin menu, editor component asset, install hook, and a `View::render` listener that injects `lead-form.js` on pages containing the block. |
| `install.php` + `install/sql/{pgsql,mysqli,sqlite}/schema/*.sql` | Creates `lead_endpoint` (config) and `lead_submission` (audit log) tables. |
| `system/Crypto.php` | AES-256-GCM encryption for stored API keys; key derived from `SECRET`/`AUTH_KEY` constants or a generated `storage/lead-platform-connector.key`. |
| `system/CsrfToken.php` | HMAC-signed token bound to endpoint slug + 30 min TTL. |
| `system/LeadClient.php` | Curl POST to `{platform_url}/api/v1/leads` with `X-Api-Key`. |
| `component/lead-form.php` | Server-side component. Issues a fresh CSRF token at render time. |
| `app/template/lead-form.tpl` | PHP-included template that injects the CSRF token + render timestamp into the form's data attributes. |
| `app/controller/submit.php` | Public proxy endpoint. Verifies CSRF/origin/rate, applies field map, calls platform, logs result. |
| `public/editor/components.js` | Registers the **"Lead Form (Platform)"** block in the Vvveb editor's component panel under "Plugins". |
| `public/js/lead-form.js` | Runtime: AJAX submit, honeypot check, time-gate, success redirect. |
| `admin/controller/endpoints.php` + `admin/template/endpoints.tpl` | CRUD for endpoints (slug, platform URL, API key, campaign, field map, allowed origins, rate limit). |
| `admin/controller/submissions.php` + `admin/template/submissions.tpl` | Read-only audit log. |

## Lead payload sent to the platform

Matches the Laravel `LeadIngestionController::store` schema:

```json
{
  "campaign":     "<from endpoint config — never from browser>",
  "name":         "...",
  "phone":        "...",
  "email":        "...",
  "zip":          "...",
  "location":     "...",
  "category":     "...",
  "source_page":  "/landing/quotes",
  "utm_params":   { "utm_source": "...", "utm_medium": "...", "gclid": "..." },
  "tool_answers": { "<any extra form fields>": "..." }
}
```

Header: `X-Api-Key: lp_...` (server-side only).

## Field map

Per-endpoint JSON. Maps input `name=` attributes to platform fields. Dot-notation creates nested keys.

```json
{
  "first_name":   "name",
  "phone_number": "phone",
  "budget":       "tool_answers.budget",
  "timeline":     "tool_answers.timeline"
}
```

Unmapped fields whose key isn't a known top-level field are auto-collected into `tool_answers`.

## Security

- API keys encrypted at rest (AES-256-GCM).
- Per-page CSRF token (HMAC), 30-min TTL, single endpoint binding.
- Origin/Referer allowlist per endpoint (supports `*.example.com`).
- Per-IP file-based rate limiting per endpoint (default 30/min, configurable).
- Honeypot field + minimum-time-to-submit gate (default 1500 ms).
- Phone/email hashed in audit log; raw payload excludes them.
- Soft success on platform 5xx/network — submission persisted as `pending` for retry; visitor isn't blocked.

## Installing / activating

The plugin is auto-discovered by Vvveb. On first activation it runs `Install` which creates the two tables. Then:

1. **Admin → Plugins → Lead Platform → Endpoints → New endpoint** — fill platform URL, API key, campaign, field map.
2. **Editor** — drop the **"Lead Form (Platform)"** block (under the "Plugins" group) onto a page, set the **Endpoint slug** in the right panel to the slug from step 1.
3. Publish the page. Submissions show up under **Lead Platform → Submissions** and (on success) inside the Laravel platform.

## Future hardening

- Move rate-limit storage from filesystem to Redis when available.
- Background queue for retrying `pending` submissions.
- Optional hCaptcha/Turnstile site-key per endpoint.
- Per-role permission gating on the admin pages.
