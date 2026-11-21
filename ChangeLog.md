# CHANGELOG PRODUCTPLUS FOR [DOLIBARR ERP & CRM](https://www.dolibarr.org)

## 1.1.0

- Create a category from the card: on the form of Labels/Categories, line "New category" (name and optional parent). It is created on save with the native method and assigned to the product with the selected ones. A name that already exists under the same parent is reused, not duplicated. All in one transaction.

## 1.0.0

First version.

- Menu Products > Product card (*Ficha Producto*), administrators only.
- Search by ref, label or barcode; an exact ref or barcode opens the product at once.
- Pencil *Modify* on every field: ref, label, barcode, description, categories, selling price (by level with multiprices), cost price, supplier prices (unit price, new supplier price) and stock by warehouse (stock correction movement).
- A barcode already used by another product is refused when no barcode numbering module is set.
