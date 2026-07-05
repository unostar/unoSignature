# Local assets index

**Important:** `/assets/` snapshots, HTML dumps, and WooCommerce EPO copies stay **gitignored**. **Project docs** in this index (`INDEX.md`, `visa/README.md`, `chat-history/conversation-history.md`, etc.) and `.agent.md` **are tracked in git** when you ask to commit — they are not pushed to customers via the plugin zip, but belong in the repo for you and agents.

**Canonical visa Firma spec (always read first):** [`visa/README.md`](visa/README.md)

**Session log:** [`chat-history/conversation-history.md`](chat-history/conversation-history.md)

---

## Visa Firma integration (active)

| Path | Role |
|------|------|
| [`visa/README.md`](visa/README.md) | **Approved spec** — template, UUIDs, textarea text format, EPO rules, API payload, test key rules. Verified 04 Jul 2026. |
| [`chat-history/conversation-history.md`](chat-history/conversation-history.md) | Why decisions were made (Sessions 21–26). |
| [`../scripts/dev/README.md`](../scripts/dev/README.md) | Test SR scripts; test key only (tracked in git). |
| [`../scripts/dev/local.env`](../scripts/dev/local.env) | `FIRMA_TEST_API_KEY` (gitignored). |

Reference TEST SR: `0ed66013-d5f4-4650-910f-bbfe4bd04de9`  
Template: `8956775a-6869-4b8d-916b-f2129b9acf92`

---

## Archived / superseded (do not use for implementation)

| Path | Notes |
|------|-------|
| [`visa/page12-experiment.md`](visa/page12-experiment.md) | Early single-textarea blob experiment. Superseded by `visa/README.md`. |
| [`text`](text) | Old annexure copy draft (multi-line fields). Superseded by compact one-line format in README. |
| [`visa_service_agreement_my.html`](visa_service_agreement_my.html) | HTML draft of agreement (Jun 2026). Reference only; Firma template is source of truth. |
| [`../esignature-current.php`](../esignature-current.php) | Old site snippet with annexure blob approach (gitignored). Not plugin code. |
| [`bad_esignature.php`](bad_esignature.php) | Discarded snapshot. |
| [`bad_firma-standalone-shortcode.php`](bad_firma-standalone-shortcode.php) | Discarded snapshot. |

---

## Site / form tooling (separate from Firma plugin)

| Path | Role |
|------|------|
| [`wp-e-signature-additional-applicants-selector.code-snippets.php`](wp-e-signature-additional-applicants-selector.code-snippets.php) | **WP E-Signature 2.x** shortcode for optional “Add applicant” blocks on the **site form** (disable hidden required fields). **Not** unoSignature / Firma API. Deploy as WP Code Snippet if used on prod. |
| [`woocommerce-tm-extra-product-options/`](woocommerce-tm-extra-product-options/) | Local copy of TM EPO plugin for reference while mapping `cssclass`. |

---

## Other

| Path | Notes |
|------|-------|
| [`chat-history/api.firma.dev-chat-history.md`](chat-history/api.firma.dev-chat-history.md) | Long Firma API / consultation transcript. |
| [`noun-smart-contracts-6751521.svg`](noun-smart-contracts-6751521.svg) | Icon asset. |

---

## What goes in git vs local only

| Tracked in git | Local only (gitignored) |
|----------------|-------------------------|
| `unosignature/` plugin | `assets/` (this folder) |
| `scripts/dev/` (except `local.env`) | `.agent.md` |
| | `esignature-current.php`, `esignature-previous.php` |

When implementing visa **Settings** step, changes will be in **`unosignature/includes/`** (class-settings, class-config) — those **will** be committable.
