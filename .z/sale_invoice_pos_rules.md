# Sale Invoice — POS Client-Side Calculation & Validation Rules

> **Purpose:** This document is the single source of truth for all financial calculations and
> validation rules that must be implemented on the **client side** of the POS terminal.
> It is derived directly from the existing server-side authoritative logic
> (`SaleInvoiceService`, `SaleInvoiceForm`, `SaleInvoiceItem`, `SaleInvoiceExtraItem` models).
>
> The server always re-validates and re-calculates everything independently.
> Client-side rules exist purely to give the cashier **instant feedback** before the
> request ever reaches the server.

---

## Table of Contents

1. [Data Model Glossary](#1-data-model-glossary)
2. [Price Types](#2-price-types)
3. [Discount Types](#3-discount-types)
4. [Extra Item Action Types](#4-extra-item-action-types)
5. [Line-Item Level Calculations](#5-line-item-level-calculations)
6. [Line-Item Level Validation Rules](#6-line-item-level-validation-rules)
7. [Invoice-Level Aggregation (Grand Totals)](#7-invoice-level-aggregation-grand-totals)
8. [Global Invoice Discount — Validation Rules](#8-global-invoice-discount--validation-rules)
9. [Extra Items — Calculation Rules](#9-extra-items--calculation-rules)
10. [Extra Items — Validation Rules (Intentional Gap — Read Carefully)](#10-extra-items--validation-rules-intentional-gap--read-carefully)
11. [Shipping Cost](#11-shipping-cost)
12. [Grand Total Assembly Formula](#12-grand-total-assembly-formula)
13. [Final Grand Total Edge Cases](#13-final-grand-total-edge-cases)
14. [Precision & Rounding Rules](#14-precision--rounding-rules)
15. [Validation Error vs. Warning Distinction](#15-validation-error-vs-warning-distinction)
16. [Complete Ordered Calculation Pipeline](#16-complete-ordered-calculation-pipeline)

---

## 1. Data Model Glossary

These are the fields you will work with. Names match the database columns exactly.

### Invoice (`sale_invoices` table)

| Field | Type | Description |
|---|---|---|
| `store_id` | integer | The store this invoice belongs to |
| `customer_id` | integer \| null | Optional customer |
| `payment_method` | enum | `cash`, `card`, etc. |
| `discount_type` | enum \| null | `fixed` or `percentage` — the **global** invoice-level discount |
| `discount_amount` | decimal(2) | The raw value entered by the cashier for the global discount |
| `global_discount_amount` | decimal(2) | The computed monetary value of the global discount |
| `grand_total_discount` | decimal(2) | `sum(all item line_total_discounts)` + `global_discount_amount` |
| `subtotal` | decimal(2) | Sum of all item `subtotal` fields (before any discounts) |
| `extra_items_total` | decimal(2) | Net signed total of all extra items |
| `total_before_tax` | decimal(2) | `subtotals_after_item_discounts_sum − global_discount_amount` |
| `total_tax_amount` | decimal(2) | Sum of all item `tax_amount` fields (currently always 0) |
| `shipping_cost` | decimal(2) | Shipping charge added to the invoice |
| `total_amount` | decimal(2) | The final payable grand total |

### Invoice Line Item (`sale_invoice_items` table)

| Field | Type | Description |
|---|---|---|
| `product_variant_id` | integer | The product variant being sold |
| `price_type` | enum | `retail` or `wholesale` |
| `quantity` | decimal(3) | Quantity sold (supports fractional units) |
| `unit_price` | decimal(4) | The **base price** for the selected price type (authoritative from DB) |
| `discount_type` | enum \| null | `fixed` or `percentage` — item-level discount |
| `unit_discount_amount` | decimal(4) | The raw discount value entered by the cashier |
| `line_total_discount` | decimal(2) | The computed monetary discount for the entire line |
| `subtotal` | decimal(2) | `unit_price × quantity` (BEFORE any item discount) |
| `tax_rate` | decimal(2) | Tax rate percentage (currently always 0 — feature postponed) |
| `tax_amount` | decimal(2) | `subtotal_after_discount × tax_rate / 100` (currently always 0) |
| `line_total` | decimal(2) | `subtotal − line_total_discount` (the post-discount line value) |

### Extra Item (`sale_invoice_extra_items` table)

| Field | Type | Description |
|---|---|---|
| `name` | string | Label shown to cashier (e.g. "Service fee", "Coupon") |
| `action_type` | enum | `addition` or `subtraction` |
| `amount` | decimal(2) | Always a positive number entered by the cashier |
| `signed_amount` | computed | `+amount` if `addition`, `−amount` if `subtraction` |

### Product Variant (read from product catalogue)

| Field | Description |
|---|---|
| `retail_price` | Base selling price (retail) |
| `wholesale_price` | Base selling price (wholesale) |
| `min_retail_price` | Absolute floor price for retail — cannot go below this |
| `min_wholesale_price` | Absolute floor price for wholesale — cannot go below this |
| `is_retail_price_negotiable` | Boolean — if false, no retail discount is allowed |
| `is_wholesale_price_negotiable` | Boolean — if false, no wholesale discount is allowed |
| `wholesale_enabled` | Boolean — if false, wholesale price type cannot be selected |
| `wholesale_qty_threshold` | Minimum quantity required to use the wholesale price type |

---

## 2. Price Types

An invoice line item can use one of two price types:

| Value | Label | Base Price Field | Min Price Field | Negotiable Flag |
|---|---|---|---|---|
| `retail` | Retail | `retail_price` | `min_retail_price` | `is_retail_price_negotiable` |
| `wholesale` | Wholesale | `wholesale_price` | `min_wholesale_price` | `is_wholesale_price_negotiable` |

**Rules:**
- `wholesale` can only be selected if `variant.wholesale_enabled === true`.
- If `variant.wholesale_qty_threshold > 0` and `price_type === wholesale`, the entered `quantity` must be `≥ wholesale_qty_threshold`. This is a **hard validation error** that blocks submission.
- The `unit_price` shown to the cashier is read-only and always equals the variant's base price for the selected `price_type`. The cashier **cannot** manually change the unit price directly; they can only apply a discount.

---

## 3. Discount Types

Both line-item discounts and the global invoice discount share the same two modes:

| Value | Label | Behaviour |
|---|---|---|
| `fixed` | Fixed Amount | The entered number is a direct monetary deduction (e.g. 10.00 = minus 10 currency units) |
| `percentage` | Percentage | The entered number is a percentage of the applicable base (e.g. 15 = 15%) |

---

## 4. Extra Item Action Types

Extra items are invoice-level additions or subtractions that are NOT product lines.
Examples: delivery fees, service charges, coupons, adjustments.

| Value | Label | Effect on total |
|---|---|---|
| `addition` | Addition | `+amount` added to the invoice total |
| `subtraction` | Subtraction | `−amount` subtracted from the invoice total |

The `amount` field is always entered as a **positive number** by the cashier.
The sign is determined solely by `action_type`.

---

## 5. Line-Item Level Calculations

These calculations must run every time the cashier changes `quantity`, `unit_discount_amount`, `discount_type`, or `price_type` on a line item.

### Step 1 — Resolve the unit price

```
unit_price = variant.getBasePrice(price_type)
  where:
    if price_type == 'retail'    → unit_price = variant.retail_price
    if price_type == 'wholesale' → unit_price = variant.wholesale_price
```

> The client should fetch and store this value. It is **read-only** and must not be modified
> by the cashier.

### Step 2 — Compute the subtotal (before any item discount)

```
subtotal = unit_price × quantity
```

### Step 3 — Compute the monetary unit discount amount

```
if discount_type == null OR unit_discount_amount == 0:
    monetary_unit_discount = 0.00

if discount_type == 'fixed':
    monetary_unit_discount = unit_discount_amount          // already in currency units

if discount_type == 'percentage':
    monetary_unit_discount = unit_price × (unit_discount_amount / 100)
```

> Always round `monetary_unit_discount` to **2 decimal places** before further use.

### Step 4 — Compute the line total discount (for the whole line)

```
line_total_discount = monetary_unit_discount × quantity

// Safety cap: discount can never exceed the raw subtotal (prevents negative line total)
line_total_discount = min(line_total_discount, subtotal)
```

> Always round `line_total_discount` to **2 decimal places**.

### Step 5 — Compute the line total (post-discount)

```
line_total = subtotal − line_total_discount
```

> Note: Tax is currently always 0. When tax is enabled in the future:
> `tax_amount = line_total × (tax_rate / 100)`
> `line_total_with_tax = line_total + tax_amount`
> For now, `tax_amount = 0` and `line_total_with_tax = line_total`.

### Step 6 — Minimum allowed line subtotal (used for basket-floor enforcement)

```
min_price = variant.getMinimumAllowedPrice(price_type)
  where:
    if price_type == 'retail'    → min_price = variant.min_retail_price
    if price_type == 'wholesale' → min_price = variant.min_wholesale_price

minimum_allowed_line_subtotal = min_price × quantity
```

This value is **not displayed** to the cashier but is used in basket-level minimum checks.

---

## 6. Line-Item Level Validation Rules

These are **hard validation errors** — they must block the cashier from proceeding.

### RULE L-1 — Non-negotiable item discount rejection

```
if variant.isPriceNegotiable(price_type) == false AND unit_discount_amount > 0:
    ERROR: "This item's price is fixed and cannot be discounted."
```

If the item is non-negotiable, the discount fields must be **disabled** in the UI and any
previously entered discount must be cleared (`discount_type = null`, `unit_discount_amount = null`).

### RULE L-2 — Percentage discount cannot exceed 100%

```
if discount_type == 'percentage' AND unit_discount_amount > 100:
    ERROR: "Percentage discount cannot exceed 100%."
```

### RULE L-3 — Fixed discount cannot exceed the unit price

```
if discount_type == 'fixed' AND round(unit_discount_amount, 2) > round(unit_price, 2):
    ERROR: "Fixed discount ({unit_discount_amount}) exceeds the unit price ({unit_price})."
```

### RULE L-4 — Unit price after item discount cannot fall below the minimum allowed price

```
unit_price_after_discount =
    if discount_type == 'fixed'      → unit_price − unit_discount_amount
    if discount_type == 'percentage' → unit_price − (unit_price × (unit_discount_amount / 100))

if unit_discount_amount > 0 AND round(unit_price_after_discount, 2) < round(min_price, 2):
    ERROR: "Price for '{variant.name}' cannot go below the minimum allowed price of {min_price}."
```

### RULE L-5 — Wholesale quantity threshold

```
if price_type == 'wholesale' AND variant.wholesale_qty_threshold > 0
    AND quantity < variant.wholesale_qty_threshold:
    ERROR: "Wholesale price requires a minimum quantity of {wholesale_qty_threshold}."
```

### RULE L-6 — Quantity must be positive

```
if quantity <= 0:
    ERROR: "Quantity must be greater than 0."
```

(Minimum accepted value: `0.001`)

---

## 7. Invoice-Level Aggregation (Grand Totals)

After all line items are calculated, aggregate the following. These must be recomputed
whenever any line item changes, a line is added/removed, the global discount changes,
an extra item changes, or the shipping cost changes.

```
subtotals_before_discount_sum = SUM(item.subtotal for all items)
subtotals_after_discount_sum  = SUM(item.line_total for all items)
total_items_discount_sum      = SUM(item.line_total_discount for all items)
total_tax_amount              = SUM(item.tax_amount for all items)   // currently always 0
total_minimum_allowed         = SUM(item.minimum_allowed_line_subtotal for all items)
```

---

## 8. Global Invoice Discount — Validation Rules

The global discount is applied to `subtotals_after_discount_sum` (i.e., after all item-level
discounts have already been deducted).

### Computation

```
if discount_type == null OR discount_amount == 0:
    global_discount_amount = 0.00

if discount_type == 'fixed':
    // Cap: global discount cannot exceed the sum of all item line totals
    global_discount_amount = min(discount_amount, subtotals_after_discount_sum)

if discount_type == 'percentage':
    // Cap the percentage at 100%
    effective_pct = min(discount_amount, 100)
    global_discount_amount = subtotals_after_discount_sum × (effective_pct / 100)
```

### RULE G-1 — Percentage discount cannot exceed 100%

```
if discount_type == 'percentage' AND discount_amount > 100:
    ERROR: "Global percentage discount cannot exceed 100%."
```

### RULE G-2 — Fixed discount cannot exceed the total invoice amount

```
if discount_type == 'fixed' AND round(discount_amount, 2) > round(subtotals_after_discount_sum, 2):
    ERROR: "Global discount exceeds the total invoice amount."
```

### RULE G-3 ⚠️ — Basket minimum floor enforcement (most critical rule)

This is the primary business rule that protects margin.

```
total_before_tax = subtotals_after_discount_sum − global_discount_amount

if round(total_before_tax, 2) < round(total_minimum_allowed, 2):
    ERROR: "The applied discount brings the invoice total below the minimum allowed
            basket total of {total_minimum_allowed}. Reduce the discount."
```

**Where:**
```
total_minimum_allowed = SUM(variant.min_price(price_type) × quantity  for all items)
```

**UI Hint (non-blocking):** Show the cashier the maximum allowable global discount
at all times:
```
max_fixed_global_discount   = max(0, subtotals_after_discount_sum − total_minimum_allowed)
max_pct_global_discount     = if subtotals_after_discount_sum > 0:
                                  (max_fixed_global_discount / subtotals_after_discount_sum) × 100
                              else: 0
```

---

## 9. Extra Items — Calculation Rules

Extra items are invoice-level add-ons, not product lines. They have no relationship to
product variants, minimum prices, or stock.

### Signed Amount Computation

```
for each extra_item:
    if extra_item.action_type == 'addition':
        extra_item.signed_amount = +extra_item.amount
    if extra_item.action_type == 'subtraction':
        extra_item.signed_amount = −extra_item.amount

extra_items_total = SUM(extra_item.signed_amount for all extra_items)
```

`extra_items_total` can be **negative** (net subtraction), **positive** (net addition), or **zero**.

### Field Validation

- `amount` must be a **positive number** (`> 0`). Zero is not allowed.
- `name` is required (non-empty string).
- `action_type` is required (`addition` or `subtraction`).

---

## 10. Extra Items — Validation Rules (Intentional Gap — Read Carefully)

> [!IMPORTANT]
> The following is a **deliberate design decision** documented here as a business requirement.

### RULE E-1 — Extra item subtractions do NOT enforce the basket minimum floor

**By design**, a `subtraction` extra item is allowed to reduce the `total_amount` to any
value — **including below the sum of all item minimum allowed prices**, and even to zero.

This is intentional because:
- Extra items represent invoice-level adjustments (coupons, negotiated total discounts,
  service fee waivers) that are conceptually separate from product pricing floors.
- Minimum price floors exist to protect per-product margins at the line level and via the
  global invoice discount. Extra items operate at a different layer.

**Therefore, the client-side implementation must:**
- ✅ Allow `subtraction` extra items to bring `total_amount` below `total_minimum_allowed`.
- ✅ Allow `subtraction` extra items to bring `total_amount` to **zero** (floored by `max(0, ...)`).
- ❌ **NOT** throw a hard validation error when an extra item subtraction causes `total_amount < total_minimum_allowed`.
- ⚠️ **OPTIONALLY** show a **soft warning** (non-blocking) when `total_amount < 0`
  (i.e., when extra item deductions exceed the pre-extra invoice total). The message should
  inform the cashier that deductions exceed the invoice total. The display value is clamped
  at `0.00` — it never goes negative on screen.

### RULE E-2 — Total amount floor is zero

```
display_total_amount = max(0, total_amount)
```

The displayed `total_amount` is always `≥ 0`. If `extra_items_total` is so large a
negative number that it makes the raw total negative, the displayed amount is `0.00`
and a soft warning is shown.

---

## 11. Shipping Cost

- `shipping_cost` is always `≥ 0`.
- It is a simple addition to the final grand total.
- It does **not** affect `total_before_tax`, `global_discount_amount`, or any minimum-price checks.
- It is **not** subject to the basket minimum floor validation.

---

## 12. Grand Total Assembly Formula

This is the single authoritative formula. Apply it **in this exact order**:

```
// Step 1: Line-item subtotals
subtotals_before_discount_sum = SUM(unit_price × quantity)           // for all items
subtotals_after_discount_sum  = SUM(line_total)                      // = subtotals_before − item discounts

// Step 2: Global invoice discount
global_discount_amount = f(discount_type, discount_amount, subtotals_after_discount_sum)
                         // see Section 8

// Step 3: Pre-tax total (items after ALL discounts)
total_before_tax = subtotals_after_discount_sum − global_discount_amount

// Step 4: Tax (currently 0)
total_tax_amount = SUM(item.tax_amount)   // = 0.00

// Step 5: Extra items net total
extra_items_total = SUM(signed_amount for all extra_items)
                    // can be negative

// Step 6: Shipping
shipping_cost = invoice.shipping_cost   // always ≥ 0

// Step 7: Raw grand total
raw_total_amount = total_before_tax + total_tax_amount + shipping_cost + extra_items_total

// Step 8: Floor at zero for display
total_amount = max(0, raw_total_amount)

// Step 9: Grand total discount (for display/reporting only)
grand_total_discount = total_items_discount_sum + global_discount_amount
```

---

## 13. Final Grand Total Edge Cases

| Scenario | Expected Behaviour |
|---|---|
| No items, no extra items | Invoice cannot be submitted. Guard must block finalization. |
| Only extra items, no line items | Allowed for submission (by design — e.g. pure fee invoice). |
| `extra_items_total` makes `raw_total_amount < 0` | Clamp `total_amount = 0.00`, show soft warning. |
| Global discount brings `total_before_tax < total_minimum_allowed` | **Hard error** — block submission. |
| Subtraction extra item brings `total_amount < total_minimum_allowed` | **Intentionally allowed** — no error. |
| Subtraction extra item brings `total_amount < 0` | Allowed, but clamp display to `0.00` + soft warning. |
| Line item with `is_negotiable = false` and a discount entered | **Hard error** — block submission. |
| Wholesale price type selected but `wholesale_enabled = false` on variant | Disable the option in the UI entirely. |

---

## 14. Precision & Rounding Rules

These rules must be followed exactly to match server-side calculations.

| Computed Field | Rounding |
|---|---|
| `monetary_unit_discount` | `round(value, 2)` |
| `line_total_discount` | `round(value, 2)` |
| `subtotal` (per line) | `round(value, 2)` |
| `line_total` (per line) | `round(value, 2)` |
| `tax_amount` (per line) | `round(value, 2)` |
| `global_discount_amount` | `round(value, 2)` |
| `extra_items_total` | `round(value, 2)` |
| `total_before_tax` | `round(value, 2)` |
| `total_tax_amount` | `round(value, 2)` |
| `total_amount` | `round(value, 2)` |
| `grand_total_discount` | `round(value, 2)` |
| Comparison with `total_minimum_allowed` | Both sides rounded to 2 decimal places before comparing |
| `total_minimum_allowed` | Sum of `round(min_price × quantity, 2)` per item |

> **Critical:** Always use `round(a, 2) < round(b, 2)` for money comparisons.
> Never compare raw floats directly (`a < b`) due to floating-point drift.

---

## 15. Validation Error vs. Warning Distinction

| Type | UX behaviour | Blocks submission? |
|---|---|---|
| **Hard Error** | Red inline error below the field, submit button disabled | ✅ Yes |
| **Soft Warning** | Toast/notification shown, field remains editable | ❌ No |

### Hard Errors (must block submission)
- L-1: Non-negotiable item discounted
- L-2: Percentage > 100% on an item
- L-3: Fixed item discount > unit price
- L-4: Item price after discount < min_price
- L-5: Wholesale qty below threshold
- L-6: Quantity ≤ 0
- G-1: Global percentage > 100%
- G-2: Global fixed discount > invoice total
- G-3: Global discount causes invoice total < basket minimum

### Soft Warnings (non-blocking)
- E-2: `raw_total_amount < 0` due to extra item subtractions (display `0.00`, warn cashier)

---

## 16. Complete Ordered Calculation Pipeline

This is the exact order the client must run recalculation, triggered on every user change:

```
1. For each line item:
   a. Resolve unit_price from variant (retail_price or wholesale_price)
   b. Compute subtotal = unit_price × quantity
   c. Compute monetary_unit_discount (from discount_type + unit_discount_amount)
   d. Compute line_total_discount = min(monetary_unit_discount × quantity, subtotal)
   e. Compute line_total = subtotal − line_total_discount
   f. Compute tax_amount = 0 (feature postponed)
   g. Compute minimum_allowed_line_subtotal = min_price × quantity

2. Aggregate:
   subtotals_before_discount_sum = SUM(subtotal)
   subtotals_after_discount_sum  = SUM(line_total)
   total_items_discount_sum      = SUM(line_total_discount)
   total_minimum_allowed         = SUM(minimum_allowed_line_subtotal)
   total_tax_amount              = SUM(tax_amount)  // = 0

3. Compute global_discount_amount from (discount_type, discount_amount, subtotals_after_discount_sum)

4. Compute total_before_tax = subtotals_after_discount_sum − global_discount_amount

5. Compute extra_items_total = SUM(signed_amount for each extra_item)

6. Compute shipping_cost (from selected destination or manual entry)

7. Compute raw_total_amount = total_before_tax + total_tax_amount + shipping_cost + extra_items_total

8. Compute total_amount = max(0, raw_total_amount)

9. Compute grand_total_discount = total_items_discount_sum + global_discount_amount

10. Run all validation rules (Sections 6, 8, 10)

11. Update UI displays with all computed values
```

---

## 17. Permissions & Roles

The system uses **Spatie Laravel Permission** (RBAC). Every user has exactly one role,
and every role carries a specific set of permission strings.

---

### 17.1 Role Definitions

| Role | Value | Scope |
|---|---|---|
| `company_admin` | `COMPANY_ADMIN` | Company-wide — all permissions |
| `store_manager` | `STORE_MANAGER` | Full access within their store |
| `cashier` | `CASHIER` | Day-to-day POS operations — limited write access |
| `stock_clerk` | `STOCK_CLERK` | Read-only on sale invoices |
| `accountant` | `ACCOUNTANT` | Read-only on sale invoices |

> **`super_admin`** exists in the enum but is a platform-level role with no company
> context. It must never interact with the POS terminal.

---

### 17.2 User Scope: Company-Level vs. Store-Level

A user's scope is determined by their `store_id` field, not their role alone:

| Condition | Scope | Meaning |
|---|---|---|
| `company_id != null` AND `store_id == null` | **Company-Level** | Can see/select any store on the invoice |
| `company_id != null` AND `store_id != null` | **Store-Level** | Locked to their assigned store |

**POS behaviour:**
- A **Store-Level** user never sees a store selector — their `store_id` is injected
  automatically and is not editable.
- A **Company-Level** user (e.g. `company_admin`) must select a store before creating
  an invoice. Once the invoice is saved, the `store_id` is locked and cannot be changed.

---

### 17.3 Sale Invoice Permission Matrix

These are the exact Spatie permission strings checked by the system.

| Permission String | What it allows |
|---|---|
| `view_any_sale_invoice` | Access the invoice list / index screen |
| `view_sale_invoice` | Open and read a single invoice |
| `create_sale_invoice` | Create a new draft invoice |
| `update_sale_invoice` | Edit an existing **draft** invoice |
| `delete_sale_invoice` | Delete a **draft** invoice (finalized invoices cannot be deleted) |
| `delete_any_sale_invoice` | Bulk delete — **disabled for all roles** by policy |
| `finalize_sale_invoice` | Trigger the finalize action (locks invoice, deducts stock) |

---

### 17.4 Role → Permission Mapping (Sale Invoice Scope Only)

| Permission | `company_admin` | `store_manager` | `cashier` | `stock_clerk` | `accountant` |
|---|:---:|:---:|:---:|:---:|:---:|
| `view_any_sale_invoice` | ✅ | ✅ | ✅ | ✅ | ✅ |
| `view_sale_invoice` | ✅ | ✅ | ✅ | ✅ | ✅ |
| `create_sale_invoice` | ✅ | ✅ | ✅ | ❌ | ❌ |
| `update_sale_invoice` | ✅ | ✅ | ✅ | ❌ | ❌ |
| `delete_sale_invoice` | ✅ | ✅ | ❌ | ❌ | ❌ |
| `finalize_sale_invoice` | ✅ | ✅ | ✅ | ❌ | ❌ |

---

### 17.5 Finalization Rules

The **Finalize** action is the most critical gated operation. The following must ALL be
true before it is allowed:

1. The user has the `finalize_sale_invoice` permission.
2. The invoice status is `Draft` (not already `Finalized`).
3. The invoice has at least one line item OR at least one extra item.

If any condition fails, the Finalize button must be hidden or disabled entirely in the UI.

> The server re-checks all three conditions inside a `lockForUpdate` transaction and will
> reject the request if they are not met, regardless of what the client sends.

---

### 17.6 Edit Guard

An invoice can only be edited (fields changed, items added/removed) when:

1. The user has `update_sale_invoice`.
2. The invoice status is **`Draft`**.

Once an invoice is `Finalized`, the entire form becomes **read-only**. No field, no item,
and no extra item can be modified. The POS terminal must enforce this by rendering a
read-only view for finalized invoices.

---

### 17.7 Delete Guard

1. The user must have `delete_sale_invoice`.
2. The invoice must be in `Draft` status.
3. **Finalized invoices can never be deleted** — this is an immutable financial record policy.
4. Bulk delete (`delete_any_sale_invoice`) is disabled for all roles.

---

### 17.8 Customer Creation Inline

The `cashier` role has `create_customer` permission and can create a new customer
inline during invoice creation. The POS terminal must expose this shortcut.

---

*Document generated from codebase analysis of `SaleInvoiceService.php`, `SaleInvoiceForm.php`,
`SaleInvoice.php`, `SaleInvoiceItem.php`, `SaleInvoiceExtraItem.php`,
`SaleInvoiceResource.php`, `company_permissions.php`, `company_standard_roles.php`, `User.php`.*
*Last reviewed: 2026-09-15*
