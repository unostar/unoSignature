<?php
/**
 * Changelog HTML for the WordPress plugin details modal.
 *
 * @package UnoSignature
 */

namespace UnoSignature;

if (!defined('ABSPATH')) {
	exit;
}

final class Changelog {
	private const GITHUB_REPO = 'unostar/unoSignature';
	private const REMOTE_CACHE_PREFIX = 'unosignature_remote_changelog_';

	public static function get_html(int $limit = 12): string {
		$markdown = self::read_local_markdown();
		if ($markdown === '') {
			return '';
		}

		return self::markdown_to_html($markdown, $limit);
	}

	/**
	 * Changelog for the update details modal.
	 *
	 * When an update is available, load CHANGELOG.md from the GitHub release tag
	 * so all versions since the installed copy are shown (not only /releases/latest body).
	 */
	public static function get_update_html(string $target_version, string $release_body, int $limit = 12): string {
		if ($target_version !== '' && version_compare($target_version, UNOSIGNATURE_VERSION, '>')) {
			$remote = self::fetch_remote_markdown($target_version);
			if ($remote !== '') {
				$html = self::markdown_to_html($remote, $limit);
				if ($html !== '') {
					return $html;
				}
			}
		}

		$html = '';
		$markdown = self::read_local_markdown();

		if (
			$target_version !== ''
			&& trim($release_body) !== ''
			&& version_compare($target_version, UNOSIGNATURE_VERSION, '>')
			&& !self::markdown_has_version($markdown, $target_version)
		) {
			$html .= self::body_to_html($target_version, $release_body);
		}

		if ($markdown !== '') {
			$html .= self::markdown_to_html($markdown, $limit, $target_version);
		} elseif ($html === '' && trim($release_body) !== '') {
			$html .= self::body_to_html($target_version, $release_body);
		}

		return $html;
	}

	private static function fetch_remote_markdown(string $version): string {
		$cache_key = self::REMOTE_CACHE_PREFIX . $version;
		$cached = get_site_transient($cache_key);
		if (is_string($cached)) {
			return $cached;
		}

		$tag = 'v' . ltrim($version, 'vV');
		$refs = [$tag, 'main'];

		foreach ($refs as $ref) {
			$url = sprintf(
				'https://raw.githubusercontent.com/%s/%s/CHANGELOG.md',
				self::GITHUB_REPO,
				rawurlencode($ref)
			);

			$response = wp_remote_get(
				$url,
				[
					'timeout' => 15,
					'headers' => [
						'User-Agent' => 'unoSignature/' . UNOSIGNATURE_VERSION,
					],
				]
			);

			if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
				continue;
			}

			$markdown = (string) wp_remote_retrieve_body($response);
			if ($markdown === '') {
				continue;
			}

			set_site_transient($cache_key, $markdown, 15 * MINUTE_IN_SECONDS);

			return $markdown;
		}

		return '';
	}

	private static function read_local_markdown(): string {
		$candidates = [
			UNOSIGNATURE_PATH . 'CHANGELOG.md',
			dirname(UNOSIGNATURE_PATH) . '/CHANGELOG.md',
		];

		foreach ($candidates as $path) {
			if (is_readable($path)) {
				$contents = file_get_contents($path);
				return is_string($contents) ? $contents : '';
			}
		}

		return '';
	}

	private static function markdown_has_version(string $markdown, string $version): bool {
		if ($markdown === '' || $version === '') {
			return false;
		}

		$pattern = '/(?m)^##\s+' . preg_quote($version, '/') . '\s*$/';

		return (bool) preg_match($pattern, $markdown);
	}

	private static function body_to_html(string $version, string $body): string {
		$items = self::bullet_items_from_text($body);
		if ($items === []) {
			return '';
		}

		return self::items_to_html($version, $items);
	}

	private static function markdown_to_html(string $markdown, int $limit, string $exclude_version = ''): string {
		if (!preg_match_all('/(?ms)^##\s+([^\n]+)\n(.*?)(?=^##\s+|\Z)/', $markdown, $matches, PREG_SET_ORDER)) {
			return '';
		}

		$html = '';
		$count = 0;

		foreach ($matches as $match) {
			if ($count >= $limit) {
				break;
			}

			$version = trim($match[1]);
			if ($exclude_version !== '' && $version === $exclude_version) {
				continue;
			}

			$items = self::bullet_items_from_text(trim($match[2]));
			if ($items === []) {
				continue;
			}

			$html .= self::items_to_html($version, $items);
			$count++;
		}

		return $html;
	}

	private static function bullet_items_from_text(string $body): array {
		$items = [];

		foreach (preg_split('/\R/u', $body) as $line) {
			$line = trim($line);
			if ($line === '' || !preg_match('/^-+\s+(.+)$/', $line, $item_match)) {
				continue;
			}

			$items[] = $item_match[1];
		}

		return $items;
	}

	private static function items_to_html(string $version, array $items): string {
		$html = '<h4>version ' . esc_html($version) . '</h4>';
		$html .= '<ul>';

		foreach ($items as $item) {
			$html .= '<li>' . esc_html($item) . '</li>';
		}

		$html .= '</ul>';

		return $html;
	}
}
