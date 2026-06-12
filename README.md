# unoSignature

WordPress Universal Non-Opinionated Signature plugin by unostar.dev.

The installable WordPress plugin lives in `unosignature/`.

## Current Scope

- WooCommerce checkout signature gate for configured products.
- Firma/firma.dev integration as the first provider.
- Private GitHub Releases update flow.
- Provider-specific code should stay behind unoSignature abstractions where practical.

## Local Workflow

1. Edit locally.
2. Commit changes.
3. Push to `main`.
4. GitHub Actions bumps the plugin patch version, creates a tag and publishes `unosignature.zip`.
5. The WordPress site sees the update.
6. Update is installed manually from the WordPress admin.

## Repository Setup

Create a private GitHub repository, then connect this local repo:

```bash
git remote add origin git@github.com:unostar/unoSignature.git
git push -u origin main
```

After the first push, `.github/workflows/release.yml` will create the first release automatically.

## WordPress Update Settings

On the WordPress site, configure either constants in `wp-config.php` or the `Settings -> unoSignature` page.

Recommended for private update credentials:

```php
define('UNOSIGNATURE_GITHUB_REPO', 'unostar/unoSignature');
define('UNOSIGNATURE_GITHUB_TOKEN', 'github_pat_...');
define('UNOSIGNATURE_GITHUB_RELEASE_ASSET', 'unosignature.zip');
```

Do not commit tokens or API keys to this repository.

## Runtime Firma Settings

Existing constants are still supported:

```php
define('FIRMA_API_KEY', '...');
define('FIRMA_WEBHOOK_SECRET', '...');
define('FIRMA_OWNER_COPY_EMAIL', 'owner@example.com');
define('PAID_CONSULTATION_EN', '...');
define('PAID_CONSULTATION_RU_EN', '...');
define('FIRMA_DEBUG', false);
```

Webhook URL remains compatible with the current site:

```text
/wp-json/firma/v1/webhook
```

