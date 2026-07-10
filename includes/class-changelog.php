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
	public static function get_html(int $limit = 12): string {
		$markdown = self::read_markdown();
		if ($markdown === '') {
			return '';
		}

		return self::markdown_to_html($markdown, $limit);
	}

	/**
	 * Changelog for the update details modal: prepend GitHub release notes for the
	 * offered version when the installed plugin does not have that section yet.
	 */
	public static function get_update_html(string $target_version, string $release_body, int $limit = 12): string {
		$markdown = self::read_markdown();
		$html = '';

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

	private static function read_markdown(): string {
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
