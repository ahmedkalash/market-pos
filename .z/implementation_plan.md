# Product Catalog & Inventory Architecture Plan (V1.0 Final)

*This is the finalized implementation plan strictly scoped to the Store-Level architecture with uniform variant handling, utilizing relational attributes for powerful filtration.*

## 1. Feature Specifications

### A. All Products Are Variants
- Every single product in the system will have at least one Variant. We are removing the confusing distinction between "Simple" and "Variable" products.
- The main `Product` acts purely as an umbrella (Name, Category, Description). 
- All actual stock and prices live inside the `product_variants` table. Barcodes are managed relationally. 

### B. Strict Store-Level Products
- **100% Isolated:** Every product (and its variants) created belongs exclusively to a specific store via `store_id`. 
- **Inventory:** Because the products are isolated per store, the `quantity` (stock) is stored directly on the `product_variants` table.

### C. Global Reporting via Barcode
- Global sales calculation across different stores will rely completely on aggregating sales data via the universal **Barcode** (or SKU).

### D. Product Attributes & Filtration (Relational Approach)
- To properly handle variations and support **advanced cross-product filtering**, we will use strict relational tables for Attributes (e.g., "Size", "Color").
- **Consistency:** Users will create standardized Attributes and Values at the company level (e.g., defining "Color: Red" once).
- **Filtration:** Because the attributes are relational, we can instantly filter reports or the POS screen (e.g., "Show me all RED products", regardless of if it's a shirt or a hat).

### E. Unit of Measure (UoM) & Weighing
- **UoM Database Table:** Instead of a static Enum, we will create a `units_of_measure` table so companies can define custom units (e.g., "Box of 12", "Carton", "Meter", alongside "Pieces", "KG").
- **Default Setup**: When a new Company is registered, the system will automatically read from a configuration file (`config/company_unit_of_measurements.php`) and seed standard default UoMs (e.g., Piece, KG, Gram, Liter) into the company's database.
- **Implicit Weighing:** Pre-packaged items will use units like "Pieces" or "Bag". If an item is assigned a weight-based unit like "KG" or "Grams", the POS will inherently treat it as a loose item requiring scale measurement at checkout. Therefore, a separate `is_weighed` flag is unnecessary.
- **Pricing Logic:** The `price` on the variant is always exactly **per 1 unit** of the chosen UoM. (e.g., if UoM is Liter, the price is for 1 Liter. If it's a Box, it's for 1 Box).

### F. Tax Exceptions & Multi-Rate Tax System
- To handle complex VAT laws across the Middle East (e.g., Standard Rates, Reduced Rates, Zero-Rated, Exempt, and Schedule Taxes), we will implement a flexible `tax_classes` database table.
- Companies can define their own custom tax rates (e.g., "Standard 14%", "Zero Rated 0%").
- Products MUST link to a specific `tax_class_id` (`tax_class_id` will NOT be nullable).
- **Default Setup**: When a new Company is created, the system will automatically generate a default `tax_class` with a rate of 14% to streamline onboarding.
- **Company Table Refactoring**: The existing `vat_rate` column must be deleted from the `companies` table since taxes are now strictly managed via `tax_classes`.
- **Company Settings UI**: We will add a "Taxes" tab to the Company Settings page, allowing administrators to dynamically create and manage these `tax_classes` for use across all stores.
---

### G. Wholesale Price and retail price

### H. Premium Brand Management
Brands act as a vital metadata layer for products. In a premium system, brands aren't just text labels; they are robust entities that can be filtered, reported on, and displayed prominently in both the back-office and the POS terminal. 
- **Company Scope:** Brands are created at the `company_id` level, making them available across all stores.
- **Premium Data:** Each brand will have English/Arabic names and an active/inactive toggle. Note: Brands will not have logos or soft deletes for now.
- **Product Association:** Brands are attached to the umbrella `Product`, *not* the variants. This is because all variants of a "Nike" shirt are still "Nike".
- **Global Filtration:** The UI will support filtering products by brand in the catalog, and future reports will allow querying "Sales by Brand".

## X. Brands Implementation Details
### 1. Database & Model
- **`brands` Table Migration:**
    - `id`, `company_id`
    - `name_ar` (String, required)
    - `name_en` (String, required)
    - `is_active` (Boolean, default true)
    - `timestamps`
- **`Brand` Model:**
    - `BelongsToCompany` trait for automatic tenant scoping.
    - Scopes for `active()`.
- **`products` Table Update:**
    - Migration to add `brand_id` (Foreign Key, nullable, constrained to `brands`, null on delete).
    - Update `Product` model to define `public function brand(): BelongsTo`.

### 2. Filament Resource UI (`BrandResource`)
- **Premium List View:**
    - Columns: Name (Arabic & English), Active Status Toggle, and a column showing the count of associated products.
- **Form View:**
    - Grid layout for `name_ar` and `name_en`.
    - `Toggle` for `is_active`.
- **Permissions & Translations:**
    - Ensure Spatie permissions are fully configured (`view_any_brand`, `create_brand`, etc.) in `lang/ar/permissions.php` and `lang/en/permissions.php`.
    - Create dedicated translation files `lang/ar/brand.php` and `lang/en/brand.php`.

### 3. Product Catalog Integration
- **`ProductForm`:**
    - Add a searchable `Select` field for `brand_id` in the main product form section.
- **`ProductsTable`:**
    - Add a `SelectFilter` for Brands to allow store managers to easily filter the catalog.
    - Add an optional image column or text column to display the Brand in the product list.
## 2. Proposed Database Architecture

We will implement the following relational schema:

### [x] Phase 1: Global Settings & Attributes 
- [x]  **`tax_classes` Table:** 
- [x]   - `id`, `company_id`, `name`, `rate` (percentage).
- [x]  **`units_of_measure` Table:**
- [x]   - `id`, `company_id`, `name` (e.g., "Piece", "KG", "Box"), `abbreviation`.
- [x] `attribute_values` `attributes` `variant_attribute_value`

### [x] Phase 2: Core Product Data (The "Umbrella")
[x] **`products` Table:**
    - `id`, `store_id`, `category_id`, `tax_class_id`.
    - `name en/ar` , `description en/ar`.

### [x] Phase 3: Variants (The "Actual" Items)
[x]  **`product_variants` Table:**
    - `id`, `product_id`.
    - `name` (Nullable. e.g., "64GB - Black". If null, the POS uses the parent Product's name).
    - `uom_id` (Foreign Key).
    - `price`.
    - `price_is_negotiable`: Boolean (If true, cashier can change price at POS).
    - `minimum_price`: Decimal/Nullable (If negotiable, price cannot drop below this).
    - `quantity` (Current Stock).
    - `low_stock_threshold` (For low stock alerts).
    - `is_active`: Boolean.
[x] **`variant_attribute_value` (Pivot Table):**
    - `product_variant_id`, `attribute_value_id`. (Links the exact combination. E.g., Variant ID 1 is linked to "Red" and "Large").

### [x]  Phase 4: Barcodes
[x] **`product_barcodes` Table:** 
        - `id`,
        - `product_variant_id`,
        - `barcode`,
        - unique key on `(barcode)`
    *Implementation Notes:*
    - **No Type Validation:** We do not store or validate the barcode type (e.g., EAN-13, UPC). The system simply matches the scanned string against the database.
    - **No Weight-Embedded Parsers:** We do not need to support complex scale-generated barcodes that embed weight/price inside the barcode string.
    - **Label Printing:** For internally generated barcodes, the system will default to **Code 128** to keep the UI simple and hide complexity from the user.

---

## [x] Filament Resource UI Plan
-[x] **Com any Settings UI**: A new "Taxes" tab will be added to allow the company owner to create/edit/delete `tax_classes` (e.g., Standard 14%, Zero Rated).
-[x] **UoM UI**: A separate resource (or tab in Settings) to manage `units_of_measure`.
-[x] **Attributes UI**: A separate resource (under Settings or Catalog) to manage Company-wide Attributes and their values.
-[x] **ProductResource:** 
         [x] - **General Section:** Product Name, Description, Category, Tax Class.
         [x] - **Attributes Section:** A checkbox/select interface to attach predefined Attributes (e.g., checking "Size" and "Color").
         [x] - **Variants Section:** A RelationManager for `product_variants`.
         [x] - The user must add at least one row here to set the Price, Minimum Price (if negotiable), UoM, and Stock.
         [x] - The form will force them to select the specific Attribute Values (from dropdowns) based on the attributes they attached to the parent product.
         [x] - **Barcodes:** A Repeater or nested relation manager within the variant form to add one or more barcodes.

### Purchasing & Vendors (Weeks 12–13)
- [x] Vendor management
- [x] Purchase invoices
  - [x] add total amount to the form like the purchase return
  - [x] add actions in the table and view page to create return
- [x] Return Purchase invoices
  - [x] Return Purchase invoice form
  - [x] Return Purchase invoice table
  - [x] Return Purchase invoice pages(edit, create, view)





todo:
- [ ] Product Expiry Dates (Plan TODO): Supermarkets heavily rely on tracking expiry dates. This is missing from the variant schema.
- [x] filter variants (price, is_price_negosuable, stock cont) in the product list page
- [x] Brands (Section 1.H & Phase 2 TODO): We haven't created a brands table or attached it to products/variants.
- [x] Wholesale vs. Retail Pricing (Section 1.G): The plan mentions this, but currently, variants only have a single price and minimum_price.
    - in product_variants:
        -[x] add `wholesale_price`, `wholesale_is_price_negotiable`, `min_wholesale_price`, (`wholesale_qty_threshold` set to zero to let the cashier decide)
        -[x] rename `price` into `retail_price` and `min_price` into `min_retail_price` and `price_is_negotiable` into `retail_is_price_negotiable`
        -[x] add `purchase_price`
- [x] Additional UI Filters (Plan TODO): You requested filtering by price, is_price_negotiable, and stock count on the product list page.
- [x] Inventory Ledger (inventory_movements): Right now, stock is likely just a quantity integer. In a premium system, you cannot just overwrite a quantity. You need an immutable ledger (Stock In, Sale, Return, Damage Adjustment) to prevent employee theft and provide auditability.
- [x] Stock Adjustments UI: A dedicated interface for store clerks to manually adjust stock with a required reason (e.g., Damage, Theft, Expiry).
- [x] Low Stock Alerts: Building the dashboard widget or notification system for items hitting their low_stock_threshold.