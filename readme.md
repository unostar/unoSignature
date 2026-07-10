# unoSignature

WordPress Universal Non-Opinionated Signature plugin.

## Requirements

- WordPress
- WooCommerce
- PHP 7.4+
- Signature provider credentials

## Settings

All plugin configuration lives in **Settings → unoSignature** and is stored in the WordPress `wp_options` table under the option key `unosignature_settings`.

### wp-config.php overrides

For backward compatibility, individual values can be overridden in `wp-config.php`. When a constant is defined, it takes priority over the saved option:

```php
define('FIRMA_API_KEY', '...');
define('FIRMA_TEST_API_KEY', '...');
define('FIRMA_USE_TEST_KEY', true);
define('FIRMA_WEBHOOK_SECRET', '...');
define('FIRMA_OWNER_COPY_EMAIL', 'owner@example.com');
define('FIRMA_DEBUG', true);
```

Signing agreement rules (products, categories, templates) are configured in the admin UI. For **visa_services** rules, set three Firma `template_field_id` UUIDs on the same row (additional applicants, representative, sponsor). No built-in defaults; copy UUIDs from your Firma template.

## Visa checkout (`visa_services`)

When the cart matches a signing rule with agreement group `visa_services`, checkout parses TM EPO cart rows by `cssclass` (`firma_primary_*`, `firma_representative_*`, `firma_sponsor_*`, additional applicants, minor children) and sends non-empty blocks as read-only Firma `fields[]` overrides. The signer is WooCommerce billing (`recipients[0]` + `custom_fields.birthdate`). Questionnaire fields and EPO toggles are ignored.

## Webhook

Configure firma.dev to send webhook events to:

```text
https://example.com/wp-json/firma/v1/webhook
```

Handled events:

- `signing_request.completed`
- `signing_request.certificate.generated`
- `signing_request.recipient.declined`

## Updates

Updates are delivered automatically from public [GitHub Releases](https://github.com/unostar/unoSignature/releases) (`unosignature.zip`). No GitHub settings or token required in WordPress.

## Firma test mode

Settings include a **test API key** and **Use test API key** toggle. Test requests do not consume live Firma credits (signing requests are watermarked).
