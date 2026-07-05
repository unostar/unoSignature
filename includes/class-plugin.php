<?php
/**
 * Main plugin bootstrap.
 *
 * @package UnoSignature
 */

namespace UnoSignature;

if (!defined('ABSPATH')) {
	exit;
}

final class Plugin {
	public static function init(): void {
		load_plugin_textdomain('unosignature', false, dirname(UNOSIGNATURE_BASENAME) . '/languages');

		Settings::init();
		Updater::init();
		Config::init();

		if (!class_exists('WooCommerce')) {
			add_action('admin_notices', [self::class, 'woocommerce_missing_notice']);
			return;
		}

		require_once UNOSIGNATURE_PATH . 'includes/class-visa-epo-parser.php';
		require_once UNOSIGNATURE_PATH . 'includes/class-visa-text-builder.php';
		require_once UNOSIGNATURE_PATH . 'includes/legacy-consultation.php';
	}

	public static function woocommerce_missing_notice(): void {
		if (!current_user_can('activate_plugins')) {
			return;
		}

		echo '<div class="notice notice-warning"><p>';
		echo esc_html__('unoSignature requires WooCommerce to be active.', 'unosignature');
		echo '</p></div>';
	}
}
