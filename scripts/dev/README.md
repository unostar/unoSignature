# Local Firma dev scripts

These scripts call Firma directly or simulate webhooks without going through WooCommerce checkout.

## Setup

```bash
cp scripts/dev/local.env.example scripts/dev/local.env
# Edit local.env — keys stay local (gitignored)
```

Use `FIRMA_USE_TEST_KEY=1` and `FIRMA_TEST_API_KEY` for free watermarked test requests.

## Create signing request

```bash
php scripts/dev/create-request.php
php scripts/dev/create-request.php --template-id=YOUR-TEMPLATE-UUID
```

Prints `request_id`, `recipient_id`, and `sign_url`.

## Fake webhook

After a real signing request exists on the site (so active-request mapping is stored), or for decline/completed transient tests:

```bash
php scripts/dev/fake-webhook.php --type=signing_request.completed --request-id=UUID
php scripts/dev/fake-webhook.php --type=signing_request.recipient.declined --request-id=UUID
```

`signing_request.completed` unlocks checkout only when WordPress already has the active-request map for that `request_id` (created during checkout validation on the site).
