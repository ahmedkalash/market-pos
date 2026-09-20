<?php

namespace App\Filament\Pages;

use App\Enums\PaymentMethod;
use App\Models\Customer;
use App\Models\InvoiceExtraItemPreset;
use App\Models\ProductBarcode;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\ShippingDestination;
use App\Models\Store;
use App\Models\User;
use App\Services\PosCheckoutService;
use App\Support\RpcResponse;
use BackedEnum;
use Exception;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;
use Throwable;

/**
 * @property-read User $user
 */
class PosTerminal extends Page
{
    use WithPagination;

    public bool $isProcessing = false;

    public string $search = '';

    public ?int $categoryId = null;

    public ?int $storeId = null;

    public string $storeName = '';

    public string $currencySymbol = '';

    public array $storeList = [];

    public array $categoryList = [];

    public array $customerList = [];

    public array $shippingDestinationList = [];

    public array $paymentMethodList = [];

    public ?int $perPage = 3;

    public int $minBarcodeSearchLength = 5;

    protected static ?string $slug = 'pos';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-computer-desktop';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.pos-terminal';

    // Use base layout to hide the standard panel sidebar and topbar for true full screen
    protected static string $layout = 'filament-panels::components.layout.base';

    public static function getNavigationLabel(): string
    {
        return __('pos.terminal');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('pos.register');
    }

    public function getTitle(): string|Htmlable
    {
        return __('pos.terminal');
    }

    public function getHeading(): string|Htmlable
    {
        return ''; // Hide default heading to save space
    }

    public function getBreadcrumbs(): array
    {
        return []; // Hide breadcrumbs to save space
    }

    #[Computed]
    public function user(): User
    {
        /** @var User */
        return auth()->user();
    }

    public function mount(): void
    {
        $this->refreshStoreContext();
    }

    public function changeStore(int $newStoreId): void
    {
        if (! $this->user->isCompanyLevel()) {
            return; // Store-level users cannot switch stores
        }
        // Tenant boundary validation: Ensure the store belongs to the user's company
        $storeExists = Store::query()
            ->where('id', $newStoreId)
            ->exists();
        if (! $storeExists) {
            Notification::make()
                ->danger()
                ->title(__('pos.store_not_found'))
                ->send();

            return;
        }

        $this->refreshStoreContext($newStoreId);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingCategoryId(): void
    {
        $this->resetPage();
    }

    protected function getViewData(): array
    {
        $paginatedVariants = $this->paginatedVariants();

        // Transform the collection items while keeping the paginator intact
        $paginatedVariants->getCollection()->transform(function ($variant) {
            return [
                'id' => $variant->id,
                'product_id' => $variant->product_id,
                'category_id' => $variant->product?->category_id,
                'name' => $variant->full_qualified_name,
                'price' => (float) $variant->retail_price,
                'wholesale_price' => (float) $variant->wholesale_price,
                'wholesale_enabled' => (bool) $variant->wholesale_enabled,
                'retail_is_price_negotiable' => (bool) $variant->retail_is_price_negotiable,
                'min_retail_price' => (float) $variant->min_retail_price,
                'wholesale_is_price_negotiable' => (bool) $variant->wholesale_is_price_negotiable,
                'min_wholesale_price' => (float) $variant->min_wholesale_price,
                'wholesale_qty_threshold' => (float) $variant->wholesale_qty_threshold,
                'uom_name' => $variant->unitOfMeasure?->{lang_suffix('name')} ?? '',
                'stock' => (float) $variant->quantity,
                'barcodes' => $variant->barcodes->pluck('barcode')->toArray(),
                'image' => (method_exists($variant->product, 'getFirstMediaUrl') ? $variant->product->getFirstMediaUrl('image', 'thumb') : null) ?: null,
            ];
        });

        return [
            'products' => $paginatedVariants,
        ];
    }

    // todo: review
    public function processCheckout(array $cartData, array $metaData): void
    {
        try {
            if (! $this->storeId) {
                throw new Exception(__('pos.select_store_first'));
            }

            $metaData['store_id'] = $this->storeId;

            $invoice = PosCheckoutService::make()->checkout($cartData, $metaData);

            Notification::make()
                ->success()
                ->title(__('pos.checkout_success'))
                ->send();

            // Tell Alpine to reset the cart and show success modal
            $this->dispatch('checkout-successful', [
                'invoice_number' => $invoice->invoice_number,
                'total' => (float) $invoice->total_amount,
            ]);

        } catch (Throwable $e) {
            Log::error('POS Checkout Failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            Notification::make()
                ->danger()
                ->title(__('pos.checkout_failed'))
                ->body($e->getMessage())
                ->send();

            $this->halt(true);
        }
    }

    // todo: review
    public function holdCart(array $cartData, array $metaData): void
    {
        try {
            if (! $this->storeId) {
                throw new Exception(__('pos.select_store_first'));
            }

            $metaData['store_id'] = $this->storeId;

            PosCheckoutService::make()->holdCart($cartData, $metaData);

            Notification::make()
                ->success()
                ->title(__('pos.cart_held_success'))
                ->send();

            $this->dispatch('cart-held-successful');

        } catch (Throwable $e) {
            Notification::make()
                ->danger()
                ->title(__('pos.cart_hold_failed'))
                ->body($e->getMessage())
                ->send();

            $this->halt(true);
        }
    }

    public function createShippingDestination(array $data): array
    {

        $validator = validator(
            array_merge($data, ['store_id' => $this->storeId]),
            [
                'store_id' => ['required', 'integer', 'exists:stores,id'],
                'name' => ['required', 'string', 'max:255'],
                'cost' => ['required', 'numeric', 'min:0'],
            ],
            [
                'store_id.required' => __('pos.select_store_first'),
                'store_id.exists' => __('pos.store_not_found'),
                'name.required' => __('pos.destination_name_required'),
                'cost.required' => __('pos.cost_required'),
                'cost.numeric' => __('pos.cost_must_be_number'),
                'cost.min' => __('pos.cost_must_be_positive'),
            ]
        );

        if ($validator->fails()) {
            return RpcResponse::fromValidator($validator);
        }

        $validated = array_map(
            fn ($value) => is_string($value) ? (blank($value) ? null : trim($value)) : $value,
            $validator->validated()
        );

        $destination = ShippingDestination::create([
            'company_id' => $this->user->company_id,
            'store_id' => $validated['store_id'],
            'name' => $validated['name'],
            'cost' => (float) $validated['cost'],
            'is_active' => true,
        ]);

        $result = [
            'id' => $destination->id,
            'name' => $destination->name,
            'cost' => (float) $destination->cost,
        ];

        $this->shippingDestinationList[] = $result;

        Notification::make()
            ->title(__('pos.destination_created_successfully'))
            ->success()
            ->send();

        return RpcResponse::success(data: $result);
    }

    public function createCustomer(array $data): array
    {
        $validator = validator($data, [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:65535'],
        ]);

        if ($validator->fails()) {
            return RpcResponse::fromValidator($validator);
        }

        $validated = array_map(
            fn ($value) => is_string($value) ? (blank($value) ? null : trim($value)) : $value,
            $validator->validated()
        );

        $customer = Customer::create([
            'company_id' => $this->user->company_id,
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'address' => $validated['address'] ?? null,
            'is_active' => true,
        ]);

        $result = [
            'id' => $customer->id,
            'name' => $customer->name,
            'phone' => $customer->phone,
            'email' => $customer->email,
            'address' => $customer->address,
        ];

        $this->customerList[] = $result;

        Notification::make()
            ->title(__('pos.customer_created_successfully'))
            ->success()
            ->send();

        return RpcResponse::success(data: $result);
    }

    public function getExtraItemPresets(): array
    {
        if (! $this->storeId) {
            return [];
        }

        return InvoiceExtraItemPreset::query()
            ->forSaleInvoice()
            ->active()
            ->filterByStore($this->storeId)
            ->get(['id', 'name', 'action_type', 'amount', 'notes'])
            ->toArray();
    }

    /**
     * Populates all public reference-data properties from the database.
     * Called on mount() and after any action that changes the active store.
     */
    private function refreshStoreContext(?int $newStoreId = null): void
    {
        $this->resetPage();

        if ($this->user->isStoreLevel()) {
            $this->storeId = $this->user->store_id;
        } elseif ($this->user->isCompanyLevel() && $newStoreId !== null) {
            $this->storeId = $newStoreId;
        }

        $this->currencySymbol = $this->user->company->currency_symbol ?? '$';

        $storesCollection = $this->stores();
        $this->storeList = $storesCollection->toArray();

        $activeStore = $this->storeId ? $storesCollection->firstWhere('id', $this->storeId) : null;
        $this->storeName = $activeStore ? $activeStore['name'] : __('pos.select_store');

        $this->categoryList = $this->productCategories()->toArray();
        $this->customerList = $this->customers()->toArray();
        $this->shippingDestinationList = $this->shippingDestinations()->toArray();
        $this->paymentMethodList = $this->paymentMethodList()->toArray();
    }

    /**
     * Fetch Categories
     */
    private function productCategories(): Collection|BaseCollection
    {
        if (! $this->storeId) {
            return collect();
        }

        return ProductCategory::query()
            ->active()
            ->filterByStore($this->storeId)
            ->get(['id', 'name_en', 'name_ar'])
            ->map(fn ($cat) => [
                'id' => $cat->id,
                'name' => $cat->name,
            ]);
    }

    /**
     * Fetch Shipping Destinations for active store
     */
    private function shippingDestinations(): BaseCollection
    {
        if (! $this->storeId) {
            return collect();
        }

        return ShippingDestination::query()
            ->active()
            ->where('store_id', $this->storeId)
            ->get(['id', 'name', 'cost']);
    }

    private function customers(): BaseCollection
    {
        return Customer::query()
            ->active()
            ->get(['id', 'name', 'phone']);
    }

    private function stores(): Collection|BaseCollection
    {
        $storesQuery = Store::query();
        if ($this->user->isStoreLevel()) {
            $storesQuery->where('id', $this->user->store_id);
        }

        return $storesQuery->get(['id', 'name_en', 'name_ar'])
            ->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
            ]);
    }

    private function paginatedVariants()
    {
        // Fetch Products (Variants) with Server-Side Pagination
        $variantsQuery = ProductVariant::query()
            ->active()
            ->with(['product.category', 'barcodes', 'unitOfMeasure']);
        if ($this->storeId) {
            $variantsQuery->filterByStore($this->storeId);
        } else {
            // Force empty results if no store is selected (Company Level admin hasn't picked yet)
            $variantsQuery->filterByStore(0);
        }
        if ($this->categoryId) {
            $variantsQuery->filterByCategory($this->categoryId);
        }

        // TODO (Performance Optimization - Future Version):
        // The combined `OR` condition between fullNameSearch and barcodes forces MySQL into full-table scans.
        // Consider:
        // 1. Fast-Path Barcode Check: If ctype_alnum($this->search), query ProductBarcode index first;
        //    only fallback to fullNameSearch if no barcode is found.
        // 2. Hardware Scanner Interceptor: In Alpine, detect <30ms keypress bursts + Enter to dispatch
        //    instant addToCartByBarcode() without filtering catalog pagination.
        // 3. UI Mode Toggle: Add an explicit [Name | Barcode] filter toggle to isolate index usage.
        if (filled($this->search)) {
            $term = trim($this->search);
            $barcodeVariantIds = collect();

            // Fast-Path: Retail barcodes are strictly numeric digits (EAN-13, UPC, EAN-8, in-store codes)
            if (ctype_digit($term) && strlen($term) >= $this->minBarcodeSearchLength) {
                $barcodeVariantIds = ProductBarcode::query()
                    ->where('barcode', 'like', "$term%")
                    ->whereHas('productVariant', function ($q) {
                        $q->filterByStore($this->storeId ?: 0);
                    })
                    ->limit(100)
                    ->pluck('product_variant_id');
            }

            if ($barcodeVariantIds->isNotEmpty()) {
                $variantsQuery->whereIn('id', $barcodeVariantIds);
            } else {
                // Graceful fallback to full-name search across variants and parent products
                $variantsQuery->fullNameSearch($term);
            }
        }

        return $variantsQuery->paginate($this->perPage ?? 10);
    }

    private function paymentMethodList(): BaseCollection
    {
        return collect(PaymentMethod::cases())->map(fn ($pm) => [
            'value' => $pm->value,
            'label' => $pm->getLabel(),
        ]);
    }
}
