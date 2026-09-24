Sale Invoice — Requirements Specification

Overview
--------

Files to inspect (implementation reference)

- app\Filament\Resources\SaleInvoices\\*

1. UI surface (fields and components)

---

1.1 Header & Meta

- Customer selection (optional) + inline Customer creation: name, mobile, email, adress. Persist to invoice.customer_id.
- barcode/search input
- POS terminal user/employee display (readonly).
-

1.2 Cart / Invoice Items
Each line must expose the following fields and labels:

* [done] Cancel / Clear cart (confirmation required).

- [done] Add item (button), Remove item (trash icon on each line
- Price type (select): retail | wholesale — required. Default = retail. Changing price type updates unit price and min quantity constraints.

  - `issue: The minimum quantity constraint is applied however it is being  silently without any disclaimers or info so we need to display a line or something on tha cart item that is displayed when the price type is changed to wholesale that say the minimum allowed quantity for wholesale price is X quantity. show we show Min-allowed quantity message if qty < minAllowedForPriceType (display inline error)?`
  - `action: currently if the price type does not allow disc, it is shown in the disc modal, but we need to make it clear with zero click from the user, what do you suggest? red line idicator, or disable disc btn with tooltip or something else`
- Quantity (numeric, editable) — required. Shows UOM. For wholesale, quantity must meet minWholesaleQty (see validation).

  - `action: make sure validations happens on both front and backend`
- Unit price (currency, readonly)

  - `Q: if a disc hass been applied should we dispaly both orignal price and unit price after disc and display the orignal price as '~~123~~', which will add more usablity and clearty to the user . what do you think? will it be easy to implement?`
- Subtotal before discount (readonly) = unit price × qty (no per-item discounts applied yet).

  - `Q: do we need to dispaly it,how we are going to do it? if there is an applied disc will we display it price as '~~123~~'? will it be better? will it be easy to implement?`
- --------- unit disc modal ---------------
- breakdowns.
- Discount type (select): fixed | percentage (per unit).
- Unit discount value (numeric): interpret as currency when discount type=fixed, or percent when percentage. Show appropriate prefix/suffix (currency symbol / %). **Constrain based on maxUnitDiscount**.

  - `action: make sure validations happens on both front and backend`
- Total item discount (readonly) = unitDiscount × qty.
- `action: we need to display the 'line total after disc' in that unit disc modal breakdowns so the use has all related info in one place, what do you think about it, will it be easy to implement?`
- --------- unit disc modal ends-------------
- Line total (readonly) = subtotalBeforeDiscount − totalItemDiscount.
- `Q: Stock availability indicator (informational) is currently displayed in the products cards. if cart item qty > available what sould we do?  show warning and prevent checkout? disable qty increment? somting else?`
- Validation error messages should be localized and appear inline with the field, or as the use case or the desing require

1.3 Extra Items / Adjustments modal

- Add adjustment button opens a sub-form per adjustment:

  - Template (optional) — select a preset (for quick reuse).
  - Name (string) — required.
  - Action type (select): add | subtract
  - Amount (currency numeric) — required. If subtract, amount reduces invoice total.
  - Notes (optional).

1.4 Invoice-level discount modal

- Discount type (select): fixed | percentage — required; default: fixed.
- Discount amount (numeric): currency or percent depending on type. Show symbol or % accordingly.
- Max allowed invoice discount: computed from sum of items' minimum-allowed prices (see Calculations). Display helper text like: "Maximum allowed invoice-level discount: X".
- Final discount amount (readonly): for percentage, compute percent × sum(line totals). For fixed, equals entered fixed amount (unless capped by max allowed) just like the sale invoice creation page

1.5 Shipping modal

- Shipping destination selector / inline create button.
- Shipping cost (currency) — editable. Default provided by shipping dist.
- Shipping address — editable free text. Default provided by shipping dist

1.6 Summary (read-only computed area)

- Items subtotal (sum of all items' subtotalBeforeDiscount).
- Items discounts total (sum of all items' total item discounts).
- Extra adjustments total (sum of adds − subtracts).
- Invoice-level discount .
- Grand total discount (Invoice-level discount + Items discounts total).
- Shipping cost.
- Grand total: final amount due = Items subtotal − items discounts − invoice-level discount + extras total + shipping.

#### ----------------------prevous items are done ----------

---

1.7 Actions

- ~~[done] Save as draft (persists invoice with status=draft).~~
- ~~[done] Print finalized invoices,~~
- [todo] Print draft invoices.
- [todo] checkout modal
  ---------------------

  - - ~~[done] select payment mothod~~
    - ~~[done] display total amount~~
    - ~~[done] print invoice checkbox (check by defut)~~
    - ~~cashair helper~~

      - ~~[done] tender/Change amount when pay in cash that auto calc remaining (Cash Received, tender, Change)~~

~~-[done] pagination~~

- ~~fix pagination ui~~,
- ~~new pages items are add/merged after the current items~~
- ~~auto fetch or infinite scroll~~


##### draft/holed invioces

- we need to display the draft/holed invioces so the cashair can refetch them and contuie his work
- we nee something that allow the cashair to mark or title drafted inv so he know which one to fetch
- draft inv printing option


##### - Lang change in pos


redisgn the summery breakdown section to be more elegant



#### - Permissions & roles:

* apply same as [SaleInvoiceForm](app\Filament\Resources\SaleInvoices\Schemas\SaleInvoiceForm.phphttps:/)


---


Appendix A: Quick reference formulas
------------------------------------

- SubtotalBeforeDiscount (line) = unitPrice × qty
- TotalItemDiscount = unitDiscount × qty
- LineTotal = SubtotalBeforeDiscount − TotalItemDiscount
- BaseItemsTotal = sum(LineTotal for all base items)
- InvoiceDiscountAmount = if fixed => min(enteredFixed, maxInvoiceDiscount) else => (percent / 100) × baseItemsTotal, capped by maxInvoiceDiscount
- GrandTotal = BaseItemsTotal − InvoiceDiscountAmount + AdjustmentsTotal + Shipping + Taxes
