#!/usr/bin/env php
<?php
/**
 * POST a signed Firma webhook payload to the WordPress REST endpoint.
 *
 * Usage:
 *   php scripts/dev/fake-webhook.php --type=signing_request.completed --request-id=UUID
 *   php scripts/dev/fake-webhook.php --type=signing_request.recipient.declined --request-id=UUID
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$env = uno_dev_load_env();
$type = uno_dev_arg_value($argv, 'type', 'signing_request.completed');
$request_id = uno_dev_arg_value($argv, 'request-id', '');
$webhook_url = $env['WEBHOOK_URL'] ?? '';
$secret = $env['FIRMA_WEBHOOK_SECRET'] ?? '';

if ($request_id === '') {
	fwrite(STDERR, "Missing --request-id=UUID\n");
	exit(1);
}

if ($webhook_url === '') {
	fwrite(STDERR, "Missing WEBHOOK_URL in local.env\n");
	exit(1);
}

if ($secret === '') {
	fwrite(STDERR, "Missing FIRMA_WEBHOOK_SECRET in local.env\n");
	exit(1);
}

$allowed = [
	'signing_request.completed',
	'signing_request.certificate.generated',
	'signing_request.recipient.declined',
];

if (!in_array($type, $allowed, true)) {
	fwrite(STDERR, "Unsupported --type. Allowed: " . implode(', ', $allowed) . "\n");
	exit(1);
}

$payload = [
	'type' => $type,
	'data' => [
		'signing_request' => [
			'id' => $request_id,
		],
	],
];

$raw_body = json_encode($payload, JSON_UNESCAPED_SLASHES);
$signature = uno_dev_build_webhook_signature($raw_body, $secret);

$ch = curl_init($webhook_url);
curl_setopt_array($ch, [
	CURLOPT_POST => true,
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_HTTPHEADER => [
		'Content-Type: application/json',
		'X-Firma-Signature: ' . $signature,
	],
	CURLOPT_POSTFIELDS => $raw_body,
	CURLOPT_TIMEOUT => 60,
]);

$response_body = curl_exec($ch);
$error = curl_error($ch);
$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response_body === false) {
	fwrite(STDERR, "Webhook request failed: {$error}\n");
	exit(1);
}

uno_dev_json_print([
	'ok' => $code >= 200 && $code < 300,
	'http_code' => $code,
	'event_type' => $type,
	'request_id' => $request_id,
	'response' => json_decode($response_body, true) ?? $response_body,
]);

if ($code >= 400) {
	exit(1);
}
