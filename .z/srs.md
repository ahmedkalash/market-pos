# Software Requirements Specification (SRS)
## Supermarket POS — SaaS Platform
**Product Name:** Market POS
**Stack:** Laravel + Filament
**Version:** 1.0 — Initial Release
**Region:** Egypt mainly and Arab Region (GCC & MENA)
**Date:** 2025

---

## 1. Introduction

### 1.1 Purpose

This document defines the requirements for **POS**, a SaaS-based Point of Sale system built for supermarkets operating in the Egypt and Arab region. The system is designed to be practical, fast to build, and easy to use — with room to grow.

### 1.2 What We Are Building (v1.0)

A web-based POS and back-office management system for supermarkets like **Mool**, built with **Laravel** (backend) and **Filament** (admin panel). It will be multi-tenant, meaning each supermarket chain gets their own isolated account.

### 1.3 What We Are NOT Building in v1.0

To keep things realistic, the following are **out of scope** for now:

- Mobile app
- Online ordering / delivery
- Self-checkout kiosk
- AI / forecasting
- E-commerce integration
- Loyalty mobile app
- EDI / ERP integration

> These will be added in future versions.

### 1.4 Suggested Tech Stack

| Layer                     | Technology                                       |
|---------------------------|--------------------------------------------------|
| Backend                   | Laravel 12                                       |
| Admin Panel / Back-office | Filament 4                                       |
| POS Terminal UI           | Laravel + Livewire (browser-based)               |
| Database                  | MySQL                                            |
| Cache                     | Redis                                            |
| Queue                     | Laravel Queue (Redis driver)                     |
| Storage                   | S3 or local disk                                 |
| Auth                      | Filament Auth, spaite roles and permpermissiosns |
| Deployment                | VPS / Laravel Forge / Hostinger                  |

---

## 2. Suggested Target Users

| User                     | Description                                                           |
|--------------------------|-----------------------------------------------------------------------|
| **Tenant Admin**         | The supermarket owner or IT manager. Sets up stores, users, products. |
| **Store-branch Manager** | Manages one store — inventory, staff, reports                         |
| **Cashier**              | Uses the POS terminal to process sales                                |
| **Stock Clerk**          | Receives goods, updates inventory                                     |
| **Accountant**           | Views reports and financial summaries                                 |

---

## 3. Multi-Tenancy Model

- Each supermarket chain (e.g., Mool) is a **tenant**
- Tenants are isolated by `tenant_id` on every database table
- Each tenant has:
    - Their own users and roles
    - Their own stores
    - Their own products and inventory
    - Their own settings (currency, language, tax)
---

## 4. Localization

Since the system targets the Arab region:

| Setting       | Details                                       |
|---------------|-----------------------------------------------|
| Languages     | Arabic (primary), English                     |
| RTL Support   | Yes — full RTL layout for Arabic              |
| Currency      | EGP (Primary for V1, configurable per tenant) |
| Date Format   | Gregorian (Hijri optional for V2)             |
| VAT           | 14% Egyptian VAT (configurable)               |
| Number Format | Arabic normal numerals optional               |

> Filament supports RTL and Arabic out of the box with minor config.

---

## 5. Functional Requirements

---

### 5.1 Authentication & Users

| ID           | Requirement                                                                       |
|--------------|-----------------------------------------------------------------------------------|
| AUTH-001     | Users register to the app                                                         |
| AUTH-002     | Users log in with email + password                                                |
| AUTH-003     | Failed login locks account after 5 attempts                                       |
| AUTH-004     | Each user is assigned one role (Admin, Manager, Cashier, Stock Clerk, Accountant) |
| AUTH-005     | Roles have predefined permissions (using Spatie Laravel Permission)               |
| AUTH-006     | Session expires after configurable inactivity period                              |
| ~~AUTH-007~~ | ~~Manager can override cashier actions~~                                          |

---

### 5.2 Store Management

| ID      | Requirement                                                |
|---------|------------------------------------------------------------|
| STR-001 | Tenant Admin can create and manage multiple store branches |
| STR-002 | Each store has: name, address, phone, working hours        |
| STR-003 | Each store has its own POS terminals                       |
| STR-004 | Each store has its own inventory                           |
| STR-005 | Store Manager is assigned to a specific store              |

---

### 5.3 Product & Catalog Management

| ID          | Requirement                                                                                                        |
|-------------|--------------------------------------------------------------------------------------------------------------------|
| PRD-001     | Create, edit, delete products                                                                                      |
| PRD-002     | Each product has: name (AR + EN), barcode, SKU, category, unit of measure, cost price, selling price, tax category |
| PRD-003     | Products can be assigned to categories (unlimited levels)                                                          |
| ~~PRD-004~~ | ~~Support product image upload~~                                                                                   |
| PRD-005     | Support multiple barcodes per product (EAN-13, EAN-8, QR)                                                          |
| PRD-006     | Support units of measure: piece, kg, gram, liter, pack                                                             |
| ~~PRD-007~~ | ~~Weighted products (sold by weight)~~                                                                             |
| PRD-008     | Bulk import products via Excel/CSV                                                                                 |
| PRD-009     | Export product list to Excel/CSV                                                                                   |
| PRD-010     | Mark products as active or inactive                                                                                |
| PRD-011     | Track product expiry date                                                                                          |

---

### 5.4 Inventory Management

| ID      | Requirement                                                                                            |
|---------|--------------------------------------------------------------------------------------------------------|
| INV-001 | Track stock quantity per product per store                                                             |
| INV-002 | Stock automatically decreases on each sale                                                             |
| INV-003 | Stock automatically increases on goods receiving                                                       |
| INV-004 | Manual stock adjustment with reason (damage, theft, expiry, correction)                                |
| INV-005 | Set minimum stock (reorder point) per product per store                                                |
| INV-006 | Low stock alert notification (in-app)                                                                  |
| INV-007 | Stock transfer between stores                                                                          |
| INV-008 | Stocktake (physical count) — create a count sheet, enter actual quantities, system calculates variance |
| INV-009 | View stock movement history per product                                                                |

---

### 5.5 Purchase Orders & Receiving

| ID     | Requirement                                                           |
|--------|-----------------------------------------------------------------------|
| PO-001 | Create purchase orders for vendors                                    |
| PO-002 | PO includes: vendor, products, quantities, expected cost price        |
| PO-003 | PO statuses: Draft → Sent → Partially Received → Received → Cancelled |
| PO-004 | Receive goods against a PO (full or partial)                          |
| PO-005 | On receiving, stock is updated automatically                          |
| PO-006 | Flag discrepancies (received more or less than ordered)               |
| PO-007 | Print or export PO as PDF                                             |

---

### 5.6 Vendor Management

| ID      | Requirement                                                                          |
|---------|--------------------------------------------------------------------------------------|
| VEN-001 | Create and manage vendor profiles                                                    |
| VEN-002 | Vendor details: name (AR + EN), contact person, phone, email, address, payment terms |
| VEN-003 | View all POs linked to a vendor                                                      |
| VEN-004 | View purchase history per vendor                                                     |

---

### 5.7 POS Terminal

> The POS runs in the browser (Livewire-based), full screen on the cashier's machine.

#### 5.7.1 Sales

| ID      | Requirement                                                     |
|---------|-----------------------------------------------------------------|
| POS-001 | Cashier logs in                                                 |
| POS-002 | Scan barcode to add item to cart                                |
| POS-003 | Search products by name or SKU                                  |
| POS-004 | Manually enter quantity per item                                |
| POS-005 | Remove item from cart                                           |
| POS-006 | Hold current transaction and start a new one (park)             |
| POS-007 | Recall a parked transaction                                     |
| POS-008 | Display subtotal, VAT amount, and total clearly                 |
| POS-009 | Display item name in Arabic                                     |
| POS-010 | For weighted items — cashier enters weight or connects to scale |

#### 5.7.2 Payments

| ID      | Requirement                                                                       |
|---------|-----------------------------------------------------------------------------------|
| PAY-001 | Accept cash — system calculates change                                            |
| PAY-005 | Record payment method for every transaction                                       |

> Card terminal integration (Stripe Terminal, HyperPay, etc.) will come later.

#### 5.7.3 Discounts

| ID       | Requirement                                                                 |
|----------|-----------------------------------------------------------------------------|
| DISC-001 | Apply percentage discount on a line item (manager authorization required)   |
| DISC-002 | Apply fixed amount discount on a line item (manager authorization required) |
| DISC-003 | Apply discount on entire transaction                                        |
| DISC-004 | Apply automatic promotions (if configured)                                  |

#### 5.7.4 Receipts

| ID      | Requirement                                                                                                |
|---------|------------------------------------------------------------------------------------------------------------|
| REC-001 | Print receipt on thermal printer via ESC/POS                                                               |
| REC-002 | Receipt includes: store name, date/time, cashier name, itemized list, VAT breakdown, total, payment method |
| REC-003 | Receipt supports Arabic text                                                                               |
| REC-004 | Option to email receipt to customer                                                                        |
| REC-005 | Reprint last receipt                                                                                       |
| REC-006 | Tenant can configure receipt header and footer                                                             |

#### 5.7.5 Returns & Exchanges

| ID      | Requirement                                                         |
|---------|---------------------------------------------------------------------|
| RET-001 | Look up original transaction by receipt number or date              |
| RET-002 | Select items to return (partial or full)                            |
| RET-003 | Refund to cash                                                      |
| RET-005 | Inventory is updated on return                                      |
| RET-006 | Return reason is required                                           |
| RET-007 | Support exchanges (negative quantity items combined with new items) |

#### 5.7.6 Cash Management

| ID       | Requirement                                                |
|----------|------------------------------------------------------------|
| CASH-001 | Cashier declares opening float at shift start              |
| CASH-002 | Cashier declares closing cash at shift end                 |
| CASH-003 | System calculates expected cash vs actual (over/short)     |
| CASH-004 | Cash drop during shift (with amount and reason)            |
| CASH-005 | Petty cash out (with amount and reason)                    |

---

### 5.8 Promotions & Pricing

| ID        | Requirement                                                               |
|-----------|---------------------------------------------------------------------------|
| PROMO-001 | Create product-level promotions (percentage off, fixed amount off)        |
| PROMO-002 | Create Buy X Get Y Free promotions                                        |
| PROMO-003 | Create multi-buy promotions (e.g., 3 for the price of 2)                  |
| PROMO-004 | Set promotion start and end dates                                         |
| PROMO-005 | Apply promotion to specific products or categories                        |
| PROMO-006 | Apply promotion to specific stores                                        |
| PROMO-007 | System auto-applies active promotions at POS                              |
| PROMO-008 | Multiple price lists (Retail, Wholesale) — configurable per customer type |

---

### 5.9 Customer Management (Basic CRM)

| ID      | Requirement                                               |
|---------|-----------------------------------------------------------|
| CRM-001 | Create customer profile: name, phone, email, address      |
| CRM-002 | Attach customer to a transaction at POS (search by phone) |
| CRM-003 | View customer purchase history                            |
| CRM-004 | ~~Store credit balance per customer~~                     |
| CRM-005 | Customer can be flagged as: Retail, Wholesale, VIP,...etc |

> Full loyalty program (points, tiers) will come later.

---

### 5.10 Employee Management

| ID      | Requirement                                                                  |
|---------|------------------------------------------------------------------------------|
| EMP-001 | Create and manage employee profiles                                          |
| EMP-002 | Employee details: name (AR + EN), national ID, role, store assignment, phone |
| EMP-003 | Clock-in / clock-out at POS terminal                                         |
| EMP-004 | View timesheet per employee                                                  |
| EMP-005 | View cashier performance: number of transactions, voids, discounts given     |
| EMP-006 | Assign POS credintails per cashier                                           |

---

### 5.11 Reporting

| ID      | Requirement                                 |
|---------|---------------------------------------------|
| REP-001 | Daily sales report (per store, per cashier) |
| REP-002 | Sales by product and category               |
| REP-003 | Sales by payment method                     |
| REP-004 | Z-Report (end of day closing report)        |
| REP-005 | X-Report (interim sales report)             |
| REP-006 | Stock level report                          |
| REP-007 | Low stock report                            |
| REP-008 | Stock movement report                       |
| REP-009 | Stocktake variance report                   |
| REP-010 | VAT report (total VAT collected per period) |
| REP-011 | Top selling products report                 |
| REP-012 | Returns and refunds report                  |
| REP-013 | Cashier cash reconciliation report          |
| REP-014 | Purchase orders report                      |
| REP-015 | All reports exportable to PDF and Excel     |

---

### 5.12 Tax (VAT) Management

| ID      | Requirement                                                     |
|---------|-----------------------------------------------------------------|
| TAX-001 | Configure VAT rate per tenant (e.g., 15% KSA, 5% UAE)           |
| TAX-002 | Assign tax category per product (taxable, zero-rated, exempt)   |
| TAX-003 | VAT is calculated and displayed separately on receipts          |
| TAX-004 | VAT compliant receipts (includes Tax Registration Number — TRN) |
| TAX-005 | VAT report for tax filing                                       |

---

### 5.13 SaaS / Tenant Management

| ID       | Requirement                                                                 |
|----------|-----------------------------------------------------------------------------|
| SAAS-001 | Super Admin can create, view, suspend, and delete tenants                   |
| SAAS-002 | Each tenant has an isolated back-office panel                               |
| SAAS-003 | Tenant settings: store name, logo, currency, language, VAT number, VAT rate |
| SAAS-004 | Super Admin can log in as any tenant (impersonation) for support            |
| SAAS-005 | Basic subscription management: Active, Suspended, Trial                     |
| SAAS-006 | Tenant trial period (e.g., 14 days)                                         |
| SAAS-007 | Suspend tenant access if subscription lapses                                |

> Full billing / payment integration for subscriptions (Stripe, etc.) will come in later.

---

## 6. Non-Functional Requirements

| ID      | Requirement                                                                                     |
|---------|-------------------------------------------------------------------------------------------------|
| NFR-001 | System must be fully usable in Arabic (RTL)                                                     |
| NFR-002 | POS screen should be responsive and work on touchscreen monitors                                |
| NFR-003 | POS transaction (scan to total) must complete in under 1 second                                 |
| NFR-004 | System should support up to 20 concurrent POS terminals per store in v1                         |
| NFR-005 | All sensitive data (passwords, tokens) must be encrypted                                        |
| NFR-006 | System must be accessible over HTTPS only                                                       |
| NFR-007 | Daily automated database backup                                                                 |
| NFR-008 | Role-based access strictly enforced on all routes and actions                                   |
| NFR-009 | ~~POS must continue to work in basic offline mode (cash sales only) and sync when reconnected~~ |

---

## 7. Filament Panel Structure

### 7.1 Panels

| Panel             | URL            | Description                         |
|-------------------|----------------|-------------------------------------|
| Super Admin Panel | `/super-admin` | Manage tenants, platform settings   |
| Tenant admin      | `/dashboard`   | Store management, products, reports |
| POS Terminal      | `/dashboard`   | Cashier-facing full-screen POS      |

### 7.2 Filament Resources (Back-office)

| Resource        | Description                     |
|-----------------|---------------------------------|
| Stores          | Manage store branches           |
| Products        | Product catalog                 |
| Categories      | Product categories              |
| Inventory       | Stock levels and adjustments    |
| Purchase Orders | PO creation and receiving       |
| Vendors         | Vendor management               |
| Customers       | Customer profiles               |
| Employees       | Staff management                |
| Promotions      | Discount and promotion rules    |
| Reports         | All report pages                |
| Settings        | Tenant configuration            |
| Users & Roles   | User management and permissions |

---

## 8. Database — Key Tables

```
tenants
stores
users
roles / permissions (Spatie)
products
product_categories
product_barcodes
inventory (store_id, product_id, quantity)
inventory_movements
purchase_orders
purchase_order_items
vendors
transactions
transaction_items
transaction_payments
customers
employees
shifts
cash_movements
promotions
promotion_rules
tax_categories
settings
```

---

## 9. Development Phases

> Note: V1 focuses heavily on the Customer/Tenant interfaces. Super Admin tools and SaaS billing will be pushed to a later iteration.

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

### Phase 3 — POS Terminal (Weeks 8–11)
- [ ] Cashier login
- [ ] Barcode scanning
- [ ] Cart management
- [ ] Payment (cash, card manual, split)
- [ ] Receipt printing
- [ ] Cash management (shift open/close)
- [ ] Transaction hold/recall
- [ ] POS User Experience (UX)
    - [ ] Hotkeys: The cashier interface must be fully navigable via keyboard (F-keys, arrows, Enter) without ever touching a mouse.
### Phase 4 — Purchasing & Vendors (Weeks 12–13)
- [x] Vendor management
- [x] Purchase Invoices (Direct Receiving — existing variants only)
- [x] Purchase Invoices Return
- [ ] **TODO: Apply race condition fixes to Purchase Invoices & Returns:** Wrap forms in a fieldset with `wire:loading.attr="disabled"` and apply `->live(debounce: 1000)` to all live fields to prevent overlapping requests (identical to Sale Invoice fixes). 


### Phase 4.1 — Sales & Returns Invoices
- [x] Sale Invoices basic CRUD
- [x] Invoice items repeater (variants, qty, prices)
- [x] Dynamic unit pricing based on variant selection
- [x] Subtotal, tax, shipping, and total calculations
- [x] Shipping destination and cost integration
- [x] Draft & Finalized states
- [x] Validate stock availability before finalizing
- [x] Deduct inventory upon invoice finalization
- [ ] Sale Return Invoices CRUD
- [ ] Link returns to original sale invoice
- [ ] Restock inventory upon sale return
- [ ] Invoice printing and PDF export

### Phase 4.2 — Advanced Procurement (V2 / Later)
- [ ] Formal Purchase Order workflow: `Draft → Sent → Approved → Received`
- [ ] Receive goods against an open PO (partial or full)
- [ ] Discrepancy flagging: ordered qty vs. received qty variance report
- [ ] **Inline product/variant creation during Purchase Invoice:** Allow clerks to create a new Product or Variant directly inside the invoice line-item repeater without leaving the page. Currently, clerks must first create the product in the Products module (with qty=0) and then return to the invoice. See `implementation_plan_direct_receiving.md` — the `finalize()` service is already architected to support this with zero changes to the service layer.
- [ ] Approval layers: Manager authorization for POs exceeding a configurable amount
- [ ] PDF export of Purchase Invoice
- [ ] Vendor performance reporting (delivery accuracy, price consistency)

### Phase 5 — CRM & Promotions (Weeks 14–15)
- [ ] Customer profiles
- [ ] Store credit
- [ ] Basic promotions engine

### Phase 6 — Reports & Tax (Weeks 16–17)
- [ ] Sales reports
- [ ] Stock reports
- [ ] VAT report
- [ ] Z/X reports
- [ ] PDF + Excel export
- [ ] Historical Tax Snapshotting on Orders (For later, but important)
When a government changes a tax rate (e.g., KSA changing from 5% to 15% a few years ago), merchants simply update their tax_classes rate. However, past orders must not change. Recommendation: When we design the orders and order_items tables (later in the plan), we must ensure we save the exact tax_rate and tax_amount as static numbers on the order row at the moment of sale. We cannot just calculate it on the fly using the current product's tax class.

### Phase 7 — Polish & Launch (Week 18)
- [ ] Arabic RTL testing
- [ ] POS performance tuning
- [ ] Security review
- [ ] Deployment
- [ ] Tenant onboarding flow

### Phase 8 - todo
- [ ] Activity logs
- [ ]- implement Audit Logs / Transfer History for moving users between stores
- [ ] phone login and notifications
- [ ] soft deletes
- [ ] **Inline product/variant creation during Purchase Invoice (V2):** A "Create New Product" modal button directly inside the invoice line-item repeater. Architecture is already prepared — the `finalize()` service only receives a `product_variant_id` so no service changes are needed, only a UI addition.
- [ ] **delete store process: handle what should happen when deleting a store from a company**
- [ ] **delete user process: handle what should happen when deleting a user from a company or a store**
- [ ] metadata for ETA E-invoicing (V2/Regional)
- [ ] ability to scan barcodes vai camera 
- [ ] `variant_price_tiers` table: Support for multiple price tiers per variant (e.g., VIP price, special contract price). Prerequisite for PROMO-008 (Multiple price lists configurable per customer type).
- [ ] Exportable/Printable filter results: Add export action to Products & Variants tables that exports the currently filtered results to CSV/Excel.
- [ ] **Price change audit log: Track every price change with a `price_audit_logs` table (variant_id, field_changed, old_value, new_value, changed_by, changed_at) to prevent fraud and provide historical pricing data.**
- [ ] Low Stock Alerts — Dashboard widget + in-app notifications for items hitting their threshold. Quick win once the ledger exists.
- [ ] Concurrency control(prices, stock qty,...etc)
- [ ] Future POS Integration:
    - [ ] When we build the Point of Sale (POS) checkout and the Purchasing/Receiving flows, they will hook directly into InventoryService::recordMovement(MovementType::Sale) and MovementType::StockIn.
- [ ] COGS (Cost of Goods Sold) Tracking (Later):
    - [ ] Link movements to purchase prices so the system can calculate accurate profit margins over time based on inventory flow (FIFO/LIFO).
- [ ] Polymorphic Reference Linking (V2):
    - [ ] Enhance `InventoryMovementResource` to provide clickable links to source documents (Invoices, POs, Transfers) once those modules are built.
- [ ] Visual Inventory Trends (Charts) — The "Wow" Factor
    The Idea: Humans are bad at reading tables but great at reading charts.
    The Feature: Add a small "Performance Chart" at the top of the ledger. It shows a line graph of the stock level for the selected variant over the last 30 days.
    The Result: A manager can instantly see: "Oh, we are selling this faster than we are buying it; we will run out in 3 days."
- [ ] Smart Reconciliation (Stocktake Mode)
    The Idea: Periodic counting is a nightmare for staff.
    The Feature: A specialized view where they scan items and enter the "Physical Count." The system automatically calculates the difference and creates the "Manual Edit" records for them.
    The Result: It turns a 5-hour job into a 30-minute job.
- [ ] **Instant Exporting (Excel/PDF)**
    The Idea: Accountants need the data outside the app.
    The Feature: A "Export Audit Log" button that generates a professional Excel file with all current filters applied.
- [ ] Implement ShouldQueue on the NotifyLowStock notification. This offloads the delivery to your queue workers (Redis/Database), keeping the POS interface lightning fast.
- [ ] Real-Time: Instant Alerts (Zero Polling):
      You recently updated the polling to 60s. In a high-traffic supermarket, 60 seconds might be too long for a critical stock alert.
      Suggestion: Use Laravel Reverb (WebSockets). Instead of the browser "asking" if there are new alerts every minute, the server "pushes" them instantly. This makes the app feel alive and responsive.
- [ ] Robustness Check: Are we missing anything? see: **.z\stock_notifications_for_bulk_importing.md**
      One small edge case in the industry is Bulk Updates. If a manager imports 500 products at once, they might get 500 individual notifications.
      Enterprise standard: We could implement a "Buffer" or "Digest" system where, if more than X alerts fire in a minute, we send a single summary notification: "15 items hit low stock in Store A."
- [ ] **Standardized Currency Codes (Native Money Formatting):** Migrate `currency_symbol` across the application to a standard ISO 4217 `currency_code` (e.g., USD, EGP, EUR). Update Company Settings to use a predefined list of codes rather than free text symbols. This will allow the application to fully utilize Filament's native `->money()` field and PHP's `NumberFormatter` for automatic locale-aware symbol formatting across the entire app.
- [ ] **Advanced Payment Methods & Tracking (V2):** 
      Allow company admins/store managers to create and manage various payment methods (Bank Transfer, Vodafone Cash, Credit Card, etc.).
      - Require a unique **Receiver Identifier** for each method (e.g., last 4 digits of a bank account, mobile wallet number) to track exactly where funds are deposited.
      - Require a **Source Identifier** (e.g., buyer's bank account, mobile wallet, or card number) for transactions to verify the sender.
      - Add comprehensive payment tracking and auditing to ensure every single payment succeeds and prevent fraud/theft.
      - Alter the invoices and receipts to display the received payment account identifier (where the money was deposited).

- [ ] Advanced Barcode Parsing (Weighted Items)
      In the Arab region (and globally), deli counters and butchers use scales that print a specific barcode (e.g., starts with 20 or 21, followed by the PLU code, then the weight/price). While your plan says "We do not need to support complex scale-generated barcodes", adding a smart barcode parser that automatically extracts weight and calculates price at the POS screen is a massive selling point for a premium supermarket POS.
- [ ] add discounts feature to the purchase invoices and also handle it their return
- [ ] **Streamlined Localization UI & Nullable English Fields (V2):**
      - Make all English columns (`name_en`, `description_en`, etc.) nullable in the database, as many users in the Arabic market may not require English data.
      - Refactor the UI (tables and forms) to consolidate the display of localized names. Instead of separate columns for Arabic and English, display the primary Arabic name prominently with the English name underneath it as a secondary description, saving horizontal space and simplifying the interface.
---

## 10. Out of Scope for v1.0

The following will be planned for **v2.0 and beyond**:

| Feature                                    | Target Version |
|--------------------------------------------|----------------|
| Loyalty points program                     | v2.0           |
| Online ordering / delivery                 | v2.0           |
| Mobile app                                 | v2.0           |
| Card terminal integration (HyperPay, etc.) | v2.0           |
| Automated subscription billing             | v2.0           |
| Self-checkout kiosk                        | v3.0           |
| AI demand forecasting                      | v3.0           |
| E-commerce integration                     | v2.0           |
| HR / Payroll                               | v2.0           |
| ERP integration                            | v3.0           |
| Offline support                            | v3.0           |
| other countries except egypt               | v3.0           |

---

## 11. Assumptions

- Supermarkets bring their own hardware (receipt printer, barcode scanner, cash drawer)
- All hardware must support standard protocols (ESC/POS for printers)
- **Barcodes:** Scanners will function as native USB keyboards (plug-and-play).
- **Printing:** Receipt printing will utilize native browser print dialogs (cashier manually clicks "Print").
- Internet connection is available at all stores
- Initial deployment targets the **Egypt** market first
- VAT compliance follows standard Egyptian VAT (ETA integration planned for V2 if required)

---

*SuperPOS v1.0 SRS — Realistic Edition*
*Built with Laravel + Filament | Arab Region Focus*
