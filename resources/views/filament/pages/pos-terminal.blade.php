<div class="pos-root">
    <!-- Tailwind CSS & Phosphor Icons -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: {
                        primary: {
                            50: '#eef2ff',
                            100: '#e0e7ff',
                            200: '#c7d2fe',
                            300: '#a5b4fc',
                            400: '#818cf8',
                            500: '#6366f1',
                            600: '#4f46e5',
                            700: '#4338ca',
                        },
                        success: { 100: '#d1fae5', 500: '#10b981', 600: '#059669' },
                        danger: { 50: '#fff1f2', 100: '#ffe4e6', 500: '#f43f5e', 600: '#e11d48' },
                        warning: { 50: '#fffbeb', 100: '#fef3c7', 200: '#fde68a', 600: '#d97706' }
                    }
                }
            }
        }
    </script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        /* Custom Scrollbar for a polished, app-like feel */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        ::-webkit-scrollbar-track {
            background: transparent;
        }
        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        /* Prevent text selection on UI elements to feel like a native app */
        .no-select {
            user-select: none;
        }

        /* Hide number input spinners */
        input[type=number]::-webkit-inner-spin-button,
        input[type=number]::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        /* Enforce Full Height */
        html, body {
            height: 100vh;
            margin: 0;
            overflow: hidden;
            font-family: 'Inter', sans-serif;
        }
    </style>

    @php
        $initialData = $this->getViewData()['initialData'];
    @endphp

    <div x-data="posSystem(@js($initialData))" class="bg-gray-50 text-gray-800 h-screen w-screen overflow-hidden flex flex-col font-sans" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">

        <!-- ================= HEADER ================= -->
        <header class="bg-white border-b border-gray-200 h-16 flex items-center justify-between px-4 shrink-0 shadow-[0_2px_10px_-3px_rgba(0,0,0,0.05)] z-30 overflow-x-auto">
            <div class="flex items-center gap-3 shrink-0">
                <!-- Logo area -->
                <div class="bg-primary-600 text-white p-1.5 rounded-lg shadow-sm">
                    <i class="ph ph-storefront text-xl"></i>
                </div>

                <!-- Register Status -->
                <div class="flex items-center gap-2 me-2">
                    <span class="font-bold text-gray-800 text-sm">{{ __('pos.terminal') }}</span>
                    <div class="flex items-center gap-1.5 border border-primary-200 bg-primary-50 rounded px-2 py-0.5 text-[10px] font-bold text-primary-600 uppercase tracking-wider">
                        <div class="w-1.5 h-1.5 rounded-full bg-primary-500"></div> Open
                    </div>
                </div>

                <!-- Header Dropdowns -->
                <button class="flex items-center gap-2 bg-primary-50 border border-primary-200 rounded-xl px-4 py-1.5 text-sm font-bold hover:bg-primary-100 transition-colors text-primary-600">
                    <i class="ph ph-warehouse text-lg"></i> <span x-text="storeName"></span> <i class="ph ph-caret-down text-primary-600/70 ms-1"></i>
                </button>

                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open" @click.outside="open = false" class="flex items-center gap-2 bg-white border border-gray-200 rounded-xl px-4 py-1.5 text-sm font-medium hover:bg-gray-50 transition-colors text-gray-600">
                        <i class="ph ph-folder text-primary-600/60 text-lg"></i> <span x-text="selectedCategoryName"></span> <i class="ph ph-caret-down text-gray-400 ms-1"></i>
                    </button>
                    <div x-show="open" x-transition x-cloak class="absolute top-full mt-2 left-0 min-w-[200px] bg-white border border-gray-200 shadow-lg rounded-xl z-50 py-2 max-h-64 overflow-y-auto">
                        <button @click="selectCategory(null); open = false" class="w-full text-start px-4 py-2 hover:bg-gray-50 text-sm font-medium" :class="!selectedCategoryId ? 'text-primary-600 bg-primary-50' : 'text-gray-700'">
                            {{ __('pos.all_categories') }}
                        </button>
                        <template x-for="cat in categories" :key="cat.id">
                            <button @click="selectCategory(cat.id); open = false" class="w-full text-start px-4 py-2 hover:bg-gray-50 text-sm font-medium" :class="selectedCategoryId === cat.id ? 'text-primary-600 bg-primary-50' : 'text-gray-700'">
                                <span x-text="cat.name"></span>
                            </button>
                        </template>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-3 shrink-0">
                <!-- Customer Selector -->
                <div class="relative" x-data="{ open: false, search: '' }">
                    <button @click="open = !open" class="flex items-center gap-3 bg-primary-50/40 border border-primary-100 rounded-full ps-1.5 pe-4 py-1 hover:bg-primary-50 transition-colors">
                        <div class="w-8 h-8 rounded-full bg-primary-400 text-white flex items-center justify-center text-sm font-bold shadow-sm" x-text="selectedCustomerInitials"></div>
                        <div class="flex flex-col items-start leading-none">
                            <span class="text-[10px] font-bold text-gray-500 uppercase mb-0.5">{{ __('pos.customer') }}</span>
                            <span class="text-sm font-bold text-primary-600" x-text="selectedCustomerName"></span>
                        </div>
                        <i class="ph ph-caret-down text-gray-400 ms-1"></i>
                    </button>
                    <div x-show="open" @click.outside="open = false" x-transition x-cloak class="absolute top-full mt-2 end-0 w-64 bg-white border border-gray-200 shadow-lg rounded-xl z-50 p-2">
                        <input type="text" x-model="search" placeholder="Search customer..." class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm font-medium focus:outline-none focus:border-primary-500 mb-2">
                        <div class="max-h-48 overflow-y-auto">
                            <button @click="selectCustomer(null); open = false" class="w-full text-start px-3 py-2 hover:bg-gray-50 rounded-lg text-sm font-medium" :class="!selectedCustomerId ? 'text-primary-600 bg-primary-50' : 'text-gray-700'">
                                {{ __('pos.walk_in') }}
                            </button>
                            <template x-for="c in customers.filter(c => c.name.toLowerCase().includes(search.toLowerCase()) || (c.phone && c.phone.includes(search)))" :key="c.id">
                                <button @click="selectCustomer(c); open = false" class="w-full text-start px-3 py-2 hover:bg-gray-50 rounded-lg text-sm font-medium flex justify-between items-center" :class="selectedCustomerId === c.id ? 'text-primary-600 bg-primary-50' : 'text-gray-700'">
                                    <span x-text="c.name"></span>
                                    <span class="text-xs text-gray-400" x-text="c.phone"></span>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Tool Icons -->
                <div class="flex items-center gap-1 border-x border-gray-200 px-3 mx-1">
                    <button class="w-9 h-9 flex items-center justify-center text-gray-400 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition-colors"><i class="ph ph-user-plus text-xl"></i></button>
                    <button class="w-9 h-9 flex items-center justify-center text-gray-400 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition-colors"><i class="ph ph-receipt text-xl"></i></button>
                    <button @click="toggleFullscreen()" class="w-9 h-9 flex items-center justify-center text-gray-400 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition-colors"><i class="ph ph-corners-out text-xl"></i></button>
                </div>

                <!-- User Profile / Close -->
                <a href="{{ url('/') }}" class="w-9 h-9 rounded-full bg-danger-50 flex items-center justify-center text-danger-600 hover:bg-danger-100 transition-colors cursor-pointer" title="Exit POS">
                    <i class="ph ph-power text-base font-bold"></i>
                </a>
            </div>
        </header>

        <main class="flex-1 flex overflow-hidden">

            <!-- ================= LEFT PANEL (Cart - 40%) ================= -->
            <section class="w-2/5 flex flex-col bg-white border-e border-gray-200 shadow-[4px_0_15px_-3px_rgba(0,0,0,0.03)] z-20">

                <!-- Cart Header -->
                <div class="px-5 py-4 flex items-center justify-between border-b border-gray-100 shrink-0">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-primary-50 text-primary-600 rounded-xl flex items-center justify-center border border-primary-100"><i class="ph ph-shopping-cart text-xl"></i></div>
                        <div>
                            <h2 class="font-bold text-gray-800 text-lg leading-tight">{{ __('pos.current_cart') }}</h2>
                            <p class="text-xs text-gray-500 font-medium"><span x-text="cartItemCount"></span> {{ __('pos.items') }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-5">
                        <div class="text-end">
                            <p class="text-[10px] text-gray-400 font-bold uppercase tracking-wider mb-0.5">{{ __('pos.total') }}</p>
                            <p class="font-extrabold text-gray-800 text-lg leading-none" x-text="'$ ' + cartTotal.toFixed(2)"></p>
                        </div>
                        <button class="w-9 h-9 rounded-lg text-danger-500 border border-danger-100 hover:bg-danger-50 flex items-center justify-center transition-colors shadow-sm" @click="clearCart()" title="Clear Cart">
                            <i class="ph ph-trash text-lg"></i>
                        </button>
                    </div>
                </div>

                <!-- Cart Items Container -->
                <div class="flex-1 overflow-y-auto p-3 bg-gray-50/30">
                    <template x-for="(item, index) in cart" :key="index">
                        <div class="flex flex-col p-3 mb-2 bg-white rounded-xl border border-gray-200 shadow-sm relative group hover:border-primary-200 transition-colors">
                            <div class="flex justify-between items-start mb-3">
                                <div class="pe-4">
                                    <h4 class="font-bold text-sm text-gray-800 leading-tight mb-1" x-text="item.name"></h4>

                                    <!-- Price Type Toggle -->
                                    <template x-if="item.wholesale_enabled">
                                        <div class="flex items-center mt-2 bg-gray-100 rounded-lg p-0.5 w-fit">
                                            <button @click="item.priceType = 'retail'" class="px-2 py-1 text-[10px] font-bold uppercase tracking-wide rounded-md transition-all" :class="item.priceType === 'retail' ? 'bg-white text-primary-600 shadow-sm' : 'text-gray-500 hover:text-gray-700'">Retail</button>
                                            <button @click="item.priceType = 'wholesale'" class="px-2 py-1 text-[10px] font-bold uppercase tracking-wide rounded-md transition-all" :class="item.priceType === 'wholesale' ? 'bg-white text-primary-600 shadow-sm' : 'text-gray-500 hover:text-gray-700'">Wholesale</button>
                                        </div>
                                    </template>
                                </div>
                                <div class="text-end">
                                    <span class="font-extrabold text-primary-600 text-sm block" x-text="'$ ' + ((item.priceType === 'wholesale' ? item.wholesale_price : item.retail_price) * item.qty - (item.discount * item.qty)).toFixed(2)"></span>
                                    <span class="text-[10px] text-gray-400 font-bold" x-text="'$ ' + (item.priceType === 'wholesale' ? item.wholesale_price : item.retail_price).toFixed(2) + ' / ea'"></span>
                                </div>
                            </div>

                            <div class="flex items-center justify-between mt-auto">
                                <div class="flex items-center bg-gray-50 border border-gray-200 rounded-lg p-0.5">
                                    <button @click="updateQty(index, -1)" class="w-7 h-7 flex items-center justify-center text-gray-500 hover:text-danger-600 hover:bg-danger-50 rounded-md transition-colors"><i class="ph ph-minus font-bold text-xs"></i></button>
                                    <div class="w-10 text-center font-bold text-sm text-gray-800" x-text="item.qty"></div>
                                    <button @click="updateQty(index, 1)" class="w-7 h-7 flex items-center justify-center text-gray-500 hover:text-primary-600 hover:bg-primary-50 rounded-md transition-colors"><i class="ph ph-plus font-bold text-xs"></i></button>
                                </div>
                                <div class="flex gap-2">
                                    <button @click="promptItemDiscount(index)" class="flex items-center gap-1 text-[11px] font-bold text-warning-600 bg-warning-50 px-2 py-1.5 rounded-lg border border-warning-100 hover:bg-warning-100 transition-colors" :class="item.discount > 0 ? 'bg-warning-100 border-warning-200' : ''">
                                        <i class="ph ph-tag"></i>
                                        <span x-text="item.discount > 0 ? '-$'+item.discount : 'Disc'"></span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>

                    <div x-show="cart.length === 0" class="flex flex-col items-center justify-center h-full text-gray-400" x-cloak>
                        <i class="ph ph-shopping-cart-simple text-5xl mb-3 opacity-20"></i>
                        <p class="text-sm font-medium">{{ __('pos.cart_empty') }}</p>
                    </div>
                </div>

                <!-- Cart Calculations Footer -->
                <div class="px-5 pt-3 pb-4 border-t border-gray-200 bg-gray-50/50 shrink-0">
                    <!-- Inputs Row -->
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="text-[10px] font-bold text-gray-500 uppercase tracking-wide mb-1.5 block">{{ __('pos.discount') }}</label>
                            <div class="relative">
                                <span class="absolute start-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm font-medium">$</span>
                                <input type="number" x-model.number="globalDiscount" class="w-full border border-gray-200 rounded-lg ps-7 p-2.5 text-sm font-semibold focus:ring-1 focus:ring-primary-500 focus:border-primary-500 outline-none bg-white shadow-sm" min="0">
                            </div>
                        </div>
                        <div>
                            <label class="text-[10px] font-bold text-gray-500 uppercase tracking-wide mb-1.5 block">{{ __('pos.shipping') }}</label>
                            <div class="relative">
                                <span class="absolute start-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm font-medium">$</span>
                                <input type="number" x-model.number="shippingCost" class="w-full border border-gray-200 rounded-lg ps-7 p-2.5 text-sm font-semibold focus:ring-1 focus:ring-primary-500 focus:border-primary-500 outline-none bg-white shadow-sm" min="0">
                            </div>
                        </div>
                    </div>

                    <!-- Totals Display -->
                    <div class="space-y-2 mb-2 px-1 text-sm">
                        <div class="flex justify-between items-center text-gray-600 font-semibold">
                            <span>{{ __('pos.subtotal') }}</span>
                            <span class="text-gray-800 font-bold" x-text="'$ ' + cartSubtotal.toFixed(2)"></span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ================= RIGHT PANEL (Products - 60%) ================= -->
            <section class="w-3/5 flex flex-col bg-gray-50 relative z-10">

                <!-- Top Search Bar -->
                <div class="p-4 bg-white border-b border-gray-200 shrink-0 flex gap-3 z-10">
                    <div class="relative flex-1">
                        <div class="absolute inset-y-0 start-0 ps-4 flex items-center pointer-events-none text-gray-400">
                            <i class="ph ph-scan text-xl" wire:loading.remove wire:target="search"></i>
                            <i class="ph ph-spinner animate-spin text-xl text-primary-600" wire:loading wire:target="search" x-cloak></i>
                        </div>
                        <!-- Livewire bound search -->
                        <input type="text" id="searchInput" wire:model.live.debounce.500ms="search"
                            class="block w-full ps-12 pe-4 py-3 border border-gray-200 rounded-xl bg-gray-50/50 placeholder-gray-400 focus:outline-none focus:bg-white focus:border-primary-500 focus:ring-1 focus:ring-primary-500 transition-all text-sm font-medium shadow-inner"
                            placeholder="{{ __('pos.search_product_barcode') }}">
                    </div>
                </div>

                <!-- Product Grid -->
                <div class="flex-1 overflow-y-auto p-5 relative">
                    <div wire:loading.class="opacity-50" class="transition-opacity duration-200">
                        @if($products->count() > 0)
                            <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-4 pb-10">
                                @foreach($products as $product)
                                    <div class="bg-white rounded-2xl border border-gray-200 p-3 flex flex-col shadow-sm hover:shadow-md hover:border-primary-200 transition-all cursor-pointer group h-full"
                                         @click="addToCart({{ Js::from($product) }})">
                                        <div class="aspect-square bg-gray-50 rounded-xl mb-3 overflow-hidden flex items-center justify-center border border-gray-100 relative">
                                            @if($product['image'])
                                                <img src="{{ $product['image'] }}" alt="{{ $product['name'] }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                                            @else
                                                <div class="w-12 h-12 rounded-full bg-primary-100 text-primary-500 flex items-center justify-center">
                                                    <i class="ph ph-cube text-2xl"></i>
                                                </div>
                                            @endif

                                            <!-- Add hover overlay -->
                                            <div class="absolute inset-0 bg-primary-600/10 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                                <div class="w-10 h-10 bg-white rounded-full flex items-center justify-center text-primary-600 shadow-sm transform translate-y-2 group-hover:translate-y-0 transition-all">
                                                    <i class="ph ph-plus font-bold"></i>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mt-auto flex flex-col">
                                            <h3 class="text-sm font-bold text-gray-800 leading-tight mb-1 line-clamp-2" title="{{ $product['name'] }}">
                                                {{ $product['name'] }}
                                            </h3>
                                            <div class="text-[11px] font-semibold text-gray-400 mb-2 whitespace-nowrap overflow-hidden text-ellipsis">
                                                <span>SKU {{ $product['barcodes'][0] ?? $product['id'] }}</span>
                                                <span>• {{ __('pos.stock') }}: {{ $product['stock'] }}</span>
                                            </div>

                                            <div class="flex justify-between items-center mt-1">
                                                <div class="text-base font-extrabold text-primary-600">$ {{ number_format($product['price'], 2) }}</div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <!-- Empty State -->
                            <div class="flex flex-col items-center justify-center h-full text-gray-400 py-12">
                                <i class="ph ph-magnifying-glass text-4xl opacity-30 mb-3 block"></i>
                                <div class="text-[15px] font-semibold">{{ __('pos.no_products_found') }}</div>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Bottom Pay Bar & Pagination -->
                <div class="bg-white border-t border-gray-200 shrink-0 flex items-center justify-between px-6 py-4 shadow-[0_-4px_15px_-3px_rgba(0,0,0,0.02)]">

                    <!-- Pagination (Server Side via Livewire) -->
                    <div class="flex items-center w-1/2">
                        {{ $products->links() }}
                    </div>

                    <!-- Pay Now -->
                    <div class="flex items-center gap-6 ms-auto">
                        <div class="text-end">
                            <p class="text-[10px] text-gray-400 font-bold uppercase tracking-wider mb-0.5">{{ __('pos.total_payable') }}</p>
                            <p class="text-2xl font-extrabold text-gray-800 leading-none tracking-tight" x-text="'$ ' + cartTotal.toFixed(2)"></p>
                        </div>
                        <button class="bg-primary-600 hover:bg-primary-700 text-white px-10 py-4 rounded-xl font-bold text-lg transition-all shadow-md hover:shadow-lg flex items-center gap-2 transform active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed"
                                @click="processPayment()"
                                :disabled="cart.length === 0 || isProcessing">
                            <i class="ph ph-check-bold text-xl" x-show="!isProcessing"></i>
                            <i class="ph ph-spinner animate-spin text-xl" x-show="isProcessing" x-cloak></i>
                            {{ __('pos.pay_now') }}
                        </button>
                    </div>
                </div>
            </section>
        </main>

        <!-- ================= PAYMENT SUCCESS MODAL ================= -->
        <div x-show="showSuccessModal"
             class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm z-50 flex items-center justify-center opacity-0 transition-opacity duration-300"
             :class="{'opacity-100 hidden': !showSuccessModal, 'opacity-100': showSuccessModal}"
             x-cloak
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0">

            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm p-8 text-center transform scale-95 transition-transform duration-300"
                 :class="{'scale-100': showSuccessModal}"
                 @click.outside="closeSuccessModal()">
                <div class="w-20 h-20 bg-success-100 rounded-full flex items-center justify-center mx-auto mb-6 text-success-500">
                    <i class="ph ph-check-circle text-5xl"></i>
                </div>
                <h2 class="text-2xl font-bold text-gray-800 mb-2">
                    {{ __('pos.payment_successful') }}
                </h2>
                <p class="text-gray-500 mb-6">
                    Invoice #<span x-text="completedInvoiceNumber" class="font-bold text-gray-800"></span> generated.
                </p>
                <p class="text-3xl font-extrabold text-gray-800 mb-8">
                    $<span x-text="completedInvoiceTotal.toFixed(2)"></span>
                </p>

                <button type="button"
                        class="w-full bg-gray-100 hover:bg-gray-200 text-gray-700 py-3 rounded-xl font-bold transition-colors"
                        @click="closeSuccessModal()">
                    {{ __('pos.new_sale') }} (Enter)
                </button>
            </div>
        </div>
    </div>

    <!-- Alpine.js POS State Engine -->
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('posSystem', (initialData) => ({
                storeId: initialData.storeId,
                storeName: initialData.storeName,
                categories: initialData.categories || [],
                customers: initialData.customers || [],

                globalDiscount: 0,
                shippingCost: 0,
                cart: [],

                // Customer selection
                selectedCustomerId: null,
                selectedCustomerName: '{{ __('pos.walk_in') }}',

                // Category selection
                selectedCategoryId: null,

                // Local processing & Modal state
                isProcessing: false,
                showSuccessModal: false,
                completedInvoiceNumber: '',
                completedInvoiceTotal: 0,

                init() {
                    window.addEventListener('keydown', (e) => {
                        if (e.key === 'Enter' && this.showSuccessModal) {
                            this.closeSuccessModal();
                            return;
                        }
                        if (e.key === 'Escape' && this.showSuccessModal) {
                            this.closeSuccessModal();
                            return;
                        }
                        if (e.key === 'F2') {
                            e.preventDefault();
                            this.focusSearch();
                        }
                        if (e.key === 'F4') {
                            e.preventDefault();
                            this.clearCart();
                        }
                    });

                    // Listen to Livewire checkout events
                    window.addEventListener('checkout-successful', (e) => {
                        this.handleCheckoutSuccess(e.detail[0] || e.detail);
                    });
                    window.addEventListener('cart-held-successful', () => {
                        this.handleCartHeld();
                    });
                },

                get selectedCategoryName() {
                    if (!this.selectedCategoryId) return '{{ __('pos.all_categories') }}';
                    const cat = this.categories.find(c => c.id == this.selectedCategoryId);
                    return cat ? cat.name : '{{ __('pos.all_categories') }}';
                },

                get selectedCustomerInitials() {
                    if (!this.selectedCustomerName) return 'W';
                    return this.selectedCustomerName.charAt(0).toUpperCase();
                },

                get cartSubtotal() {
                    return this.cart.reduce((sum, item) => {
                        let activePrice = item.priceType === 'wholesale' ? item.wholesale_price : item.retail_price;
                        return sum + ((activePrice - item.discount) * item.qty);
                    }, 0);
                },

                get cartTotal() {
                    let sub = this.cartSubtotal;
                    let discount = parseFloat(this.globalDiscount) || 0;
                    let shipping = parseFloat(this.shippingCost) || 0;
                    if (discount > sub) discount = sub;
                    return Math.max(0, (sub - discount) + shipping);
                },

                get cartItemCount() {
                    return this.cart.reduce((sum, item) => sum + item.qty, 0);
                },

                selectCustomer(c) {
                    if (!c) {
                        this.selectedCustomerId = null;
                        this.selectedCustomerName = '{{ __('pos.walk_in') }}';
                    } else {
                        this.selectedCustomerId = c.id;
                        this.selectedCustomerName = c.name;
                    }
                },

                selectCategory(id) {
                    this.selectedCategoryId = id;
                    this.$wire.set('categoryId', id);
                },

                addToCart(product) {
                    const existing = this.cart.find(item => item.variant_id === product.id);
                    if (existing) {
                        existing.qty++;
                    } else {
                        this.cart.unshift({
                            variant_id: product.id,
                            name: product.name,
                            retail_price: parseFloat(product.price) || 0,
                            wholesale_price: parseFloat(product.wholesale_price) || parseFloat(product.price) || 0,
                            priceType: 'retail', // default
                            wholesale_enabled: !!product.wholesale_enabled,
                            qty: 1,
                            discount: 0,
                        });
                    }
                },

                updateQty(index, delta) {
                    if (!this.cart[index]) return;
                    this.cart[index].qty += delta;
                    if (this.cart[index].qty <= 0) {
                        this.cart.splice(index, 1);
                    }
                },

                removeItem(index) {
                    this.cart.splice(index, 1);
                },

                clearCart() {
                    this.cart = [];
                    this.globalDiscount = 0;
                    this.shippingCost = 0;
                },

                promptItemDiscount(index) {
                    const item = this.cart[index];
                    if (!item) return;
                    const amount = prompt(`Enter discount per unit for ${item.name} ($):`, item.discount);
                    if (amount !== null) {
                        const parsed = parseFloat(amount);
                        if (!isNaN(parsed) && parsed >= 0) {
                            item.discount = parsed;
                        }
                    }
                },

                focusSearch() {
                    document.getElementById('searchInput')?.focus();
                },

                toggleFullscreen() {
                    if (!document.fullscreenElement) {
                        document.documentElement.requestFullscreen().catch(err => {
                            console.warn("Fullscreen API failed", err);
                        });
                    } else if (document.exitFullscreen) {
                        document.exitFullscreen();
                    }
                },

                processPayment() {
                    if (this.cart.length === 0 || this.isProcessing) return;
                    this.isProcessing = true;

                    // Format cart for backend matching the expected structure
                    const formattedCart = this.cart.map(item => ({
                        variant_id: item.variant_id,
                        qty: item.qty,
                        discount: item.discount,
                        price_type: item.priceType
                    }));

                    this.$wire.processCheckout(formattedCart, {
                        customer_id: this.selectedCustomerId || null,
                        store_id: this.storeId || null,
                        global_discount: parseFloat(this.globalDiscount) || 0,
                        shipping_cost: parseFloat(this.shippingCost) || 0,
                    }).finally(() => {
                        this.isProcessing = false;
                    });
                },

                holdCartAction() {
                    if (this.cart.length === 0 || this.isProcessing) return;
                    this.isProcessing = true;

                    const formattedCart = this.cart.map(item => ({
                        variant_id: item.variant_id,
                        qty: item.qty,
                        discount: item.discount,
                        price_type: item.priceType
                    }));

                    this.$wire.holdCart(formattedCart, {
                        customer_id: this.selectedCustomerId || null,
                        store_id: this.storeId || null,
                        global_discount: parseFloat(this.globalDiscount) || 0,
                        shipping_cost: parseFloat(this.shippingCost) || 0,
                    }).finally(() => {
                        this.isProcessing = false;
                    });
                },

                handleCheckoutSuccess(detail) {
                    this.completedInvoiceNumber = detail?.invoice_number || 'INV-001';
                    this.completedInvoiceTotal = detail?.total || this.cartTotal;
                    this.showSuccessModal = true;
                    this.clearCart();
                },

                closeSuccessModal() {
                    this.showSuccessModal = false;
                    this.focusSearch();
                },

                handleCartHeld() {
                    this.clearCart();
                }
            }));
        });
    </script>
</div>
