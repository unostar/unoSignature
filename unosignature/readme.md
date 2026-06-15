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
define('UNOSIGNATURE_GITHUB_REPO', 'unostar/unoSignature');
define('UNOSIGNATURE_GITHUB_TOKEN', '...');
define('UNOSIGNATURE_GITHUB_RELEASE_ASSET', 'unosignature.zip');
```

Signing agreement rules (products, categories, templates) are configured only in the admin UI.

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

Private GitHub Releases updates are configured in **Settings → unoSignature** (collapsed section):

- GitHub repo (e.g. `unostar/unoSignature`)
- GitHub PAT token
- Release asset name (`unosignature.zip`)

Release notes on GitHub are generated automatically from `CHANGELOG.md` when CI publishes a new version.

## Firma test mode

Settings include a **test API key** and **Use test API key** toggle. Test requests do not consume live Firma credits (signing requests are watermarked).
