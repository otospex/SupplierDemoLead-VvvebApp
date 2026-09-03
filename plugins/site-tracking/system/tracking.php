<?php

namespace Vvveb\Plugins\SiteTracking\System;

if (! defined('V_VERSION')) {
	die('Invalid request!');
}

/**
 * Renders the tracking markup from the settings saved in the admin.
 *
 * Two layers, on purpose:
 *  - head(): audience measurement that needs no consent (a cookieless Matomo
 *    tag, or any script the operator declares exempt) and is emitted on every
 *    page;
 *  - body(): marketing tags (Google Ads, Meta, GTM…) stored INERT in a
 *    text/plain element and only executed after the visitor accepts them in
 *    the consent banner. The choice is kept six months, as the CNIL expects.
 *
 * Nothing here may ever break a page: every entry point catches and returns
 * an empty string.
 */
final class Tracking {
	public const CONSENT_KEY = 'id_consent';

	public const CONSENT_DAYS = 182;

	private static function e($value): string {
		return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}

	/** http(s) URL with a trailing slash, or '' when unusable. */
	public static function matomoUrl(array $settings): string {
		$url = trim((string) ($settings['matomo_url'] ?? ''));
		if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL) || ! in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
			return '';
		}

		return rtrim($url, '/') . '/';
	}

	public static function matomoSiteId(array $settings): string {
		$id = trim((string) ($settings['matomo_site_id'] ?? ''));

		return preg_match('/^\d{1,9}$/', $id) ? $id : '';
	}

	private static function goalId(array $settings): int {
		$id = trim((string) ($settings['matomo_goal_id'] ?? ''));

		return preg_match('/^\d{1,9}$/', $id) ? (int) $id : 0;
	}

	private static function jsString(string $value): string {
		// JSON_HEX_TAG keeps "</script>" from terminating the element.
		return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
	}

	private static function matomoTag(array $settings): string {
		$url = self::matomoUrl($settings);
		$id  = self::matomoSiteId($settings);
		if ($url === '' || $id === '') {
			return '';
		}
		$u = self::jsString($url);
		$i = self::jsString($id);

		return "<script>var _paq=window._paq=window._paq||[];_paq.push(['disableCookies']);_paq.push(['trackPageView']);_paq.push(['enableLinkTracking']);"
			. "(function(){var u=$u;_paq.push(['setTrackerUrl',u+'matomo.php']);_paq.push(['setSiteId',$i]);"
			. "var d=document,g=d.createElement('script'),s=d.getElementsByTagName('script')[0];g.async=true;g.src=u+'matomo.js';s.parentNode.insertBefore(g,s);})();</script>";
	}

	public static function head(array $settings): string {
		$html = self::matomoTag($settings);
		$raw  = trim((string) ($settings['head_scripts'] ?? ''));
		if ($raw !== '') {
			$html .= "\n" . $raw;
		}

		return $html === '' ? '' : "\n<!-- site-tracking -->\n" . $html . "\n";
	}

	private static function enabled(array $settings): bool {
		return self::matomoTag($settings) !== ''
			|| trim((string) ($settings['head_scripts'] ?? '')) !== ''
			|| trim((string) ($settings['marketing_scripts'] ?? '')) !== '';
	}

	public static function body(array $settings): string {
		if (! self::enabled($settings)) {
			return '';
		}
		$marketing = trim((string) ($settings['marketing_scripts'] ?? ''));
		$goal      = self::goalId($settings);
		$key       = self::CONSENT_KEY;
		$days      = self::CONSENT_DAYS;

		$html = '<script>(function(){'
			. 'window.idTrack=function(name,props){props=props||{};try{'
			. "if(window._paq){_paq.push(['trackEvent','lead',String(name),String(props.endpoint||'')]);" . ($goal > 0 ? "if(name==='lead'){_paq.push(['trackGoal', $goal]);}" : '') . '}'
			. "if(window.dataLayer){window.dataLayer.push(Object.assign({event:'id_'+name},props));}"
			. "document.dispatchEvent(new CustomEvent('id:track',{detail:{name:name,props:props}}));"
			. '}catch(e){}};'
			. "document.addEventListener('lead-platform-connector:success',function(e){var f=e&&e.detail&&e.detail.form;window.idTrack('lead',{endpoint:(f&&f.getAttribute('data-v-endpoint'))||''});});"
			. '})();</script>';

		if ($marketing === '') {
			return "\n<!-- site-tracking -->\n" . $html . "\n";
		}

		$text = trim((string) ($settings['consent_text'] ?? ''));
		if ($text === '') {
			$text = 'Nous utilisons des traceurs publicitaires (mesure des campagnes) uniquement avec votre accord. La mesure d’audience, sans cookie, ne nécessite pas de consentement.';
		}
		// The inert container: text/plain scripts never execute; "</script" is
		// neutralised so the markup cannot close the container early.
		$inert = str_ireplace('</script', '<\/script', $marketing);

		$html .= '<script type="text/plain" data-id-consent="marketing">' . $inert . '</script>'
			. '<div id="id-consent" class="id-consent" role="dialog" aria-live="polite" aria-label="Choix des traceurs" hidden>'
			. '<p class="id-consent-text">' . self::e($text) . ' <a href="/confidentialite">En savoir plus</a></p>'
			. '<div class="id-consent-actions"><button type="button" class="sd-btn sd-btn-secondary" data-id-consent-refuse>Refuser</button>'
			. '<button type="button" class="sd-btn sd-btn-primary" data-id-consent-accept>Accepter</button></div></div>'
			. '<style>.id-consent{position:fixed;inset:auto 1rem 1rem 1rem;z-index:1080;max-width:38rem;margin-inline:auto;padding:1rem 1.25rem;border:1px solid var(--color-rule,#d6dde8);border-radius:var(--radius-lg,12px);background:var(--color-panel-raised,#fff);box-shadow:0 12px 32px rgba(11,27,51,.14);font-size:.92rem;color:var(--color-ink,#0b1b33)}'
			. '.id-consent[hidden]{display:none}.id-consent-text{margin:0 0 .75rem}.id-consent-actions{display:flex;gap:.5rem;flex-wrap:wrap;justify-content:flex-end}.id-consent .sd-btn{padding:.6rem 1rem;font-size:.9rem}</style>'
			. '<script>(function(){var KEY=' . self::jsString($key) . ',DAYS=' . $days . ';'
			. 'function read(){try{var m=document.cookie.match(new RegExp("(?:^|; )"+KEY+"=([^;]*)"));if(m)return decodeURIComponent(m[1]);return window.localStorage.getItem(KEY)||"";}catch(e){return "";}}'
			. 'function write(v){try{document.cookie=KEY+"="+encodeURIComponent(v)+"; max-age="+(DAYS*86400)+"; path=/; SameSite=Lax"+(location.protocol==="https:"?"; Secure":"");window.localStorage.setItem(KEY,v);}catch(e){}}'
			. 'var loaded=false;function load(){if(loaded)return;loaded=true;var src=document.querySelector("script[data-id-consent=marketing]");if(!src)return;var html=src.textContent.replace(/<\\\\\\/script/gi,"</script");var frag=document.createRange().createContextualFragment(html);document.body.appendChild(frag);window.idMarketingConsent=true;document.dispatchEvent(new CustomEvent("id:consent",{detail:{marketing:true}}));}'
			. 'var box=document.getElementById("id-consent");function show(){if(box)box.hidden=false;}function hide(){if(box)box.hidden=true;}'
			. 'window.idConsentOpen=function(){show();};window.idConsentReset=function(){write("");show();};'
			. 'if(box){box.querySelector("[data-id-consent-accept]").addEventListener("click",function(){write("marketing:1");hide();load();});'
			. 'box.querySelector("[data-id-consent-refuse]").addEventListener("click",function(){write("marketing:0");hide();});}'
			. 'var choice=read();if(choice==="marketing:1"){load();}else if(choice!=="marketing:0"){show();}'
			. 'document.querySelectorAll("[data-id-consent-open]").forEach(function(a){a.hidden=false;a.addEventListener("click",function(e){e.preventDefault();show();});});'
			. '})();</script>';

		return "\n<!-- site-tracking -->\n" . $html . "\n";
	}

	/** Entry points used by app/template/common.tpl: settings from storage, never throw. */
	public static function headHtml(): string {
		try {
			require_once __DIR__ . '/tracking-settings.php';

			return self::head(TrackingSettings::load());
		} catch (\Throwable $e) {
			error_log('site-tracking head: ' . $e->getMessage());

			return '';
		}
	}

	public static function bodyHtml(): string {
		try {
			require_once __DIR__ . '/tracking-settings.php';

			return self::body(TrackingSettings::load());
		} catch (\Throwable $e) {
			error_log('site-tracking body: ' . $e->getMessage());

			return '';
		}
	}
}
