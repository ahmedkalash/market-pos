/**
 * Livewire $wire type definitions for: App\Filament\Pages\PosTerminal
 * View: resources/views/filament/pages/pos-terminal.blade.php
 *
 * AUTO-GENERATED — do not edit manually.
 * Run `php artisan livewire:ide-helper` to regenerate.
 */

interface PosTerminalWire {
    // -------------------------------------------------------------------------
    // Public Properties
    // -------------------------------------------------------------------------

    /** Whether a checkout or hold operation is currently being processed. */
    isProcessing: boolean;

    /** The current product/barcode search query string. */
    search: string;

    /** The currently selected product category ID filter (null = all categories). */
    categoryId: number | null;

    /** The currently active store ID. Null until a store is selected (company-level users). */
    storeId: number | null;

    /** Display name of the currently active store. */
    storeName: string;

    /** The currency symbol for the active company (e.g. "ج.م", "$"). */
    currencySymbol: string;

    /** List of stores available to the current user. */
    storeList: Array<{
        id: number;
        name: string;
    }>;

    /** List of product categories for the active store. */
    categoryList: Array<{
        id: number;
        name: string;
    }>;

    /** List of customers available for selection at checkout. */
    customerList: Array<{
        id: number;
        name: string;
        phone: string | null;
    }>;

    /** List of shipping destinations configured for the active store. */
    shippingDestinationList: Array<{
        id: number;
        name: string;
        cost: number;
    }>;

    /** List of available payment methods (value/label pairs from PaymentMethod enum). */
    paymentMethodList: Array<{
        value: string;
        label: string;
    }>;

    // -------------------------------------------------------------------------
    // Public RPC Methods
    // -------------------------------------------------------------------------

    /**
     * Process the active POS cart and create a finalized sale invoice.
     * Dispatches 'checkout-successful' event to Alpine on success.
     *
     * @param cartData   Array of cart line items from Alpine.
     * @param metaData   Checkout metadata (customer, discount, shipping, etc.).
     */
    processCheckout(
        cartData: Array<{
            variant_id: number;
            qty: number;
            discount_type: string | null;
            discount_amount: number;
            price_type: string;
        }>,
        metaData: {
            customer_id?: number | null;
            store_id?: number | null;
            payment_method?: string;
            global_discount_type?: string;
            global_discount_amount?: number;
            shipping_destination_id?: number | null;
            shipping_cost?: number;
            shipping_address?: string | null;
            extra_items?: Array<{
                presetId?: string | number | null;
                name: string;
                amount: number;
                action_type?: string;
                notes?: string | null;
            }>;
        }
    ): Promise<void>;

    /**
     * Hold the active POS cart as a draft invoice without finalizing.
     * Dispatches 'cart-held-successful' event to Alpine on success.
     *
     * @param cartData   Array of cart line items from Alpine.
     * @param metaData   Checkout metadata (customer, discount, shipping, etc.).
     */
    holdCart(
        cartData: Array<{
            variant_id: number;
            qty: number;
            discount_type: string | null;
            discount_amount: number;
            price_type: string;
        }>,
        metaData: {
            customer_id?: number | null;
            store_id?: number | null;
            payment_method?: string;
            global_discount_type?: string;
            global_discount_amount?: number;
            shipping_destination_id?: number | null;
            shipping_cost?: number;
            shipping_address?: string | null;
            extra_items?: Array<{
                presetId?: string | number | null;
                name: string;
                amount: number;
                action_type?: string;
                notes?: string | null;
            }>;
        }
    ): Promise<void>;

    /**
     * Switch the active store context. Reloads all reference data (categories,
     * customers, shipping destinations) for the newly selected store.
     *
     * @param storeId   The ID of the store to activate.
     */
    changeStore(storeId: number): Promise<void>;

    /**
     * Create a new shipping destination for the active store via RPC.
     * Appends the new destination to shippingDestinationList on success.
     * Returns an RpcResponse envelope.
     *
     * @param data  { name: string, cost: number }
     */
    createShippingDestination(data: {
        name: string;
        cost: number;
    }): Promise<{
        success: boolean;
        data: { id: number; name: string; cost: number } | null;
        message: string | null;
        errors: Record<string, string[]>;
    }>;

    /**
     * Create a new customer record via RPC.
     * Appends the new customer to customerList on success.
     * Returns an RpcResponse envelope.
     *
     * @param data  Customer fields.
     */
    createCustomer(data: {
        name: string;
        phone?: string | null;
        email?: string | null;
        address?: string | null;
    }): Promise<{
        success: boolean;
        data: { id: number; name: string; phone: string | null; email: string | null; address: string | null } | null;
        message: string | null;
        errors: Record<string, string[]>;
    }>;

    /**
     * Fetch all active invoice extra-item presets for the active store.
     * Returns a flat array of preset objects.
     */
    getExtraItemPresets(): Promise<Array<{
        id: number;
        name: string;
        action_type: string;
        amount: number;
        notes: string | null;
    }>>;

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

/** Direct access in Alpine expressions: `$wire.processCheckout(...)` */
declare const $wire: PosTerminalWire;
