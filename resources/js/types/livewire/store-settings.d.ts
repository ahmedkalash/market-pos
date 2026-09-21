/**
 * Livewire $wire type definitions for: App\Filament\Pages\StoreSettingsPage
 * View: resources/views/filament/pages/store-settings.blade.php
 *
 * AUTO-GENERATED — do not edit manually.
 * Run `php artisan livewire:ide-helper` to regenerate.
 */

interface StoreSettingsPageWire {
    // -------------------------------------------------------------------------
    // Public Properties
    // -------------------------------------------------------------------------

    /** The store model data array, bound to the Filament form. */
    data: Record<string, unknown> | null;

    // -------------------------------------------------------------------------
    // Public RPC Methods
    // -------------------------------------------------------------------------

    /**
     * Persist the store settings form data to the database.
     * Shows a success or error Filament notification on completion.
     */
    save(): Promise<void>;

    /**
     * Exists only on store setting .d.ts file to test if the IDE helper is working correctly.
     */
    abc(): Promise<void>;

    // -------------------------------------------------------------------------
    // Standard Livewire $wire helpers
    // -------------------------------------------------------------------------

    $refresh(): Promise<void>;
    $set(property: string, value: unknown): Promise<void>;
    $get(property: string): unknown;
    $call(method: string, ...params: unknown[]): Promise<unknown>;

    /** Allow any other unlisted dynamic property without breaking the IDE. */
    [key: string]: unknown;
}

/** Direct access in Alpine expressions: `$wire.save()` */
declare const $wire: StoreSettingsPageWire;
