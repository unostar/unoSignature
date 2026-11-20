<?php
/**
 * Public visa-agreement page for clients who do not come through checkout.
 *
 * The page is a normal WordPress page. The form is plain HTML. The primary
 * applicant is the Firma signer. Other party blocks become the same textarea
 * overrides as checkout. No product and no order.
 *
 * @package UnoSignature
 */

namespace UnoSignature;

if (!defined('ABSPATH')) {
	exit;
}

final class WalkinAgreement {
	private const PAGE_SLUGS = [
		'sign-visa-services-agreement',
		'sign-visa-services-agreement-en',
	];

	private const TITLES = ['Mr.', 'Mrs.', 'Ms.', 'Miss', 'Mx.', 'Dr.'];

	/**
	 * Address and phone match snippet 194 (EPO latinaddress and phonenumber).
	 * A phone also needs 7 to 15 digits. Names match the checkout Latin check.
	 * Postcode stays Latin: the EPO unicode rule also allows Cyrillic.
	 *
	 * @var array<string, string>
	 */
	private const FORMAT_RULES = [
		'name'     => '^[A-Za-z\'\\.\\-\\s,/]+$',
		'address'  => '^[A-Za-z0-9\\s.,\\-/\'#]+$',
		'postcode' => '^[A-Za-z0-9][A-Za-z0-9 \\-]*$',
		'phone'    => '^\\+?[0-9()\\s-]+$',
	];

	/**
	 * @var array<string, string>
	 */
	private const FIELD_FORMAT = [
		'first_name'  => 'name',
		'last_name'   => 'name',
		'address'     => 'address',
		'city_region' => 'address',
		'country'     => 'name',
		'postcode'    => 'postcode',
		'messenger'   => 'phone',
	];

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

	public static function init(): void {
		add_filter('the_content', [self::class, 'append_form']);
		add_action('rest_api_init', [self::class, 'register_routes']);
	}

	public static function append_form(string $content): string {
		if (!is_singular('page') || !in_the_loop() || !is_main_query()) {
			return $content;
		}

		$post = get_post();
		if (!$post instanceof \WP_Post || !self::is_agreement_page($post)) {
			return $content;
		}

		$content = str_replace('[unosignature_visa_agreement]', '', $content);
		return $content . self::render_form();
	}

	public static function register_routes(): void {
		register_rest_route('firma/v1', '/walkin-signing', [
			'methods'             => 'POST',
			'callback'            => [self::class, 'handle_request'],
			'permission_callback' => '__return_true',
		]);
	}

	public static function handle_request(\WP_REST_Request $request): \WP_REST_Response {
		$posted = wp_unslash($_POST);
		if (!is_array($posted)) {
			$posted = [];
		}

		$token = (string) ($posted['unosignature_walkin_token'] ?? $request->get_param('unosignature_walkin_token'));
		if (!self::token_is_valid($token)) {
			return self::error_response(self::text('expired'), 403);
		}

		$parties = self::parties_from_post($posted);
		$primary = is_array($parties['primary'] ?? null) ? $parties['primary'] : [];
		$signer = self::signer_from_primary($primary);
		$parties_error = self::included_sections_error($posted);
		if (is_wp_error($signer) || $parties_error instanceof \WP_Error) {
			$fields = [];
			$message = '';
			if (is_wp_error($signer)) {
				$data = $signer->get_error_data();
				$fields = is_array($data) && is_array($data['fields'] ?? null) ? $data['fields'] : [];
				$message = $signer->get_error_message();
			}
			if ($parties_error instanceof \WP_Error) {
				$data = $parties_error->get_error_data();
				$more = is_array($data) && is_array($data['fields'] ?? null) ? $data['fields'] : [];
				$fields = array_values(array_merge($fields, $more));
				if ($message === '') {
					$message = $parties_error->get_error_message();
				}
			}
			return self::error_response($message, 400, $fields);
		}

		$rule = self::visa_rule();
		if ($rule === null) {
			return self::error_response(self::text('not_configured'), 500);
		}

		$api_key = Config::get_firma_api_key();
		if ($api_key === '') {
			return self::error_response(self::text('unavailable'), 500);
		}

		$template_id = (string) $rule['template_id'];
		$email = (string) $signer['billing_email'];
		$fingerprint = uno_signing_context_fingerprint($email, $template_id, 'visa_services', $signer, [], $parties);
		$active = uno_get_active_request($email, $template_id);

		if (is_array($active)) {
			$active_id = (string) ($active['request_id'] ?? '');
			$active_fp = (string) ($active['fingerprint'] ?? '');
			$same_attempt = $active_fp !== '' && hash_equals($active_fp, $fingerprint);

			if (!$same_attempt && $active_id !== '' && uno_signing_request_in_declined_cooloff($api_key, $active_id)) {
				return self::error_response(self::text('declined'), 409);
			}

			if ($same_attempt && $active_id !== '' && !empty($active['sign_url'])) {
				return new \WP_REST_Response([
					'sign_url'   => (string) $active['sign_url'],
					'request_id' => $active_id,
				], 200);
			}

			if (!$same_attempt && $active_id !== '') {
				uno_cancel_signing_request($api_key, $active_id, 'Walk-in agreement details changed; creating a new agreement.');
				uno_delete_active_request($email, $template_id);
			}
		}

		$lock_key = 'firma_walkin_lock_' . md5(strtolower($email) . '|' . $template_id);
		if (get_transient($lock_key)) {
			return self::error_response(self::text('wait'), 429);
		}
		set_transient($lock_key, 1, 20);

		$payload = uno_build_firma_create_payload($rule, 'visa_services', $signer, [], $parties);
		$created = self::create_and_send($api_key, $payload);
		delete_transient($lock_key);

		if (is_wp_error($created)) {
			uno_debug([
				'scope' => 'walkin',
				'step'  => 'create_failed',
				'error' => $created->get_error_code(),
			], true);
			return self::error_response(self::text('error'), 502);
		}

		uno_set_active_request(
			$email,
			$template_id,
			$created['request_id'],
			$created['sign_url'],
			$fingerprint
		);

		return new \WP_REST_Response([
			'sign_url'   => $created['sign_url'],
			'request_id' => $created['request_id'],
		], 200);
	}

	private static function render_form(): string {
		$labels = self::labels();
		ob_start();
		echo '<form id="unosignature-walkin" class="unosignature-walkin" method="post" action="" novalidate>';
		echo '<input type="hidden" name="unosignature_walkin_token" value="' . esc_attr(self::form_token()) . '">';

		echo '<div class="unosignature-walkin-board">';
		echo '<div class="unosignature-walkin-col" data-col="left">';
		echo '<fieldset class="unosignature-walkin-party unosignature-walkin-primary">';
		echo '<legend>' . esc_html($labels['primary']) . '</legend>';
		self::title_fields('primary[title]', $labels['title'], true);
		self::adult_fields('primary', true);
		self::input_field('primary[birthdate]', $labels['birthdate'], 'date', true);
		echo '</fieldset>';
		echo '</div>';
		echo '<div class="unosignature-walkin-col" data-col="right">';
		echo '<fieldset class="unosignature-walkin-party unosignature-walkin-others">';
		echo '<legend>' . esc_html($labels['others']) . '</legend>';
		foreach (self::optional_sections() as $key) {
			echo '<label class="unosignature-walkin-toggle"><input type="checkbox" name="include[' . esc_attr($key) . ']" value="1" data-unosignature-toggle="' . esc_attr($key) . '"> ' . esc_html($labels[$key]) . '</label>';
		}
		echo '</fieldset>';
		echo '</div>';
		echo '</div>';
		echo '<div class="unosignature-walkin-tiles">';

		foreach (['spouse', 'representative', 'sponsor'] as $key) {
			echo '<fieldset class="unosignature-walkin-party" data-unosignature-section="' . esc_attr($key) . '" hidden disabled>';
			echo '<legend>' . esc_html($labels[$key]) . '</legend>';
			self::adult_fields($key, false);
			echo '</fieldset>';
		}

		self::repeater('additional', $labels['adult'], $labels['add_adult'], $labels['remove'], false);
		self::repeater('children', $labels['child'], $labels['add_child'], $labels['remove'], true);
		echo '</div>';

		echo '<p id="unosignature-walkin-status" role="status"></p>';
		echo '<p class="unosignature-walkin-actions">';
		echo '<button type="submit" id="unosignature-walkin-submit">' . esc_html($labels['submit']) . '</button>';
		echo '</p>';
		echo '</form>';
		self::print_script($labels);
		return (string) ob_get_clean();
	}

	/**
	 * @param array<string, mixed> $posted
	 * @return array<string, mixed>
	 */
	private static function parties_from_post(array $posted): array {
		$primary = self::adult_from_input($posted['primary'] ?? []);
		$primary_input = is_array($posted['primary'] ?? null) ? $posted['primary'] : [];
		$title = sanitize_text_field((string) ($primary_input['title'] ?? ''));
		$primary['title'] = in_array($title, self::TITLES, true) ? $title : '';
		$primary['birthdate'] = sanitize_text_field((string) ($primary_input['birthdate'] ?? ''));

		$additional = [];
		if (is_array($posted['additional'] ?? null)) {
			foreach ($posted['additional'] as $row) {
				$contact = self::adult_from_input($row);
				if (self::has_value($contact)) {
					$additional[] = $contact;
				}
			}
		}

		$children = [];
		if (is_array($posted['children'] ?? null)) {
			foreach ($posted['children'] as $row) {
				$row = is_array($row) ? $row : [];
				$contact = [
					'first_name' => sanitize_text_field((string) ($row['first_name'] ?? '')),
					'last_name'  => sanitize_text_field((string) ($row['last_name'] ?? '')),
					'birthdate'  => sanitize_text_field((string) ($row['birthdate'] ?? '')),
				];
				if (self::has_value($contact)) {
					$children[] = $contact;
				}
			}
		}

		return [
			'primary'               => $primary,
			'spouse'                => self::adult_from_input($posted['spouse'] ?? []),
			'representative'        => self::adult_from_input($posted['representative'] ?? []),
			'sponsor'               => self::adult_from_input($posted['sponsor'] ?? []),
			'additional_applicants' => $additional,
			'minor_children'        => $children,
		];
	}

	/**
	 * @param mixed $input
	 * @return array<string, string>
	 */
	private static function adult_from_input($input): array {
		$input = is_array($input) ? $input : [];
		$contact = [];
		foreach (self::ADULT_FIELDS as $field) {
			$contact[$field] = sanitize_text_field((string) ($input[$field] ?? ''));
		}

		return $contact;
	}

	/**
	 * @param array<string, string> $contact
	 */
	private static function has_value(array $contact): bool {
		foreach ($contact as $value) {
			if (trim($value) !== '') {
				return true;
			}
		}

		return false;
	}

	/**
	 * A checked party block is part of the agreement, so every field in it is required.
	 *
	 * @param array<string, mixed> $posted
	 */
	private static function included_sections_error(array $posted): ?\WP_Error {
		$include = is_array($posted['include'] ?? null) ? $posted['include'] : [];
		$empty = [];
		$format = [];
		$bad_email = [];
		$bad_date = [];

		foreach (['spouse', 'representative', 'sponsor'] as $key) {
			if (empty($include[$key])) {
				continue;
			}
			$input = is_array($posted[$key] ?? null) ? $posted[$key] : [];
			self::collect_adult_errors($input, $key, $empty, $format, $bad_email);
		}

		if (!empty($include['adult'])) {
			$rows = is_array($posted['additional'] ?? null) ? $posted['additional'] : [];
			if ($rows === []) {
				$empty[] = 'include[adult]';
			}
			foreach ($rows as $index => $row) {
				self::collect_adult_errors(is_array($row) ? $row : [], 'additional[' . $index . ']', $empty, $format, $bad_email);
			}
		}

		if (!empty($include['child'])) {
			$rows = is_array($posted['children'] ?? null) ? $posted['children'] : [];
			if ($rows === []) {
				$empty[] = 'include[child]';
			}
			foreach ($rows as $index => $row) {
				$row = is_array($row) ? $row : [];
				foreach (['first_name', 'last_name', 'birthdate'] as $field) {
					$value = trim((string) ($row[$field] ?? ''));
					$name = 'children[' . $index . '][' . $field . ']';
					if ($value === '') {
						$empty[] = $name;
						continue;
					}
					if ($field === 'birthdate' && !preg_match('/^\d{2}-[A-Za-z]{3}-\d{4}$/', VisaTextBuilder::format_contract_date($value))) {
						$bad_date[] = $name;
						continue;
					}
					if (($field === 'first_name' || $field === 'last_name') && !self::matches_format('name', $value)) {
						$format[] = $name;
					}
				}
			}
		}

		if ($empty !== []) {
			return new \WP_Error('walkin_parties', self::text('parties_incomplete'), ['fields' => array_values(array_merge($empty, $format, $bad_email, $bad_date))]);
		}
		if ($format !== []) {
			return new \WP_Error('walkin_format', self::text('format'), ['fields' => array_values(array_merge($format, $bad_email, $bad_date))]);
		}
		if ($bad_date !== []) {
			return new \WP_Error('walkin_birthdate', self::text('date_invalid'), ['fields' => array_values(array_merge($bad_date, $bad_email))]);
		}
		if ($bad_email !== []) {
			return new \WP_Error('walkin_email', self::text('email_invalid'), ['fields' => $bad_email]);
		}

		return null;
	}

	/**
	 * @param array<string, mixed> $input
	 * @param array<int, string> $empty
	 * @param array<int, string> $format
	 * @param array<int, string> $bad_email
	 */
	private static function collect_adult_errors(array $input, string $prefix, array &$empty, array &$format, array &$bad_email): void {
		foreach (self::ADULT_FIELDS as $field) {
			$value = trim((string) ($input[$field] ?? ''));
			$name = $prefix . '[' . $field . ']';
			if ($value === '') {
				$empty[] = $name;
				continue;
			}
			if ($field === 'email') {
				if (!is_email($value)) {
					$bad_email[] = $name;
				}
				continue;
			}
			$kind = self::FIELD_FORMAT[$field] ?? '';
			if ($kind !== '' && !self::matches_format($kind, $value)) {
				$format[] = $name;
			}
		}
	}

	/**
	 * @param array<string, mixed> $input
	 * @return array<int, string>
	 */
	private static function invalid_format_names(array $input, string $prefix): array {
		$names = [];
		foreach (self::FIELD_FORMAT as $field => $kind) {
			$value = trim((string) ($input[$field] ?? ''));
			if ($value === '' || self::matches_format($kind, $value)) {
				continue;
			}
			$names[] = $prefix . '[' . $field . ']';
		}

		return $names;
	}

	private static function matches_format(string $kind, string $value): bool {
		$pattern = self::FORMAT_RULES[$kind] ?? '';
		if ($pattern === '') {
			return true;
		}

		if (preg_match('~' . $pattern . '~', $value) !== 1) {
			return false;
		}
		if ($kind !== 'phone') {
			return true;
		}

		$digits = preg_replace('/\D/', '', $value);

		return strlen((string) $digits) >= 7 && strlen((string) $digits) <= 15;
	}

	/**
	 * @param array<string, string> $primary
	 * @return array<string, string>|\WP_Error
	 */
	private static function signer_from_primary(array $primary) {
		$first = trim((string) ($primary['first_name'] ?? ''));
		$last = trim((string) ($primary['last_name'] ?? ''));
		$title = trim((string) ($primary['title'] ?? ''));
		$email = trim((string) ($primary['email'] ?? ''));
		$phone = trim((string) ($primary['messenger'] ?? ''));
		$address = trim((string) ($primary['address'] ?? ''));
		$city = trim((string) ($primary['city_region'] ?? ''));
		$postcode = trim((string) ($primary['postcode'] ?? ''));
		$country = trim((string) ($primary['country'] ?? ''));
		$birthdate = VisaTextBuilder::format_contract_date(trim((string) ($primary['birthdate'] ?? '')));

		$missing = [];
		foreach ([
			'title'       => 'primary[title]',
			'first_name'  => 'primary[first_name]',
			'last_name'   => 'primary[last_name]',
			'address'     => 'primary[address]',
			'city_region' => 'primary[city_region]',
			'postcode'    => 'primary[postcode]',
			'country'     => 'primary[country]',
			'email'       => 'primary[email]',
			'messenger'   => 'primary[messenger]',
			'birthdate'   => 'primary[birthdate]',
		] as $key => $name) {
			if (trim((string) ($primary[$key] ?? '')) === '') {
				$missing[] = $name;
			}
		}
		$format = self::invalid_format_names($primary, 'primary');
		$email_fields = ($email !== '' && !is_email($email)) ? ['primary[email]'] : [];
		$date_fields = (trim((string) ($primary['birthdate'] ?? '')) !== '' && !preg_match('/^\d{2}-[A-Za-z]{3}-\d{4}$/', $birthdate)) ? ['primary[birthdate]'] : [];
		if ($missing !== []) {
			return new \WP_Error('walkin_incomplete', self::text('incomplete'), ['fields' => array_values(array_merge($missing, $format, $email_fields, $date_fields))]);
		}
		if ($format !== []) {
			return new \WP_Error('walkin_format', self::text('format'), ['fields' => array_values(array_merge($format, $email_fields, $date_fields))]);
		}
		if ($date_fields !== []) {
			return new \WP_Error('walkin_birthdate', self::text('birthdate_invalid'), ['fields' => array_values(array_merge($date_fields, $email_fields))]);
		}
		if ($email_fields !== []) {
			return new \WP_Error('walkin_email', self::text('email_invalid'), ['fields' => $email_fields]);
		}

		return [
			'billing_first_name' => $first,
			'billing_last_name'  => $last,
			'billing_title'      => $title,
			'billing_birthdate'  => $birthdate,
			'billing_email'      => $email,
			'billing_phone'      => $phone,
			'billing_address_1'  => $address,
			'billing_city'       => $city,
			'billing_state'      => '',
			'billing_postcode'   => $postcode,
			'billing_country'    => $country,
		];
	}

	private static function title_fields(string $name, string $label, bool $required): void {
		echo '<label>' . esc_html($label) . ($required ? ' *' : '');
		echo '<select name="' . esc_attr($name) . '"' . ($required ? ' required' : '') . '>';
		echo '<option value=""></option>';
		foreach (self::TITLES as $title) {
			echo '<option value="' . esc_attr($title) . '">' . esc_html($title) . '</option>';
		}
		echo '</select>';
		echo '</label>';
	}

	private static function adult_fields(string $prefix, bool $required): void {
		$labels = self::labels();
		$types = [
			'first_name'  => 'text',
			'last_name'   => 'text',
			'address'     => 'text',
			'city_region' => 'text',
			'postcode'    => 'text',
			'country'     => 'text',
			'email'       => 'email',
			'messenger'   => 'tel',
		];
		foreach (self::ADULT_FIELDS as $field) {
			self::input_field($prefix . '[' . $field . ']', $labels[$field], $types[$field], $required, $field === 'first_name' || $field === 'last_name' ? $labels['passport'] : '');
		}
	}

	private static function input_field(string $name, string $label, string $type, bool $required, string $placeholder = ''): void {
		$autocomplete = [
			'first_name' => 'given-name',
			'last_name'  => 'family-name',
			'address'    => 'street-address',
			'email'      => 'email',
			'messenger'  => 'tel',
			'birthdate'  => 'bday',
		];
		$field = '';
		if (preg_match('/\[([a-z_]+)\]$/', $name, $match)) {
			$field = $match[1];
		}
		echo '<label>' . esc_html($label) . ($required ? ' *' : '');
		echo '<input type="' . esc_attr($type) . '" name="' . esc_attr($name) . '"';
		if ($placeholder !== '') {
			echo ' placeholder="' . esc_attr($placeholder) . '"';
		}
		if (isset($autocomplete[$field])) {
			echo ' autocomplete="' . esc_attr($autocomplete[$field]) . '"';
		}
		if ($required) {
			echo ' required';
		}
		echo '>';
		echo '</label>';
	}

	private static function repeater(string $name, string $legend, string $add_label, string $remove_label, bool $child): void {
		$labels = self::labels();
		echo '<fieldset class="unosignature-walkin-party" data-unosignature-section="' . esc_attr($name === 'additional' ? 'adult' : 'child') . '" hidden disabled>';
		echo '<legend>' . esc_html($legend) . '</legend>';
		echo '<div class="unosignature-walkin-rows" data-unosignature-rows="' . esc_attr($name) . '">';
		echo '<template>';
		echo '<div class="unosignature-walkin-row">';
		if ($child) {
			self::input_field($name . '[__i__][first_name]', $labels['first_name'], 'text', false, $labels['passport']);
			self::input_field($name . '[__i__][last_name]', $labels['last_name'], 'text', false, $labels['passport']);
			self::input_field($name . '[__i__][birthdate]', $labels['birthdate'], 'date', false);
		} else {
			self::adult_fields($name . '[__i__]', false);
		}
		echo '<button type="button" class="unosignature-walkin-remove">' . esc_html($remove_label) . '</button>';
		echo '</div>';
		echo '</template>';
		echo '<button type="button" class="unosignature-walkin-add">' . esc_html($add_label) . '</button>';
		echo '</div>';
		echo '</fieldset>';
	}

	/**
	 * @return array<int, string>
	 */
	private static function optional_sections(): array {
		return ['spouse', 'representative', 'sponsor', 'adult', 'child'];
	}

	private static function is_agreement_page(\WP_Post $post): bool {
		if (in_array($post->post_name, self::PAGE_SLUGS, true)) {
			return true;
		}

		return has_shortcode($post->post_content, 'unosignature_visa_agreement');
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private static function visa_rule(): ?array {
		foreach (Config::get_template_map() as $entry) {
			if (!is_array($entry)) {
				continue;
			}
			if (($entry['agreement_group'] ?? '') === 'visa_services' && !empty($entry['template_id'])) {
				return $entry;
			}
		}

		return null;
	}

	/**
	 * @param array<string, mixed> $payload
	 * @return array{request_id: string, sign_url: string}|\WP_Error
	 */
	private static function create_and_send(string $api_key, array $payload) {
		$response = wp_remote_post(
			'https://api.firma.dev/functions/v1/signing-request-api/signing-requests',
			[
				'headers' => [
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				],
				'body'    => wp_json_encode($payload),
				'timeout' => 30,
			]
		);

		if (is_wp_error($response)) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code($response);
		$body = json_decode(wp_remote_retrieve_body($response), true);
		$recipient_id = is_array($body) ? (string) ($body['recipients'][0]['id'] ?? '') : '';
		$request_id = is_array($body) ? (string) ($body['id'] ?? '') : '';

		if ($code >= 400 || $recipient_id === '' || $request_id === '') {
			return new \WP_Error('walkin_create', 'create_failed');
		}

		$sent = uno_send_signing_request($api_key, $request_id);
		if (empty($sent['ok'])) {
			return new \WP_Error('walkin_send', 'send_failed');
		}

		return [
			'request_id' => $request_id,
			'sign_url'   => 'https://app.firma.dev/signing/' . $recipient_id,
		];
	}

	private static function form_token(): string {
		return hash_hmac('sha256', 'unosignature_walkin', wp_salt('auth'));
	}

	private static function token_is_valid(string $token): bool {
		$expected = self::form_token();
		return $token !== '' && hash_equals($expected, $token);
	}

	/**
	 * @param array<int, string> $fields
	 */
	private static function error_response(string $message, int $status, array $fields = []): \WP_REST_Response {
		$body = ['message' => $message];
		if ($fields !== []) {
			$body['fields'] = array_values($fields);
		}

		return new \WP_REST_Response($body, $status);
	}

	private static function text(string $key): string {
		$labels = self::labels();
		return (string) ($labels[$key] ?? '');
	}

	/**
	 * @return array<string, string>
	 */
	private static function labels(): array {
		$language = apply_filters('wpml_current_language', null);
		$russian = $language === 'ru' || ($language === null && str_starts_with(determine_locale(), 'ru'));
		if ($russian) {
			return [
				'primary'            => 'Основной заявитель',
				'others'             => 'Другие стороны',
				'spouse'             => 'Супруг / партнер',
				'representative'     => 'Представитель',
				'sponsor'            => 'Спонсор',
				'adult'              => 'Дополнительный заявитель (18+)',
				'child'              => 'Ребенок до 18',
				'title'              => 'Обращение',
				'first_name'         => 'Имя',
				'last_name'          => 'Фамилия',
				'address'            => 'Адрес',
				'city_region'        => 'Город, область',
				'postcode'           => 'Индекс',
				'country'            => 'Страна',
				'email'              => 'Email',
				'messenger'          => 'WhatsApp/Telegram',
				'birthdate'          => 'Дата рождения',
				'passport'           => 'Как в паспорте, латинскими буквами',
				'add_adult'          => 'Добавить заявителя',
				'add_child'          => 'Добавить ребенка',
				'remove'             => 'Удалить',
				'submit'             => 'Подписать договор',
				'preparing'          => 'Готовим договор...',
				'signed'             => 'Договор подписан. Эту страницу можно закрыть.',
				'closed'             => 'Окно закрыто. Данные на месте, можно подписать ещё раз.',
				'expired'            => 'Форма устарела. Обновите страницу и попробуйте снова.',
				'incomplete'         => 'Заполните основного заявителя: обращение, имя, дату рождения, адрес, email и телефон.',
				'parties_incomplete' => 'Заполните все поля у отмеченных сторон.',
				'email_invalid'      => 'Введите корректный email.',
				'format'             => 'Имя, фамилия и страна — латинскими буквами. Адрес, город и индекс — латиницей и цифрами. Телефон — от 7 до 15 цифр.',
				'date_invalid'       => 'Введите дату рождения.',
				'birthdate_invalid'  => 'Введите дату рождения основного заявителя.',
				'not_configured'     => 'Договор не настроен.',
				'unavailable'        => 'Подписание временно недоступно.',
				'declined'           => 'Вы отклонили этот договор. Подписать снова можно через 48 часов.',
				'wait'               => 'Подождите немного и попробуйте снова.',
				'error'              => 'Не удалось подготовить договор. Попробуйте снова.',
			];
		}

		return [
			'primary'            => 'Primary applicant',
			'others'             => 'Other parties',
			'spouse'             => 'Spouse / Partner',
			'representative'     => 'Representative',
			'sponsor'            => 'Sponsor',
			'adult'              => 'Additional applicant (18+)',
			'child'              => 'Minor child',
			'title'              => 'Title',
			'first_name'         => 'First name',
			'last_name'          => 'Last name',
			'address'            => 'Address',
			'city_region'        => 'City, region',
			'postcode'           => 'Postal / ZIP code',
			'country'            => 'Country',
			'email'              => 'Email',
			'messenger'          => 'WhatsApp/Telegram',
			'birthdate'          => 'Date of birth',
			'passport'           => 'As in the passport, Latin letters',
			'add_adult'          => 'Add applicant',
			'add_child'          => 'Add child',
			'remove'             => 'Remove',
			'submit'             => 'Sign agreement',
			'preparing'          => 'Preparing the agreement...',
			'signed'             => 'The agreement is signed. You can close this page.',
			'closed'             => 'The window was closed. Your details are still here. You can sign again.',
			'expired'            => 'The form expired. Reload the page and try again.',
			'incomplete'         => 'Fill in the primary applicant, including title, name, date of birth, address, email and telephone.',
			'parties_incomplete' => 'Fill in every field for the parties you selected.',
			'email_invalid'      => 'Enter a valid email address.',
			'format'             => 'Names and country use Latin letters. Address, city and postcode use Latin letters and digits. Telephone is 7 to 15 digits.',
			'date_invalid'       => 'Enter a date of birth.',
			'birthdate_invalid'  => 'Enter the primary applicant date of birth.',
			'not_configured'     => 'The visa agreement is not configured.',
			'unavailable'        => 'Signing is temporarily unavailable.',
			'declined'           => 'You previously declined this agreement. If you change your mind, you can try signing again after 48 hours.',
			'wait'               => 'Please wait a moment and try again.',
			'error'              => 'There was an error preparing the agreement. Please try again.',
		];
	}

	/**
	 * @param array<string, string> $labels
	 */
	private static function print_script(array $labels): void {
		$settings = [
			'endpoint'  => rest_url('firma/v1/walkin-signing'),
			'preparing'     => $labels['preparing'],
			'signed'        => $labels['signed'],
			'closed'        => $labels['closed'],
			'declined'      => $labels['declined'],
			'incomplete'    => $labels['incomplete'],
			'parties_incomplete' => $labels['parties_incomplete'],
			'email_invalid' => $labels['email_invalid'],
			'format'        => $labels['format'],
			'patterns'      => self::FORMAT_RULES,
			'fieldFormat'   => self::FIELD_FORMAT,
			'error'         => $labels['error'],
		];
		?>
		<style>
			.unosignature-walkin { max-width: 720px; }
			@media (min-width: 960px) {
				.unosignature-walkin { max-width: none; }
				.unosignature-walkin-board {
					display: grid;
					grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
					column-gap: 32px;
					align-items: start;
				}
				.unosignature-walkin-col {
					display: flex;
					flex-direction: column;
					gap: 16px;
					min-width: 0;
				}
				.unosignature-walkin-tiles { display: none; }
				.unosignature-walkin input[type="text"],
				.unosignature-walkin input[type="email"],
				.unosignature-walkin input[type="tel"],
				.unosignature-walkin input[type="date"],
				.unosignature-walkin select { max-width: none; }
			}
			.unosignature-walkin fieldset { border: 1px solid #ddd; margin: 0 0 16px; padding: 16px; }
			@media (min-width: 960px) {
				.unosignature-walkin fieldset { margin-bottom: 0; }
			}
			.unosignature-walkin label { display: block; margin: 12px 0; }
			.unosignature-walkin-toggle { display: block; margin: 8px 0; }
			.unosignature-walkin input[type="text"],
			.unosignature-walkin input[type="email"],
			.unosignature-walkin input[type="tel"],
			.unosignature-walkin input[type="date"],
			.unosignature-walkin select { display: block; width: 100%; max-width: 420px; margin-top: 4px; }
			.unosignature-walkin-row { margin: 0 0 16px; padding-bottom: 8px; border-bottom: 1px solid #eee; }
			.unosignature-walkin-actions { margin-top: 24px; text-align: center; }
			#unosignature-walkin-submit {
				cursor: pointer;
				font-weight: 700;
				letter-spacing: 0.04em;
				padding: 12px 28px;
				text-transform: uppercase;
			}
			.unosignature-walkin-add,
			.unosignature-walkin-remove { cursor: pointer; }
			#unosignature-walkin-status { margin: 16px 0 0; text-align: left; }
			#unosignature-walkin-status:empty { margin: 0; }
			#unosignature-walkin input.is-invalid,
			#unosignature-walkin select.is-invalid {
				border-color: #b00020;
				outline: 1px solid #b00020;
				outline-offset: 0;
			}
			#unosignature-walkin label:has(.is-invalid) { color: #b00020; }
			#unosignature-walkin-status.is-error { color: #b00020; }
			#unosignature-walkin-status.is-success { color: #1b7f3a; font-weight: 700; }
			#unosignature-walkin-status.is-info { color: #666; }
		</style>
		<script>
			(function () {
				const settings = <?php echo wp_json_encode($settings); ?>;
				const form = document.getElementById('unosignature-walkin');
				if (!form) return;
				const status = document.getElementById('unosignature-walkin-status');
				const button = document.getElementById('unosignature-walkin-submit');
				let rowIndex = 0;
				let signed = false;

				const setStatus = function (message, kind) {
					if (!status) return;
					status.textContent = message || '';
					status.className = kind ? 'is-' + kind : '';
				};

				const closeModal = function () {
					const modal = document.getElementById('firma-modal');
					if (modal) modal.remove();
				};

				const showSuccess = function () {
					if (signed) return;
					signed = true;
					closeModal();
					form.querySelectorAll('fieldset, .unosignature-walkin-actions').forEach(function (node) {
						node.hidden = true;
					});
					setStatus(settings.signed, 'success');
				};

				const clearInvalid = function () {
					form.querySelectorAll('.is-invalid').forEach(function (field) {
						field.classList.remove('is-invalid');
						field.removeAttribute('aria-invalid');
					});
				};

				const markInvalid = function (fields) {
					clearInvalid();
					fields.forEach(function (field) {
						field.classList.add('is-invalid');
						field.setAttribute('aria-invalid', 'true');
					});
					if (fields[0]) fields[0].focus();
				};

				const setSectionRequired = function (root, on) {
					root.querySelectorAll('label').forEach(function (label) {
						const mark = label.querySelector('.unosignature-required');
						if (!on) {
							if (mark) mark.remove();
							return;
						}
						if (mark) return;
						const field = label.querySelector('input, select');
						if (!field || field.type === 'checkbox' || field.type === 'button') return;
						const star = document.createElement('span');
						star.className = 'unosignature-required';
						star.textContent = ' *';
						label.insertBefore(star, field);
					});
				};

				const patterns = {};
				Object.keys(settings.patterns || {}).forEach(function (kind) {
					patterns[kind] = new RegExp(settings.patterns[kind]);
				});

				const fieldKind = function (field) {
					const match = field.name && field.name.match(/\[([a-z_]+)\]$/);
					return match ? (settings.fieldFormat[match[1]] || '') : '';
				};

				const valueMatches = function (kind, value) {
					if (!kind || !patterns[kind]) return true;
					if (!patterns[kind].test(value)) return false;
					if (kind !== 'phone') return true;
					const digits = value.replace(/\D/g, '').length;
					return digits >= 7 && digits <= 15;
				};

				const reject = function (invalid, seen, field, reason) {
					if (seen.has(field)) return;
					seen.add(field);
					field.dataset.reason = reason;
					invalid.push(field);
				};

				const clientInvalidFields = function () {
					const invalid = [];
					const seen = new Set();
					const checkValue = function (field) {
						const value = field.value.trim();
						if (value === '') return;
						const kind = fieldKind(field);
						if (kind && !valueMatches(kind, value)) {
							reject(invalid, seen, field, 'format');
						}
						if (field.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
							reject(invalid, seen, field, 'email');
						}
					};
					form.querySelectorAll('input, select').forEach(function (field) {
						if (field.disabled || field.type === 'hidden' || field.type === 'checkbox') return;
						const value = field.value.trim();
						if (field.required && value === '') reject(invalid, seen, field, 'empty');
						checkValue(field);
					});
					form.querySelectorAll('[data-unosignature-toggle]:checked').forEach(function (toggle) {
						const section = form.querySelector('[data-unosignature-section="' + toggle.getAttribute('data-unosignature-toggle') + '"]');
						if (!section) return;
						const fields = [...section.querySelectorAll('input, select')].filter(function (field) {
							return field.type !== 'hidden' && field.type !== 'checkbox' && field.type !== 'button';
						});
						if (!fields.length) {
							reject(invalid, seen, toggle, 'empty');
							return;
						}
						fields.forEach(function (field) {
							if (field.value.trim() === '') reject(invalid, seen, field, 'empty');
							checkValue(field);
						});
					});
					return invalid;
				};

				form.addEventListener('input', function (event) {
					const field = event.target;
					if (!field.classList || !field.classList.contains('is-invalid')) return;
					const value = field.value.trim();
					if (value === '') return;
					const kind = fieldKind(field);
					if (kind && !valueMatches(kind, value)) return;
					if (field.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) return;
					field.classList.remove('is-invalid');
					field.removeAttribute('aria-invalid');
				});

				const tiles = form.querySelector('.unosignature-walkin-tiles');
				const leftCol = form.querySelector('[data-col="left"]');
				const rightCol = form.querySelector('[data-col="right"]');
				const sections = [...form.querySelectorAll('[data-unosignature-section]')];
				const wideQuery = window.matchMedia('(min-width: 960px)');

				const placeTiles = function () {
					if (!tiles || !leftCol || !rightCol) return;
					if (!wideQuery.matches) {
						sections.forEach(function (section) {
							if (section.parentElement !== tiles) tiles.appendChild(section);
						});
						return;
					}
					const others = rightCol.querySelector('.unosignature-walkin-others');
					const primary = leftCol.querySelector('.unosignature-walkin-primary');
					sections.forEach(function (section) {
						if (section.hidden) {
							if (section.parentElement !== tiles) tiles.appendChild(section);
							return;
						}
						if (section.parentElement !== rightCol) rightCol.appendChild(section);
					});
					const gap = 16;
					let leftHeight = primary ? primary.offsetHeight : 0;
					let rightHeight = others ? others.offsetHeight : 0;
					const visible = sections.filter(function (section) { return !section.hidden; });
					const plan = visible.map(function (section) {
						const height = section.offsetHeight;
						const useLeft = leftHeight <= rightHeight;
						if (useLeft) leftHeight += gap + height;
						else rightHeight += gap + height;
						return useLeft ? leftCol : rightCol;
					});
					visible.forEach(function (section, index) {
						if (section.parentElement !== plan[index]) plan[index].appendChild(section);
					});
				};

				form.querySelectorAll('[data-unosignature-toggle]').forEach(function (toggle) {
					toggle.addEventListener('change', function () {
						const section = form.querySelector('[data-unosignature-section="' + toggle.getAttribute('data-unosignature-toggle') + '"]');
						if (!section) return;
						section.hidden = !toggle.checked;
						section.disabled = !toggle.checked;
						setSectionRequired(section, toggle.checked);
						if (!toggle.checked) {
							toggle.classList.remove('is-invalid');
							toggle.removeAttribute('aria-invalid');
							section.querySelectorAll('.is-invalid').forEach(function (field) {
								field.classList.remove('is-invalid');
								field.removeAttribute('aria-invalid');
							});
						}
						placeTiles();
					});
				});
				wideQuery.addEventListener('change', placeTiles);
				placeTiles();

				form.querySelectorAll('[data-unosignature-rows]').forEach(function (rows) {
					const template = rows.querySelector('template');
					const add = rows.querySelector('.unosignature-walkin-add');
					if (!template || !add) return;
					add.addEventListener('click', function () {
						const fragment = template.content.cloneNode(true);
						fragment.querySelectorAll('[name]').forEach(function (field) {
							field.name = field.name.replace('__i__', String(rowIndex));
						});
						rowIndex += 1;
						const row = fragment.querySelector('.unosignature-walkin-row');
						const remove = fragment.querySelector('.unosignature-walkin-remove');
						if (remove && row) {
							remove.addEventListener('click', function () {
								row.remove();
							});
						}
						if (row) setSectionRequired(row, true);
						rows.insertBefore(fragment, add);
					});
				});

				const openModal = function (signUrl) {
					const existing = document.getElementById('firma-modal');
					if (existing) existing.remove();
					const modal = document.createElement('div');
					modal.id = 'firma-modal';
					modal.innerHTML = '<div id="firma-overlay" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.8);z-index:999999;"><div style="position:absolute;top:5%;left:5%;width:90%;height:90%;background:#3B3B3B;border-radius:10px;overflow:hidden;"><iframe src="' + signUrl + '" style="width:100%;height:100%;border:0;" title="Sign agreement"></iframe></div></div>';
					document.body.appendChild(modal);
					const overlay = document.getElementById('firma-overlay');
					if (overlay) {
						overlay.addEventListener('click', function (event) {
							if (event.target.id !== 'firma-overlay' || signed) return;
							closeModal();
							setStatus(settings.closed, 'info');
							if (button) button.disabled = false;
						});
					}
				};

				window.addEventListener('message', function (event) {
					if (event.origin !== 'https://app.firma.dev') return;
					const type = event.data && event.data.type;
					if (type === 'signing.completed') {
						showSuccess();
					}
					if (type === 'signing.declined') {
						closeModal();
						setStatus(settings.declined, 'error');
						if (button) button.disabled = false;
					}
					if (type === 'signing.error') {
						closeModal();
						setStatus(settings.error, 'error');
						if (button) button.disabled = false;
					}
				});

				form.addEventListener('submit', async function (event) {
					event.preventDefault();
					if (!button || signed) return;
					const invalid = clientInvalidFields();
					if (invalid.length) {
						markInvalid(invalid);
						const emptyPrimary = invalid.some(function (field) {
							return field.name && field.name.indexOf('primary[') === 0 && field.value.trim() === '';
						});
						const emptyParty = invalid.some(function (field) {
							const other = field.type === 'checkbox' || (field.name && field.name.indexOf('primary[') !== 0);
							return other && (field.type === 'checkbox' || field.value.trim() === '');
						});
						const badEmail = invalid.some(function (field) {
							return field.dataset.reason === 'email';
						});
						const badFormat = invalid.some(function (field) {
							return field.dataset.reason === 'format';
						});
						let message = settings.incomplete;
						if (!emptyPrimary && emptyParty) message = settings.parties_incomplete;
						else if (!emptyPrimary && !emptyParty && badFormat) message = settings.format;
						else if (!emptyPrimary && !emptyParty && badEmail) message = settings.email_invalid;
						setStatus(message, 'error');
						button.disabled = false;
						return;
					}
					clearInvalid();
					button.disabled = true;
					setStatus(settings.preparing, 'info');
					try {
						const body = new FormData(form);
						const res = await fetch(settings.endpoint, {
							method: 'POST',
							credentials: 'same-origin',
							body: body
						});
						const data = await res.json().catch(function () { return {}; });
						if (!res.ok || !data.sign_url) {
							const named = Array.isArray(data.fields) ? data.fields.map(function (name) {
								return form.elements[name];
							}).filter(Boolean) : [];
							if (named.length) markInvalid(named);
							setStatus(data.message || settings.error, 'error');
							button.disabled = false;
							return;
						}
						setStatus('', '');
						button.disabled = false;
						openModal(data.sign_url);
					} catch (e) {
						setStatus(settings.error, 'error');
						button.disabled = false;
					}
				});
			}());
		</script>
		<?php
	}
}
