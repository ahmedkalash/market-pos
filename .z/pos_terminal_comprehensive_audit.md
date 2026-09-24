# POS Terminal Feature: Comprehensive Architectural Review & Bug Audit

**Audit Date:** September 2026  
**Audited Components:**
- Page Component: [`app/Filament/Pages/PosTerminal.php`](file:///d:/Herd/markt_pos/app/Filament/Pages/PosTerminal.php)
- Core Service: [`app/Services/PosCheckoutService.php`](file:///d:/Herd/markt_pos/app/Services/PosCheckoutService.php)
- Terminal View: [`resources/views/filament/pages/pos-terminal.blade.php`](file:///d:/Herd/markt_pos/resources/views/filament/pages/pos-terminal.blade.php)
- Pagination View: [`resources/views/filament/pages/pos/catalog-pagination.blade.php`](file:///d:/Herd/markt_pos/resources/views/filament/pages/pos/catalog-pagination.blade.php)
- Checkout DTOs: [`CartItemDTO`](file:///d:/Herd/markt_pos/app/DTOs/Checkout/CartItemDTO.php), [`CheckoutMetaDataDTO`](file:///d:/Herd/markt_pos/app/DTOs/Checkout/CheckoutMetaDataDTO.php), [`ExtraItemDTO`](file:///d:/Herd/markt_pos/app/DTOs/Checkout/ExtraItemDTO.php)

---

## Executive Summary

The POS Terminal is a high-performance, single-page retail interface bridging Filament 4, Livewire 3, Alpine.js, and a robust transactional backend (`PosCheckoutService`). The overall design demonstrates strong engineering principles:
- Clear transaction isolation with pessimistic row locking (`lockForUpdate`).
- Strict separation between Livewire-owned reference data and Alpine-owned interactive cart state.
- Authoritative backend recalculation (`SaleInvoiceService::recalculateTotals`).
- Comprehensive regression test suite exceeding 3,000 lines of test coverage.

However, a forensic examination across the backend service, Livewire page controller, and frontend Blade/Alpine layers revealed several **critical bugs, tenant isolation risks, performance bottlenecks, race conditions, and UI/UX ergonomics shortcomings**.

---

## 1. Critical Bugs & Security / Multi-Tenant Isolation Risks

### 1.1 Cross-Store Inventory Contamination by Company-Level Users
- **Severity:** 🔴 **Critical**
- **Location:** [`app/Services/PosCheckoutService.php:167-171`](file:///d:/Herd/markt_pos/app/Services/PosCheckoutService.php#L167-L171)
- **Problem:**
  When resolving purchased variant records in `saveDraftInvoice()`, the query locks variants by IDs without scoping by store:
  ```php
  $variantIds = array_unique(array_map(fn (CartItemDTO $item): int => $item->variantId, $cartItems));
  $variants = ProductVariant::whereIn('id', $variantIds)
      ->lockForUpdate()
      ->get()
      ->keyBy('id');
  ```
  While `ProductVariant` implements [`BelongsToStore`](file:///d:/Herd/markt_pos/app/Models/Concerns/BelongsToStore.php), its global scope **explicitly skips scoping for Company-Level users**:
  ```php
  if ($user->isSuperAdmin() || $user->isCompanyLevel()) {
      return; // Global scope bypassed!
  }
  ```
  If a Company-Level user switches active context to **Store A** and checks out or holds a cart containing variant IDs belonging to **Store B**, the backend checks stock against Store B, creates invoice line items for Store B's variants under Store A's invoice, and decrements Store B's inventory movements!
- **Remediation:**
  Explicitly apply the store filter to the variant query:
  ```php
  $variants = ProductVariant::whereIn('id', $variantIds)
      ->filterByStore($storeId)
      ->lockForUpdate()
      ->get()
      ->keyBy('id');
  ```

---

### 1.2 Frontend Scope Pollution Syntax Error on Livewire Morphs
- **Severity:** 🔴 **Critical**
- **Location:** [`resources/views/filament/pages/pos-terminal.blade.php:1594`](file:///d:/Herd/markt_pos/resources/views/filament/pages/pos-terminal.blade.php#L1594)
- **Problem:**
  The Blade view declares top-level `const` and `function` statements directly inside a `<script>` tag:
  ```javascript
  const RpcHandler = { ... };
  function createDefaultModalData() { ... }
  ```
  User rule [`rules-3.md`](file:///d:/Herd/markt_pos/.agents/rules/rules-3.md) explicitly warns:
  > *"Never declare plain `const` or `let` variables in `<script>` tags inside Livewire component views that may be re-evaluated... assign them to `window` (e.g., `window.RpcHandler = window.RpcHandler || { ... }`) to prevent `Identifier has already been declared` syntax errors during Livewire DOM morphs."*
  When Livewire DOM morphing evaluates this script tag during a component update or store context refresh, Chrome and Safari throw:
  `Uncaught SyntaxError: Identifier 'RpcHandler' has already been declared`
  This halts all Alpine event listeners on the terminal.
- **Remediation:**
  Attach to `window` with idempotent guards:
  ```javascript
  window.RpcHandler = window.RpcHandler || { ... };
  window.createDefaultModalData = window.createDefaultModalData || function() { ... };
  ```

---

### 1.3 Catastrophic Shortcut Contradiction: Tooltip Says "Held Carts (F4)", Key Clears Cart!
- **Severity:** 🔴 **Critical UX Hazard**
- **Location:**
  - Tooltips: [`pos-terminal.blade.php:163`](file:///d:/Herd/markt_pos/resources/views/filament/pages/pos-terminal.blade.php#L163) and [`pos-terminal.blade.php:210`](file:///d:/Herd/markt_pos/resources/views/filament/pages/pos-terminal.blade.php#L210)
  - Key Handler: [`pos-terminal.blade.php:1838-1845`](file:///d:/Herd/markt_pos/resources/views/filament/pages/pos-terminal.blade.php#L1838-L1845)
- **Problem:**
  In the UI header and cart action bar, the buttons for "Held Carts" explicitly state:
  ```html
  title="{{ __('pos.held_carts') }} (F4)"
  ```
  However, in the Alpine global keydown listener:
  ```javascript
  if (e.key === 'F4') {
      e.preventDefault();
      this.confirmClearCart(); // ❌ CLEARS THE ACTIVE CART!
  }
  if (e.key === 'F6' || (e.altKey && e.key.toLowerCase() === 'h')) {
      e.preventDefault();
      this.openHeldInvoicesModal(); // F6 actually opens held invoices!
  }
  ```
  If a cashier has a 20-item cart, reads the button tooltip saying `Held Carts (F4)` to check another transaction, and presses **F4**, the system immediately prompts them to **DELETE THEIR ENTIRE CART**!
- **Remediation:**
  Align tooltips and keylisteners consistently:
  - F4: Clear Cart (or change to another key)
  - F6: Held Carts (update button tooltips from `(F4)` to `(F6)`).

---

### 1.4 Asynchronous Race Condition on "Hold Active & Resume Held Cart"
- **Severity:** 🟠 **High**
- **Location:** [`resources/views/filament/pages/pos-terminal.blade.php:2587-2625`](file:///d:/Herd/markt_pos/resources/views/filament/pages/pos-terminal.blade.php#L2587-L2625)
- **Problem:**
  When a cashier attempts to resume a held draft while having items in their cart, the conflict modal calls `conflictHoldActiveAndResume()`:
  ```javascript
  await this.$wire.holdCart(formattedCart, { ... });
  await this.executeResumeDraft(nextDraftId);
  ```
  Meanwhile, the backend `PosTerminal::holdCart()` dispatches the Livewire event `cart-held-successful`:
  ```javascript
  window.addEventListener('cart-held-successful', (e) => {
      this.handleCartHeld(...);
  });
  handleCartHeld(detail) {
      this.clearCart(); // ❌ Clears the cart!
  }
  ```
  Because Livewire events are dispatched through the browser event loop concurrently with or after the resolved promise, `executeResumeDraft()` hydrates the new cart from the server, and shortly thereafter the global `cart-held-successful` event listener fires and executes `this.clearCart()`, **silently wiping out the newly resumed draft**!
- **Remediation:**
  Guard `handleCartHeld()` so that if `this.pendingResumeDraftId` is present, it does not wipe the newly hydrated cart, or pass a flag to `holdCart` preventing the generic wipe event.

---

## 2. Architectural & Business Logic Flaws

### 2.1 Sequence Consumption & Sequence Gaps on Draft Invoices
- **Severity:** 🟠 **Medium-High**
- **Location:** [`app/Services/PosCheckoutService.php:153`](file:///d:/Herd/markt_pos/app/Services/PosCheckoutService.php#L153)
- **Problem:**
  When an invoice is placed on hold (`holdCart()`), `saveDraftInvoice()` calls:
  ```php
  'invoice_number' => SequenceService::make()->next($companyId, SequenceType::SaleInvoice),
  ```
  If the cashier later cancels or discards the held invoice via `discardDraftInvoice()`, the invoice row is deleted:
  ```php
  $invoice->delete();
  ```
  The consumed sequence number (e.g., `INV-00104`) is permanently lost, creating non-contiguous sequence gaps in financial documents. In many tax jurisdictions and strict accounting audits, non-sequential finalized invoices trigger regulatory inquiries.
- **Remediation:**
  Either:
  1. Assign a temporary draft sequence (e.g. `HOLD-0001` or `DRAFT-{id}`) and only consume `SequenceType::SaleInvoice` upon final checkout in `SaleInvoiceService::finalize()`.
  2. Implement sequence number recycling for discarded un-finalized drafts.

---

### 2.2 "Phantom" Split Payment Option in Checkout Modal
- **Severity:** 🟡 **Medium**
- **Location:** [`resources/views/filament/pages/pos-terminal.blade.php:1186-1201`](file:///d:/Herd/markt_pos/resources/views/filament/pages/pos-terminal.blade.php#L1186-L1201)
- **Problem:**
  The Checkout Settlement modal presents three payment buttons: **Cash**, **Card**, and **Split**.
  When **Split** is clicked:
  - There is zero UI for inputting split amounts (e.g., "$50 Cash, $50 Card").
  - The cash settlement box hides, but no split settlement box appears.
  - Submitting sends `payment_method: 'split'` to the backend.
  - The database `sale_invoices` table has no split ledger table, so the transaction records only the enum `split` with no record of how much was collected in cash vs. card for till reconciliation!
- **Remediation:**
  Either implement full split ledger breakdown (cash amount + card amount inputs validated to equal `cartTotal`), or disable the "Split" button until multi-tender ledgers are implemented.

---

### 2.3 Violation of Client/Server Boundary Rule on Float Coercion
- **Severity:** 🟡 **Medium**
- **Location:** [`resources/views/filament/pages/pos-terminal.blade.php:2369, 2371, 2422, 2424`](file:///d:/Herd/markt_pos/resources/views/filament/pages/pos-terminal.blade.php#L2369)
- **Problem:**
  User rule [`rules-3.md`](file:///d:/Herd/markt_pos/.agents/rules/rules-3.md) states:
  > *"Never coerce invalid client numeric input with `parseFloat(val) || 0` before sending to the server. Pass the raw value and let Laravel's `numeric` / `decimal` validator emit localized validation errors, preventing malformed inputs from silently persisting as zero."*
  In `processPayment()` and `holdCartAction()`:
  ```javascript
  global_discount_amount: parseFloat(this.globalDiscountAmount) || 0,
  shipping_cost: parseFloat(this.shippingCost) || 0,
  ```
  If an invalid value or string is passed, it is silently coerced to `0`, bypassing backend validation.
- **Remediation:**
  Send the clean or raw input and allow Laravel's `numeric` validator in `validateCheckoutPayload` to validate it.

---

### 2.4 Mutation of Reactive `$wire` Getter Arrays via `.push()`
- **Severity:** 🟡 **Medium**
- **Location:**
  - [`pos-terminal.blade.php:1987`](file:///d:/Herd/markt_pos/resources/views/filament/pages/pos-terminal.blade.php#L1987): `this.customers.push(created);`
  - [`pos-terminal.blade.php:2267`](file:///d:/Herd/markt_pos/resources/views/filament/pages/pos-terminal.blade.php#L2267): `this.shippingDestinations.push(created);`
- **Problem:**
  In Alpine, `this.customers` and `this.shippingDestinations` are defined as reactive getters:
  ```javascript
  get customers() { return this.$wire.customerList || []; },
  get shippingDestinations() { return this.$wire.shippingDestinationList || []; },
  ```
  Calling `.push()` on a getter mutates the transient array instance returned by the getter call. Since the backend Livewire methods (`createCustomer` and `createShippingDestination`) already push the created record to `$this->customerList` and `$this->shippingDestinationList`, Livewire automatically syncs the updated array to the client. Mutating the getter's return array is redundant, creates proxy mutation warnings, and risks duplicate entries.
- **Remediation:**
  Remove `.push()` calls; rely purely on the reactive getter.

---

### 2.5 Violation of Alpine Reactive Error Clearing Rule
- **Severity:** 🟡 **Medium**
- **Location:** [`resources/views/filament/pages/pos-terminal.blade.php:1688-1690`](file:///d:/Herd/markt_pos/resources/views/filament/pages/pos-terminal.blade.php#L1688-L1690)
- **Problem:**
  User rule [`rules-3.md`](file:///d:/Herd/markt_pos/.agents/rules/rules-3.md) requires:
  ```javascript
  const { [field]: _, ...rest } = this.errors;
  this.errors = rest;
  ```
  The code currently uses:
  ```javascript
  const updated = { ...this.errors };
  delete updated[field];
  this.errors = updated;
  ```
  Using `delete` operator on proxy objects can cause Alpine v3's reactivity tracking to miss property removals.

---

## 3. Performance & Scalability Bottlenecks

### 3.1 N+1 Query on Catalog Product Images
- **Severity:** 🟠 **High Performance Issue**
- **Location:** [`app/Filament/Pages/PosTerminal.php:193`](file:///d:/Herd/markt_pos/app/Filament/Pages/PosTerminal.php#L193)
- **Problem:**
  In `getViewData()`:
  ```php
  'image' => (method_exists($variant->product, 'getFirstMediaUrl') 
      ? $variant->product->getFirstMediaUrl('image', 'thumb') 
      : null) ?: null,
  ```
  In `paginatedVariants()`:
  ```php
  $variantsQuery = ProductVariant::query()
      ->active()
      ->with(['product.category', 'barcodes', 'unitOfMeasure']);
  ```
  `product.media` is **not eager loaded**!
  Every product displayed in the grid executes a separate `SELECT * FROM media WHERE model_id = ...` query. For 24 products per page, this generates 24 redundant SQL queries per catalog refresh or search keystroke!
- **Remediation:**
  Add `'product.media'` to the eager-load array:
  ```php
  ->with(['product.category', 'product.media', 'barcodes', 'unitOfMeasure']);
  ```

---

### 3.2 Unbounded Customer Collection Loaded into Memory
- **Severity:** 🟠 **High Scalability Issue**
- **Location:** [`app/Filament/Pages/PosTerminal.php:810-814`](file:///d:/Herd/markt_pos/app/Filament/Pages/PosTerminal.php#L810-L814)
- **Problem:**
  On `mount()` and store changes:
  ```php
  private function customers(): BaseCollection
  {
      return Customer::query()
          ->active()
          ->get(['id', 'name', 'phone']);
  }
  ```
  In a store with 5,000 or 20,000 customer records, this executes an unpaginated query loading all customers into the public `$customerList` property. This payload must be serialized to JSON on every Livewire response, and Alpine renders them in an unvirtualized `<template x-for>` in the header dropdown.
- **Remediation:**
  Limit initial load to top 50 recent customers and provide a debounced server-side search RPC for customer lookup.

---

### 3.3 Production CDN Runtime Dependencies
- **Severity:** 🟠 **High Deployment / Offline Risk**
- **Location:** [`resources/views/filament/pages/pos-terminal.blade.php:3, 28`](file:///d:/Herd/markt_pos/resources/views/filament/pages/pos-terminal.blade.php#L3)
- **Problem:**
  The view loads runtime CSS and JS from external CDNs:
  ```html
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/@phosphor-icons/web"></script>
  ```
  1. The project already uses Tailwind CSS v4 in its build pipeline (`resources/css/app.css`). Loading the Tailwind Play CDN script causes the browser to download a ~400KB compiler, run JIT compilation in browser memory on every render, and clash with Filament styles.
  2. In retail environments, cashiers often operate under spotty or offline local network connections. If the terminal loses external internet access, icons and styles fail to load completely!
- **Remediation:**
  Bundle Phosphor Icons and styles locally via Vite, or include them as local SVG assets, removing the runtime CDN scripts.

---

### 3.4 Hardcoded Testing Artifact: `$perPage = 3`
- **Severity:** 🟡 **Medium**
- **Location:** [`app/Filament/Pages/PosTerminal.php:68`](file:///d:/Herd/markt_pos/app/Filament/Pages/PosTerminal.php#L68)
- **Problem:**
  `public ?int $perPage = 3;`
  The catalog displays only **3 items per page**. In a real POS station, a cashier would have to click pagination 50 times to browse inventory. This was clearly a test fixture that was not reverted back to standard POS density (e.g., 12, 16, or 20 items).
- **Remediation:**
  Set `$perPage = 16` or `$perPage = 20`.

---

## 4. UI / UX & POS Ergonomics Shortcomings

### 4.1 Missing Direct Numeric Quantity Input on Cart Line Items
- **Severity:** 🟡 **Medium (High Cashier Friction)**
- **Location:** [`resources/views/filament/pages/pos-terminal.blade.php:322-330`](file:///d:/Herd/markt_pos/resources/views/filament/pages/pos-terminal.blade.php#L322-L330)
- **Problem:**
  Cart lines provide only `+` and `-` stepper buttons:
  ```html
  <button @click="updateQty(index, -1)">...</button>
  <div x-text="item.qty"></div>
  <button @click="updateQty(index, 1)">...</button>
  ```
  If a customer purchases 50 units of a product, the cashier must click the `+` button 49 times! There is no direct numeric input or modal to type a custom quantity.
- **Remediation:**
  Replace or supplement the quantity text with an editable input or click-to-edit numeric modal.

---

### 4.2 Barcode Hardware Scanner Workflow Gap
- **Severity:** 🟡 **Medium**
- **Location:** [`resources/views/filament/pages/pos-terminal.blade.php:462`](file:///d:/Herd/markt_pos/resources/views/filament/pages/pos-terminal.blade.php#L462)
- **Problem:**
  Hardware barcode scanners act as rapid keyboard inputs followed by an `Enter` keystroke. Currently, the search input uses `wire:model.live.debounce.500ms="search"`. Scanning a barcode filters the catalog grid on the right side, but **does not automatically add the scanned product to the cart**. The cashier is forced to switch from the barcode gun to the mouse and click the product card.
- **Remediation:**
  Intercept rapid scanner bursts ending in `Enter` or add a fast-path scanner listener in Alpine that directly dispatches `addToCart` when an exact barcode match is found.

---

### 4.3 Missing Double-Submission Guard on "Save Destination"
- **Severity:** 🟡 **Medium**
- **Location:** [`resources/views/filament/pages/pos-terminal.blade.php:852`](file:///d:/Herd/markt_pos/resources/views/filament/pages/pos-terminal.blade.php#L852)
- **Problem:**
  Unlike `saveNewCustomer`, which disables the button and displays a spinner during RPC execution, `saveNewDestination` has no `isSavingDestination` state:
  ```html
  <button type="button" @click="saveNewDestination()" :disabled="!newDestination.name">
  ```
  Double-clicking this button creates duplicate shipping destinations in the database.
- **Remediation:**
  Add `isSavingDestination` state and guard the button with `:disabled="isSavingDestination"`.

---

### 4.4 Discarding Draft Closes the Entire Held Invoices Modal
- **Severity:** 🔵 **Minor UX Inconvenience**
- **Location:** [`resources/views/filament/pages/pos-terminal.blade.php:2665`](file:///d:/Herd/markt_pos/resources/views/filament/pages/pos-terminal.blade.php#L2665)
- **Problem:**
  When a cashier browses held carts and deletes one using the trash button, the `executeDiscardDraft()` finally block calls `this.closeModal()`, which sets `this.activeModal = null`. This abruptly closes the held carts list and returns the cashier to the main register, forcing them to press F6 and reopen the modal to continue managing held carts.
- **Remediation:**
  In `executeDiscardDraft()`, set `this.activeModal = 'heldCarts'` instead of resetting `activeModal` to `null`.

---

### 4.5 Hardcoded Print Route & Cashier Permission Gate
- **Severity:** 🔵 **Minor**
- **Location:**
  - [`pos-terminal.blade.php:2442, 2675`](file:///d:/Herd/markt_pos/resources/views/filament/pages/pos-terminal.blade.php#L2442): Hardcoded `/print/invoice/sale_invoice/${invoiceId}`
  - [`PrintInvoiceController.php:61`](file:///d:/Herd/markt_pos/app/Http/Controllers/PrintInvoiceController.php#L61): `Gate::authorize('view_sale_invoice')`
- **Problem:**
  Cashiers assigned solely to the POS terminal may not have the general administrative `view_sale_invoice` permission, causing receipt printing to fail with HTTP 403 Forbidden. Furthermore, the print URL is hardcoded as a string rather than generated via Laravel route helpers.

---

## 5. Comprehensive Summary Matrix of Findings

| ID | Finding Description | Severity | Area | Impact |
|---|---|---|---|---|
| **1.1** | Cross-store variant inventory contamination for company-level users | 🔴 Critical | Security / Inventory | Selling stock belonging to another store |
| **1.2** | `const RpcHandler` global scope pollution in Livewire view | 🔴 Critical | Stability | SyntaxError halts JS engine on DOM morphs |
| **1.3** | Shortcut mismatch: Tooltip says "Held Carts (F4)" but F4 clears cart | 🔴 Critical | UX / Data Loss | Accidental cart wiping by cashiers |
| **1.4** | Race condition between `cart-held-successful` and draft hydration | 🟠 High | Concurrency | Resumed draft wiped out immediately after resume |
| **2.1** | Sequence gap creation on discarded draft invoices | 🟠 High | Accounting / DB | Non-contiguous official invoice numbering |
| **2.2** | Missing Split Payment UI and multi-tender ledger tracking | 🟡 Medium | Financials | Inability to track cash vs card split payments |
| **2.3** | Premature `parseFloat() \|\| 0` coercion bypassing backend validation | 🟡 Medium | Architecture | Bypasses localized Laravel input validators |
| **2.4** | Calling `.push()` on reactive Livewire getter properties in Alpine | 🟡 Medium | State Management | Redundant mutation of read-only getters |
| **2.5** | Reactive error clearing using `delete` rather than destructuring | 🟡 Medium | Standards | Potential reactivity loss in Alpine proxies |
| **3.1** | N+1 media queries on catalog variant images (`product.media`) | 🟠 High | Performance | 24+ extra queries on every search/page load |
| **3.2** | Unbounded `Customer::get()` loading entire customer table | 🟠 High | Performance | Memory bloat on accounts with thousands of customers |
| **3.3** | External CDN dependencies for Tailwind and Phosphor Icons | 🟠 High | Reliability | Offline failure / slow load on poor internet |
| **3.4** | Catalog pagination hardcoded to `$perPage = 3` | 🟡 Medium | UX | Unusable catalog requiring excessive pagination |
| **4.1** | Cart line quantity lacking direct numeric input | 🟡 Medium | POS Ergonomics | Must click `+` dozens of times for bulk items |
| **4.2** | Barcode hardware scanner requires manual mouse click to add | 🟡 Medium | POS Ergonomics | Slows down checkout lanes |
| **4.3** | Missing double-submit guard on "Save Destination" button | 🟡 Medium | Concurrency | Creates duplicate shipping destinations on double click |
| **4.4** | Discarding a held draft exits the held carts modal completely | 🔵 Minor | UX Flow | Forces cashier to re-open modal repeatedly |
| **4.5** | Hardcoded print route and strict `view_sale_invoice` gate | 🔵 Minor | Authorization | Cashier 403 on thermal receipt print |

---

## 6. Recommended Action Plan & Next Steps

1. **Immediate Fixes (Safety & Stability):**
   - Scope variant fetching in `PosCheckoutService::saveDraftInvoice()` with `filterByStore($storeId)`.
   - Wrap `RpcHandler` and helper functions in `window` assignments (`window.RpcHandler = ...`).
   - Fix the F4 / F6 shortcut tooltip mismatch so F4 is accurately labeled or re-mapped.
   - Adjust `$perPage` from 3 to 16/20 in `PosTerminal.php`.
2. **Performance Optimizations:**
   - Eager load `'product.media'` in `paginatedVariants()`.
   - Paginate or limit `$customerList` in Livewire.
   - Replace external CDN scripts with local bundled assets.
3. **UX & Ergonomics Enhancements:**
   - Add direct numeric input or quick keypad for line item quantities.
   - Implement fast-path auto-add on barcode scanner Enter events.
   - Add double-click protection to `saveNewDestination()`.
