# unoSignature

WordPress Universal Non-Opinionated Signature plugin.

## Requirements

- WordPress
- WooCommerce
- PHP 7.4+
- Signature provider credentials

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

Private GitHub Releases updates require:

- `UNOSIGNATURE_GITHUB_REPO`
- `UNOSIGNATURE_GITHUB_TOKEN`
- `UNOSIGNATURE_GITHUB_RELEASE_ASSET`

These can be defined in `wp-config.php` or configured in `Settings -> unoSignature`.

