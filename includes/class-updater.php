<?php
/**
 * Private GitHub Releases updater for unoSignature.
 *
 * @package UnoSignature
 */

namespace UnoSignature;

if (!defined('ABSPATH')) {
	exit;
}

final class Updater {
	private const CACHE_KEY = 'unosignature_latest_release';

	public static function init(): void {
		add_filter('pre_set_site_transient_update_plugins', [self::class, 'check_for_update']);
		add_filter('plugins_api', [self::class, 'plugin_info'], 10, 3);
		add_filter('upgrader_pre_download', [self::class, 'download_private_asset'], 10, 4);
	}

	public static function check_for_update($transient) {
		if (!is_object($transient)) {
			return $transient;
		}

		$release = self::latest_release();
		$item = self::update_item($release);
		if (!$item) {
			return $transient;
		}

		if (!empty($release['version']) && version_compare((string) $release['version'], UNOSIGNATURE_VERSION, '>')) {
			$transient->response[UNOSIGNATURE_BASENAME] = $item;
		} else {
			$transient->no_update[UNOSIGNATURE_BASENAME] = $item;
		}

		return $transient;
	}

	public static function plugin_info($result, string $action, $args) {
		if ($action !== 'plugin_information' || empty($args->slug) || $args->slug !== 'unosignature') {
			return $result;
		}

		$release = self::latest_release();

		return (object) [
			'name'          => 'unoSignature',
			'slug'          => 'unosignature',
			'version'       => (string) ($release['version'] ?? UNOSIGNATURE_VERSION),
			'author'        => '<a href="https://unostar.dev/">unostar.dev</a>',
			'homepage'      => 'https://unostar.dev/',
			'download_link' => (string) ($release['asset_url'] ?? ''),
			'tested'        => get_bloginfo('version'),
			'requires'      => '6.0',
			'requires_php'  => '7.4',
			'icons'         => self::plugin_icons(),
			'sections'      => [
				'description' => self::plugin_description_html(),
				'changelog'   => Changelog::get_html() ?: wp_kses_post((string) ($release['body'] ?? '')),
			],
		];
	}

	private static function plugin_icons(): array {
		return [
			'svg' => UNOSIGNATURE_URL . 'assets/icon.svg',
			'1x'  => UNOSIGNATURE_URL . 'assets/icon-128x128.png',
			'2x'  => UNOSIGNATURE_URL . 'assets/icon-256x256.png',
		];
	}

	private static function update_item(?array $release): ?object {
		if (!$release || empty($release['version']) || empty($release['asset_url'])) {
			return null;
		}

		return (object) [
			'id'            => UNOSIGNATURE_BASENAME,
			'slug'          => 'unosignature',
			'plugin'        => UNOSIGNATURE_BASENAME,
			'new_version'   => (string) $release['version'],
			'url'           => (string) ($release['html_url'] ?? 'https://unostar.dev/'),
			'package'       => (string) $release['asset_url'],
			'tested'        => get_bloginfo('version'),
			'requires'      => '6.0',
			'requires_php'  => '7.4',
			'icons'         => self::plugin_icons(),
			'banners'       => [],
			'banners_rtl'   => [],
			'compatibility' => new \stdClass(),
		];
	}

	private static function plugin_description_html(): string {
		$settings_url = esc_url(admin_url('options-general.php?page=unosignature'));

		return wp_kses(
			'<p><strong>unoSignature</strong> is a WordPress integration layer for the <a href="https://firma.dev/">firma.dev</a> e-signature API.</p>'
			. '<p>Configure contract templates, signing fields, and agreement rules in <strong><a href="' . $settings_url . '">Settings → unoSignature</a></strong>.</p>',
			[
				'p'      => [],
				'strong' => [],
				'a'      => [
					'href' => true,
				],
			]
		);
	}

	public static function download_private_asset($reply, string $package, $upgrader, array $hook_extra = []) {
		unset($upgrader);

		if (strpos($package, 'https://api.github.com/repos/') !== 0 || strpos($package, '/releases/assets/') === false) {
			return $reply;
		}

		$plugin = $hook_extra['plugin'] ?? '';
		if ($plugin !== UNOSIGNATURE_BASENAME) {
			return $reply;
		}

		$token = (string) Config::get('github_token', '');
		if ($token === '') {
			return new \WP_Error('unosignature_missing_github_token', 'Missing GitHub token for private unoSignature update.');
		}

		$tmp_file = wp_tempnam('unosignature-update');
		if (!$tmp_file) {
			return new \WP_Error('unosignature_temp_file_failed', 'Could not create temporary update file.');
		}

		$response = wp_remote_get(
			$package,
			[
				'headers'  => self::github_headers($token, 'application/octet-stream'),
				'timeout'  => 60,
				'stream'   => true,
				'filename' => $tmp_file,
			]
		);

		if (is_wp_error($response)) {
			@unlink($tmp_file);
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code($response);
		if ($code >= 400 || !file_exists($tmp_file) || filesize($tmp_file) <= 0) {
			@unlink($tmp_file);
			return new \WP_Error('unosignature_download_failed', 'Could not download private unoSignature update asset.');
		}

		return $tmp_file;
	}

	private static function latest_release(): ?array {
		$cached = get_site_transient(self::CACHE_KEY);
		if (is_array($cached)) {
			return $cached;
		}

		$repo = trim((string) Config::get('github_repo', ''));
		$token = trim((string) Config::get('github_token', ''));
		if ($repo === '' || $token === '' || strpos($repo, '/') === false) {
			return null;
		}

		$response = wp_remote_get(
			'https://api.github.com/repos/' . self::repo_path($repo) . '/releases/latest',
			[
				'headers' => self::github_headers($token),
				'timeout' => 20,
			]
		);

		if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) >= 400) {
			return null;
		}

		$body = json_decode((string) wp_remote_retrieve_body($response), true);
		if (!is_array($body)) {
			return null;
		}

		$asset = self::release_asset($body);
		if (!$asset) {
			return null;
		}

		$release = [
			'version'   => ltrim((string) ($body['tag_name'] ?? ''), 'vV'),
			'html_url'  => (string) ($body['html_url'] ?? ''),
			'body'      => (string) ($body['body'] ?? ''),
			'asset_url' => (string) ($asset['url'] ?? ''),
		];

		set_site_transient(self::CACHE_KEY, $release, 15 * MINUTE_IN_SECONDS);

		return $release;
	}

	private static function release_asset(array $release): ?array {
		$asset_name = (string) Config::get('github_release_asset', 'unosignature.zip');
		$assets = $release['assets'] ?? [];

		if (!is_array($assets)) {
			return null;
		}

		foreach ($assets as $asset) {
			if (is_array($asset) && (string) ($asset['name'] ?? '') === $asset_name) {
				return $asset;
			}
		}

		return null;
	}

	private static function github_headers(string $token, string $accept = 'application/vnd.github+json'): array {
		return [
			'Accept'               => $accept,
			'Authorization'        => 'Bearer ' . $token,
			'X-GitHub-Api-Version' => '2022-11-28',
			'User-Agent'           => 'unoSignature/' . UNOSIGNATURE_VERSION,
		];
	}

	private static function repo_path(string $repo): string {
		$parts = array_map('rawurlencode', explode('/', $repo));

		return implode('/', $parts);
	}
}
