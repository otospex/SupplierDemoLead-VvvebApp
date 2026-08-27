<?php

namespace IndependantDigital\Publishing;

final class CacheInvalidator {
	private static function remove(string $path): void {
		if (is_dir($path) && ! is_link($path)) {
			foreach (scandir($path) ?: [] as $entry) {
				if ($entry === '.' || $entry === '..') continue;
				self::remove($path . DIRECTORY_SEPARATOR . $entry);
			}
			if (! @rmdir($path)) throw new \RuntimeException("Impossible de purger le cache: $path");
			return;
		}
		if ((is_file($path) || is_link($path)) && ! @unlink($path)) {
			throw new \RuntimeException("Impossible de purger le cache: $path");
		}
	}

	public static function clear(string $root): void {
		$patterns = [
			$root . '/public/page-cache/*',
			$root . '/public/assets-cache/*',
			$root . '/storage/compiled-templates/app_*',
			$root . '/storage/cache/app.site.*',
			$root . '/storage/cache/site.*',
			$root . '/storage/cache/routes.app',
		];
		foreach ($patterns as $pattern) {
			foreach (glob($pattern) ?: [] as $path) {
				self::remove($path);
			}
		}
	}
}
