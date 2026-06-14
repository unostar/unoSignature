<?php
/**
 * Shared helpers for local unoSignature Firma dev scripts.
 */

declare(strict_types=1);

function uno_dev_env_path(): string
{
	return __DIR__ . '/local.env';
}

function uno_dev_load_env(?string $path = null): array
{
	$path = $path ?? uno_dev_env_path();
	if (!is_readable($path)) {
		fwrite(STDERR, "Missing {$path}. Copy local.env.example to local.env and fill in values.\n");
		exit(1);
	}

	$env = [];
	foreach (file($path, FILE_IGNORE_NEW_LINES) as $line) {
		$line = trim($line);
		if ($line === '' || uno_dev_starts_with($line, '#')) {
			continue;
		}

		$parts = explode('=', $line, 2);
		if (count($parts) !== 2) {
			continue;
		}

		$env[trim($parts[0])] = trim($parts[1]);
	}

	return $env;
}

function uno_dev_truthy(string $value): bool
{
	return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
}

function uno_dev_firma_api_key(array $env): string
{
	$use_test = uno_dev_truthy($env['FIRMA_USE_TEST_KEY'] ?? '0');
	$key = $use_test ? ($env['FIRMA_TEST_API_KEY'] ?? '') : ($env['FIRMA_API_KEY'] ?? '');

	if ($key === '') {
		fwrite(STDERR, $use_test
			? "Missing FIRMA_TEST_API_KEY in local.env\n"
			: "Missing FIRMA_API_KEY in local.env\n");
		exit(1);
	}

	return $key;
}

function uno_dev_firma_request(string $method, string $url, array $env, ?string $body = null): array
{
	$headers = [
		'Authorization: Bearer ' . uno_dev_firma_api_key($env),
		'Accept: application/json',
	];

	if ($body !== null) {
		$headers[] = 'Content-Type: application/json';
	}

	$ch = curl_init($url);
	curl_setopt_array($ch, [
		CURLOPT_CUSTOMREQUEST => strtoupper($method),
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_HTTPHEADER => $headers,
		CURLOPT_TIMEOUT => 60,
	]);

	if ($body !== null) {
		curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
	}

	$response_body = curl_exec($ch);
	$error = curl_error($ch);
	$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);

	if ($response_body === false) {
		fwrite(STDERR, "HTTP request failed: {$error}\n");
		exit(1);
	}

	return [
		'code' => $code,
		'body' => $response_body,
		'json' => json_decode($response_body, true),
	];
}

function uno_dev_json_print(array $payload): void
{
	echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

function uno_dev_build_webhook_signature(string $raw_body, string $secret): string
{
	$timestamp = (string) time();
	$signature = hash_hmac('sha256', $timestamp . '.' . $raw_body, $secret);

	return 't=' . $timestamp . ',v1=' . $signature;
}

function uno_dev_starts_with(string $haystack, string $needle): bool
{
	return $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0;
}

function uno_dev_arg_value(array $argv, string $name, string $default = ''): string
{
	$prefix = '--' . $name . '=';
	foreach ($argv as $arg) {
		if (uno_dev_starts_with($arg, $prefix)) {
			return substr($arg, strlen($prefix));
		}
	}

	return $default;
}
