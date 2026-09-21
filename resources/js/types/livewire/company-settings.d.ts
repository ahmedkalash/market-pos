/**
 * Livewire $wire type definitions for: App\Filament\Pages\CompanySettingsPage
 * View: resources/views/filament/pages/company-settings.blade.php
 *
 * AUTO-GENERATED — do not edit manually.
 * Run `php artisan livewire:ide-helper` to regenerate.
 */

export interface CompanySettingsPageWire {
    // -------------------------------------------------------------------------
    // Public Properties
    // -------------------------------------------------------------------------

    /** The company model data array, bound to the Filament form. */
    data: Record<string, unknown> | null;

    // -------------------------------------------------------------------------
    // Public RPC Methods
    // -------------------------------------------------------------------------

    /**
     * Persist the company settings form data to the database.
     * Shows a success or error Filament notification on completion.
     */
    save(): Promise<void>;

    /**
     * Format a monetary preview amount using the current form's localization
     * settings. Used internally by Filament's live TextEntry components.
     *
     * @param amount   The raw float amount to format.
     * @param get      The Filament Get utility (server-side only, not callable from Alpine).
     */
    formatPreview(amount: number, get: unknown): Promise<string>;

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
declare const $wire: CompanySettingsPageWire;


