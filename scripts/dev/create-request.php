#!/usr/bin/env php
<?php
/**
 * Create and send a Firma signing request using the same payload shape as checkout.
 *
 * Usage:
 *   php scripts/dev/create-request.php
 *   php scripts/dev/create-request.php --template-id=UUID
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$env = uno_dev_load_env();
$template_id = uno_dev_arg_value($argv, 'template-id', $env['TEMPLATE_ID'] ?? '');

if ($template_id === '') {
	fwrite(STDERR, "Missing template ID. Set TEMPLATE_ID in local.env or pass --template-id=...\n");
	exit(1);
}

$first = $env['SIGNER_FIRST_NAME'] ?? 'Test';
$last = $env['SIGNER_LAST_NAME'] ?? 'Signer';
$title = $env['SIGNER_TITLE'] ?? 'Mr';
$birthdate = $env['SIGNER_BIRTHDATE'] ?? '1990-01-01';
$email = $env['SIGNER_EMAIL'] ?? 'test@example.com';
$phone = $env['SIGNER_PHONE'] ?? '+10000000000';
$address = $env['SIGNER_ADDRESS'] ?? '1 Test Street';
$city = $env['SIGNER_CITY'] ?? 'Brisbane';
$state = $env['SIGNER_STATE'] ?? 'QLD';
$postcode = $env['SIGNER_POSTCODE'] ?? '4000';
$country = $env['SIGNER_COUNTRY'] ?? 'AU';

$name = sprintf(
	'%s-%s-%s-DOB-%s',
	strtoupper(preg_replace('/[^A-Za-z0-9-]/', '', $last) ?? $last),
	preg_replace('/[^A-Za-z0-9-]/', '', $first) ?? $first,
	str_replace(['.', ',', ' '], '', $title),
	gmdate('d-M-Y', strtotime($birthdate))
);

$payload = [
	'template_id' => $template_id,
	'name' => $name,
	'recipients' => [[
		'designation' => 'Signer',
		'order' => 1,
		'first_name' => $first,
		'last_name' => $last,
		'title' => $title,
		'email' => $email,
		'phone_number' => $phone,
		'street_address' => $address,
		'city' => $city,
		'state_province' => $state,
		'postal_code' => $postcode,
		'country' => $country,
	]],
];

$create = uno_dev_firma_request(
	'POST',
	'https://api.firma.dev/functions/v1/signing-request-api/signing-requests',
	$env,
	json_encode($payload, JSON_UNESCAPED_SLASHES)
);

if ($create['code'] >= 400 || !is_array($create['json']) || empty($create['json']['id'])) {
	fwrite(STDERR, "Create failed (HTTP {$create['code']}):\n{$create['body']}\n");
	exit(1);
}

$request_id = (string) $create['json']['id'];
$recipient_id = (string) ($create['json']['recipients'][0]['id'] ?? '');

$send = uno_dev_firma_request(
	'POST',
	'https://api.firma.dev/functions/v1/signing-request-api/signing-requests/' . rawurlencode($request_id) . '/send',
	$env,
	json_encode(['send_signing_email' => false], JSON_UNESCAPED_SLASHES)
);

if ($send['code'] >= 400) {
	$already_sent = $send['code'] === 400
		&& is_array($send['json'])
		&& !empty($send['json']['error'])
		&& stripos((string) $send['json']['error'], 'already been sent') !== false;

	if (!$already_sent) {
		fwrite(STDERR, "Send failed (HTTP {$send['code']}):\n{$send['body']}\n");
		exit(1);
	}
}

uno_dev_json_print([
	'ok' => true,
	'request_id' => $request_id,
	'recipient_id' => $recipient_id,
	'sign_url' => $recipient_id !== '' ? 'https://app.firma.dev/signing/' . $recipient_id : null,
	'template_id' => $template_id,
	'use_test_key' => uno_dev_truthy($env['FIRMA_USE_TEST_KEY'] ?? '0'),
]);
