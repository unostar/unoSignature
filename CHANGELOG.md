# Changelog

All notable changes to unoSignature will be documented in this file.

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
