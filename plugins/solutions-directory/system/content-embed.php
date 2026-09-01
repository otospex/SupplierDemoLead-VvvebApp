<?php

namespace Vvveb\Plugins\SolutionsDirectory\System;

if (! defined('V_VERSION')) {
	die('Invalid request!');
}

/**
 * Renders directory blocks that live in stored page content.
 *
 * Vvveb only discovers `data-v-component-*` elements while it compiles a THEME
 * template file (`System\Component\Component::process()` walks that file's DOM),
 * so a component written into `post_content` — the way the guide pages embed the
 * directory, spec 2026-09-01 §7/§8 — is never instantiated and the block's
 * fallback markup is all a visitor ever sees. That fallback says "no published
 * solution matches", which would be a false claim on a site that publishes only
 * verifiable ones.
 *
 * This class closes that gap: the plugin runs it over the post component's
 * `content` result, before the page cache captures anything, so a cached page
 * carries the rendered block. Blocks vtpl did render carry `data-sd-rendered`
 * (set in the plugin's solutions.tpl) and are skipped here, never rendered
 * twice.
 */
final class ContentEmbed {
	/** Attribute that marks a directory block, with or without an instance id. */
	public const ATTRIBUTE = 'data-v-component-plugin-solutions-directory-solutions';

	/** Written by solutions.tpl onto every block vtpl already rendered. */
	public const RENDERED_ATTRIBUTE = 'data-sd-rendered';

	/**
	 * Replaces the children of every unrendered directory block with the HTML
	 * returned by $renderer, which receives the block's parsed options.
	 */
	public static function render(string $html, callable $renderer): string {
		if (strpos($html, self::ATTRIBUTE) === false) {
			return $html;
		}

		$offset = 0;

		while (($position = strpos($html, self::ATTRIBUTE, $offset)) !== false) {
			$start = strrpos(substr($html, 0, $position), '<');
			$tagEnd = strpos($html, '>', $position);

			if ($start === false || $tagEnd === false) {
				break;
			}

			$openTag = substr($html, $start, $tagEnd - $start + 1);

			if (! preg_match('/^<([a-zA-Z][a-zA-Z0-9]*)/', $openTag, $name)) {
				$offset = $tagEnd + 1;

				continue;
			}

			$tag = strtolower($name[1]);
			$closeAt = strpos($openTag, self::RENDERED_ATTRIBUTE) === false
				? self::closingTagPosition($html, $tag, $tagEnd + 1)
				: null;

			if ($closeAt === null) {
				$offset = $tagEnd + 1;

				continue;
			}

			$replacement = substr($openTag, 0, -1) . ' ' . self::RENDERED_ATTRIBUTE . '="1">'
				. (string) $renderer(self::options($openTag));
			$html = substr($html, 0, $start) . $replacement . substr($html, $closeAt);
			$offset = $start + strlen($replacement);
		}

		return $html;
	}

	/**
	 * Offset of the `</tag>` that closes the element opened before $from,
	 * or null when the markup is unbalanced (in which case nothing is touched).
	 */
	private static function closingTagPosition(string $html, string $tag, int $from): ?int {
		$depth = 1;
		$open = '<' . $tag;
		$close = '</' . $tag;

		while ($depth > 0) {
			$nextOpen = stripos($html, $open, $from);
			$nextClose = stripos($html, $close, $from);

			if ($nextClose === false) {
				return null;
			}

			if ($nextOpen !== false && $nextOpen < $nextClose) {
				// Only a real element opens a level: <div> and <divider> differ.
				$depth += self::isElementStart($html, $nextOpen + strlen($open)) ? 1 : 0;
				$from = $nextOpen + strlen($open);

				continue;
			}

			$depth--;
			$from = $nextClose + strlen($close);

			if ($depth === 0) {
				return $nextClose;
			}
		}

		return null;
	}

	private static function isElementStart(string $html, int $after): bool {
		$next = $html[$after] ?? '';

		return $next === '' || $next === '>' || $next === '/' || trim($next) === '';
	}

	/**
	 * Reads `data-v-*` options off the opening tag, applying the same JSON rule
	 * as `System\Component\Component::process()`: a value that starts a JSON
	 * literal, or carries a comma, is decoded — so a multi-term filter must be
	 * a JSON array and a bare "a,b,c" would decode to null.
	 */
	public static function options(string $openTag): array {
		preg_match_all(
			'/([a-zA-Z_:][-a-zA-Z0-9_:.]*)\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/',
			$openTag,
			$matches,
			PREG_SET_ORDER
		);

		$options = [];

		foreach ($matches as $match) {
			$name = strtolower($match[1]);

			if ($name === self::ATTRIBUTE || strncmp($name, 'data-v-', 7) !== 0) {
				continue;
			}

			$value = html_entity_decode($match[2] !== '' ? $match[2] : ($match[3] ?? ''), ENT_QUOTES);
			$key = substr($name, 7);

			if ((isset($value[0]) && ($value[0] === '{' || $value[0] === '[')) || strpos($value, ',') !== false) {
				$options[$key] = json_decode($value, true);
			} else {
				$options[$key] = $value;
			}
		}

		return $options;
	}
}
