# TODO: Store Deletion Workflow & Data Safety

*This document serves as a blueprint for implementing a robust deletion process for Stores in the future, once the application has more complex related data like invoices and products.*

## Current Status (Temporary)
- **SoftDeletes Disabled:** We have deliberately elected **not** to use `SoftDeletes` across the application at this time to maintain simplicity. The `SoftDeletes` trait has been removed from the `Store` model.
- **Store Deletion Disabled:** Because we do not yet have the full scope of related models (like Invoices, Orders, Products), we cannot safely program all the "cascading" side-effects. Therefore, **all delete actions have been disabled** on the `StoreResource`. Stores cannot currently be deleted from the Filament UI.

---

## Future Implementation Plan

When it is time to enable Store deletion (e.g., when Invoices & Products are implemented), follow this workflow to ensure data integrity.

### 1. Handling Assigned Users
When a store is deleted, its assigned users **must not** be left pointing to a non-existent or "dead" store.
- **Action Required:** We must capture the deletion event (via an Observer or Action Hook) and actively update all associated users to set `store_id = null`.
- **Why:** This places the users back into a "company-level" holding state, preventing login errors and allowing a Company Admin to reassign them to a new active store.

### 2. Handling Transactional Data (Invoices, Orders, Products)
In a POS/SaaS environment, transactional records must be preserved for financial auditing.
- **Action Required:** Do **not** use database-level `cascadeOnDelete()` for financial records linked to a store. 
- **Action Required:** Re-evaluate `SoftDeletes`. When this feature is built, we should re-introduce `SoftDeletes` on the `Store` model. 
- **Why:** This allows the store to disappear from the active UI while preserving its primary key in the database so that historical invoices and reports remain perfectly intact.

### 3. Filament UI Warnings
Before the user confirms deletion, the UI must definitively explain the side-effects.
- **Action Required:** Customize the `DeleteAction` and `BulkDeleteAction` in `StoresTable` to include a dynamic modal description.
- **Example Warning:** *"Are you sure you would like to delete this store? This will unassign [X] active users and soft-delete [Y] associated products. Historical invoices will be preserved but hidden."*

---

By following this workflow, we will ensure that store deletion is a safe, transparent, and non-destructive operation for essential business data.
