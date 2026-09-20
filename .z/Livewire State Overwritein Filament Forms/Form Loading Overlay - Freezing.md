# Implementation Plan: Option 5 (Form Loading Overlay / Freezing)

## Goal
To implement a simple but effective mechanism that locks the user out of typing or modifying the form while a Livewire `->live()` request (such as a recalculation) is currently in-flight. This completely eliminates the race condition where server responses overwrite new user input.

## Sub-Options Investigation

There are several ways to implement "Option 5" natively in Filament/Livewire. Here is a breakdown of the sub-options, their differences, and how they would be implemented:

### Sub-Option A: Input Disabling (`wire:loading.attr="disabled"`)
**How it works:** We attach Livewire's loading attribute to all interactive fields (Quantity, Price, Discount) using Filament's `->extraAttributes()`. When *any* Livewire request is processing, these inputs automatically gain the `disabled` HTML attribute.
* **Pros:** 100% Native. Very easy to implement. Blocks all keyboard and mouse interactions.
* **Cons:** **Focus Loss.** When an HTML input is marked `disabled`, the browser immediately drops the user's cursor focus. When the request finishes and the input is re-enabled, the user has to click the input again to continue typing. This is a very poor UX for fast typists.

### Sub-Option B: Input Read-Only (`wire:loading.attr="readonly"`)
**How it works:** Similar to Sub-Option A, but we use `readonly` instead of `disabled`.
* **Pros:** **Preserves Focus.** The user's cursor stays in the input while the server is loading. They just can't type anything until the loader finishes.
* **Cons:** Doesn't visually look "disabled" unless we also bind a `wire:loading.class="bg-gray-100"`. Also, `readonly` doesn't strictly work on `Select` dropdowns (they still open), only on text/number inputs.

### Sub-Option C: CSS Wrapper Lock (`wire:loading.class="pointer-events-none opacity-50"`)
**How it works:** We apply a loading class to the entire `Repeater` wrapper or the Form wrapper.
* **Pros:** Gives a beautiful visual fade effect (opacity 50%) to the entire list of items while recalculating. Prevents mouse clicks (`pointer-events-none`).
* **Cons:** `pointer-events-none` only blocks the mouse. If the user's cursor is already inside the input, they can still type with their keyboard, which defeats the purpose of preventing the race condition.

### Sub-Option D: The Transparent Overlay Shield (Custom View)
**How it works:** We inject a custom, invisible (or semi-transparent) `<div>` with `absolute inset-0 z-50` over the entire `Repeater`. We attach `wire:loading` to this div. It only exists in the DOM while a request is in-flight.
* **Pros:** Blocks all mouse clicks on the form. If we add a bit of Alpine.js (`x-on:keydown.prevent`), it can also swallow keyboard inputs.
* **Cons:** Requires creating a small custom Blade view and injecting it into the Filament schema layout. Can be slightly tricky to get the CSS positioning perfect so it covers exactly what we want.

## Comparison & Recommendation

| Sub-Option | Preserves Focus? | Blocks Typing? | Blocks Clicks? | Implementation Effort |
| :--- | :--- | :--- | :--- | :--- |
| **A (Disabled)** | ❌ No | ✅ Yes | ✅ Yes | Very Low |
| **B (Readonly)** | ✅ Yes | ✅ Yes | ⚠️ Partial | Very Low |
| **C (CSS Lock)** | ✅ Yes | ❌ No | ✅ Yes | Very Low |
| **D (Overlay)** | ✅ Yes | ⚠️ Needs Alpine | ✅ Yes | Medium |

### My Recommendation
**I highly recommend Sub-Option B (Readonly + Opacity Class).** 

It provides the best balance. By using `->extraAttributes(['wire:loading.attr' => 'readonly', 'wire:loading.class' => 'opacity-70 cursor-wait'])`, we achieve the following:
1. The user's cursor stays exactly where it is (focus is preserved).
2. Typing is strictly blocked until the server responds, preventing the overwrite race condition.
3. The visual opacity change signals to the user that the system is "thinking" without aggressively flashing disabled inputs.
4. It requires no custom views—just simple Filament configuration.

For `Select` components (like Product selection), we can fallback to `wire:loading.attr="disabled"` since focus preservation is less critical for dropdowns compared to rapid number typing.

## Open Questions for You

1. **Which Sub-Option do you prefer?** Do you agree with using Sub-Option B (Readonly) for the text inputs?
2. **Would you like this applied globally** to the entire form, or strictly to the `Repeater` item inputs? (Applying it just to the repeater items is usually less disruptive).

Please let me know if you approve this approach or if you'd like to adjust the sub-option choice!
