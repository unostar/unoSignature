<?php
/**
 * Parse TM EPO cart rows for visa contract contact blocks.
 *
 * Anchors on cssclass + repeater (see assets/visa/README.md).
 *
 * @package UnoSignature
 */

namespace UnoSignature;

if (!defined('ABSPATH')) {
	exit;
}

final class VisaEpoParser {
	private const ADULT_FIELDS = [
		'first_name',
		'last_name',
		'address',
		'city_region',
		'postcode',
		'country',
		'email',
		'messenger',
	];

	private const CHILD_FIELDS = [
		'first_name',
		'last_name',
		'birthdate',
	];

	/**
	 * @param array<int, array<string, mixed>> $epo_rows tmcartepo rows from cart item(s).
	 * @return array<string, mixed>
	 */
	public static function parse(array $epo_rows): array {
		$result = [
			'primary'               => self::empty_adult(),
			'representative'        => self::empty_adult(),
			'sponsor'               => self::empty_adult(),
			'additional_applicants' => [],
			'minor_children'        => [],
		];

		$aa_index = -1;

		foreach ($epo_rows as $row) {
			if (!is_array($row)) {
				continue;
			}

			$cssclass = sanitize_key((string) ($row['cssclass'] ?? ''));
			if ($cssclass === '') {
				continue;
			}

			$value = self::normalize_value((string) ($row['value'] ?? ''));
			$parsed = self::parse_cssclass($cssclass);
			if ($parsed === null) {
				continue;
			}

			$repeater = self::repeater_index($row);

			if ($parsed['type'] === 'primary') {
				self::set_adult_field($result['primary'], $parsed['field'], $value);
				continue;
			}

			if ($parsed['type'] === 'representative') {
				self::set_adult_field($result['representative'], $parsed['field'], $value);
				continue;
			}

			if ($parsed['type'] === 'sponsor') {
				self::set_adult_field($result['sponsor'], $parsed['field'], $value);
				continue;
			}

			if ($parsed['type'] === 'additional_applicant') {
				if ($repeater >= 0) {
					$index = $repeater;
				} else {
					if ($parsed['field'] === 'first_name') {
						$aa_index++;
					} elseif ($aa_index < 0) {
						$aa_index = 0;
					}
					$index = $aa_index;
				}

				if (!isset($result['additional_applicants'][$index])) {
					$result['additional_applicants'][$index] = self::empty_adult();
				}

				self::set_adult_field($result['additional_applicants'][$index], $parsed['field'], $value);
				continue;
			}

			if ($parsed['type'] === 'child') {
				$index = max(0, $repeater);
				if (!isset($result['minor_children'][$index])) {
					$result['minor_children'][$index] = self::empty_child();
				}

				self::set_child_field($result['minor_children'][$index], $parsed['field'], $value);
			}
		}

		$result['additional_applicants'] = array_values(array_filter(
			$result['additional_applicants'],
			[self::class, 'has_contact']
		));
		$result['minor_children'] = array_values(array_filter(
			$result['minor_children'],
			[self::class, 'has_contact']
		));

		return $result;
	}

	/**
	 * @param array<string, string> $contact
	 */
	private static function has_contact(array $contact): bool {
		foreach ($contact as $value) {
			if (trim((string) $value) !== '') {
				return true;
			}
		}

		return false;
	}

	/**
	 * @return array<string, string>
	 */
	private static function empty_adult(): array {
		$contact = [];
		foreach (self::ADULT_FIELDS as $field) {
			$contact[$field] = '';
		}

		return $contact;
	}

	/**
	 * @return array<string, string>
	 */
	private static function empty_child(): array {
		$contact = [];
		foreach (self::CHILD_FIELDS as $field) {
			$contact[$field] = '';
		}

		return $contact;
	}

	/**
	 * @param array<string, string> $contact
	 */
	private static function set_adult_field(array &$contact, string $field, string $value): void {
		if (!in_array($field, self::ADULT_FIELDS, true)) {
			return;
		}

		if ($contact[$field] !== '') {
			return;
		}

		$contact[$field] = $value;
	}

	/**
	 * @param array<string, string> $contact
	 */
	private static function set_child_field(array &$contact, string $field, string $value): void {
		if (!in_array($field, self::CHILD_FIELDS, true)) {
			return;
		}

		if ($contact[$field] !== '') {
			return;
		}

		$contact[$field] = $value;
	}

	/**
	 * @param array<string, mixed> $row
	 */
	private static function repeater_index(array $row): int {
		if (!array_key_exists('repeater', $row)) {
			return -1;
		}

		$repeater = $row['repeater'];
		if ($repeater === '' || $repeater === null) {
			return -1;
		}

		return (int) $repeater;
	}

	private static function normalize_value(string $value): string {
		return trim(wp_strip_all_tags($value));
	}

	/**
	 * @return array{type: string, field: string}|null
	 */
	private static function parse_cssclass(string $cssclass): ?array {
		if ($cssclass === 'firma_visa_type' || substr($cssclass, -6) === '_added') {
			return null;
		}
		if (preg_match('/^firma_additional_applicant_child_(.+)$/', $cssclass, $matches)) {
			return ['type' => 'child', 'field' => $matches[1]];
		}

		if (preg_match('/^firma_additional_applicant_(.+)$/', $cssclass, $matches)) {
			return ['type' => 'additional_applicant', 'field' => $matches[1]];
		}

		if (preg_match('/^firma_primary_(.+)$/', $cssclass, $matches)) {
			return ['type' => 'primary', 'field' => $matches[1]];
		}

		if (preg_match('/^firma_representative_(.+)$/', $cssclass, $matches)) {
			return ['type' => 'representative', 'field' => $matches[1]];
		}

		if (preg_match('/^firma_sponsor_(.+)$/', $cssclass, $matches)) {
			return ['type' => 'sponsor', 'field' => $matches[1]];
		}

		return null;
	}
}
