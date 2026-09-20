
### Phase 1 — Core Foundation & Settings (Weeks 1–4)
- [x] Laravel project setup + Filament installation
- [x] Multi-tenancy setup (Tenant-scoped DB logic)
- [x] Authentication (Filament Auth: login, register, email verify, forget password)
- [x] Handling Roles and Permissions (Spatie)
- [x] Implement settings architecture (Tenant vs Global settings)
- [x] Files and image storing configuration (S3/local disk)
- [x] profile page
- [x] User management
    [x]- enforce permissions and roles 
    [x]- a user can not manage or perform crud on him self instead he can use the edit profile page
    [x]- Store level users can not see or manage or perform crud on company-only-level users, or other stores users(table, forms, actions)
    [x]- a user can not manage or perform crud on his manager (table, forms, actions)
    [x]- only company admins can assign a company admin role to a user
    [x]- in store level, only store manager and company admins can assign a store manager role to a user in their store, 
    [x]- there can be multiple users with company-admin or store-manager roles
    [x]- only company level users can move a user form a store to another(edit form)
    [x]- in store level, when creating new user the user_id must be set automaticly
    [x]- company level user can not have a store assigned to him, but store level users must have a store assigned to them(edit->invaiable, create)
    [x]- store selecting should inclue an option or a placholder that can be used to make the user as a company level user e.g. all_stores
- [x] roles management
- [x] company Settings
   - [x] Implement the `add_advanced_settings_to_companies_table` migration.
   - [x] Refactor `CompanySettingsPage` to the new "Premium" layout with tabs and advanced rounding/taxation fields.
   - [x] (Testing) Implement feature tests for `CompanySettingsPage` to ensure 403 authorization for non-company-level users.
   - [x] enforce permissions
   - [x] implementation of Egypt-specific POS settings (14% VAT, EGP currency)
   - [x] Advanced Rounding (0.25, 0.50) for Egyptian cash transactions
- [X] localization (Arabic + English)
- [x] handle deleting files form storage when deleting or updating them
### Phase 2 — Products & Inventory (Weeks 5–7)
- [x] Store management
  - [x] store settings page
  - [x] store resource
    - [x] moving a user from a store to another store process
    - [x] add managers names to the stores table
- [x] Categories 

- [x] Product catalog
    - [x] products table: Stores common data (Name, Description, Category,...etc).
    - [x] product variant
        - [x] product_id, store_id, company_id
        - [x] Attributes & variants (unique per product_id)
        - [x] barcode (unique)
        - [x] sku (unique)
        - [x] price
        - [x] qty
        - [x] variant should be unique (composite index with product_id)
        - [ ] low stock alert quality 
        - [x] product units
        - [x] brands
- [x] Inventory tracking
- [x] Stock adjustments
- [x] Low stock alerts

### Phase 4 — Purchasing & Vendors (Weeks 12–13)
- [x] Vendor management
- [x] Purchase Invoices (Direct Receiving — existing variants only)
- [x] Purchase Invoices Return
- [x] Apply race condition fixes to Purchase Invoices & Returns: Wrap forms in a fieldset with `wire:loading.attr="disabled"` and apply `->live(debounce: 1000)` to all live fields to prevent overlapping requests (identical to Sale Invoice fixes).
- [x] Added custom invoice items/fees support with prorated refund calculations.


### Phase 4.1 — Sales & Returns Invoices
- [x] Sale Invoices basic CRUD
- [x] Invoice items repeater (variants, qty, prices)
- [x] Dynamic unit pricing based on variant selection
- [x] Subtotal, tax, shipping, and total calculations
- [x] Shipping destination and cost integration
- [x] Draft & Finalized states
- [x] Validate stock availability before finalizing
- [x] Deduct inventory upon invoice finalization
- [x] Sale Return Invoices CRUD
- [x] Link returns to original sale invoice
- [x] Restock inventory upon sale return
- [x] printing invoices to printer
- [x] Missing Granular Authorization Checks:
    Route invoice.print only requires 'auth'. It does not check whether the authenticated user has permissions like view_purchase_invoice or view_sale_invoice.
    Filament actions (Action::make('print_thermal')) also lack ->authorize(...).
- [x] Draft Invoices Lack "DRAFT / مسودة" Warning:
Currently, an invoice in draft status can be printed, and looks identical to a finalized invoice.
- [x] No Document Type Heading on Paper:
Neither invoicesprints a title heading (e.g., "Sale Invoice / فاتورة مبيعات" or "Sale Return / مرتجع مبيعات"). The document type is only inside the HTML <head><title>, so the physical paper receipt doesn't explicitly state what kind of document it is.
- [x] Shipping Cost Missing from Totals Breakdown:
    In SaleInvoiceService.php total_amount includes shipping_cost.
    In invoice.blade.ph, shipping_cost is never displayed. If an invoice has shipping, the printed subtotal and total will mathematically not add up.
- [x] Customer Display for Walk-in Retail:
    When a sale has no customer (customer_id is null), it prints Customer: followed by an empty line. It should say "Walk-in Customer / عميل نقدي" or be hidden
- [x] Invoices printing
- [x] - Add `purchase_price` to sale and return invoices item models/tables (SaleInvoiceItem, SaleReturnInvoiceItem) for accurate profit calculation and historical records.




## Finshed Todos
- [x] Enhance `InventoryMovementResource` to provide clickable links to source documents
