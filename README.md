# unoSignature

WordPress Universal Non-Opinionated Signature plugin by unostar.dev.

The installable WordPress plugin lives in `unosignature/`.

## Current Scope

- WooCommerce checkout signature gate for configured products.
- Firma/firma.dev integration as the first provider.
- Private GitHub Releases update flow.

## Local Workflow

1. Edit locally.
2. Commit changes.
3. Push to `main` on `unostar/unoSignature`.
4. GitHub Actions bumps the plugin patch version, creates a tag and publishes `unosignature.zip`.
5. The WordPress site sees the update.
6. Update is installed manually from the WordPress admin.

## WordPress Settings

Configure everything in **Settings → unoSignature**:

- Firma API key, webhook secret, owner copy email
- Consultation template IDs
- GitHub repo: `unostar/unoSignature`
- GitHub token (private repo access)
- Release asset: `unosignature.zip`

Do not commit tokens or API keys to this repository.

Webhook URL:

```text
/wp-json/firma/v1/webhook
```
