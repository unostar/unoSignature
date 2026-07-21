<?php
/**
 * Legacy consultation checkout flow ported from the stable Code Snippets version.
 *
 * Keep REST routes, option keys and session keys compatible with the existing site.
 *
 * @package UnoSignature
 */

if (!defined('ABSPATH')) {
	exit;
}

/**
 * eSignature // firma.dev // API, REST, Webhooks and checkout process validation (backup)
 */
if (!function_exists('uno_debug')) {
	/**
	 * @param mixed $payload
	 */
	function uno_debug($payload, bool $force = false)
	{
		$enabled = class_exists('\UnoSignature\Config')
			? \UnoSignature\Config::is_debug_enabled()
			: false;

		if (!$force && !$enabled) return;

		if (is_scalar($payload) || $payload === null) {
			error_log((string) $payload);
			return;
		}

		error_log(print_r($payload, true));
	}
}

/**
 * Returns the active Firma API key (test or live).
 */
function uno_get_firma_api_key(): string {
	return \UnoSignature\Config::get_firma_api_key();
}

// =========================================================================
// Template map & cart resolver
// =========================================================================

/**
 * Agreement rules from plugin settings (Settings → unoSignature).
 */
function uno_get_template_map()
{
	return \UnoSignature\Config::get_template_map();
}

/**
 * Scans the current cart and returns the first matching Firma template_id,
 * or null if no cart items require a signing agreement.
 * First match wins; order in uno_get_template_map() is significant.
 */
function uno_resolve_template_for_cart()
{
	$rule = uno_resolve_rule_for_cart();
	return is_array($rule) ? (string) ($rule['template_id'] ?? '') : null;
}

/**
 * Returns the first matching signing agreement rule for the current cart.
 *
 * @return array<string, mixed>|null
 */
function uno_resolve_rule_for_cart()
{
	if (!WC()->cart) {
		return null;
	}

	foreach (WC()->cart->get_cart() as $item) {
		$product_id = (int) $item['product_id'];
		foreach (uno_get_template_map() as $entry) {
			if (!empty($entry['excluded_ids']) && in_array($product_id, $entry['excluded_ids'], true)) {
				continue;
			}

			if (
				(!empty($entry['product_ids']) && in_array($product_id, $entry['product_ids'], true))
				|| (!empty($entry['categories']) && has_term($entry['categories'], 'product_cat', $product_id))
			) {
				if (!empty($entry['template_id'])) {
					return $entry;
				}
			}
		}
	}

	return null;
}

/**
 * Collect TM EPO rows from all cart items.
 *
 * @return array<int, array<string, mixed>>
 */
function uno_get_cart_tmcartepo(): array
{
	if (!WC()->cart) {
		return [];
	}

	$rows = [];

	foreach (WC()->cart->get_cart() as $item) {
		if (empty($item['tmcartepo']) || !is_array($item['tmcartepo'])) {
			continue;
		}

		foreach ($item['tmcartepo'] as $row) {
			if (is_array($row)) {
				$rows[] = $row;
			}
		}
	}

	return $rows;
}

/**
 * Build Firma signing-request create payload for checkout.
 *
 * @param array<string, mixed> $rule
 * @param array<string, mixed> $data Checkout posted data.
 * @return array<string, mixed>
 */
function uno_build_firma_create_payload(array $rule, string $agreement_group, array $data): array
{
	$template_id = (string) ($rule['template_id'] ?? '');
	$first = (string) ($data['billing_first_name'] ?? '');
	$last = (string) ($data['billing_last_name'] ?? '');
	$title = (string) ($data['billing_title'] ?? '');
	$birthdate = (string) ($data['billing_birthdate'] ?? '');
	$email = (string) ($data['billing_email'] ?? '');
	$phone = (string) ($data['billing_phone'] ?? '');
	// Prefilled Firma fields require non-empty recipient data at send; WC omits postcode/state for some countries (e.g. AE).
	$postcode = !empty($data['billing_postcode']) ? (string) $data['billing_postcode'] : 'N/A';
	$state = !empty($data['billing_state']) ? (string) $data['billing_state'] : 'N/A';
	$city = !empty($data['billing_city']) ? (string) $data['billing_city'] : '';
	$address = !empty($data['billing_address_1']) ? (string) $data['billing_address_1'] : '';
	$country = !empty($data['billing_country']) ? (string) $data['billing_country'] : '';

	$dob_name = sprintf(
		'%s-%s-%s-DOB-%s',
		uno_normalize_request_name_segment($last, true),
		uno_normalize_request_name_segment($first),
		str_replace(['.', ',', ' '], '', $title),
		gmdate('d-M-Y', strtotime($birthdate))
	);

	$recipient = [
		'designation'    => 'Signer',
		'order'          => 1,
		'first_name'     => $first,
		'last_name'      => $last,
		'title'          => $title,
		'email'          => $email,
		'phone_number'   => $phone,
		'street_address' => $address,
		'city'           => $city,
		'state_province' => $state,
		'postal_code'    => $postcode,
		'country'        => $country,
	];

	if ($birthdate !== '') {
		$timestamp = strtotime($birthdate);
		if ($timestamp !== false) {
			$recipient['custom_fields'] = [
				'birthdate' => gmdate('d-M-Y', $timestamp),
			];
		}
	}

	$payload = [
		'template_id' => $template_id,
		'name'        => $dob_name,
		'recipients'  => [$recipient],
	];

	if (\UnoSignature\Config::is_visa_agreement_group($agreement_group)) {
		$parties = \UnoSignature\VisaEpoParser::parse(uno_get_cart_tmcartepo());
		$field_overrides = \UnoSignature\VisaTextBuilder::build_field_overrides($parties, $rule);

		uno_debug([
			'scope' => 'visa_epo_parsed',
			'has_primary' => \UnoSignature\VisaTextBuilder::adult_has_contact($parties['primary'] ?? []),
			'has_representative' => \UnoSignature\VisaTextBuilder::adult_has_contact($parties['representative'] ?? []),
			'has_sponsor' => \UnoSignature\VisaTextBuilder::adult_has_contact($parties['sponsor'] ?? []),
			'additional_applicant_count' => count($parties['additional_applicants'] ?? []),
			'minor_child_count' => count($parties['minor_children'] ?? []),
			'field_override_count' => count($field_overrides),
		]);

		if ($field_overrides !== []) {
			$payload['fields'] = $field_overrides;
		}

		$payload['settings'] = [
			'send_signing_email' => false,
		];
	}

	return $payload;
}

/**
 * Resolves logical agreement group for the current cart.
 */
function uno_resolve_agreement_group_for_cart()
{
	if (!WC()->cart) return null;
	foreach (WC()->cart->get_cart() as $item) {
		$product_id = (int) $item['product_id'];
		foreach (uno_get_template_map() as $entry) {
			if (!empty($entry['excluded_ids']) && in_array($product_id, $entry['excluded_ids'], true)) continue;
			if (
				(!empty($entry['product_ids']) && in_array($product_id, $entry['product_ids'], true))
				|| (!empty($entry['categories']) && has_term($entry['categories'], 'product_cat', $product_id))
			) {
				if (!empty($entry['agreement_group'])) return (string) $entry['agreement_group'];
			}
		}
	}
	return null;
}

/**
 * Resolves logical agreement group by template ID.
 */
function uno_resolve_agreement_group_by_template(string $template_id)
{
	foreach (uno_get_template_map() as $entry) {
		if ((string) ($entry['template_id'] ?? '') === (string) $template_id) {
			return (string) ($entry['agreement_group'] ?? '');
		}
	}

	return '';
}

/**
 * Returns signed-state option key for email + logical agreement group.
 */
function uno_signed_option_key(string $email, string $agreement_group)
{
	return 'firma_signed_' . md5((string) $email . (string) $agreement_group);
}

/**
 * Normalizes a signing-request name segment so multi-part names use hyphens.
 */
function uno_normalize_request_name_segment(string $value, bool $uppercase = false)
{
	$value = trim($value);
	if ($value === '') return '';

	$value = preg_replace('/\s+/', '-', $value);
	$value = preg_replace('/-+/', '-', (string) $value);

	if ($uppercase) {
		return strtoupper((string) $value);
	}

	return ucwords(strtolower((string) $value), '-');
}

// =========================================================================
// WooCommerce hooks
// =========================================================================

/**
 * Shows an informational notice at the top of the checkout form
 * if the cart contains items that require a signing agreement.
 * Skipped if a signing session is already in progress.
 */
add_action('woocommerce_before_checkout_form', function () {
	
	if (WC()->session && (WC()->session->get('firma_request_id') || WC()->session->get('firma_url'))) return;
	if (!uno_resolve_template_for_cart()) return;

	$agreement_group = uno_resolve_agreement_group_for_cart();
	$checkout_email = '';

	if (WC()->customer) {
		$checkout_email = (string) WC()->customer->get_billing_email();
		if ($checkout_email === '') {
			$checkout_email = (string) WC()->customer->get_email();
		}
	}

	if ($checkout_email === '' && is_user_logged_in()) {
		$current_user = wp_get_current_user();
		if ($current_user instanceof WP_User) {
			$checkout_email = (string) ($current_user->user_email ?? '');
		}
	}

	$checkout_email = strtolower(trim($checkout_email));
	if ($agreement_group && $checkout_email !== '' && get_option(uno_signed_option_key($checkout_email, $agreement_group))) return;

	wc_print_notice(
		__('Selected services require signing an agreement. After you complete all details, you will be prompted to sign the agreement. After signing, you will be able to proceed to payment.', 'unosignature'),
		'notice'
	);
}, 10);

/**
 * Intercepts checkout validation to enforce agreement signing.
 * If the cart requires a signature and none is on record:
 *   — creates a Firma signing request (with a 20s duplicate-create lock),
 *   — or resumes an existing in-progress request.
 * Blocks order placement by adding a WC error with the signing payload.
 * If already signed: clears session state and allows checkout to proceed.
 */
add_action('woocommerce_after_checkout_validation', function (array $data, WP_Error $errors) {

	if ($errors->has_errors()) return;

	$api_key = uno_get_firma_api_key();
	$rule = uno_resolve_rule_for_cart();
	$template_id = is_array($rule) ? (string) ($rule['template_id'] ?? '') : '';
	$agreement_group = uno_resolve_agreement_group_for_cart();
	if ($template_id === '' || !$agreement_group || $api_key === '') return;

	$first = $data['billing_first_name'] ?? '';
	$last  = $data['billing_last_name'] ?? '';
	$title = $data['billing_title'] ?? '';
	$birthdate = $data['billing_birthdate'] ?? '';
	$email = $data['billing_email'] ?? '';
	$phone = $data['billing_phone'] ?? '';
	$postcode = !empty($data['billing_postcode']) ? $data['billing_postcode'] : 'N/A';
	$state = !empty($data['billing_state']) ? $data['billing_state'] : 'N/A';
	$city = !empty($data['billing_city']) ? $data['billing_city'] : '';
	$address = !empty($data['billing_address_1']) ? $data['billing_address_1'] : '';
	$country = !empty($data['billing_country']) ? $data['billing_country'] : '';
	$active_request = uno_get_active_request($email, $template_id);
	$create_lock_key = 'firma_create_lock_' . md5(strtolower(trim($email)) . '|' . $template_id);
	uno_debug([
		'scope' => 'checkout_validation',
		'step' => 'input_prepared',
		'email' => $email,
		'template_id' => $template_id,
		'agreement_group' => $agreement_group,
		'has_active_request' => is_array($active_request),
	]);

	if (get_option(uno_signed_option_key($email, $agreement_group))) {
		uno_debug([
			'scope' => 'checkout_validation',
			'step' => 'already_signed_short_circuit',
			'email' => $email,
			'template_id' => $template_id,
			'agreement_group' => $agreement_group,
		]);
		WC()->session->__unset('firma_url');
		uno_delete_active_request($email, $template_id);
		return;
	}

	$request_id = WC()->session->get('firma_request_id');
	$sign_url = WC()->session->get('firma_url');

	if ((!$request_id || !$sign_url) && !empty($active_request['request_id']) && !empty($active_request['sign_url'])) {
		$request_id = $active_request['request_id'];
		$sign_url = $active_request['sign_url'];
		if (WC()->session) {
			WC()->session->set('firma_request_id', $request_id);
			WC()->session->set('firma_url', $sign_url);
		}
	}
	uno_debug([
		'scope' => 'checkout_validation',
		'step' => 'session_resolved',
		'request_id' => $request_id,
		'has_sign_url' => !empty($sign_url),
	]);

	if ($request_id) {
		uno_debug([
			'scope' => 'checkout_validation',
			'step' => 'fetch_request_status',
			'request_id' => $request_id,
		]);
		$res = wp_remote_get(
			"https://api.firma.dev/functions/v1/signing-request-api/signing-requests/$request_id",
			[
				'headers' => [
					'Authorization' => 'Bearer ' . $api_key
				]
			]
		);

		$req_data = json_decode(wp_remote_retrieve_body($res), true);

		$status = $req_data['status'] ?? null;
		$status_value = is_string($status) ? strtolower($status) : '';

		$is_sent = (is_array($status) && !empty($status['sent']))
		|| $status_value === 'in_progress';
		$is_finished = (is_array($status) && !empty($status['finished'])) || $status_value === 'finished';
		$is_declined = (is_array($status) && !empty($status['declined'])) || $status_value === 'declined';
		$is_cancelled = (is_array($status) && !empty($status['cancelled'])) || $status_value === 'cancelled';
		$is_expired = (is_array($status) && !empty($status['expired'])) || $status_value === 'expired';
		uno_debug([
			'scope' => 'checkout_validation',
			'step' => 'request_status_evaluated',
			'request_id' => $request_id,
			'is_sent' => $is_sent,
			'is_finished' => $is_finished,
			'is_declined' => $is_declined,
			'is_cancelled' => $is_cancelled,
			'is_expired' => $is_expired,
		]);

		if ($is_finished) {
			uno_debug([
				'scope' => 'checkout_validation',
				'step' => 'finished_unlock',
				'email' => $email,
				'template_id' => $template_id,
				'agreement_group' => $agreement_group,
			]);
			WC()->session->__unset('firma_url');
			WC()->session->__unset('firma_request_id');
			update_option(uno_signed_option_key($email, $agreement_group), 1, false);
			uno_delete_active_request($email, $template_id);
			return;
		}

		if ($is_declined) {
			if (empty($sign_url) && !empty($req_data['recipients'][0]['id'])) {
				$sign_url = 'https://app.firma.dev/signing/' . $req_data['recipients'][0]['id'];
			}

			$declined_on = $req_data['timestamps']['declined_on'] ?? '';
			$declined_ts = $declined_on ? strtotime($declined_on) : false;
			$unlock_after_ts = $declined_ts ? ($declined_ts + 48 * HOUR_IN_SECONDS) : 0;
			$is_unlocked = $unlock_after_ts > 0 && time() >= $unlock_after_ts;

			if ($is_unlocked) {
				uno_debug([
					'scope' => 'checkout_validation',
					'step' => 'declined_unlocked_cleanup',
					'request_id' => $request_id,
				]);
				WC()->session->__unset('firma_url');
				WC()->session->__unset('firma_request_id');
				uno_delete_active_request($email, $template_id);
				$request_id = '';
				$sign_url = '';
			} else {
				uno_debug([
					'scope' => 'checkout_validation',
					'step' => 'declined_locked_block_checkout',
					'request_id' => $request_id,
				]);
				if (!empty($request_id) && !empty($sign_url)) {
					uno_set_active_request($email, $template_id, $request_id, $sign_url);
					if (WC()->session) {
						WC()->session->set('firma_request_id', $request_id);
						WC()->session->set('firma_url', $sign_url);
					}

					$errors->add('esignature_declined_wait', __('You previously declined this agreement. If you change your mind, you can try signing again after 48 hours.', 'unosignature') . ' ' . uno_get_firma_sign_payload($sign_url, $request_id));
					return;
				}

				$errors->add('esignature_api_error', __('Agreement is declined but sign link is unavailable. Please contact support.', 'unosignature'));
				return;
			}
		}

		if ($is_cancelled || $is_expired) {
			uno_debug([
				'scope' => 'checkout_validation',
				'step' => 'cancelled_or_expired_cleanup',
				'request_id' => $request_id,
			]);
			WC()->session->__unset('firma_url');
			WC()->session->__unset('firma_request_id');
			uno_delete_active_request($email, $template_id);
			$request_id = '';
			$sign_url = '';
		}

		if ($is_sent) {
			uno_debug([
				'scope' => 'checkout_validation',
				'step' => 'sent_require_signature_notice',
				'request_id' => $request_id,
			]);
			uno_set_active_request($email, $template_id, $request_id, $sign_url);
			$errors->add('esignature_required', __('This service requires an agreement. Please sign the agreement to continue.', 'unosignature') . ' ' . uno_get_firma_sign_payload($sign_url, $request_id));
			return;
		}
	}

	if ($request_id && $sign_url) {
		uno_debug([
			'scope' => 'checkout_validation',
			'step' => 'send_existing_request',
			'request_id' => $request_id,
		]);
		$send_result = uno_send_signing_request($api_key, $request_id);

		if (empty($send_result['ok'])) {
			uno_debug('Firma backend send error for existing request: ' . wp_json_encode($send_result), true);
			$errors->add('esignature_api_error', __('There was an error sending the agreement for signature. Please try again.', 'unosignature'));
			return;
		}

		uno_set_active_request($email, $template_id, $request_id, $sign_url);
		$errors->add('esignature_required', __('This service requires an agreement. Please sign the agreement to continue.', 'unosignature') . ' ' . uno_get_firma_sign_payload($sign_url, $request_id));
		return;
	}

	if ($sign_url) {
		if (!$request_id) {
			$errors->add('esignature_api_error', __('There was an error retrieving the eSignature document. Please try again.', 'unosignature'));
			return;
		}
		uno_debug([
			'scope' => 'checkout_validation',
			'step' => 'existing_sign_url_notice_only',
			'request_id' => $request_id,
		]);
		$errors->add('esignature_required', __('This service requires an agreement. Please sign the agreement to continue.', 'unosignature') . ' ' . uno_get_firma_sign_payload($sign_url, $request_id));
		return;
	}

	if (get_transient($create_lock_key)) {
		uno_debug([
			'scope' => 'checkout_validation',
			'step' => 'create_lock_exists',
			'create_lock_key' => $create_lock_key,
		]);
		if (!empty($active_request['sign_url'])) {
			if (WC()->session) {
				WC()->session->set('firma_request_id', $active_request['request_id']);
				WC()->session->set('firma_url', $active_request['sign_url']);
			}
			$errors->add('esignature_required', __('This service requires an agreement. Please sign the agreement to continue.', 'unosignature') . ' ' . uno_get_firma_sign_payload($active_request['sign_url'], $active_request['request_id']));
			return;
		}

		$errors->add('esignature_api_error', __('Please wait a moment and try again.', 'unosignature'));
		return;
	}

	set_transient($create_lock_key, 1, 20);
	uno_debug([
		'scope' => 'checkout_validation',
		'step' => 'create_lock_set',
		'create_lock_key' => $create_lock_key,
	]);

	$create_payload = uno_build_firma_create_payload($rule, $agreement_group, $data);
	uno_debug([
		'scope' => 'checkout_validation',
		'step' => 'create_request_payload',
		'agreement_group' => $agreement_group,
		'has_fields' => !empty($create_payload['fields']),
		'field_count' => isset($create_payload['fields']) && is_array($create_payload['fields']) ? count($create_payload['fields']) : 0,
	]);

	$res = wp_remote_post(
		"https://api.firma.dev/functions/v1/signing-request-api/signing-requests",
		[
			'headers' => [
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type' => 'application/json'
			],
			'body' => json_encode($create_payload)
		]
	);

	$api = json_decode(wp_remote_retrieve_body($res), true);
	uno_debug([
		'scope' => 'checkout_validation',
		'step' => 'create_request_response',
		'http_code' => (int) wp_remote_retrieve_response_code($res),
		'has_request_id' => !empty($api['id']),
		'has_recipient_id' => !empty($api['recipients'][0]['id']),
	]);

	if (empty($api['recipients'][0]['id'])) {
		delete_transient($create_lock_key);
		$errors->add('esignature_api_error', __('There was an error retrieving the eSignature document. Please try again.', 'unosignature'));
		return;
	}

	WC()->session->set('firma_request_id', $api['id']);
	$recipient_id = $api['recipients'][0]['id'];
	$signing_url = "https://app.firma.dev/signing/$recipient_id";

	WC()->session->set('firma_url', $signing_url);

	$send_result = uno_send_signing_request($api_key, $api['id']);
	uno_debug([
		'scope' => 'checkout_validation',
		'step' => 'send_new_request_result',
		'request_id' => $api['id'],
		'ok' => !empty($send_result['ok']),
	]);

	if (empty($send_result['ok'])) {
		delete_transient($create_lock_key);
		uno_debug('Firma backend send error for new request: ' . wp_json_encode($send_result), true);
		$errors->add('esignature_api_error', __('There was an error sending the agreement for signature. Please try again.', 'unosignature'));
		return;
	}

	uno_set_active_request($email, $template_id, $api['id'], $signing_url);
	delete_transient($create_lock_key);

	$errors->add('esignature_required', __('This service requires an agreement. Please sign the agreement to continue.', 'unosignature') . ' ' . uno_get_firma_sign_payload($signing_url, $api['id']));
}, 10, 2);

// =========================================================================
// WooCommerce order meta (Firma request/document data)
// =========================================================================

/**
 * Stores current signing request ID in the created WooCommerce order.
 * Uses only the active checkout session request_id.
 */
add_action('woocommerce_checkout_create_order', function (WC_Order $order) {
	if (!($order instanceof WC_Order)) {
		return;
	}

	if (!WC()->session) {
		return;
	}

	$request_id = (string) WC()->session->get('firma_request_id');
	if ($request_id === '') {
		return;
	}

	$order->update_meta_data('_firma_request_id', $request_id);

	uno_debug([
		'scope' => 'order_meta',
		'step' => 'request_id_attached',
		'order_id' => $order->get_id(),
		'request_id' => $request_id,
	]);
}, 10, 2);

/**
 * Clears Firma checkout session markers once order is created.
 */
add_action('woocommerce_checkout_order_processed', function () {
	if (!WC()->session) {
		return;
	}

	WC()->session->__unset('firma_url');
	WC()->session->__unset('firma_request_id');
}, 10);

/**
 * Displays Firma metadata on WooCommerce admin order page.
 */
add_action('woocommerce_admin_order_data_after_shipping_address', function (WC_Order $order) {
	if (!($order instanceof WC_Order)) {
		return;
	}

	$request_id = (string) $order->get_meta('_firma_request_id');

	if ($request_id === '') {
		return;
	}

	echo '<div class="firma-order-meta" style="margin-top:12px;">';
	echo '<h3>Agreement Details</h3>';
	if ($request_id !== '') {
		echo '<p><strong>Signing Request ID:</strong> ' . esc_html($request_id) . '</p>';
	}
	echo '</div>';
}, 20);

// =========================================================================
// UI helpers
// =========================================================================

/**
 * Returns the HTML signing payload injected into WooCommerce error notices.
 * Contains a hidden data div (url + request_id) and two action buttons:
 * "Sign agreement" (visible by default) and "I signed, continue"
 * (shown after signing).
 */
function uno_get_firma_sign_payload(string $sign_url, string $request_id) {
	return sprintf(
		'<div id="firma-sign-data" data-url="%s" data-request-id="%s"></div><button id="firma-open-signing" type="button">%s</button><button id="firma-continue-checkout" type="button">%s</button>',
		esc_attr($sign_url),
		esc_attr($request_id),
		esc_html__('Sign agreement', 'unosignature'),
		esc_html__('I signed, continue checkout', 'unosignature')
	);
}

// =========================================================================
// Active request storage (WP Options CRUD)
// =========================================================================

/**
 * Returns the WP option key for an active signing request,
 * keyed by a hash of email + template_id.
 */
function uno_active_request_option_key(string $email, string $template_id)
{
	return 'firma_active_' . md5(strtolower(trim((string) $email)) . '|' . (string) $template_id);
}

/**
 * Retrieves the active signing request record for a given
 * email + template_id.
 * Returns an array with request_id, sign_url, email, 
 * template_id, updated_at, or null if not found or incomplete.
 */
function uno_get_active_request(string $email, string $template_id)
{
	$key = uno_active_request_option_key($email, $template_id);
	$value = get_option($key);

	if (!is_array($value)) {
		uno_debug([
			'scope' => 'active_request',
			'step' => 'get_not_found_or_invalid',
			'email' => $email,
			'template_id' => $template_id,
		]);
		return null;
	}

	if (empty($value['request_id']) || empty($value['sign_url'])) {
		uno_debug([
			'scope' => 'active_request',
			'step' => 'get_missing_fields',
			'email' => $email,
			'template_id' => $template_id,
		]);
		return null;
	}

	return $value;
}

/**
 * Persists an active signing request record.
 * Also writes a reverse lookup map: request_id → email + template_id,
 * used by the webhook handler where only request_id is known.
 */
function uno_set_active_request(string $email, string $template_id, string $request_id, string $sign_url)
{
	$key = uno_active_request_option_key($email, $template_id);
	update_option($key, [
		'request_id' => (string) $request_id,
		'sign_url' => (string) $sign_url,
		'email' => (string) $email,
		'template_id' => (string) $template_id,
		'updated_at' => gmdate('c'),
	], false);

	$map_key = 'firma_active_request_' . md5((string) $request_id);
	update_option($map_key, [
		'email' => (string) $email,
		'template_id' => (string) $template_id,
	], false);
	uno_debug([
		'scope' => 'active_request',
		'step' => 'set',
		'email' => $email,
		'template_id' => $template_id,
		'request_id' => $request_id,
	]);
}

/**
 * Removes the active signing request record and its reverse
 * lookup map entry.
 */
function uno_delete_active_request(string $email, string $template_id)
{
	$key = uno_active_request_option_key($email, $template_id);
	$current = get_option($key);
	if (is_array($current) && !empty($current['request_id'])) {
		$map_key = 'firma_active_request_' . md5((string) $current['request_id']);
		delete_option($map_key);
	}
	delete_option($key);
	uno_debug([
		'scope' => 'active_request',
		'step' => 'delete',
		'email' => $email,
		'template_id' => $template_id,
	]);
}

// =========================================================================
// Firma API helpers
// =========================================================================

/**
 * Sends a signing request via the Firma API (idempotent).
 * An "already been sent" 400 response is treated as success.
 * Returns ['ok' => true] on success, 
 * ['ok' => false, 'error' => ...] on failure.
 */
function uno_send_signing_request(string $api_key, string $request_id)
{
	uno_debug([
		'scope' => 'send_request',
		'step' => 'start',
		'request_id' => $request_id,
	]);
	$res = wp_remote_post(
		"https://api.firma.dev/functions/v1/signing-request-api/signing-requests/{$request_id}/send",
		[
			'headers' => [
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type' => 'application/json'
			],
			'body' => wp_json_encode(['send_signing_email' => false])
		]
	);

	if (is_wp_error($res)) {
		uno_debug([
			'scope' => 'send_request',
			'step' => 'wp_error',
			'request_id' => $request_id,
			'error' => $res->get_error_message(),
		]);
		return [
			'ok' => false,
			'error' => $res->get_error_message(),
		];
	}

	$http_code = (int) wp_remote_retrieve_response_code($res);
	$raw_body = wp_remote_retrieve_body($res);
	$api_body = json_decode($raw_body, true);

	$already_sent = $http_code === 400
		&& is_array($api_body)
		&& !empty($api_body['error'])
		&& stripos($api_body['error'], 'already been sent') !== false;

	if ($already_sent) {
		uno_debug([
			'scope' => 'send_request',
			'step' => 'already_sent',
			'request_id' => $request_id,
		]);
		return ['ok' => true, 'already_sent' => true];
	}

	if ($http_code >= 400) {
		uno_debug([
			'scope' => 'send_request',
			'step' => 'http_error',
			'request_id' => $request_id,
			'http_code' => $http_code,
		]);
		return [
			'ok' => false,
			'error' => 'send_http_error',
			'http_code' => $http_code,
			'details' => $api_body,
			'raw_body' => $raw_body,
		];
	}

	if (is_array($api_body) && (!empty($api_body['error']) || !empty($api_body['validation_errors']))) {
		uno_debug([
			'scope' => 'send_request',
			'step' => 'validation_error',
			'request_id' => $request_id,
		]);
		return [
			'ok' => false,
			'error' => 'send_validation_error',
			'details' => $api_body,
			'raw_body' => $raw_body,
		];
	}

	return ['ok' => true];
}

// =========================================================================
// Checkout UI: signing modal + button state management (JS)
// =========================================================================

/**
 * Outputs translated strings and the signing flow JavaScript after
 * the checkout form.
 * Handles: modal open/close, postMessage events from Firma iframe,
 * webhook confirmation polling, and checkout auto-submit after signing.
 */
add_action('woocommerce_after_checkout_form', function () {
	$firma_l10n = [
		'declined' => __('Agreement declined. Please sign to continue.', 'unosignature'),
		'declined_wait' => __('You previously declined this agreement. If you change your mind, you can try signing again after 48 hours.', 'unosignature'),
		'error' => __('An error occurred during signing. Please try again.', 'unosignature'),
		'sign_agreement' => __('Sign agreement', 'unosignature'),
		'continue_checkout' => __('I signed, continue checkout', 'unosignature'),
		'checking_signature' => __('Checking signature...', 'unosignature'),
	];
?>
	<script>
		window.firmaL10n = <?php echo wp_json_encode($firma_l10n); ?>;
	</script>
	<style>
		#firma-open-signing,
		#firma-continue-checkout {
			margin-top: 8px;
		}

		#firma-open-signing {
			display: block;
		}

		#firma-continue-checkout {
			display: none;
		}
	</style>
	<script>
		(function($) {
			window.firmaSigningCompleted = window.firmaSigningCompleted || false;
			window.firmaAutoSubmitTriggered = window.firmaAutoSubmitTriggered || false;

			function getSignData() {
				const node = document.querySelector('#firma-sign-data');
				if (!node) return null;
				return {
					url: node.dataset.url || '',
					requestId: node.dataset.requestId || ''
				};
			}

			function getContinueButton() {
				return document.querySelector('#firma-continue-checkout');
			}

			function getSignButton() {
				return document.querySelector('#firma-open-signing');
			}

			function showSignButton() {
				const btn = getSignButton();
				if (!btn) return;
				btn.style.display = 'block';
				btn.disabled = false;
				btn.textContent = firmaL10n.sign_agreement;
			}

			function hideSignButton() {
				const btn = getSignButton();
				if (!btn) return;
				btn.style.display = 'none';
			}

			function showContinueButton() {
				const btn = getContinueButton();
				if (!btn) return;
				btn.style.display = 'block';
				btn.disabled = false;
				btn.textContent = firmaL10n.continue_checkout;
			}

			function submitCheckout() {
				const placeOrderBtn = document.getElementById('place_order');
				if (!placeOrderBtn) return;
				placeOrderBtn.click();
			}

			async function waitForWebhookConfirmation(requestId) {
				if (!requestId) return false;

				const maxChecks = 25;
				for (let i = 0; i < maxChecks; i++) {
					try {
						const res = await fetch(`/wp-json/firma/v1/signing-completed-status?request_id=${encodeURIComponent(requestId)}`, {
							credentials: 'same-origin'
						});

						if (res.ok) {
							const data = await res.json();
							if (data && data.confirmed) {
								return true;
							}
						}
					} catch (e) {
						// Keep waiting; transient network hiccups should not break UX.
					}

					await new Promise(resolve => setTimeout(resolve, 600));
				}

				return false;
			}

			async function waitForDeclinedWebhook(requestId) {
				if (!requestId) return false;

				const maxChecks = 25;
				for (let i = 0; i < maxChecks; i++) {
					try {
						const res = await fetch(`/wp-json/firma/v1/signing-completed-status?request_id=${encodeURIComponent(requestId)}`, {
							credentials: 'same-origin'
						});

						if (res.ok) {
							const data = await res.json();
							if (data && data.declined) {
								return true;
							}
						}
					} catch (e) {
						// Keep waiting; transient network hiccups should not break UX.
					}

					await new Promise(resolve => setTimeout(resolve, 600));
				}

				return false;
			}

			function showDeclinedNoticeText() {
				const marker = document.querySelector('#firma-sign-data');
				if (!marker || !marker.parentNode) return;

				const parent = marker.parentNode;
				Array.from(parent.childNodes).forEach(function(node) {
					if (node.nodeType === Node.TEXT_NODE) {
						node.textContent = '';
					}
				});

				let message = document.querySelector('#firma-sign-status-message');
				if (!message) {
					message = document.createElement('span');
					message.id = 'firma-sign-status-message';
					parent.insertBefore(message, marker);
				}

				message.textContent = `${firmaL10n.declined_wait} `;
			}

			function openModal(signingUrl) {
				if (!signingUrl) return;
				if (window.firmaSigningCompleted) return;
				if ($('#firma-modal').length) return;

				const modal = $(`
					<div id="firma-modal">
							<div id="firma-overlay" style="position: fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:999999;">
									<div style="position:absolute; top:5%;left:5%; width:90%;height:90%; background:#3B3B3B; border-radius:10px; overflow:hidden;">
											<iframe src="${signingUrl}" style="width:100%;height:100%;border:0;"></iframe>
									</div>
							</div>
					</div>
				`);

				$('body').append(modal);

				$('#firma-overlay').on('click', function(e) {
					if (e.target.id === 'firma-overlay') {
						modal.remove();
					}
				});
			}

			function maybeOpenSigningModal() {
				const signData = getSignData();
				if (!signData || !signData.url) return;
				if (window.firmaSigningCompleted) return;
				openModal(signData.url);
			}

			function syncSigningButtonsAfterRefresh() {
				const signData = getSignData();
				if (!signData || !signData.url) return;

				if (window.firmaSigningCompleted) {
					hideSignButton();
					showContinueButton();
				}
			}

			// Open signing modal after checkout refresh that returns
			// eSignature notice
			$(document.body).on('updated_checkout', function() {
				syncSigningButtonsAfterRefresh();
				maybeOpenSigningModal();
			});

			$(document.body).on('checkout_error', function() {
				setTimeout(function() {
					syncSigningButtonsAfterRefresh();
					maybeOpenSigningModal();
				}, 50);
			});

			syncSigningButtonsAfterRefresh();
			maybeOpenSigningModal();

			window.addEventListener('message', function(e) {
				if (e.origin !== 'https://app.firma.dev') return;

				if (e.data?.type === 'signing.completed') {
					const signData = getSignData();
					const continueBtn = getContinueButton();
					if (continueBtn) {
						continueBtn.disabled = true;
						continueBtn.style.display = 'block';
						continueBtn.textContent = firmaL10n.checking_signature;
					}

					waitForWebhookConfirmation(signData?.requestId || '').then(function(confirmed) {
						if (confirmed) {
							$('#firma-modal').remove();
							window.firmaSigningCompleted = true;
							hideSignButton();
							showContinueButton();

							if (!window.firmaAutoSubmitTriggered) {
								window.firmaAutoSubmitTriggered = true;
								submitCheckout();
							}
						} else {
							if (continueBtn) {
								continueBtn.disabled = false;
								continueBtn.textContent = firmaL10n.continue_checkout;
							}
						}
					});
				}

				if (e.data?.type === 'signing.declined') {
					const signData = getSignData();
					const signBtn = getSignButton();
					if (signBtn) {
						signBtn.disabled = true;
						signBtn.textContent = firmaL10n.checking_signature;
					}

					waitForDeclinedWebhook(signData?.requestId || '').then(function() {
						$('#firma-modal').remove();
						showSignButton();
						showDeclinedNoticeText();
					});
				}

				if (e.data?.type === 'signing.error') {
					$('#firma-modal').remove();
					showSignButton();
				}
			});

			$(document).on('click', '#firma-open-signing', function() {
				if (window.firmaSigningCompleted) {
					submitCheckout();
					return;
				}

				const signData = getSignData();
				if (!signData || !signData.url) return;
				openModal(signData.url);
			});

			$(document).on('click', '#firma-continue-checkout', function() {
				const btn = this;
				btn.disabled = true;
				btn.textContent = firmaL10n.checking_signature;
				submitCheckout();
			});

		})(jQuery);
	</script>

<?php
});

// =========================================================================
// REST API: webhook receiver + signing status endpoint
// =========================================================================

/**
 * Sends immediate 200 OK webhook acknowledgement and flushes response,
 * allowing heavy processing to continue in the same request lifecycle.
 */
function uno_send_webhook_ack_now()
{
	ignore_user_abort(true);
	if (function_exists('status_header')) {
		status_header(200);
	}
	if (!headers_sent()) {
		header('Content-Type: application/json; charset=' . get_option('blog_charset'));
	}
	echo wp_json_encode(['ok' => true, 'accepted' => true]);

	if (function_exists('fastcgi_finish_request')) {
		fastcgi_finish_request();
		return;
	}

	@ob_flush();
	@flush();
}

/**
 * Handles certificate.generated owner-copy flow.
 * Called after immediate webhook ACK has already been sent.
 */
function uno_process_certificate_generated_owner_copy(array $event_data)
{
	$request_id = $event_data['signing_request']['id'] ?? '';
	$api_key = uno_get_firma_api_key();
	$owner_email = (string) \UnoSignature\Config::get('firma_owner_copy_email', '');

	if (!$request_id || !$api_key || !$owner_email) {
		uno_debug([
			'scope' => 'webhook',
			'step' => 'certificate_generated_skip_missing_requirements',
			'request_id' => $request_id,
			'has_api_key' => !empty($api_key),
			'has_owner_email' => !empty($owner_email),
		]);
		return;
	}

	uno_debug([
		'scope' => 'webhook',
		'step' => 'certificate_generated_start',
		'request_id' => $request_id,
		'owner_email' => $owner_email,
	]);

	$request_res = wp_remote_get(
		"https://api.firma.dev/functions/v1/signing-request-api/signing-requests/{$request_id}",
		[
			'headers' => [
				'Authorization' => 'Bearer ' . $api_key
			],
			'timeout' => 20,
		]
	);

	if (is_wp_error($request_res)) {
		uno_debug([
			'scope' => 'webhook',
			'step' => 'certificate_request_wp_error',
			'request_id' => $request_id,
			'error' => $request_res->get_error_message(),
		]);
		return;
	}

	uno_debug([
		'scope' => 'webhook',
		'step' => 'certificate_request_fetched',
		'request_id' => $request_id,
		'http_code' => (int) wp_remote_retrieve_response_code($request_res),
	]);
	$request_body = json_decode(wp_remote_retrieve_body($request_res), true);

	$users = [];
	$users_res = wp_remote_get(
		"https://api.firma.dev/functions/v1/signing-request-api/signing-requests/{$request_id}/users",
		[
			'headers' => [
				'Authorization' => 'Bearer ' . $api_key
			],
			'timeout' => 20,
		]
	);
	if (!is_wp_error($users_res)) {
		$users_body = json_decode(wp_remote_retrieve_body($users_res), true);
		if (is_array($users_body) && isset($users_body['results']) && is_array($users_body['results'])) {
			$users = $users_body['results'];
		} else {
			uno_debug([
				'scope' => 'webhook',
				'step' => 'certificate_users_unexpected_shape',
				'request_id' => $request_id,
				'users_body' => $users_body,
			]);
		}
		uno_debug([
			'scope' => 'webhook',
			'step' => 'certificate_users_fetched',
			'request_id' => $request_id,
			'http_code' => (int) wp_remote_retrieve_response_code($users_res),
			'users_count' => is_array($users) ? count($users) : 0,
		]);
	} else {
		uno_debug([
			'scope' => 'webhook',
			'step' => 'certificate_users_fetch_wp_error',
			'request_id' => $request_id,
			'error' => $users_res->get_error_message(),
		]);
	}

	$download_url = $request_body['final_document_download_url'] ?? '';

	uno_debug([
		'scope' => 'webhook',
		'step' => 'certificate_download_url_extracted',
		'request_body' => $request_body,
		'has_download_url' => !empty($download_url),
	]);

	if (!$download_url) {
		return;
	}

	uno_debug([
		'scope' => 'webhook',
		'step' => 'certificate_download_start',
		'request_id' => $request_id,
	]);

	$agreement_name = trim((string) ($request_body['name'] ?? ''));
	$template_description = trim((string) ($request_body['template_description'] ?? ''));
	$subject = $template_description !== ''
		? sprintf('%s - %s', $agreement_name, $template_description)
		: $agreement_name;

	$download_path = (string) parse_url($download_url, PHP_URL_PATH);
	$download_ext = strtolower((string) pathinfo($download_path, PATHINFO_EXTENSION));
	$attachment_filename = $agreement_name . '.' . $download_ext;
	uno_debug([
		'scope' => 'webhook',
		'step' => 'certificate_download_extension_resolved',
		'request_id' => $request_id,
		'extension' => $download_ext,
		'attachment_filename' => $attachment_filename,
	]);

	$tmp_file = wp_tempnam('firma-owner-copy-' . $request_id);
	if (!$tmp_file) {
		return;
	}

	$tmp_attachment_file = trailingslashit(dirname($tmp_file)) . $attachment_filename;
	@rename($tmp_file, $tmp_attachment_file);
	$tmp_file = $tmp_attachment_file;

	$pdf_res = wp_remote_get($download_url, [
		'timeout' => 30,
		'stream' => true,
		'filename' => $tmp_file,
	]);

	if (!is_wp_error($pdf_res) && (int) wp_remote_retrieve_response_code($pdf_res) < 400 && file_exists($tmp_file) && filesize($tmp_file) > 0) {
		uno_debug([
			'scope' => 'webhook',
			'step' => 'certificate_download_ok',
			'request_id' => $request_id,
			'file_size' => filesize($tmp_file),
		]);

		$message_lines = [
			'Signing Request ID: ' . (string) ($request_body['id'] ?? $request_id),
		];

		if (!empty($users) && is_array($users)) {
			foreach ($users as $index => $user) {
				if (!is_array($user)) continue;
				$message_lines[] = 'Full name: ' . trim(((string) ($user['title'] . ' ' ?? '')) . ((string) ($user['first_name'] ?? '')) . ' ' . ((string) ($user['last_name'] ?? '')));
				$message_lines[] = 'First name: ' . (string) ($user['first_name'] ?? '');
				$message_lines[] = 'Last name: ' . (string) ($user['last_name'] ?? '');
				$message_lines[] = 'Email: ' . (string) ($user['email'] ?? '');
				$message_lines[] = 'Phone: ' . (string) ($user['phone_number'] ?? '');
				$message_lines[] = 'Street address: ' . (string) ($user['street_address'] ?? '');
				$message_lines[] = 'City: ' . (string) ($user['city'] ?? '');
				$message_lines[] = 'State/Province: ' . (string) ($user['state_province'] ?? '');
				$message_lines[] = 'Postal code: ' . (string) ($user['postal_code'] ?? '');
				$message_lines[] = 'Country: ' . (string) ($user['country'] ?? '');
				$message_lines[] = '';
			}
		}

		$mail_sent = wp_mail(
			$owner_email,
			$subject,
			implode("\n", $message_lines),
			['Content-Type: text/plain; charset=UTF-8'],
			[$tmp_file]
		);

		if (!$mail_sent) {
			uno_debug([
				'scope' => 'webhook',
				'step' => 'owner_copy_mail_failed',
				'request_id' => $request_id,
			]);
			uno_debug('Firma owner-copy email failed for request_id=' . $request_id, true);
		} else {
			uno_debug([
				'scope' => 'webhook',
				'step' => 'owner_copy_mail_sent',
				'request_id' => $request_id,
			]);
		}
	} else {
		uno_debug([
			'scope' => 'webhook',
			'step' => 'certificate_download_failed',
			'request_id' => $request_id,
		]);
		uno_debug('Firma owner-copy PDF download failed for request_id=' . $request_id, true);
	}

	@unlink($tmp_file);
}

/**
 * Handles signing_request.completed business logic.
 * Called after immediate webhook ACK has already been sent.
 */
function uno_process_signing_request_completed(array $event_data)
{
	$request_id = $event_data['signing_request']['id'] ?? '';

	uno_debug([
		'scope' => 'webhook',
		'step' => 'completed_received',
		'request_id' => $request_id,
	]);

	if (!$request_id) {
		return;
	}

	set_transient('firma_completed_' . md5((string) $request_id), 1, 10 * MINUTE_IN_SECONDS);
	$map_key = 'firma_active_request_' . md5((string) $request_id);
	$map_value = get_option($map_key);

	if (!is_array($map_value)) {
		delete_option($map_key);
		uno_debug([
			'scope' => 'webhook',
			'step' => 'completed_map_not_found',
			'request_id' => $request_id,
		]);
		return;
	}

	$email = (string) ($map_value['email'] ?? '');
	$template_id = (string) ($map_value['template_id'] ?? '');
	$agreement_group = uno_resolve_agreement_group_by_template($template_id);
	if ($email === '' || $template_id === '' || $agreement_group === '') {
		return;
	}

	update_option(uno_signed_option_key($email, $agreement_group), 1, false);
	uno_delete_active_request($email, $template_id);
	uno_debug([
		'scope' => 'webhook',
		'step' => 'completed_unlocked',
		'request_id' => $request_id,
		'email' => $email,
		'template_id' => $template_id,
		'agreement_group' => $agreement_group,
	]);
}

/**
 * Handles signing_request.recipient.declined business logic.
 * Called after immediate webhook ACK has already been sent.
 */
function uno_process_signing_request_declined(array $event_data)
{
	$request_id = $event_data['signing_request']['id'] ?? '';

	uno_debug([
		'scope' => 'webhook',
		'step' => 'declined_received',
		'request_id' => $request_id,
	]);

	if (!$request_id) {
		return;
	}

	set_transient('firma_declined_' . md5((string) $request_id), 1, 10 * MINUTE_IN_SECONDS);
}

/**
 * Sends immediate ACK and continues processing certificate.generated.
 */
function uno_ack_and_process_certificate_generated(array $event_data)
{
	$request_id = $event_data['signing_request']['id'] ?? '';
	uno_debug([
		'scope' => 'webhook',
		'step' => 'certificate_generated_ack_now',
		'request_id' => $request_id,
	]);

	uno_send_webhook_ack_now();
	uno_process_certificate_generated_owner_copy($event_data);
	exit;
}

/**
 * Sends immediate ACK and continues processing signing_request.completed.
 */
function uno_ack_and_process_signing_request_completed(array $event_data)
{
	$request_id = $event_data['signing_request']['id'] ?? '';
	uno_debug([
		'scope' => 'webhook',
		'step' => 'completed_ack_now',
		'request_id' => $request_id,
	]);

	uno_send_webhook_ack_now();
	uno_process_signing_request_completed($event_data);
	exit;
}

/**
 * Sends immediate ACK and continues processing signing_request.recipient.declined.
 */
function uno_ack_and_process_signing_request_declined(array $event_data)
{
	$request_id = $event_data['signing_request']['id'] ?? '';
	uno_debug([
		'scope' => 'webhook',
		'step' => 'declined_ack_now',
		'request_id' => $request_id,
	]);

	uno_send_webhook_ack_now();
	uno_process_signing_request_declined($event_data);
	exit;
}

/**
 * Registers two REST routes:
 *
 * POST /firma/v1/webhook
 *   Receives signed webhook events from firma.dev.
 *   signing_request.completed — sets firma_signed flag,
 *   cleans up active request.
 *   signing_request.certificate.generated — downloads PDF and
 *   emails owner copy.
 *   Signature verified via HMAC-SHA256 (X-Firma-Signature, 5-minute
 *   replay window).
 *
 * GET /firma/v1/signing-completed-status?request_id=...
 *   Polled by frontend after signing.completed postMessage to confirm
 *   the webhook was received before closing the modal and auto-submitting.
 */
add_action('rest_api_init', function () {
	register_rest_route('firma/v1', '/webhook', [
		'methods' => 'POST',
		'callback' => function (WP_REST_Request $req) {
			$raw_body = $req->get_body();

			// Parse and process event
			$data = json_decode($raw_body, true);
			$event_type = $data['type'] ?? '';
			$event_data = $data['data'] ?? [];
			uno_debug([
				'scope' => 'webhook',
				'step' => 'received',
				'event_type' => $event_type,
				'event_data' => $event_data
			]);

			switch ($event_type) {
				case 'signing_request.certificate.generated':
					uno_ack_and_process_certificate_generated($event_data);
					break;

				case 'signing_request.completed':
					uno_ack_and_process_signing_request_completed($event_data);
					break;

				case 'signing_request.recipient.declined':
					uno_ack_and_process_signing_request_declined($event_data);
					break;
			}

			return ['ok' => true, 'accepted' => true];
		},
		'permission_callback' => function (WP_REST_Request $req) {
			$raw_body = $req->get_body();
			$signature_header = $req->get_header('X-Firma-Signature');
			$secret = (string) \UnoSignature\Config::get('firma_webhook_secret', '');
			uno_debug([
				'scope' => 'webhook_permission',
				'step' => 'start',
				'has_signature_header' => !empty($signature_header),
				'has_secret' => !empty($secret),
			]);

			if (!$secret) {
				uno_debug([
					'scope' => 'webhook_permission',
					'step' => 'reject_missing_secret',
				]);
				return new WP_Error('invalid_signature', 'Missing webhook secret configuration', array('status' => 401));
			}

			if (!$signature_header) {
				uno_debug([
					'scope' => 'webhook_permission',
					'step' => 'reject_missing_signature_header',
				]);
				return new WP_Error('invalid_signature', 'Missing webhook signature', array('status' => 401));
			}

			$parts = array();
			foreach (explode(',', $signature_header) as $part) {
				$kv = explode('=', $part);
				if (count($kv) === 2) {
					$parts[trim($kv[0])] = trim($kv[1]);
				}
			}

			$timestamp = isset($parts['t']) ? $parts['t'] : null;
			$signature = isset($parts['v1']) ? $parts['v1'] : null;
			if (!$timestamp || !$signature) {
				uno_debug([
					'scope' => 'webhook_permission',
					'step' => 'reject_invalid_signature_header_format',
				]);
				return new WP_Error('invalid_signature', 'Invalid webhook signature header', array('status' => 401));
			}

			$timestamp_age = abs(time() - intval($timestamp));
			if ($timestamp_age > 300) {
				uno_debug([
					'scope' => 'webhook_permission',
					'step' => 'reject_expired_signature',
					'timestamp_age' => $timestamp_age,
				]);
				return new WP_Error('invalid_signature', 'Webhook signature expired', array('status' => 401));
			}

			$signed_payload = $timestamp . '.' . $raw_body;
			$expected_signature = hash_hmac('sha256', $signed_payload, $secret);
			if (!hash_equals($signature, $expected_signature)) {
				uno_debug([
					'scope' => 'webhook_permission',
					'step' => 'reject_signature_mismatch',
				]);
				return new WP_Error('invalid_signature', 'Invalid webhook signature', array('status' => 401));
			}

			uno_debug([
				'scope' => 'webhook_permission',
				'step' => 'ok',
			]);

			return true;
		}
	]);

	register_rest_route('firma/v1', '/signing-completed-status', [
		'methods' => 'GET',
		'callback' => function (WP_REST_Request $req) {
			$request_id = sanitize_text_field((string) $req->get_param('request_id'));
			if ($request_id === '') {
				return [
					'confirmed' => false,
					'declined' => false,
				];
			}

			$confirmed = (bool) get_transient('firma_completed_' . md5($request_id));
			$declined = (bool) get_transient('firma_declined_' . md5($request_id));
			return [
				'confirmed' => $confirmed,
				'declined' => $declined,
			];
		},
		'permission_callback' => '__return_true',
	]);
});
