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
		'paid_consultation_en'          => 'PAID_CONSULTATION_EN',
		'paid_consultation_ru_en'       => 'PAID_CONSULTATION_RU_EN',
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
