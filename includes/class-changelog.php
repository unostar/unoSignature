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

	private static function markdown_to_html(string $markdown, int $limit): string {
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
			$body = trim($match[2]);
			$items = [];

			foreach (preg_split('/\R/u', $body) as $line) {
				$line = trim($line);
				if ($line === '' || !preg_match('/^-+\s+(.+)$/', $line, $item_match)) {
					continue;
				}

				$items[] = $item_match[1];
			}

			if ($items === []) {
				continue;
			}

			$html .= '<h4>version ' . esc_html($version) . '</h4>';
			$html .= '<ul>';

			foreach ($items as $item) {
				$html .= '<li>' . esc_html($item) . '</li>';
			}

			$html .= '</ul>';
			$count++;
		}

		return $html;
	}
}
