<?php

namespace Vvveb\Plugins\SolutionsDirectory\System;

if (! defined('V_VERSION')) {
	die('Invalid request!');
}

final class DraftMapper {
	private static function text($value): string {
		return trim(strip_tags((string) $value));
	}

	private static function slug(string $value): string {
		$value = preg_replace('/[^\pL\d]+/u', '-', $value);
		if (function_exists('iconv')) {
			$value = (string) iconv('utf-8', 'us-ascii//TRANSLIT', $value);
		}
		$value = strtolower(trim((string) preg_replace('/[^-\w]+/', '', $value), '-'));

		return (string) preg_replace('/-+/', '-', $value);
	}

	private static function list($value): array {
		if (is_string($value)) {
			$value = explode(',', $value);
		}
		if (! is_array($value)) {
			return [];
		}

		return array_values(array_unique(array_filter(array_map(function ($item) {
			$item = self::text($item);
			return preg_match('/^[a-z0-9-]+$/', $item) ? $item : '';
		}, $value))));
	}

	private static function paragraphs(string $text): string {
		$text = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

		return $text === '' ? '<p>Non communiqué.</p>' : '<p>' . nl2br($text, false) . '</p>';
	}

	public static function map(array $fields, array $config, int $submissionId): array {
		$name = self::text($fields['solution_name'] ?? '');
		$pitch = self::text($fields['pitch'] ?? '');
		if (function_exists('mb_substr')) {
			$pitch = mb_substr($pitch, 0, 160);
		} else {
			$pitch = substr($pitch, 0, 160);
		}
		$advantages = self::text($fields['advantages'] ?? '');
		$qualifications = self::text($fields['qualifications'] ?? '');
		$otherAlternative = self::text($fields['alternative_to_other'] ?? '');

		$body = '<h2>Ce que la solution propose</h2>' . self::paragraphs($advantages)
			. '<h2>Hébergement, juridiction et éléments de preuve</h2>'
			. self::paragraphs(self::text($fields['hosting_countries'] ?? ''))
			. '<h2>Qualifications déclarées</h2>' . self::paragraphs($qualifications)
			. '<h2>Tarification</h2>' . self::paragraphs(self::text($fields['pricing_model'] ?? ''))
			. '<h2>Limites et questions ouvertes</h2><p>À compléter pendant la revue éditoriale.</p>'
			. '<h2>Relation commerciale</h2><p>Aucune relation commerciale établie à ce stade.</p>'
			. '<h2>Alternatives</h2>' . self::paragraphs($otherAlternative);

		$allowedKinds = ['logiciel', 'hebergeur', 'integrateur'];
		$kind = self::text($fields['kind'] ?? '');
		if (! in_array($kind, $allowedKinds, true)) {
			$kind = 'logiciel';
		}
		$allowedPricing = ['public', 'sur-devis', 'gratuit', 'mixte', 'non-communique'];
		$pricing = self::text($fields['pricing_model'] ?? 'non-communique');
		if (! in_array($pricing, $allowedPricing, true)) {
			$pricing = 'non-communique';
		}

		return [
			'post' => [
				'admin_id'      => (int) ($config['admin_id'] ?? 0),
				'status'        => 'draft',
				'comment_status'=> 'closed',
				'type'          => $config['post_type'],
				'template'      => $config['post_template'],
			],
			'content' => [
				'language_id'     => (int) ($config['language_id'] ?? 0),
				'name'            => $name,
				'slug'            => self::slug($name),
				'excerpt'         => $pitch,
				'content'         => $body,
				'meta_description'=> $pitch,
			],
			'meta' => [
				'kind'                    => $kind,
				'website'                 => self::text($fields['website'] ?? ''),
				'hq_country'              => self::text($fields['hq_country'] ?? ''),
				'hosting_countries'       => self::text($fields['hosting_countries'] ?? '') ?: 'non-communique',
				'pricing_model'           => $pricing,
				'qualifications'          => $qualifications,
				'commercial_relationship' => 'aucune',
				'verification_status'     => 'declare',
				'reviewed_at'             => '',
				'reviewer'                => '',
				'submitted_by_email'      => self::text($fields['email'] ?? ''),
				'submission_id'           => (string) $submissionId,
			],
			'terms' => [
				$config['taxonomies']['categorie']     => self::list($fields['categories'] ?? $fields['categories[]'] ?? []),
				$config['taxonomies']['alternative_a'] => self::list($fields['alternative_to'] ?? $fields['alternative_to[]'] ?? []),
			],
			'site_id' => (int) ($config['site_id'] ?? 0),
		];
	}
}
