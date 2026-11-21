# PRODUCTPLUS (FICHA PRODUCTO) FOR [DOLIBARR ERP & CRM](https://www.dolibarr.org)

## Features

Quick product card for the administrators, in the menu **Products > Product card** (*Ficha Producto*).

- Search the product by ref, label or barcode. An exact ref or barcode (typed or scanned) opens the product at once; otherwise the matching products are listed.
- On the card every field has its pencil *Modify*, like the native card:
  - Ref, label, barcode (value and type), description and categories.
  - Selling price (price, HT/TTC and VAT rate), one row per level when the multiprices are enabled. The min price does not change.
  - Purchase price: cost price, weighted average price (read only) and the unit price of each supplier price. New supplier price (quantity 1).
  - Stock by warehouse: type the real quantity, the difference is saved as a stock correction movement. Products with lots/serial numbers are corrected on their native stock page.
- Every change goes through the native methods of Dolibarr (`Product::update()`, `updatePrice()`, `setCategories()`, `ProductFournisseur::update_buyprice()`, `correct_stock()`), so triggers, price history and stock movements are the same as on the native card.
- Without a barcode numbering module, a barcode already used by another product is refused.

Only the administrators (`$user->admin`) see the menu and can open the page.

## Translations

`langs/en_US` and `langs/es_ES`.

## Installation

Unpack the zip in the `custom` directory of Dolibarr (or use *Setup > Modules > Deploy external module*), then enable the module **Ficha Producto** in *Setup > Modules*. After an upgrade, disable and enable the module again (it registers the menu).

Requires Dolibarr 19 or higher, PHP 7.2 or higher. MySQL/MariaDB and PostgreSQL.

## Licenses

GPLv3 or (at your option) any later version. See file COPYING for more information.
