<?php
/**
 * Plugin Name: unoSignature
 * Plugin URI: https://unostar.dev/
 * Description: WordPress integration for the firma.dev e-signature API — configurable contracts, signing fields, and dynamic document content.
 * Version: 0.1.25
 * Author: unostar.dev
 * Author URI: https://unostar.dev/
 * Text Domain: unosignature
 * Requires Plugins: woocommerce
 * Update URI: https://github.com/unostar/unoSignature
 * Requires PHP: 7.4
 *
 * @package UnoSignature
 */

if (!defined('ABSPATH')) {
	exit;
}

define('UNOSIGNATURE_VERSION', '0.1.25');
define('UNOSIGNATURE_FILE', __FILE__);
define('UNOSIGNATURE_PATH', plugin_dir_path(__FILE__));
define('UNOSIGNATURE_URL', plugin_dir_url(__FILE__));
define('UNOSIGNATURE_BASENAME', plugin_basename(__FILE__));

require_once UNOSIGNATURE_PATH . 'includes/class-config.php';
require_once UNOSIGNATURE_PATH . 'includes/class-changelog.php';
require_once UNOSIGNATURE_PATH . 'includes/class-settings.php';
require_once UNOSIGNATURE_PATH . 'includes/class-updater.php';
require_once UNOSIGNATURE_PATH . 'includes/class-plugin.php';

add_action('plugins_loaded', ['UnoSignature\\Plugin', 'init']);
