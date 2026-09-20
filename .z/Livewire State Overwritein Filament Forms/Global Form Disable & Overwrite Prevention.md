# Implementation Plan: Global Form Disable & Overwrite Prevention

## Part 1: Applying the "Disabled" Option Globally

You asked to implement the `disabled` loading state across the **entire form**, not just the interactive fields, to prevent the user from jumping to a non-interactive field and having it overridden.

### How we will implement this:
To disable the entire form simultaneously in Filament without individually editing every single field, we can use an HTML `<fieldset>` trick. In HTML, if a `<fieldset>` element is given the `disabled` attribute, the browser automatically disables *all* inputs, selects, and textareas inside it.

We will inject a custom `ViewField` or use a wrapper at the very root of our form schema in `SaleInvoiceForm.php`. 
It will essentially do this:
```html
<fieldset wire:loading.attr="disabled">
   <!-- All Filament form sections and fields go here -->
</fieldset>
```
When Livewire triggers *any* network request (`wire:loading`), the `disabled` attribute is temporarily added to the fieldset, instantly locking the entire screen of inputs until the response comes back.

---

## Part 2: Preventing Livewire from Overriding Browser Data

You asked a very insightful question: *"Is there any option or solution that we can prevent livewire from overriding the form because we do not want to overwrite the form for each request?"*

You are absolutely right that sending data to the server just to have the server echo the same data back and overwrite the browser is inefficient and causes these exact race conditions.

### Why does Livewire do this?
In standard Livewire 3, if the server does *not* change a value, the morphdom engine is smart enough to leave the browser's DOM alone. **However**, Filament Forms are incredibly complex. Because the invoice items are stored in a massive array (the `Repeater`), whenever you trigger a `->live()` recalculation, Filament forces the server to re-evaluate the *entire array* to check for validation errors, visibility rules, and totals. Consequently, the server sends back the entire array structure, forcing Livewire to overwrite the repeater's DOM.

### Solutions to Stop the Overwrite:

#### 1. The Ultimate Solution: Pure Alpine.js (Client-Side Math)
If we want to completely stop the server from overriding the form during data entry, the **only true way** in Filament is to stop sending the data to the server on every keystroke.
We would remove `->live()` from the inputs entirely. Instead, we write a small JavaScript (Alpine.js) snippet that calculates the `quantity * price` and updates the totals directly in the browser's memory.
* **Pros:** Livewire never interrupts the user. Zero overwrites. 100% instant real-time calculation.
* **Cons:** We have to write custom JavaScript to handle the math, rather than relying on PHP.

#### 2. The `wire:ignore` / `wire:ignore.self` Approach
Livewire provides a `wire:ignore` attribute which tells the browser "never let the server update this HTML element after the first load." 
* **Pros:** The server can never overwrite what the user typed.
* **Cons:** We cannot use this natively in Filament easily because if the server legitimately *needs* to update the field (for example, showing a red validation error, or applying a discount), `wire:ignore` will block the server from doing so.

#### 3. Isolating the "Totals" into a separate Livewire Component
Instead of the whole form being one component, the "Items" and the "Totals" are split into two separate Livewire components on the page. When an item changes, it emits a silent event to the Totals component.
* **Pros:** The items array is never re-rendered by the server, so it never overwrites the browser.
* **Cons:** Extremely difficult to build within Filament's strictly coupled Form architecture.

### My Recommendation

Since you noted that bringing the same data back to overwrite the browser is inappropriate, you are leaning towards a true "Single Page Application" mindset. 

If we strictly apply the **Global Form Disable** (Part 1), we physically prevent the user from typing during the overwrite window. This acts as a "band-aid" over the Livewire architecture.

If you want to **cure the architecture** so overwrites don't happen in the first place, we must implement **Pure Alpine.js Client-Side Calculations** for the repeater math, completely removing `->live()` from the quantity and price fields.

**How would you like to proceed?**
1. Proceed with adding the **Global `<fieldset>` Disable** (band-aid approach, very fast to implement).
2. Pivot to exploring the **Alpine.js approach** to stop the server roundtrips entirely for basic math?
