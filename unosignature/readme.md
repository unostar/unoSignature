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

Private GitHub Releases updates are configured in **Settings → unoSignature** (collapsed section):

- GitHub repo (e.g. `unostar/unoSignature`)
- GitHub PAT token
- Release asset name (`unosignature.zip`)

## Firma test mode

Settings include a **test API key** and **Use test API key** toggle. Test requests do not consume live Firma credits (signing requests are watermarked).
