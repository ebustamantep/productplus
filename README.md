# PRODUCTPLUS (FICHA PRODUCTO) FOR [DOLIBARR ERP & CRM](https://www.dolibarr.org)

## Features

Quick product card, in the menu **Products > Product card** (*Ficha Producto*).

- Search the product by ref, label or barcode. An exact ref or barcode (typed or scanned) opens the product at once; otherwise the matching products are listed.
- On the card every field has its pencil *Modify*, like the native card:
  - Ref, label, barcode (value and type), description and categories, with the creation of a new category (and its parent) without leaving the card.
  - Selling price (price, HT/TTC and VAT rate), one row per level when the multiprices are enabled. The min price does not change.
  - Purchase price: cost price, weighted average price (read only) and the unit price of each supplier price. New supplier price (quantity 1).
  - Stock by warehouse: type the real quantity, the difference is saved as a stock correction movement. Products with lots/serial numbers are corrected on their native stock page.
- Every change goes through the native methods of Dolibarr (`Product::update()`, `updatePrice()`, `setCategories()`, `ProductFournisseur::update_buyprice()`, `correct_stock()`), so triggers, price history and stock movements are the same as on the native card.
- Without a barcode numbering module, a barcode already used by another product is refused.

The menu and the page need the permission **Use the product card** (*Users > Permissions > Ficha Producto*). The administrators get it when the module is enabled. What a user can change on the card still depends on the native permissions, as on the native pages: create/modify products (or services) for the data and the selling price, read prices with the advanced permissions, create categories for the new category, read suppliers for the purchase prices, read stock and create stock movements for the stock.

## Translations

`langs/en_US` and `langs/es_ES`.

## Installation

Unpack the zip in the `custom` directory of Dolibarr (or use *Setup > Modules > Deploy external module*), then enable the module **Ficha Producto** in *Setup > Modules*. After an upgrade, disable and enable the module again (it registers the menu).

Requires Dolibarr 19 or higher, PHP 7.2 or higher. MySQL/MariaDB and PostgreSQL.

## Licenses

GPLv3 or (at your option) any later version. See file COPYING for more information.
