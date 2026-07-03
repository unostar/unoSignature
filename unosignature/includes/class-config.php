<?php
/**
 * Configuration access for unoSignature.
 *
 * Settings are stored in wp_options under Config::OPTION_KEY.
 * wp-config.php constants override stored values when defined.
 *
 * @package UnoSignature
 */

namespace UnoSignature;

if (!defined('ABSPATH')) {
	exit;
}

final class Config {
	public const OPTION_KEY = 'unosignature_settings';

	/**
	 * Optional wp-config.php overrides. When defined, these take priority over Settings.
	 */
	private const WP_CONFIG_OVERRIDES = [
		'firma_api_key'            => 'FIRMA_API_KEY',
		'firma_test_api_key'       => 'FIRMA_TEST_API_KEY',
		'firma_webhook_secret'     => 'FIRMA_WEBHOOK_SECRET',
		'firma_owner_copy_email'   => 'FIRMA_OWNER_COPY_EMAIL',
		'firma_debug'              => 'FIRMA_DEBUG',
		'github_repo'              => 'UNOSIGNATURE_GITHUB_REPO',
		'github_token'             => 'UNOSIGNATURE_GITHUB_TOKEN',
		'github_release_asset'     => 'UNOSIGNATURE_GITHUB_RELEASE_ASSET',
		'visa_field_additional_applicants' => 'VISA_FIRMA_FIELD_ADDITIONAL_APPLICANTS',
		'visa_field_representative'        => 'VISA_FIRMA_FIELD_REPRESENTATIVE',
		'visa_field_sponsor'               => 'VISA_FIRMA_FIELD_SPONSOR',
	];

	public static function init(): void {
		self::maybe_migrate_options();
	}

	public static function get(string $key, $default = '') {
		if ($key === 'firma_use_test_key') {
			return self::use_test_api_key() ? '1' : '';
		}

		$constant = self::WP_CONFIG_OVERRIDES[$key] ?? '';
		if ($constant !== '' && defined($constant)) {
			return constant($constant);
		}

		$options = get_option(self::OPTION_KEY, []);
		if (is_array($options) && array_key_exists($key, $options) && $options[$key] !== '') {
			return $options[$key];
		}

		return $default;
	}

	public static function is_debug_enabled(): bool {
		return (bool) self::get('firma_debug', false);
	}

	public static function use_test_api_key(): bool {
		if (defined('FIRMA_USE_TEST_KEY')) {
			return (bool) constant('FIRMA_USE_TEST_KEY');
		}

		return self::get_option_value('firma_use_test_key') === '1';
	}

	public static function get_firma_api_key(): string {
		if (self::use_test_api_key()) {
			$test_key = (string) self::get('firma_test_api_key', '');
			if ($test_key !== '') {
				return $test_key;
			}
		}

		return (string) self::get('firma_api_key', '');
	}

	/**
	 * Agreement rules from Settings → unoSignature. First matching row wins.
	 */
	public static function get_template_map(): array {
		$map = self::get_option_value('template_map');
		if (!is_array($map) || empty($map)) {
			return [];
		}

		return self::normalize_template_map($map);
	}

	/**
	 * Firma template_field_id UUIDs for visa textarea overrides (data-only at SR create).
	 *
	 * @return array{additional_applicants: string, representative: string, sponsor: string}
	 */
	public static function get_visa_firma_fields(): array {
		return [
			'additional_applicants' => (string) self::get('visa_field_additional_applicants'),
			'representative'        => (string) self::get('visa_field_representative'),
			'sponsor'               => (string) self::get('visa_field_sponsor'),
		];
	}

	public static function is_visa_agreement_group(string $agreement_group): bool {
		return sanitize_key($agreement_group) === 'visa_services';
	}

	private static function normalize_template_map(array $map): array {
		$normalized = [];

		foreach ($map as $entry) {
			if (!is_array($entry)) {
				continue;
			}

			$template_id = sanitize_text_field((string) ($entry['template_id'] ?? ''));
			if ($template_id === '') {
				continue;
			}

			$normalized[] = [
				'admin_label'     => sanitize_text_field((string) ($entry['admin_label'] ?? '')),
				'categories'      => array_values(array_filter(array_map('sanitize_title', (array) ($entry['categories'] ?? [])))),
				'product_ids'     => array_values(array_unique(array_filter(array_map('absint', (array) ($entry['product_ids'] ?? []))))),
				'excluded_ids'    => array_values(array_unique(array_filter(array_map('absint', (array) ($entry['excluded_ids'] ?? []))))),
				'agreement_group' => sanitize_key((string) ($entry['agreement_group'] ?? '')),
				'template_id'     => $template_id,
			];
		}

		return $normalized;
	}

	/**
	 * One-time migration from removed flat template ID settings to template_map rows.
	 */
	private static function maybe_migrate_options(): void {
		$options = get_option(self::OPTION_KEY, []);
		if (!is_array($options)) {
			return;
		}

		$has_legacy_templates = !empty($options['paid_consultation_en']) || !empty($options['paid_consultation_ru_en']);
		if (!$has_legacy_templates) {
			return;
		}

		if (empty($options['template_map'])) {
			$map = [];

			foreach (['paid_consultation_en', 'paid_consultation_ru_en'] as $legacy_key) {
				$template_id = sanitize_text_field((string) ($options[$legacy_key] ?? ''));
				if ($template_id === '') {
					continue;
				}

				$map[] = [
					'categories'      => [],
					'product_ids'     => [],
					'excluded_ids'    => [],
					'agreement_group' => '',
					'template_id'     => $template_id,
				];
			}

			if (!empty($map)) {
				$options['template_map'] = $map;
			}
		}

		unset($options['paid_consultation_en'], $options['paid_consultation_ru_en']);
		update_option(self::OPTION_KEY, $options);
	}

	private static function get_option_value(string $key) {
		$options = get_option(self::OPTION_KEY, []);

		if (!is_array($options) || !array_key_exists($key, $options)) {
			return '';
		}

		return $options[$key];
	}
}
