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

Private GitHub Releases updates are configured in **Settings → unoSignature**:

- GitHub repo (e.g. `unostar/unoSignature`)
- GitHub token
- Release asset name (`unosignature.zip`)
