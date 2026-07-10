<?php
/**
 * Public GitHub Releases updater for unoSignature.
 *
 * @package UnoSignature
 */

namespace UnoSignature;

if (!defined('ABSPATH')) {
	exit;
}

final class Updater {
	private const CACHE_KEY = 'unosignature_latest_release';
	private const GITHUB_REPO = 'unostar/unoSignature';
	private const RELEASE_ASSET = 'unosignature.zip';

	public static function init(): void {
		add_filter('pre_set_site_transient_update_plugins', [self::class, 'check_for_update']);
		add_filter('plugins_api', [self::class, 'plugin_info'], 10, 3);
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
				'changelog'   => Changelog::get_update_html(
					(string) ($release['version'] ?? ''),
					(string) ($release['body'] ?? '')
				),
			],
		];
	}

	private static function plugin_icons(): array {
		return [
			'svg' => UNOSIGNATURE_URL . 'assets/icon.svg',
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
			'url'           => (string) ($release['html_url'] ?? 'https://github.com/' . self::GITHUB_REPO),
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

	private static function latest_release(): ?array {
		$cached = get_site_transient(self::CACHE_KEY);
		if (is_array($cached)) {
			return $cached;
		}

		$response = wp_remote_get(
			'https://api.github.com/repos/' . self::repo_path(self::GITHUB_REPO) . '/releases/latest',
			[
				'headers' => self::github_headers(),
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

		$download_url = (string) ($asset['browser_download_url'] ?? '');
		if ($download_url === '') {
			return null;
		}

		$release = [
			'version'   => ltrim((string) ($body['tag_name'] ?? ''), 'vV'),
			'html_url'  => (string) ($body['html_url'] ?? ''),
			'body'      => (string) ($body['body'] ?? ''),
			'asset_url' => $download_url,
		];

		set_site_transient(self::CACHE_KEY, $release, 15 * MINUTE_IN_SECONDS);

		return $release;
	}

	private static function release_asset(array $release): ?array {
		$assets = $release['assets'] ?? [];
		if (!is_array($assets)) {
			return null;
		}

		foreach ($assets as $asset) {
			if (is_array($asset) && (string) ($asset['name'] ?? '') === self::RELEASE_ASSET) {
				return $asset;
			}
		}

		return null;
	}

	private static function github_headers(string $accept = 'application/vnd.github+json'): array {
		return [
			'Accept'               => $accept,
			'X-GitHub-Api-Version' => '2022-11-28',
			'User-Agent'           => 'unoSignature/' . UNOSIGNATURE_VERSION,
		];
	}

	private static function repo_path(string $repo): string {
		$parts = array_map('rawurlencode', explode('/', $repo));

		return implode('/', $parts);
	}
}
