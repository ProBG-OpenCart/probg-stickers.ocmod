# Changelog

All notable changes to ProBG Product Stickers are documented in this file.

## [2.2.1] - Unreleased

### Fixed
- corrected OCMOD integration for the standard OpenCart **Bestseller**, **Latest**, and **Special** modules to use `$result['product_id']` instead of the undefined/null `$product_info['product_id']`, preventing PHP "Trying to access array offset on value of type null" notices.

## [2.2.0] - 2026-10-04

### Added
- multilingual renaming of the protected automated **New** and **Sale** stickers from module settings;
- configurable background and text colors for the automated **New** and **Sale** stickers.

### Changed
- automated sticker names are validated per configured store language and stored in `product_sticker_description` without weakening system-record delete/edit protection; missing language rows receive defaults without overwriting renamed values.

### Fixed
- loading sticker language strings inside Catalog → Products and Catalog → Categories no longer overwrites the native page heading with "Product Stickers" / "Стикери за продукти".
- legacy automated sticker records are normalized into the canonical `new` and `sale` records, preventing duplicate system stickers after upgrade while preserving existing names/colors when the canonical values are still defaults.

## [2.1.0] - 2026-10-02

### Added
- automated **New** system sticker based on product `date_added`;
- configurable number of days during which a product is considered new;
- automated **Sale** system sticker based on active `product_special` pricing;
- optional calculated discount percentage in the sale sticker;
- protected `system_key` records for automated stickers;
- automatic sticker rendering in the default OpenCart 3 product page, related products, product lists, and standard product modules;
- GitHub Actions validation for PHP syntax, OCMOD XML, and repository invariants.

### Changed
- system stickers can only be enabled or disabled from module settings;
- module settings are persisted with OpenCart 3 `editSetting()`;
- storage tables use InnoDB and utf8mb4;
- mapping tables include indexes for `product_sticker_id`;
- storefront sticker queries are cached and reduced for product lists;
- English and Bulgarian administration translations were normalized;
- sticker CSS now stacks multiple stickers without overlap;
- OCMOD code remains stable across releases to prevent duplicate modification records during upgrades.

### Fixed
- incorrect internal version on clean install;
- missing/duplicated language strings;
- non-functional language fallback path;
- inconsistent Twig/CSS class names in integration examples;
- missing automatic stylesheet loading;
- missing permission protection for the module update action;
- legacy v2.0 storage migration and index upgrades.

## [2.0.0]

- multilingual manual product stickers;
- product and category assignment;
- product/category filtering by sticker;
- global sticker position;
- OpenCart 3 OCMOD integration.
