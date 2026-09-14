<?php

namespace App\Filament\Pages;

use App\Models\Customer;
use App\Models\InvoiceExtraItemPreset;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\ShippingDestination;
use App\Models\Store;
use App\Models\User;
use App\Services\PosCheckoutService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Log;
use Livewire\WithPagination;

class PosTerminal extends Page
{
    use WithPagination;

    public bool $isProcessing = false;
    public string $search = '';
    public ?int $categoryId = null;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-computer-desktop';

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

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingCategoryId()
    {
        $this->resetPage();
    }

    protected function getViewData(): array
    {
        /** @var User $user */
        $user = auth()->user();
        $companyId = $user->company_id;

        $stores = Store::query()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->get(['id', 'name_en', 'name_ar'])
            ->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->{lang_suffix('name')},
            ]);

        // todo handel company level accounts
        $storeId = $user->store_id
            ?? $user->company?->stores()->first()?->id
            ?? $stores->first()['id'] ?? null;

        $activeStore = $stores->firstWhere('id', $storeId);

        // Fetch Categories
        $categoriesQuery = ProductCategory::query()->where('is_active', true);
        if ($storeId) {
            $categoriesQuery->where('store_id', $storeId);
        } elseif ($companyId) {
            $categoriesQuery->where('company_id', $companyId);
        }
        $categories = $categoriesQuery->get(['id', 'name_en', 'name_ar'])
            ->map(fn ($cat) => [
                'id' => $cat->id,
                'name' => $cat->{lang_suffix('name')},
            ]);

        // Fetch Customers
        $customers = Customer::query()->where('is_active', true)
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->get(['id', 'name', 'phone'])
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'phone' => $c->phone,
            ]);

        // Fetch Products (Variants) with Server-Side Pagination
        $variantsQuery = ProductVariant::query()->with(['product.category', 'barcodes'])
            ->where('is_active', true);

        if ($storeId) {
            $variantsQuery->where('store_id', $storeId);
        } elseif ($companyId) {
            $variantsQuery->where('company_id', $companyId);
        }

        if ($this->categoryId) {
            $variantsQuery->whereHas('product', function ($q) {
                $q->where('category_id', $this->categoryId);
            });
        }

        if (filled($this->search)) {
            $variantsQuery->where(function ($query) {
                $query->whereNameLike($this->search)
                    ->orWhereHas('product', function ($q) {
                        $q->whereNameLike($this->search);
                    })
                    ->orWhereHas('barcodes', function ($q) {
                        $q->where('barcode', 'like', "%{$this->search}%");
                    });
            });
        }

        $paginatedVariants = $variantsQuery->paginate(16);

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
                'stock' => (float) $variant->quantity,
                'barcodes' => $variant->barcodes->pluck('barcode')->toArray(),
                'image' => (method_exists($variant->product, 'getFirstMediaUrl') ? $variant->product->getFirstMediaUrl('image', 'thumb') : null) ?: null,
            ];
        });

        return [
            'initialData' => [
                'storeId' => $storeId,
                'storeName' => $activeStore['name'] ?? __('pos.main_store'),
                'currencySymbol' => $user->company->currency_symbol ?? 'ج.م',
                'stores' => $stores,
                'categories' => $categories,
                'customers' => $customers,
                'shippingDestinations' => ShippingDestination::query()
                    ->where('is_active', true)
                    ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
                    ->get(['id', 'name', 'cost'])
                    ->map(fn ($d) => [
                        'id' => $d->id,
                        'name' => $d->name,
                        'cost' => (float) $d->cost,
                    ]),
                // Products array is now empty in initialData since we render via Blade,
                // but we might still need some data for barcodes.
                // However, barcode scanning will be harder if products are paginated.
                // For a real POS, barcode scanning usually queries an API.
                // We will leave it empty and handle scanning differently if needed.
            ],
            'products' => $paginatedVariants,
        ];
    }

    public function processCheckout(array $cartData, array $metaData)
    {
        dd($cartData, $metaData);
        try {
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

        } catch (\Throwable $e) {
            Log::error('POS Checkout Failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            Notification::make()
                ->danger()
                ->title(__('pos.checkout_failed'))
                ->body($e->getMessage())
                ->send();

            $this->halt(true);
        }
    }

    public function holdCart(array $cartData, array $metaData)
    {
        try {
            PosCheckoutService::make()->holdCart($cartData, $metaData);

            Notification::make()
                ->success()
                ->title(__('pos.cart_held_success'))
                ->send();

            $this->dispatch('cart-held-successful');

        } catch (\Throwable $e) {
            Notification::make()
                ->danger()
                ->title(__('pos.cart_hold_failed'))
                ->body($e->getMessage())
                ->send();

            $this->halt(true);
        }
    }

    public function getExtraItemPresets(): array
    {
        $companyId = auth()->user()->company_id;

        return InvoiceExtraItemPreset::query()
            ->forSaleInvoice()
            ->where('is_active', true)
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->get(['id', 'name', 'action_type', 'amount', 'notes'])
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'action_type' => $p->action_type->value,
                'amount' => (float) $p->amount,
                'notes' => $p->notes,
            ])
            ->toArray();
    }
}
