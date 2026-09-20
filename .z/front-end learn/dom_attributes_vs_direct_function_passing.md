# Frontend Architecture: Direct State Passing vs. DOM Data Attributes (`data-*`)

A comprehensive reference guide for modern frontend data handling, comparing direct function argument passing with storing state in DOM attributes, and explaining how Laravel Collections and Paginators bridge to the client.

---

## 1. The Core Comparison

When displaying a list of items (e.g., product cards in a POS grid) and needing to interact with them in JavaScript, there are two fundamental approaches:

```
┌────────────────────────────────────────────────────────────────────────┐
│ Approach A: Direct Parameter Passing (Modern Reactive State Pattern)   │
│ <div @click="addToCart({{ Js::from($product) }})">                     │
└────────────────────────────────────────────────────────────────────────┘
                                    vs.
┌────────────────────────────────────────────────────────────────────────┐
│ Approach B: DOM Data Attributes (Traditional / Hypermedia Pattern)     │
│ <div data-product="{{ json_encode($product) }}" @click="addToCart($el)">│
└────────────────────────────────────────────────────────────────────────┘
```

---

### Detailed Comparison Table

| Feature | Approach A: Direct State Passing | Approach B: DOM Attributes (`data-*`) |
| :--- | :--- | :--- |
| **Syntax** | `@click="addToCart({{ Js::from($item) }})"` | `<div data-item="{{ json_encode($item) }}" @click="addToCart($el)">` |
| **Execution Speed** | **Instant (0ms)** — Native pre-compiled JS object | **Requires `JSON.parse()` on every single click** |
| **DOM Coupling** | **Decoupled** — Pure JavaScript function | **Tightly Coupled** — Requires an existing HTML node |
| **Non-Click Triggers** | **100% Compatible** (Barcode scanners, hotkeys, search) | **Broken** (Requires finding/fabricating a DOM node) |
| **Event Target Issues** | None | Risk of child elements (`<img>`, `<icon>`) intercepting click |
| **HTML Footprint** | Clean JavaScript object in handler | Verbose attribute with HTML entity escaping (`&quot;`) |
| **Best Used In** | Alpine.js, Vue, React, Svelte | Vanilla JS, Stimulus.js, HTMX, Analytics, UI Plugins |

---

## 2. Why Direct Passing (Approach A) is Superior for Reactive Apps & POS

### A. Universal Function Signature (Decoupled from DOM)
In a real-world POS application, items are added to the cart through **three distinct channels**:
1. **Mouse / Touch Clicks** on a product card.
2. **Hardware Barcode Scanners** (firing a keyboard wedge or custom scan event).
3. **Search Input Autocomplete** (pressing `Enter` on a search result).

With **Approach A**, `addToCart(product)` is a pure, universal function:
```javascript
addToCart(product) {
    // Doesn't know or care WHERE the data came from!
    this.cart.push({ ...product, qty: 1 });
}
```
* Card Click $\rightarrow$ `addToCart(product)`
* Barcode Scan $\rightarrow$ finds product in memory $\rightarrow$ `addToCart(matchedProduct)`
* Search Bar $\rightarrow$ `addToCart(firstResult)`

With **Approach B**, `addToCart($el)` expects an HTML element. When a laser barcode scanner reads a barcode, there is no clicked HTML element, completely breaking the function unless you write redundant, duplicate cart logic.

---

### B. Performance: Memory vs. Click Latency
* **Approach A (`Js::from`)**: Laravel's `Js::from($product)` places a native JavaScript object literal directly into the Alpine handler. The JavaScript engine parses it **once** during template compilation. Clicking the card triggers zero string parsing.
* **Approach B (`data-*`)**: The data is stored as a raw escaped string:
  ```html
  data-product="{&quot;id&quot;:14,&quot;name&quot;:&quot;Milk&quot;,&quot;price&quot;:5}"
  ```
  Every time a cashier clicks, the browser must read the DOM attribute string and invoke `JSON.parse()`. In high-speed retail checkout, parsing strings repeatedly adds unnecessary CPU overhead.

---

### C. The Child Element Trap (Event Bubbling)
A product card has multiple child elements: images, badges, title headings, and icons.

With **Approach B**:
```html
<div data-product="..." onclick="addToCart(event)">
    <img src="thumb.jpg">
    <i class="ph ph-plus"></i>
</div>
```
If the user clicks the `<i class="ph ph-plus"></i>`:
* `event.target` is the `<i>` icon, **not** the parent `<div>`!
* The `<i>` icon does not have `data-product`, causing `event.target.dataset.product` to return `undefined`.
* You would be forced to write defensive DOM-traversal code on every click:
  ```javascript
  const el = event.currentTarget.closest('[data-product]');
  ```

---

## 3. Real-World Use Cases: Where DOM Data Attributes (`data-*`) Actually Shine

While reactive frameworks prefer Approach A, storing data in DOM attributes is the **industry standard** in the following 5 architectures:

### 1. The Stimulus.js & HTMX Ecosystem (HTML-over-the-Wire)
Frameworks like **Hotwire (Stimulus / Turbo)** and **HTMX** treat the DOM as the **Single Source of Truth**:
```html
<!-- Stimulus.js Standard -->
<div data-controller="cart"
     data-cart-product-id-value="42"
     data-cart-price-value="19.99">
    <button data-action="click->cart#add">Add</button>
</div>
```
Stimulus controllers do not hold state in JavaScript memory; they read values directly from HTML attributes on demand.

### 2. Event Delegation on Massive Lists (Memory Optimization)
In Vanilla JavaScript, if you render a table with **5,000 rows**:
* Adding 5,000 separate event listeners or inline objects consumes massive browser RAM.
* **Event Delegation Pattern**: Attach **only ONE single listener** to the parent `<tbody>`:
```html
<tbody id="orders-table">
    <tr data-order-id="101" data-status="pending">...</tr>
    <tr data-order-id="102" data-status="shipped">...</tr>
    <!-- 5,000 rows -->
</tbody>
```
```javascript
document.getElementById('orders-table').addEventListener('click', (e) => {
    const row = e.target.closest('tr[data-order-id]');
    if (row) {
        openOrder(row.dataset.orderId);
    }
});
```

### 3. Third-Party UI Plugins (Tooltips, Modals, Lightboxes)
Agnostic JavaScript libraries (Tippy.js, Fancybox, Bootstrap, Chart.js) have no knowledge of Alpine or Vue. They configure themselves by scanning DOM attributes:
```html
<!-- Tooltips -->
<button data-tippy-content="Remaining Stock: 15">Hover Me</button>

<!-- Lightbox Gallery -->
<a href="large.jpg" data-lightbox="gallery" data-caption="Front View">
    <img src="thumb.jpg">
</a>
```

### 4. Web Analytics & Event Tracking (Google Tag Manager, Mixpanel)
Marketing and tracking scripts listen globally to document clicks without modifying application code:
```html
<button data-analytics-event="add_to_cart"
        data-analytics-sku="SKU-102"
        data-analytics-category="Electronics">
    Add to Cart
</button>
```

### 5. CSS State Selectors (Tailwind CSS v3 / v4)
Modern CSS uses `data-*` attributes directly for styling conditional states:
```html
<div data-status="out-of-stock" class="data-[status=out-of-stock]:opacity-50">
```

---

## 4. Deep Dive: Laravel Collections vs. Paginators (`map` vs `transform`)

### The Fundamental Difference

| Method | Behavior | Return Value | Original Collection |
| :--- | :--- | :--- | :--- |
| **`map()`** | **Immutable** | A **new** `Collection` instance | Completely untouched |
| **`transform()`** | **Mutable** | The **same** `Collection` instance | Overwritten **in-place** |

---

### The Two-Layer Architecture of `LengthAwarePaginator`

A Laravel Paginator is **not** a Collection. It is an object containing two layers:

```
┌─────────────────────────────────────────────────────────────┐
│ Layer 1: LengthAwarePaginator (Paginator Box)               │
│                                                             │
│   • $total = 500              (Metadata)                    │
│   • $perPage = 10             (Metadata)                    │
│   • $currentPage = 1          (Metadata)                    │
│   • links()                   (HTML Link Generator)         │
│                                                             │
│   • protected $items = [Collection Object] ────┐            │
└────────────────────────────────────────────────┼────────────┘
                                                 │
                                                 ▼
       ┌──────────────────────────────────────────────────────┐
       │ Layer 2: Collection (List Wrapper)                   │
       │                                                      │
       │   • protected $items = [ <── Raw Native PHP Array    │
       │       0 => ProductVariant Model,                     │
       │       1 => ProductVariant Model,                     │
       │     ]                                                │
       └──────────────────────────────────────────────────────┘
```

### Why `getCollection()->transform()` Preserves Pagination
1. `$paginatedVariants->getCollection()` retrieves the internal **Layer 1** Collection reference.
2. `->transform($callback)` runs `array_map` directly on **Layer 2** (the raw PHP array inside that Collection).
3. The raw models are replaced with clean array structures **in-place**.
4. The outer Paginator metadata (`total`, `currentPage`, `links()`) was **never recreated or touched**.

### Why `->map()` Breaks the Paginator
`map()` creates a **new, detached Collection** floating in memory. The paginator box never receives it and still holds the old models.

### Laravel's Built-in Shortcut: `->through()`
Laravel provides a native method on the paginator that runs `transform()` under the hood:
```php
$paginatedVariants = $this->paginatedVariants()->through(function ($variant) {
    return [
        'id' => $variant->id,
        'name' => $variant->full_qualified_name,
        'price' => (float) $variant->retail_price,
    ];
});
```

---

## 5. Summary Rules of Thumb

1. **In Alpine / Vue / React**: Always pass data directly into functions (`addToCart({{ Js::from($item) }})`). Keep business logic decoupled from DOM elements.
2. **In Stimulus / HTMX / Vanilla JS**: Use `data-*` attributes and event delegation (`$el.dataset`).
3. **Inside Laravel Paginators**: Use `->through()` or `->getCollection()->transform()` to shape page items in-place without destroying pagination metadata.
