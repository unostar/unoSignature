<?php
/**
 * Build Firma read-only textarea values for visa service agreements.
 *
 * @package UnoSignature
 */

namespace UnoSignature;

if (!defined('ABSPATH')) {
	exit;
}

final class VisaTextBuilder {
	/**
	 * @param array<string, mixed> $parties Parsed EPO parties from VisaEpoParser.
	 * @param array<string, mixed> $rule Matched signing agreement rule.
	 * @return array<int, array{template_field_id: string, read_only: bool, read_only_value: string}>
	 */
	public static function build_field_overrides(array $parties, array $rule): array {
		$field_ids = Config::get_visa_firma_fields($rule);
		$overrides = [];

		$additional = self::build_additional_applicants_text($parties);
		if ($additional !== '' && $field_ids['additional_applicants'] !== '') {
			$overrides[] = [
				'template_field_id' => $field_ids['additional_applicants'],
				'read_only'         => true,
				'read_only_value'   => $additional,
			];
		}

		$representative = self::build_representative_text($parties);
		if ($representative !== '' && $field_ids['representative'] !== '') {
			$overrides[] = [
				'template_field_id' => $field_ids['representative'],
				'read_only'         => true,
				'read_only_value'   => $representative,
			];
		}

		$sponsor = self::build_sponsor_text($parties);
		if ($sponsor !== '' && $field_ids['sponsor'] !== '') {
			$overrides[] = [
				'template_field_id' => $field_ids['sponsor'],
				'read_only'         => true,
				'read_only_value'   => $sponsor,
			];
		}

		return $overrides;
	}

	/**
	 * @param array<string, string> $contact
	 */
	public static function adult_has_contact(array $contact): bool {
		foreach ($contact as $value) {
			if (trim((string) $value) !== '') {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param array<string, string> $contact
	 */
	public static function child_has_contact(array $contact): bool {
		foreach ($contact as $value) {
			if (trim((string) $value) !== '') {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param array<string, mixed> $parties
	 */
	private static function build_additional_applicants_text(array $parties): string {
		$lines = [];

		if (self::adult_has_contact($parties['primary'] ?? [])) {
			$lines[] = 'Primary Applicant: ' . self::format_adult_contact_line($parties['primary']);
		}

		foreach ($parties['additional_applicants'] ?? [] as $contact) {
			if (!self::adult_has_contact($contact)) {
				continue;
			}

			$lines[] = 'Additional Applicant (18+): ' . self::format_adult_contact_line($contact);
		}

		foreach ($parties['minor_children'] ?? [] as $contact) {
			if (!self::child_has_contact($contact)) {
				continue;
			}

			$lines[] = 'Minor child: ' . self::format_minor_child_line($contact);
		}

		if ($lines === []) {
			return '';
		}

		return "Additional Applicant's:\n\n" . implode("\n", $lines);
	}

	/**
	 * @param array<string, mixed> $parties
	 */
	private static function build_representative_text(array $parties): string {
		$contact = $parties['representative'] ?? [];
		if (!self::adult_has_contact($contact)) {
			return '';
		}

		return "Annexure 5: \"Client(s) Representative\"\n\n"
			. "We hereby authorize the set out below person to represent our interests as Client(s) Representative in dealing with the Migration Agent(s) set out in Annexure 1. In consideration of their acting as our Client(s) Representative, We hereby indemnify Migration Agent(s) set out in Annexure 1 against any claims or demands made against her/him arising from any declarations that the Client(s) Representative makes on my/our behalf:\n\n"
			. self::format_adult_contact_line($contact);
	}

	/**
	 * @param array<string, mixed> $parties
	 */
	private static function build_sponsor_text(array $parties): string {
		$contact = $parties['sponsor'] ?? [];
		if (!self::adult_has_contact($contact)) {
			return '';
		}

		return "Applicant's Sponsor:\n\n" . self::format_adult_contact_line($contact);
	}

	/**
	 * @param array<string, string> $contact
	 */
	private static function format_adult_contact_line(array $contact): string {
		$parts = [];

		$name = trim((string) ($contact['first_name'] ?? '') . ' ' . (string) ($contact['last_name'] ?? ''));
		if ($name !== '') {
			$parts[] = $name;
		}

		foreach (['address', 'city_region'] as $field) {
			$value = trim((string) ($contact[$field] ?? ''));
			if ($value !== '') {
				$parts[] = $value;
			}
		}

		$postcode = trim((string) ($contact['postcode'] ?? ''));
		if ($postcode !== '') {
			$parts[] = $postcode;
		}

		foreach (['country', 'email', 'messenger'] as $field) {
			$value = trim((string) ($contact[$field] ?? ''));
			if ($value !== '') {
				$parts[] = $value;
			}
		}

		return implode(', ', $parts);
	}

	/**
	 * @param array<string, string> $contact
	 */
	private static function format_minor_child_line(array $contact): string {
		$name = trim((string) ($contact['first_name'] ?? '') . ' ' . (string) ($contact['last_name'] ?? ''));
		$birthdate = self::format_date((string) ($contact['birthdate'] ?? ''));

		if ($name === '' && $birthdate === '') {
			return '';
		}

		if ($birthdate === '') {
			return $name;
		}

		if ($name === '') {
			return $birthdate;
		}

		return $name . ', ' . $birthdate;
	}

	private static function format_date(string $value): string {
		$value = trim($value);
		if ($value === '') {
			return '';
		}

		$timestamp = strtotime($value);
		if ($timestamp === false) {
			return $value;
		}

		return gmdate('d-M-Y', $timestamp);
	}
}
