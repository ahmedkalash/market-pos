# Tax Strategy & Implementation Plan (V4 - Premium Additions)

## 1. The Core Architecture (Approved)
*   **Invoice-Level Toggle:** Invoices have a `tax_calculation_mode` (`inclusive`, `exclusive`, `exempt`). This dictates how math works for that specific document.
*   **Company Defaults:** `is_purchase_tax_inclusive` and `is_selling_tax_inclusive` act as fallback defaults.
*   **Transaction Immutability:** Line items permanently store `unit_cost` (base), `tax_rate`, `tax_amount`, and `line_total`.

---

## 2. Premium Optimizations & Uncovered Edge Cases
You asked for edge cases and ways to make this truly premium. Here are the advanced scenarios we must handle to ensure the system never breaks in the real world:

### A. Vendor-Specific Defaults (Premium UX)
*   **The Reality:** "Supplier A" (a local farmer) always gives you handwritten *inclusive* receipts. "Supplier B" (Pepsi Co) always gives you printed *exclusive* corporate invoices.
*   **The Optimization:** We should add a `tax_mode` column to the `vendors` table. 
*   **UX Flow:** When you create a Purchase Invoice, the "Amounts Are" dropdown defaults to the Company setting. But the moment you select "Pepsi Co" from the Vendor dropdown, the system detects their preference and auto-switches the dropdown to "Exclusive". This saves the user clicks and prevents data entry errors.

### B. The 1-Cent Rounding Nightmare (ZATCA & ERP Compliance)
*   **The Reality:** The biggest bug in all POS systems is rounding. 
    *   Example: An item is 100 Inclusive, Tax is 14%. Base = `87.7192...`
    *   If you sell 3 items, do you round the line first (`87.72 * 3 = 263.16`) or the document first (`87.7192 * 3 = 263.15`)? This 1-cent difference will cause Saudi ZATCA e-invoicing validations to reject the invoice.
*   **The Optimization:** We need to ensure our database columns (`subtotal`, `tax_amount`, `line_total`) use high precision (e.g., `DECIMAL(15,4)`) for background storage, even if we only show 2 decimals in the UI. Also, we must strictly define our formula as **Line-Level Rounding** (standard for retail POS).

### C. Discounts vs. Taxes (Order of Operations)
*   **The Reality:** If an item is 100, and you apply a 10% discount, the tax MUST be calculated on the discounted amount (90), not the original 100.
*   **The Optimization:** When we write the Livewire math in the form, the formula must explicitly be:
    *   *Exclusive:* `(Unit Price - Discount) * Qty = Subtotal` -> `Subtotal * Tax% = Tax Amount`
    *   *Inclusive:* `(Unit Price - Discount) * Qty = Line Total` -> `Line Total - (Line Total / (1 + Tax%)) = Tax Amount`

### D. The "Exempt Customer" (B2B Sales Edge Case)
*   **The Reality:** Some products are taxable (14%), but sometimes you sell to an embassy, a charity, or export it to another country, making the *entire transaction* tax-exempt, regardless of the product.
*   **The Optimization:** Because we are capturing `tax_rate` on the invoice line item (and not just reading it dynamically from the product), the user can manually override the tax rate to `0%` on the invoice line if they are dealing with a tax-exempt customer. Our architecture already supports this!

---

## 3. Implementation Roadmap

### Phase 1: Database Migrations (The Foundation)
1.  `purchase_invoices` table: Add `tax_calculation_mode` (enum).
2.  `vendors` table: Add `default_tax_mode` (enum, nullable).
3.  `companies` table: Add `is_purchase_tax_inclusive` and `is_selling_tax_inclusive` (booleans).
4.  *Optional but Recommended:* Alter financial columns to `DECIMAL(15,4)` to future-proof against ZATCA/rounding issues.

### Phase 2: Purchase Invoice Logic & UI
1.  Update the `PurchaseInvoiceForm` with the `tax_calculation_mode` toggle.
2.  Add an event listener to the Vendor dropdown so it auto-updates the tax mode.
3.  Rewrite the line-item repeater math to perfectly calculate base, tax, and totals based on the selected mode, strictly following the Discount -> Tax order of operations.

> [!IMPORTANT]
> **Next Steps:**
> I have added these premium ERP features (Vendor Defaults, Strict Rounding, Discount Logic) to the plan. This covers every major financial edge case. 
> 
> Are you ready to begin executing Phase 1 (Database Migrations)?
