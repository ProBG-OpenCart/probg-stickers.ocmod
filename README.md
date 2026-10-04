# ProBG Product Stickers for OpenCart 3

OCMOD extension for managing multilingual product stickers and displaying them in OpenCart 3 storefront product cards and product pages.

Bulgarian documentation: [README_BG.md](README_BG.md)

## Version

Current development version: **2.2.1**

## Features

- multilingual manual product stickers;
- assign manual stickers directly to products or to whole categories;
- filter products and categories by manually assigned sticker;
- configurable global sticker position;
- automated **New** sticker based on the product creation date;
- configurable number of days during which a product is considered new;
- automated **Sale** sticker for products with an active OpenCart special price;
- optional calculated discount percentage in the sale sticker, for example `Sale -20%`;
- automated stickers are protected system records: they cannot be deleted or edited as normal stickers; they can be enabled/disabled, renamed per language, and have their background/text colors changed from the module settings;
- active special prices are evaluated for the current customer group;
- automatic rendering in the default OpenCart 3 product page, category/search/manufacturer/special lists, and the standard Featured/Latest/Bestseller/Special modules;
- automatic stylesheet loading through OCMOD;
- upgrade-safe database migration to InnoDB/utf8mb4 with mapping-table indexes.

## Installation

1. Upload the matching `probg-stickers-<version>.ocmod.zip` package through **Extensions → Installer**.
2. Refresh **Extensions → Modifications**.
3. Install **Product Stickers** from **Extensions → Extensions → Modules**.
4. Open the module settings once after upgrading from an older version so the database migration can be applied.
5. The default OpenCart 3 theme is integrated automatically. For custom themes, adapt the product image/card templates when they do not output the provided sticker data. [readme.txt](readme.txt) contains product-card and product-page Twig examples.

## Automated stickers

### New products

Enable **New product sticker**, define its name for each store language, choose its background and text colors, and set how many days after `date_added` a product should be considered new.

### Sale products

Enable **Sale sticker**, define its name for each store language, choose its background and text colors, and automatically mark products that currently have a valid OpenCart `product_special` price for the active customer group. Enable **Show discount percentage** to append the calculated reduction to the configured sticker name.

## Support development

If this module is useful to you, you can support its development through Revolut:

[![Buy me a coffee](https://img.shields.io/badge/Buy%20me%20a%20coffee-Revolut-0075EB?style=for-the-badge&logo=revolut&logoColor=white)](https://revolut.me/vtotev)
