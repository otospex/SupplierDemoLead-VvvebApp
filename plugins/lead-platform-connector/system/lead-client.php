<?php

namespace Vvveb\Plugins\LeadPlatformConnector\System;

if (! defined('V_VERSION')) {
	die('Invalid request!');
}

class LeadClient {

	public static function send(string $platformUrl, string $apiKey, array $payload, int $timeoutSec = 8): array {
		// The Platform URL field is the full endpoint (e.g. https://host/api/v1/leads).
		// We POST to it as-is; the admin form labels it accordingly.
		$url = rtrim($platformUrl, '/');
		$body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

		$ch = curl_init($url);
		curl_setopt_array($ch, [
			CURLOPT_POST           => true,
			CURLOPT_POSTFIELDS     => $body,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER     => [
				'Content-Type: application/json',
				'Accept: application/json',
				'X-Api-Key: ' . $apiKey,
				'User-Agent: Vvveb-LeadConnector/0.1',
			],
			CURLOPT_TIMEOUT        => $timeoutSec,
			CURLOPT_CONNECTTIMEOUT => 4,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_SSL_VERIFYHOST => 2,
		]);

		$raw    = curl_exec($ch);
		$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$err    = curl_error($ch);
		curl_close($ch);

		if ($raw === false) {
			return ['ok' => false, 'http' => 0, 'error' => $err ?: 'network', 'data' => null, 'raw' => ''];
		}

		$data = json_decode($raw, true);

		return [
			'ok'    => $status >= 200 && $status < 300,
			'http'  => $status,
			'data'  => is_array($data) ? $data : null,
			'raw'   => is_string($raw) ? mb_substr($raw, 0, 4000) : '',
			'error' => null,
		];
	}
}
