<?php
/**
 * Configuration access for unoSignature.
 *
 * @package UnoSignature
 */

namespace UnoSignature;

if (!defined('ABSPATH')) {
	exit;
}

final class Config {
	public const OPTION_KEY = 'unosignature_settings';

	private const CONSTANTS = [
		'firma_api_key'                 => 'FIRMA_API_KEY',
		'firma_test_api_key'            => 'FIRMA_TEST_API_KEY',
		'firma_webhook_secret'          => 'FIRMA_WEBHOOK_SECRET',
		'firma_owner_copy_email'        => 'FIRMA_OWNER_COPY_EMAIL',
		'firma_debug'                   => 'FIRMA_DEBUG',
		'github_repo'                   => 'UNOSIGNATURE_GITHUB_REPO',
		'github_token'                  => 'UNOSIGNATURE_GITHUB_TOKEN',
		'github_release_asset'          => 'UNOSIGNATURE_GITHUB_RELEASE_ASSET',
	];

	public static function get(string $key, $default = '') {
		if ($key === 'firma_use_test_key') {
			return self::use_test_api_key() ? '1' : '';
		}

		$constant = self::CONSTANTS[$key] ?? '';
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
	 * Agreement rules: which products/categories require signing and which Firma template to use.
	 * First matching row wins; order is significant.
	 */
	public static function get_template_map(): array {
		$map = self::get_option_value('template_map');
		if (is_array($map) && !empty($map)) {
			return self::normalize_template_map($map);
		}

		return self::legacy_default_template_map();
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
				'categories'      => array_values(array_filter(array_map('sanitize_title', (array) ($entry['categories'] ?? [])))),
				'product_ids'     => array_values(array_unique(array_filter(array_map('absint', (array) ($entry['product_ids'] ?? []))))),
				'excluded_ids'    => array_values(array_unique(array_filter(array_map('absint', (array) ($entry['excluded_ids'] ?? []))))),
				'agreement_group' => sanitize_key((string) ($entry['agreement_group'] ?? '')),
				'template_id'     => $template_id,
			];
		}

		return $normalized;
	}

	private static function legacy_default_template_map(): array {
		$en_template = (string) self::get_option_value('paid_consultation_en');
		$ru_template = (string) self::get_option_value('paid_consultation_ru_en');
		$map = [];

		if ($en_template !== '') {
			$map[] = [
				'categories'      => [],
				'product_ids'     => [20203, 20047],
				'excluded_ids'    => [],
				'agreement_group' => 'paid_consultation',
				'template_id'     => $en_template,
			];
		}

		if ($ru_template !== '') {
			$map[] = [
				'categories'      => [],
				'product_ids'     => [19741, 20202],
				'excluded_ids'    => [],
				'agreement_group' => 'paid_consultation',
				'template_id'     => $ru_template,
			];
		}

		return $map;
	}

	public static function maybe_define_legacy_constants(): void {
		foreach (self::CONSTANTS as $key => $constant) {
			if (strpos($constant, 'FIRMA_') !== 0 && strpos($constant, 'PAID_') !== 0) {
				continue;
			}

			if (defined($constant)) {
				continue;
			}

			$value = self::get_option_value($key);
			if ($value === '') {
				continue;
			}

			define($constant, $value);
		}
	}

	private static function get_option_value(string $key) {
		$options = get_option(self::OPTION_KEY, []);

		if (!is_array($options) || !array_key_exists($key, $options)) {
			return '';
		}

		return $options[$key];
	}
}
