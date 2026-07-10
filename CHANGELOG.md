# Changelog

All notable changes to unoSignature will be documented in this file.

## 0.1.33

- Visa minor child birthdates: always format contract dates as `d-M-Y`, parsing EPO `DD/MM/YYYY` correctly instead of relying on `strtotime()`.

## 0.1.32

- Fix update details modal: show the offered release changelog from GitHub when the installed plugin does not include that version yet.

## 0.1.31

- Visa `additional_applicants` textarea: put Primary Applicant first; show the Additional Applicant's header only when there are additional adults or minor children.
- Firma recipient address: use empty strings instead of `N/A` when optional billing fields are missing.

## 0.1.30

- Switch the GitHub updater to public releases: hardcoded `unostar/unoSignature`, no token required.
- Remove the Plugin updates (GitHub) section from Settings; drop stored GitHub credentials on upgrade.

## 0.1.29

- Restructure the GitHub repository to plugin-only files (clean public layout).
- Inline release automation in GitHub Actions; remove the `scripts/` folder from the repo tree.

## 0.1.28

- Redesign the SVG plugin icon: contract sheet, signature stroke, and verified seal on an indigo background.

## 0.1.27

- Replace plugin PNG icons with a single SVG icon: document, signature, and signed badge on an indigo background.
- Drop PNG icon assets; WordPress update UI uses the SVG only.

## 0.1.26

- Regenerate plugin icons with a transparent PNG background so rounded corners no longer show black edges in the WordPress admin UI.
- Add an SVG plugin icon for sharper rendering on update screens.

## 0.1.25

- Fix duplicate View details link on the Plugins screen (WordPress already adds one via the update API).
- Render plugin description HTML correctly in the details modal instead of showing escaped tags.

## 0.1.24

- Add plugin icon (128px and 256px) for the WordPress plugins list and update details modal.
- Drive release versions from the top `CHANGELOG.md` entry instead of auto-bumping the plugin header, so release numbers and notes stay in sync.

## 0.1.23

- Load WooCommerce product categories for signing rules with an unfiltered WordPress term query when WPML is active, so RU and EN categories are visible together in the admin selector.

## 0.1.21

- List all WooCommerce product categories (all WPML languages) in signing agreement rules; keep saved slugs when switching admin language.
- Remove unused `state` field from visa EPO adult contact parsing; EPO uses `city_region` and postcode only.

## 0.1.20

- Add visa EPO parser and text builder; visa_services checkout sends Firma read-only textarea overrides from cart contact blocks.
- Detect visa contact blocks by `firma_*` cssclass only; ignore questionnaire and EPO toggle classes.
- Track visa project docs in git (`.agent.md`, `assets/visa/README.md`, conversation history).

## 0.1.19

- Move visa Firma textarea field UUIDs into each Signing agreement rule row (per template).
- Remove separate Visa service agreement settings section and wp-config overrides for field UUIDs.

## 0.1.17

- Add Visa service agreement settings for three Firma textarea field UUIDs (additional applicants, representative, sponsor).
- Expose `Config::get_visa_firma_fields()` and `Config::is_visa_agreement_group()` for upcoming visa checkout flow.
- No hardcoded field UUIDs in plugin code; values come from admin settings or optional wp-config overrides.

## 0.1.16

- Add View details link on the Plugins screen with standard WordPress plugin information modal.
- Rewrite plugin description to reflect firma.dev API integration and configurable contract workflows.

## 0.1.15

- Move Firma debug and test mode settings below signing agreement rules.
- Render plugin update changelog in standard WordPress HTML format from CHANGELOG.md.

## 0.1.14

- Restore white card layout for signing rules with collapsible details panels.
- Add admin-only rule name field for clearer rule identification in settings.

## 0.1.13

- Rework signing agreement rules UI with native WP details panels and form-table layout.
- Narrow settings block to 480px and use standard buttons with fit-content select2 tags.

## 0.1.11

- Fix product search in signing agreement rules with substring, SKU, and ID matching.
- Improve multi-select tag layout in agreement rules settings.

## 0.1.10

- Publish GitHub Release notes from `CHANGELOG.md` instead of generic automated text.

## 0.1.9

- Allow saving an empty signing rules list and fully removing the last rule.
- Drop browser-required validation on template ID; empty rows are ignored on save.

## 0.1.8

- Redesigned signing agreement rules UI as compact vertical cards.
- Removed hardcoded legacy defaults; all configuration now comes from plugin settings.
- Added wp-config.php constant overrides with priority over saved options.
- Migrated old flat template ID settings into template_map rows on upgrade.

## 0.1.7

- Moved signing agreement rules (products, categories, templates) to Settings → unoSignature admin UI.
- Replaced hardcoded template map with configurable repeater using WooCommerce product search.

## 0.1.6

- Added Firma test API key and test mode toggle in plugin settings.
- Added local dev scripts for Firma create-request and fake-webhook flows.
- Report WordPress compatibility in private update metadata.

## 0.1.5

- Collapsed GitHub updater settings behind a closed details panel.

## 0.1.4

- Masked Firma API key and webhook secret in settings like the GitHub token.

## 0.1.2

- Renamed checkout helper functions from `unosignature_*` to `uno_*`.
- Switched plugin text domain from `reality-maker` to `unosignature`.

## 0.1.1

- Added automated GitHub Releases packaging for the installable plugin ZIP.
- Added private release update support for WordPress sites.
- Added settings screen for provider and updater configuration.
- Ported the initial WooCommerce checkout signature flow into the plugin structure.

## 0.1.0

- Initial unoSignature plugin scaffold.
