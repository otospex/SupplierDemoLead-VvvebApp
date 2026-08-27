<?php

namespace Vvveb\Plugins\LeadPlatformConnector\System;

if (! defined('V_VERSION')) {
	die('Invalid request!');
}

final class PrivacyAcknowledgement {

	public static function validate(array $fields): array {
		if (($fields['privacy_acknowledgement'] ?? null) !== '1') {
			return [
				'ok' => false,
				'message' => 'Veuillez lire et accepter la notice de confidentialité.',
				'fields' => $fields,
			];
		}

		unset($fields['privacy_acknowledgement']);

		return ['ok' => true, 'message' => null, 'fields' => $fields];
	}
}
