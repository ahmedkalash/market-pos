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

        /* Ensure Filament notifications appear above modals and remain clickable */
        .fi-no {
            z-index: 9999 !important;
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

    <div x-data="posSystem()" class="bg-gray-50 text-gray-800 h-screen w-screen overflow-hidden flex flex-col font-sans" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">

        <!-- Hidden Thermal Receipt Print Target Frame -->
        <iframe id="receiptPrintFrame" style="position: absolute; width: 0; height: 0; border: 0; visibility: hidden;"></iframe>

        <!-- ================= HEADER ================= -->
        <header  class="relative bg-white border-b border-gray-200 h-16 flex items-center justify-between px-4 shrink-0 shadow-[0_2px_10px_-3px_rgba(0,0,0,0.05)] z-40">
            <div class="flex items-center gap-3 shrink-0">
                <!-- Logo area -->
                <div class="bg-primary-600 text-white p-1.5 rounded-lg shadow-sm">
                    <i class="ph ph-storefront text-xl"></i>
                </div>

                <!-- Register Status -->
                <div class="flex items-center gap-2 me-2">
                    <span class="font-bold text-gray-800 text-sm">{{ __('pos.terminal') }}</span>
                    <div class="flex items-center gap-1.5 border border-primary-200 bg-primary-50 rounded px-2 py-0.5 text-[10px] font-bold text-primary-600 uppercase tracking-wider">
                        <div class="w-1.5 h-1.5 rounded-full bg-primary-500"></div> {{ __('pos.open') }}
                    </div>
                </div>

                <!-- Header Dropdowns -->
                <div class="relative" x-data="{ open: false }">
                    <button @click="$wire.storeList.length > 1 ? open = !open : null" @click.outside="open = false" class="flex items-center gap-2 bg-primary-50 border border-primary-200 rounded-xl px-4 py-1.5 text-sm font-bold hover:bg-primary-100 transition-colors text-primary-600" :class="$wire.storeList.length > 1 ? 'cursor-pointer' : 'cursor-default'">
                        <i class="ph ph-warehouse text-lg"></i>
                        <span x-text="$wire.storeName"></span>
                        <i class="ph ph-caret-down text-primary-600/70 ms-1" x-show="$wire.storeList.length > 1"></i>
                    </button>
                    <div x-show="open" x-transition x-cloak class="absolute top-full mt-2 left-0 min-w-[200px] bg-white border border-gray-200 shadow-lg rounded-xl z-50 py-2 max-h-64 overflow-y-auto">
                        <template x-for="store in $wire.storeList" :key="store.id">
                            <button @click="$wire.changeStore(store.id); cart = []; open = false" class="w-full text-start px-4 py-2 hover:bg-gray-50 text-sm font-medium" :class="$wire.storeId === store.id ? 'text-primary-600 bg-primary-50' : 'text-gray-700'">
                                <span x-text="store.name"></span>
                            </button>
                        </template>
                    </div>
                </div>

                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open" @click.outside="open = false" class="flex items-center gap-2 bg-white border border-gray-200 rounded-xl px-4 py-1.5 text-sm font-medium hover:bg-gray-50 transition-colors text-gray-600">
                        <i class="ph ph-folder text-primary-600/60 text-lg"></i> <span x-text="selectedCategoryName"></span> <i class="ph ph-caret-down text-gray-400 ms-1"></i>
                    </button>
                    <div x-show="open" x-transition x-cloak class="absolute top-full mt-2 left-0 min-w-[200px] bg-white border border-gray-200 shadow-lg rounded-xl z-50 py-2 max-h-64 overflow-y-auto">
                        <button @click="selectCategory(null); open = false" class="w-full text-start px-4 py-2 hover:bg-gray-50 text-sm font-medium" :class="!selectedCategoryId ? 'text-primary-600 bg-primary-50' : 'text-gray-700'">
                            {{ __('pos.all_categories') }}
                        </button>
                        <template x-for="cat in $wire.categoryList" :key="cat.id">
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
                        <div class="flex justify-between items-center mb-1.5 pb-1 border-b border-gray-100">
                            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">{{ __('pos.customer') }}</span>
                            <button type="button" @click="openCustomerModal(); open = false" class="text-xs font-bold text-primary-600 hover:text-primary-700 flex items-center gap-1 transition-colors">
                                <i class="ph ph-plus text-xs"></i>
                                <span>{{ __('pos.new_customer') }}</span>
                            </button>
                        </div>
                        <input type="text" x-model="search" placeholder="{{ __('pos.search_customer') }}" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm font-medium focus:outline-none focus:border-primary-500 mb-2">
                        <div class="max-h-48 overflow-y-auto">
                            <button @click="selectCustomer(null); open = false" class="w-full text-start px-3 py-2 hover:bg-gray-50 rounded-lg text-sm font-medium" :class="!selectedCustomerId ? 'text-primary-600 bg-primary-50' : 'text-gray-700'">
                                {{ __('pos.walk_in') }}
                            </button>
                            <template x-for="c in $wire.customerList.filter(c => c.name.toLowerCase().includes(search.toLowerCase()) || (c.phone && c.phone.includes(search)))" :key="c.id">
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
                    <button @click="openCustomerModal()" class="w-9 h-9 flex items-center justify-center text-gray-400 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition-colors" title="{{ __('pos.create_customer') }}"><i class="ph ph-user-plus text-xl"></i></button>
                    <button class="w-9 h-9 flex items-center justify-center text-gray-400 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition-colors"><i class="ph ph-receipt text-xl"></i></button>
                    <button @click="toggleFullscreen()" class="w-9 h-9 flex items-center justify-center text-gray-400 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition-colors"><i class="ph ph-corners-out text-xl"></i></button>
                </div>

                <!-- Cashier Display -->
                <div class="flex items-center gap-2 px-3 py-1 bg-gray-50 rounded-xl border border-gray-200 text-xs text-gray-700">
                    <div class="w-7 h-7 rounded-full bg-primary-100 text-primary-600 flex items-center justify-center font-bold text-xs">
                        <i class="ph ph-user"></i>
                    </div>
                    <div class="flex flex-col text-start leading-tight">
                        <span class="text-[9px] font-bold text-gray-400 uppercase tracking-wider">{{ __('pos.cashier') }}</span>
                        <span class="font-bold text-gray-800 text-xs truncate max-w-[120px]">{{ auth()->user()->name }}</span>
                    </div>
                </div>

                <!-- User Profile / Close -->
                <a href="{{ url('/') }}" class="w-9 h-9 rounded-full bg-danger-50 flex items-center justify-center text-danger-600 hover:bg-danger-100 transition-colors cursor-pointer" title="{{ __('pos.exit_pos') }}">
                    <i class="ph ph-power text-base font-bold"></i>
                </a>
            </div>
        </header>

        <main class="flex-1 flex overflow-hidden">

            <!-- ================= LEFT PANEL (Cart - 40%) ================= -->
            <section class="w-2/5 flex flex-col bg-white border-e border-gray-200 shadow-[4px_0_15px_-3px_rgba(0,0,0,0.03)] z-20">

                <!-- Cart Header -->
                <div class="p-5 flex items-center justify-between border-b border-gray-200 bg-white shrink-0 shadow-sm relative z-20">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-primary-100 text-primary-600 rounded-xl flex items-center justify-center shadow-inner">
                            <i class="ph ph-shopping-bag text-xl font-bold"></i>
                        </div>
                        <div>
                            <h2 class="text-lg font-extrabold text-gray-800 leading-tight">{{ __('pos.current_cart') }}</h2>
                            <p class="text-xs font-bold text-gray-400"><span x-text="cartItemCount"></span> {{ __('pos.items') }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <button @click="openExtraItemsModal()" class="w-9 h-9 flex items-center justify-center text-primary-600 bg-primary-50 rounded-xl border border-primary-100 hover:bg-primary-100 hover:border-primary-200 transition-all shadow-sm group relative" title="{{ __('pos.extra_items_tooltip') }}">
                            <i class="ph ph-plus-minus font-bold text-lg group-hover:scale-110 transition-transform"></i>
                            <span x-show="extraItems.length > 0" class="absolute -top-1 -right-1 flex h-3 w-3 items-center justify-center rounded-full bg-danger-500 text-[9px] font-bold text-white ring-2 ring-white"></span>
                        </button>
                        <button type="button"
                                @click="holdCartAction()"
                                :disabled="cart.length === 0 || isProcessing || hasInvalidCartItems"
                                :class="(cart.length === 0 || isProcessing || hasInvalidCartItems) ? 'opacity-40 cursor-not-allowed' : 'hover:text-amber-600 hover:bg-amber-50 hover:border-amber-200 text-gray-500'"
                                class="w-9 h-9 flex items-center justify-center bg-gray-50 rounded-xl border border-gray-200 transition-all shadow-sm group"
                                :title="hasInvalidCartItems ? '{{ __('pos.resolve_cart_issues') }}' : '{{ __('pos.hold_cart_tooltip') }}'">
                            <i class="ph ph-pause font-bold text-lg group-hover:scale-110 transition-transform" x-show="!isProcessing"></i>
                            <i class="ph ph-spinner animate-spin text-sm" x-show="isProcessing" x-cloak></i>
                        </button>
                        <button @click="confirmClearCart()"
                                :disabled="cart.length === 0"
                                :class="cart.length === 0 ? 'opacity-40 cursor-not-allowed' : 'hover:text-danger-600 hover:bg-danger-50 hover:border-danger-200'"
                                class="w-9 h-9 flex items-center justify-center text-gray-500 bg-gray-50 rounded-xl border border-gray-200 transition-all shadow-sm group"
                                title="{{ __('pos.clear') }}">
                            <i class="ph ph-trash font-bold text-lg group-hover:scale-110 transition-transform"></i>
                        </button>
                    </div>
                </div>

                <!-- Cart Items Container -->
                <div class="flex-1 overflow-y-auto p-3 bg-gray-50/30">
                    <template x-for="(item, index) in cart" :key="item.variant_id">
                        <div class="flex flex-col p-3 mb-2 bg-white rounded-xl border border-gray-200 shadow-sm relative group hover:border-primary-200 transition-colors">
                            <div class="flex justify-between items-start mb-3">
                                <div class="pe-4">
                                    <h4 class="font-bold text-sm text-gray-800 leading-tight mb-1" x-text="item.name"></h4>

                                    <!-- Price Type Toggle / Indicator -->
                                    <template x-if="item.wholesale_enabled">
                                        <div class="flex items-center mt-2 bg-gray-100 rounded-lg p-0.5 w-fit">
                                            <button @click="togglePriceType(index, 'retail')" class="px-2 py-1 text-[10px] font-bold uppercase tracking-wide rounded-md transition-all" :class="item.priceType === 'retail' ? 'bg-white text-primary-600 shadow-sm' : 'text-gray-500 hover:text-gray-700'" title="{{ __('pos.retail_tooltip') }}">{{ __('pos.retail') }}</button>
                                            <button @click="togglePriceType(index, 'wholesale')" class="px-2 py-1 text-[10px] font-bold uppercase tracking-wide rounded-md transition-all" :class="item.priceType === 'wholesale' ? 'bg-white text-primary-600 shadow-sm' : 'text-gray-500 hover:text-gray-700'" title="{{ __('pos.wholesale_tooltip') }}">{{ __('pos.wholesale') }}</button>
                                        </div>
                                    </template>
                                    <template x-if="!item.wholesale_enabled">
                                        <div class="flex items-center mt-2 bg-gray-100 rounded-lg p-0.5 w-fit">
                                            <span class="px-2 py-1 text-[10px] font-bold uppercase tracking-wide rounded-md bg-white text-gray-600 shadow-sm border border-gray-100/50" title="{{ __('pos.retail_tooltip') }}">
                                                {{ __('pos.retail') }}
                                            </span>
                                        </div>
                                    </template>

                                    <!-- Wholesale Info & Minimum Qty Warning -->
                                    <template x-if="item.priceType === 'wholesale' && item.wholesale_qty_threshold > 0">
                                        <div class="mt-1 flex flex-col gap-0.5">
                                            <span class="text-[10px] font-bold text-primary-700 bg-primary-50 border border-primary-200 px-1.5 py-0.5 rounded w-fit"
                                                  x-text="'{{ __('pos.wholesale_min_qty_warning', ['qty' => '']) }}' + item.wholesale_qty_threshold + ' ' + (item.uom_name || '')">
                                            </span>
                                            <span x-show="item.qty < item.wholesale_qty_threshold"
                                                  class="text-[10px] font-bold text-danger-600 bg-danger-50 border border-danger-200 px-1.5 py-0.5 rounded flex items-center gap-1 w-fit animate-pulse">
                                                <i class="ph ph-warning-circle text-xs"></i>
                                                <span>{{ __('pos.wholesale_min_qty_error', ['product' => '', 'min' => '']) }} (<span x-text="item.wholesale_qty_threshold"></span>)</span>
                                            </span>
                                        </div>
                                    </template>

                                    <!-- Stock Insufficient Warning -->
                                    <div x-show="item.stock !== undefined && item.qty > item.stock" class="mt-1 text-[10px] font-bold text-danger-600 bg-danger-50 border border-danger-200 px-1.5 py-0.5 rounded flex items-center gap-1 w-fit">
                                        <i class="ph ph-warning text-xs"></i>
                                        <span>{{ __('pos.insufficient_stock') }} ({{ __('pos.stock') }}: <span x-text="item.stock"></span>)</span>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <!-- Line Subtotal Before Discount (Strikethrough if discounted) -->
                                    <span x-show="item.discountAmount > 0"
                                          class="line-through text-xs text-gray-400 font-semibold block"
                                          x-text="currencySymbol + ' ' + ((item.priceType === 'wholesale' ? item.wholesale_price : item.retail_price) * item.qty).toFixed(2)">
                                    </span>
                                    <!-- Net Line Total -->
                                    <span class="font-extrabold text-primary-600 text-sm block"
                                          x-text="currencySymbol + ' ' + ((item.priceType === 'wholesale' ? item.wholesale_price : item.retail_price) * item.qty - (item.discountType === 'percentage' ? ((item.priceType === 'wholesale' ? item.wholesale_price : item.retail_price) * (item.discountAmount/100) * item.qty) : (item.discountAmount * item.qty))).toFixed(2)">
                                    </span>
                                    <!-- Unit Price (Strikethrough original if discounted) -->
                                    <div class="text-[10px] text-gray-400 font-bold flex items-center justify-end gap-1">
                                        <template x-if="item.discountAmount > 0">
                                            <span class="line-through text-gray-400" x-text="currencySymbol + ' ' + (item.priceType === 'wholesale' ? item.wholesale_price : item.retail_price).toFixed(2)"></span>
                                        </template>
                                        <span x-text="currencySymbol + ' ' + ((item.priceType === 'wholesale' ? item.wholesale_price : item.retail_price) - (item.discountType === 'percentage' ? ((item.priceType === 'wholesale' ? item.wholesale_price : item.retail_price) * (item.discountAmount/100)) : item.discountAmount)).toFixed(2) + ' / ' + (item.uom_name || '{{ __('pos.each') }}')"></span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center justify-between mt-auto">
                                <div class="flex items-center bg-gray-50 border border-gray-200 rounded-lg p-0.5">
                                    <button @click="updateQty(index, -1)" class="w-7 h-7 flex items-center justify-center text-gray-500 hover:text-danger-600 hover:bg-danger-50 rounded-md transition-colors" title="{{ __('pos.decrease_qty') }}"><i class="ph ph-minus font-bold text-xs"></i></button>
                                    <div class="w-auto min-w-[2.5rem] px-1 text-center font-bold text-sm text-gray-800" x-text="item.qty + (item.uom_name ? ' ' + item.uom_name : '')"></div>
                                    <button @click="updateQty(index, 1)"
                                            :disabled="item.stock !== undefined && item.qty >= item.stock"
                                            :class="(item.stock !== undefined && item.qty >= item.stock) ? 'opacity-30 cursor-not-allowed text-gray-300' : 'text-gray-500 hover:text-primary-600 hover:bg-primary-50'"
                                            class="w-7 h-7 flex items-center justify-center rounded-md transition-colors"
                                            title="{{ __('pos.increase_qty') }}"><i class="ph ph-plus font-bold text-xs"></i></button>
                                </div>
                                <div class="flex items-center gap-2">
                                    <template x-if="item.priceType === 'wholesale' ? item.wholesale_is_price_negotiable : item.retail_is_price_negotiable">
                                        <button @click="promptItemDiscount(index)"
                                                class="flex items-center gap-1.5 text-[11px] font-bold text-warning-600 bg-warning-50 px-2.5 py-1.5 rounded-lg border border-warning-100 hover:bg-warning-100 transition-colors"
                                                :class="item.discountAmount > 0 ? 'bg-warning-100 border-warning-200' : ''"
                                                title="{{ __('pos.item_discount_tooltip') }}">
                                            <i class="ph ph-tag"></i>
                                            <span x-text="item.discountAmount > 0 ? (item.discountType === 'percentage' ? item.discountAmount + '% (' + currencySymbol + ' ' + (((item.priceType === 'wholesale' ? item.wholesale_price : item.retail_price) * (item.discountAmount/100)) * item.qty).toFixed(2) + ')' : currencySymbol + ' ' + (item.discountAmount * item.qty).toFixed(2)) : '{{ __('pos.discount_short') }}'"></span>
                                        </button>
                                    </template>
                                    <template x-if="!(item.priceType === 'wholesale' ? item.wholesale_is_price_negotiable : item.retail_is_price_negotiable)">
                                        <button disabled
                                                class="flex items-center gap-1 text-[10px] font-bold text-gray-400 bg-gray-100 px-2 py-1.5 rounded-lg border border-gray-200 opacity-60 cursor-not-allowed"
                                                title="{{ __('pos.non_negotiable') }}">
                                            <i class="ph ph-lock-simple text-xs"></i>
                                            <span>{{ __('pos.fixed_price') }}</span>
                                        </button>
                                    </template>
                                    <button @click="removeItem(index)"
                                            class="w-7 h-7 flex items-center justify-center text-gray-400 bg-gray-50 rounded-lg border border-gray-200 hover:text-danger-600 hover:bg-danger-50 hover:border-danger-200 transition-all shadow-sm group"
                                            title="{{ __('pos.delete_item') }}">
                                        <i class="ph ph-trash font-bold text-xs group-hover:scale-110 transition-transform"></i>
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
                    <!-- Modals Buttons Row -->
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <button @click="openGlobalDiscountModal()" class="flex justify-between items-center bg-white border border-gray-200 rounded-lg p-2.5 text-sm font-semibold hover:border-primary-300 transition-colors text-start shadow-sm" :class="globalDiscountAmount > 0 ? 'border-primary-300 bg-primary-50/50' : ''">
                            <div class="flex items-center gap-1">
                                <span class="text-[11px] font-bold text-gray-500 uppercase tracking-wide">{{ __('pos.global_discount_btn') }}</span>
                                <span class="text-gray-400 hover:text-gray-600 cursor-help" title="{{ __('pos.global_discount_tooltip') }}">
                                    <i class="ph ph-info text-xs"></i>
                                </span>
                            </div>
                            <span class="text-gray-800 font-bold" x-text="globalDiscountAmount > 0 ? (globalDiscountType === 'percentage' ? globalDiscountAmount + '% (' + currencySymbol + ' ' + cartGlobalDiscount.toFixed(2) + ')' : currencySymbol + ' ' + parseFloat(globalDiscountAmount).toFixed(2)) : '{{ __('pos.add') }}'"></span>
                        </button>
                        <button @click="openShippingModal()" class="flex justify-between items-center bg-white border border-gray-200 rounded-lg p-2.5 text-sm font-semibold hover:border-primary-300 transition-colors text-start shadow-sm" :class="(shippingDestinationId || shippingCost > 0) ? 'border-primary-300 bg-primary-50/50' : ''">
                            <div class="flex items-center gap-1">
                                <span class="text-[11px] font-bold text-gray-500 uppercase tracking-wide">{{ __('pos.shipping') }}</span>
                                <span class="text-gray-400 hover:text-gray-600 cursor-help" title="{{ __('pos.shipping_tooltip') }}">
                                    <i class="ph ph-info text-xs"></i>
                                </span>
                            </div>
                            <span class="text-gray-800 font-bold" x-text="(shippingDestinationId || shippingCost > 0) ? currencySymbol + ' ' + parseFloat(shippingCost || 0).toFixed(2) : '{{ __('pos.add') }}'"></span>
                        </button>
                    </div>

                    <!-- Totals Grid Display -->
                    <div class="grid grid-cols-2 gap-2 mb-2 px-1 text-[11px] font-bold">
                        <!-- 1. Gross Items Total Before Discounts -->
                        <div class="bg-white border border-gray-200 rounded-lg p-2 flex justify-between items-center shadow-sm">
                            <div class="flex items-center gap-1">
                                <span class="text-gray-500 uppercase tracking-wider">{{ __('pos.items_before_discount') }}</span>
                                <span class="text-gray-400 hover:text-gray-600 cursor-help" title="{{ __('pos.items_before_discount_tooltip') }}">
                                    <i class="ph ph-info text-xs"></i>
                                </span>
                            </div>
                            <span class="text-gray-800 text-xs font-bold" x-text="currencySymbol + ' ' + cartSubtotal.toFixed(2)"></span>
                        </div>

                        <!-- 2. Extra Items / Adjustments (Dynamic Red/Blue) -->
                        <div class="rounded-lg p-2 flex justify-between items-center shadow-sm border transition-colors"
                            :class="extraItemsTotal < 0 ? 'bg-danger-50 border-danger-100' : 'bg-primary-50 border-primary-100'">
                            <div class="flex items-center gap-1">
                                <span class="uppercase tracking-wider" :class="extraItemsTotal < 0 ? 'text-danger-600' : 'text-primary-600'">{{ __('pos.extra_items_adjustments') }}</span>
                                <span class="cursor-help" :class="extraItemsTotal < 0 ? 'text-danger-400' : 'text-primary-400'" title="{{ __('pos.extra_items_tooltip') }}">
                                    <i class="ph ph-info text-xs"></i>
                                </span>
                            </div>
                            <span class="text-xs font-bold" :class="extraItemsTotal < 0 ? 'text-danger-700' : 'text-primary-700'"
                                x-text="(extraItemsTotal < 0 ? '-' + currencySymbol + ' ' + Math.abs(extraItemsTotal).toFixed(2) : (extraItemsTotal > 0 ? '+' : '') + currencySymbol + ' ' + extraItemsTotal.toFixed(2))"></span>
                        </div>

                        <!-- 3. Line Items Discount Total -->
                        <div class="bg-danger-50 border border-danger-100 rounded-lg p-2 flex justify-between items-center shadow-sm">
                            <div class="flex items-center gap-1">
                                <span class="text-danger-600 uppercase tracking-wider">{{ __('pos.line_items_discount') }}</span>
                                <span class="text-danger-400 hover:text-danger-600 cursor-help" title="{{ __('pos.line_items_discount_tooltip') }}">
                                    <i class="ph ph-info text-xs"></i>
                                </span>
                            </div>
                            <span class="text-danger-700 text-xs font-bold" x-text="'-' + currencySymbol + ' ' + cartItemsDiscountTotal.toFixed(2)"></span>
                        </div>

                        <!-- 4. Final Total Discounts -->
                        <div class="bg-danger-50 border border-danger-200 rounded-lg p-2 flex justify-between items-center shadow-sm">
                            <div class="flex items-center gap-1">
                                <span class="text-danger-700 uppercase tracking-wider font-extrabold">{{ __('pos.final_total_discount') }}</span>
                                <span class="text-danger-400 hover:text-danger-600 cursor-help" title="{{ __('pos.final_total_discount_tooltip') }}">
                                    <i class="ph ph-info text-xs"></i>
                                </span>
                            </div>
                            <span class="text-danger-800 text-sm font-extrabold" x-text="'-' + currencySymbol + ' ' + cartTotalDiscounts.toFixed(2)"></span>
                        </div>

                        <!-- 5. Grand Total (Full width, Green) -->
                        <div class="col-span-2 bg-success-50 border border-success-200 rounded-lg p-2.5 flex justify-between items-center shadow-sm ring-1 ring-success-500/20">
                            <div class="flex items-center gap-1.5">
                                <span class="text-success-700 uppercase tracking-wider font-extrabold">{{ __('pos.grand_total') }}</span>
                                <span class="text-success-500 hover:text-success-700 cursor-help" title="{{ __('pos.grand_total_tooltip') }}">
                                    <i class="ph ph-info text-xs"></i>
                                </span>
                            </div>
                            <span class="text-success-800 text-base font-black" x-text="currencySymbol + ' ' + cartTotal.toFixed(2)"></span>
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
                                    @php
                                        $isOutOfStock = ($product['stock'] ?? 0) <= 0;
                                    @endphp
                                    <div class="bg-white rounded-2xl border border-gray-200 p-3 flex flex-col shadow-sm transition-all h-full {{ $isOutOfStock ? 'opacity-60 cursor-not-allowed' : 'hover:shadow-md hover:border-primary-200 cursor-pointer group' }}"
                                         @click="{{ $isOutOfStock ? 'null' : 'addToCart(' . Js::from($product) . ')' }}">
                                        <div class="aspect-square bg-gray-50 rounded-xl mb-3 overflow-hidden flex items-center justify-center border border-gray-100 relative">
                                            @if($isOutOfStock)
                                                <span class="absolute top-2 start-2 bg-danger-600 text-white text-[10px] font-extrabold px-2 py-0.5 rounded-md shadow uppercase tracking-wider z-10">
                                                    {{ __('pos.out_of_stock') }}
                                                </span>
                                            @endif

                                            @if($product['image'])
                                                <img src="{{ $product['image'] }}" alt="{{ $product['name'] }}" class="w-full h-full object-cover {{ $isOutOfStock ? 'grayscale' : 'group-hover:scale-105 transition-transform duration-300' }}">
                                            @else
                                                <div class="w-12 h-12 rounded-full {{ $isOutOfStock ? 'bg-gray-200 text-gray-400' : 'bg-primary-100 text-primary-500' }} flex items-center justify-center">
                                                    <i class="ph ph-cube text-2xl"></i>
                                                </div>
                                            @endif

                                            @if(!$isOutOfStock)
                                                <!-- Add hover overlay -->
                                                <div class="absolute inset-0 bg-primary-600/10 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                                    <div class="w-10 h-10 bg-white rounded-full flex items-center justify-center text-primary-600 shadow-sm transform translate-y-2 group-hover:translate-y-0 transition-all">
                                                        <i class="ph ph-plus font-bold"></i>
                                                    </div>
                                                </div>
                                            @endif
                                        </div>

                                        <div class="mt-auto flex flex-col">
                                            <h3 class="text-sm font-bold text-gray-800 leading-tight mb-1 line-clamp-2" title="{{ $product['name'] }}">
                                                {{ $product['name'] }}
                                            </h3>
                                            <div class="text-[11px] font-semibold text-gray-400 mb-2 whitespace-nowrap overflow-hidden text-ellipsis flex items-center gap-1.5">
                                                <span>{{ __('pos.sku') }} {{ $product['barcodes'][0] ?? $product['id'] }}</span>
                                                <span>•</span>
                                                <span class="{{ $isOutOfStock ? 'text-danger-600 font-bold' : '' }}">{{ __('pos.stock') }}: {{ $product['stock'] }}</span>
                                            </div>

                                            <div class="flex justify-between items-center mt-1">
                                                <div class="text-base font-extrabold {{ $isOutOfStock ? 'text-gray-400' : 'text-primary-600' }}" x-text="currencySymbol + ' ' + Number({{ $product['price'] }}).toFixed(2)"></div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <!-- Empty State -->
                            <div class="flex flex-col items-center justify-center h-full text-gray-400 py-12">
                                @if(!$this->storeId)
                                    <i class="ph ph-warehouse text-4xl opacity-30 mb-3 block"></i>
                                    <div class="text-[15px] font-semibold">{{ __('pos.select_store_first') ?? 'Please select a store' }}</div>
                                @else
                                    <i class="ph ph-magnifying-glass text-4xl opacity-30 mb-3 block"></i>
                                    <div class="text-[15px] font-semibold">{{ __('pos.no_products_found') }}</div>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Catalog Pagination Footer -->
                {{ $products->links('filament.pages.pos.catalog-pagination') }}

                <!-- Cart Issues Warning Banner (Blocks checkout if wholesale min or stock is violated) -->
                <div x-show="hasInvalidCartItems" class="px-6 py-2.5 bg-danger-50 border-t border-danger-200 text-danger-700 text-xs font-bold flex items-center justify-between shadow-inner" x-cloak>
                    <div class="flex items-center gap-2">
                        <i class="ph ph-warning-circle text-base shrink-0 animate-pulse text-danger-600"></i>
                        <span>{{ __('pos.resolve_cart_issues') }}</span>
                    </div>
                </div>

                <!-- Bottom Pay Bar -->
                <div class="bg-white border-t border-gray-200 shrink-0 flex items-center justify-end px-6 py-4 shadow-[0_-4px_15px_-3px_rgba(0,0,0,0.02)]">
                    <!-- Payment Method & Pay Now -->
                    <div class="flex items-center gap-4">
                        <!-- Payment Method Dropdown -->
                        <div class="flex flex-col text-start">
                            <label for="posPaymentMethod" class="text-[10px] text-gray-400 font-bold uppercase tracking-wider mb-1">{{ __('sale_invoice.payment_method') }}</label>
                            <div class="relative">
                                <select id="posPaymentMethod"
                                        x-model="paymentMethod"
                                        class="bg-gray-50 border border-gray-200 text-gray-800 text-sm font-bold rounded-xl focus:ring-primary-500 focus:border-primary-500 block py-2.5 ps-3 pe-8 appearance-none transition-colors shadow-sm cursor-pointer hover:border-gray-300">
                                    <option value="cash">{{ __('sale_invoice.payment_method_cash') }}</option>
                                    <option value="card">{{ __('sale_invoice.payment_method_card') }}</option>
                                    <option value="split">{{ __('sale_invoice.payment_method_split') }}</option>
                                </select>
                                <div class="pointer-events-none absolute inset-y-0 end-0 flex items-center pe-2.5 text-gray-500">
                                    <i class="ph ph-caret-down text-xs font-bold"></i>
                                </div>
                            </div>
                        </div>

                        <div class="text-end">
                            <p class="text-[10px] text-gray-400 font-bold uppercase tracking-wider mb-0.5">{{ __('pos.total_payable') }}</p>
                            <p class="text-2xl font-extrabold text-gray-800 leading-none tracking-tight" x-text="currencySymbol + ' ' + cartTotal.toFixed(2)"></p>
                        </div>
                        <button type="button"
                                class="bg-amber-500 hover:bg-amber-600 text-white px-6 py-4 rounded-xl font-bold text-lg transition-all shadow-md hover:shadow-lg flex items-center gap-2 transform active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed"
                                @click="holdCartAction()"
                                :disabled="cart.length === 0 || isProcessing || hasInvalidCartItems"
                                :title="hasInvalidCartItems ? '{{ __('pos.resolve_cart_issues') }}' : '{{ __('pos.hold_cart_tooltip') }}'">
                            <i class="ph ph-pause-circle text-xl" x-show="!isProcessing"></i>
                            <i class="ph ph-spinner animate-spin text-xl" x-show="isProcessing" x-cloak></i>
                            <span>{{ __('pos.hold_cart') }}</span>
                        </button>
                        <button type="button"
                                class="bg-primary-600 hover:bg-primary-700 text-white px-10 py-4 rounded-xl font-bold text-lg transition-all shadow-md hover:shadow-lg flex items-center gap-2 transform active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed"
                                @click="openCheckoutModal()"
                                :disabled="cart.length === 0 || isProcessing || hasInvalidCartItems"
                                :title="hasInvalidCartItems ? '{{ __('pos.resolve_cart_issues') }}' : ''">
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
             class="fixed inset-0 bg-gray-900/50 z-50 flex items-center justify-center opacity-0 transition-opacity duration-300"
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
                    {{ __('pos.invoice_num') }}<span x-text="completedInvoiceNumber" class="font-bold text-gray-800"></span> {{ __('pos.generated') }}
                </p>
                <p class="text-3xl font-extrabold text-gray-800 mb-8">
                    <span x-text="currencySymbol"></span><span x-text="completedInvoiceTotal.toFixed(2)"></span>
                </p>

                <button type="button"
                        class="w-full bg-gray-100 hover:bg-gray-200 text-gray-700 py-3 rounded-xl font-bold transition-colors"
                        @click="closeSuccessModal()">
                    {{ __('pos.new_sale') }} (Enter)
                </button>
            </div>
        </div>
        <!-- Modals Container -->
        <div >
            <template x-teleport="body">
                <div>
                <!-- Item Discount Modal -->
                <div x-show="activeModal === 'itemDiscount'" class="fixed inset-0 bg-gray-900/50 z-[100] flex items-center justify-center" x-cloak>
                    <template x-if="activeModal === 'itemDiscount'">
                        <div class="bg-white rounded-xl shadow-xl w-full max-w-sm" @click.outside="closeModal()">
                            <div class="p-4 border-b border-gray-100 flex justify-between items-center bg-gray-50/50 rounded-t-xl">
                                <div>
                                    <h3 class="font-bold text-gray-800">{{ __('pos.item_discount') }}</h3>
                                    <p class="text-[10px] font-bold text-gray-500 mt-0.5"><span x-text="modalData.name || ''"></span></p>
                                </div>
                                <button @click="closeModal()" class="text-gray-400 hover:text-gray-600"><i class="ph ph-x text-lg"></i></button>
                            </div>

                            <!-- Not Negotiable Warning -->
                            <div x-show="!modalData.isNegotiable" class="p-4 bg-danger-50 text-danger-600 text-sm font-semibold text-center border-b border-danger-100">
                                <i class="ph ph-warning-circle text-lg mb-1 block"></i>
                                {{ __('pos.item_not_negotiable') }}
                            </div>

                            <!-- Error Message -->
                            <div x-show="modalData.error" class="mx-4 mt-4 p-3 bg-danger-50 text-danger-600 text-xs font-bold rounded-lg border border-danger-100 flex items-start gap-2">
                                <i class="ph ph-warning-circle text-base"></i>
                                <span x-text="modalData.error"></span>
                            </div>

                            <div class="p-4 space-y-4" :class="!modalData.isNegotiable ? 'opacity-50 pointer-events-none' : ''">

                                <!-- Unit Discount Notice Banner -->
                                <div class="flex items-start gap-2.5 p-3 bg-primary-50/70 border border-primary-100 rounded-xl text-primary-800 text-xs font-medium leading-relaxed">
                                    <i class="ph ph-info text-base shrink-0 text-primary-600 mt-0.5"></i>
                                    <span>{{ __('pos.unit_discount_notice') }}</span>
                                </div>

                                <!-- Financial Summary -->
                                <div class="bg-gray-50 p-3.5 rounded-xl border border-gray-200 space-y-2">
                                    <div class="flex justify-between items-center text-xs">
                                        <span class="text-gray-500 font-semibold">{{ __('pos.original_unit_price') }}</span>
                                        <span class="text-gray-800 font-bold" x-text="currencySymbol + ' ' + (modalData.unitPrice || 0).toFixed(2)"></span>
                                    </div>
                                    <div class="flex justify-between items-center text-xs">
                                        <span class="text-gray-500 font-semibold">{{ __('pos.min_allowed_price') }}</span>
                                        <span class="text-warning-600 font-bold" x-text="currencySymbol + ' ' + (modalData.minAllowed || 0).toFixed(2)"></span>
                                    </div>
                                    <div class="border-t border-gray-200 pt-2 flex justify-between items-center text-xs">
                                        <span class="text-gray-700 font-bold">{{ __('pos.final_unit_price') }}</span>
                                        <span class="text-primary-600 font-extrabold text-sm" x-text="currencySymbol + ' ' + ((modalData.unitPrice || 0) - (modalData.type === 'percentage' ? ((modalData.unitPrice || 0) * ((modalData.amount || 0) / 100)) : (modalData.amount || 0))).toFixed(2)"></span>
                                    </div>
                                    <div class="border-t border-dashed border-gray-200 pt-1.5 flex justify-between items-center text-[11px] text-gray-500">
                                        <span>{{ __('pos.subtotal_before_discount') }} (<span x-text="(cart[modalData.index]?.qty || 1) + ' ' + (cart[modalData.index]?.uom_name || '{{ __('pos.each') }}')"></span>)</span>
                                        <span class="text-gray-800 font-bold" x-text="currencySymbol + ' ' + ((modalData.unitPrice || 0) * (cart[modalData.index]?.qty || 1)).toFixed(2)"></span>
                                    </div>
                                    <div class="flex justify-between items-center text-[11px] text-gray-500">
                                        <span>{{ __('pos.total_line_impact') }}</span>
                                        <span class="text-danger-600 font-bold" x-text="'- ' + currencySymbol + ' ' + (((modalData.type === 'percentage' ? ((modalData.unitPrice || 0) * ((modalData.amount || 0) / 100)) : (modalData.amount || 0))) * (cart[modalData.index]?.qty || 1)).toFixed(2)"></span>
                                    </div>
                                    <div class="border-t border-gray-200 pt-1.5 flex justify-between items-center text-xs">
                                        <span class="text-gray-800 font-bold">{{ __('pos.final_line_total') }}</span>
                                        <span class="text-primary-700 font-extrabold text-sm" x-text="currencySymbol + ' ' + ((((modalData.unitPrice || 0) - (modalData.type === 'percentage' ? ((modalData.unitPrice || 0) * ((modalData.amount || 0) / 100)) : (modalData.amount || 0)))) * (cart[modalData.index]?.qty || 1)).toFixed(2)"></span>
                                    </div>
                                </div>

                                <div>
                                    <label class="text-[11px] font-bold text-gray-500 uppercase tracking-wide">{{ __('pos.discount_type') }}</label>
                                    <div class="flex mt-1.5 border border-gray-200 rounded-lg overflow-hidden p-0.5 bg-gray-50">
                                        <button @click="modalData.type = 'percentage'; modalData.error = ''" :class="modalData.type === 'percentage' ? 'bg-white text-primary-600 font-bold shadow-sm' : 'text-gray-500'" class="flex-1 py-1.5 text-sm rounded-md transition-all">{{ __('pos.percentage') }}</button>
                                        <button @click="modalData.type = 'fixed'; modalData.error = ''" :class="modalData.type === 'fixed' ? 'bg-white text-primary-600 font-bold shadow-sm' : 'text-gray-500'" class="flex-1 py-1.5 text-sm rounded-md transition-all">{{ __('pos.fixed_amount') }}</button>
                                    </div>
                                </div>
                                <div>
                                    <div class="flex justify-between items-center mb-1">
                                        <label class="text-[11px] font-bold text-gray-500 uppercase tracking-wide">{{ __('pos.discount_amount') }}</label>
                                        <div class="flex items-center gap-2">
                                            <!-- Max Allowed Discount Badge (Switches dynamically between % and Currency) -->
                                            <span class="text-[10px] font-extrabold text-gray-700 bg-gray-100 px-2 py-0.5 rounded border border-gray-200"
                                                  x-text="modalData.type === 'percentage'
                                                      ? '{{ __('pos.max_allowed_discount', ['max' => '']) }}' + ((modalData.unitPrice || 0) > 0 ? Math.max(0, (((modalData.unitPrice || 0) - (modalData.minAllowed || 0)) / (modalData.unitPrice || 1)) * 100).toFixed(1) + '%' : '0%')
                                                      : '{{ __('pos.max_allowed_discount', ['max' => '']) }}' + Math.max(0, (modalData.unitPrice || 0) - (modalData.minAllowed || 0)).toFixed(2) + ' ' + currencySymbol">
                                            </span>
                                            <span class="text-[9px] font-bold text-primary-500 bg-primary-50 px-1.5 py-0.5 rounded">{{ __('pos.applied_per_unit') }}</span>
                                        </div>
                                    </div>
                                    <div class="relative mt-1.5 flex items-center">
                                        <input type="number" x-model.number="modalData.amount" @input="modalData.error = ''" class="w-full border border-gray-200 rounded-lg py-2.5 text-sm font-bold outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary-500 text-center" min="0" :class="modalData.type === 'fixed' ? 'ps-8 pe-4' : 'ps-4 pe-8'">

                                        <!-- Dynamic Symbol Positioning -->
                                        <span x-show="modalData.type === 'fixed'" class="absolute start-3 text-gray-400 text-sm font-bold" x-text="currencySymbol"></span>
                                        <span x-show="modalData.type === 'percentage'" class="absolute end-3 text-gray-400 text-sm font-bold">%</span>
                                    </div>
                                    <div x-show="modalData.type === 'percentage' && modalData.amount > 0" class="mt-1 text-center text-xs font-bold text-primary-600">
                                        = <span x-text="currencySymbol + ' ' + ((modalData.unitPrice || 0) * ((modalData.amount || 0) / 100)).toFixed(2)"></span> {{ __('pos.discount_value') }}
                                    </div>
                                </div>
                            </div>
                            <div class="p-4 border-t border-gray-100 flex gap-2 bg-gray-50/50 rounded-b-xl">
                                <button @click="modalData.amount = 0; applyItemDiscount()" :disabled="!modalData.isNegotiable" class="flex-1 bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 py-2.5 rounded-lg font-bold text-sm shadow-sm disabled:opacity-50">{{ __('pos.clear_discount') }}</button>
                                <button @click="applyItemDiscount()" :disabled="!modalData.isNegotiable" class="flex-1 bg-primary-600 hover:bg-primary-700 text-white py-2.5 rounded-lg font-bold text-sm shadow-sm disabled:opacity-50">{{ __('pos.apply_discount') }}</button>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Global Discount Modal -->
                <div x-show="activeModal === 'globalDiscount'" class="fixed inset-0 bg-gray-900/50 z-[100] flex items-center justify-center" x-cloak>
                    <template x-if="activeModal === 'globalDiscount'">
                        <div class="bg-white rounded-xl shadow-xl w-full max-w-sm" @click.outside="closeModal()">
                            <div class="p-4 border-b border-gray-100 flex justify-between items-center bg-gray-50/50 rounded-t-xl">
                                <h3 class="font-bold text-gray-800">{{ __('pos.global_discount') }}</h3>
                                <button @click="closeModal()" class="text-gray-400 hover:text-gray-600"><i class="ph ph-x text-lg"></i></button>
                            </div>

                            <!-- Error Message -->
                            <div x-show="modalData.error" class="mx-4 mt-4 p-3 bg-danger-50 text-danger-600 text-xs font-bold rounded-lg border border-danger-100 flex items-start gap-2">
                                <i class="ph ph-warning-circle text-base"></i>
                                <span x-text="modalData.error"></span>
                            </div>

                            <div class="p-4 space-y-4">
                                <!-- Financial Summary -->
                                <div class="bg-gray-50 p-3.5 rounded-xl border border-gray-200 space-y-2">
                                    <div class="flex justify-between items-center text-xs">
                                        <span class="text-gray-500 font-semibold">{{ __('pos.gross_total') }}</span>
                                        <span class="text-gray-800 font-bold" x-text="currencySymbol + ' ' + cartSubtotal.toFixed(2)"></span>
                                    </div>
                                    <div class="flex justify-between items-center text-xs">
                                        <span class="text-gray-500 font-semibold">{{ __('pos.min_allowed_total') }}</span>
                                        <span class="text-warning-600 font-bold" x-text="currencySymbol + ' ' + (modalData.minAllowedTotal || 0).toFixed(2)"></span>
                                    </div>
                                    <div class="border-t border-gray-200 pt-2 flex justify-between items-center text-xs">
                                        <span class="text-gray-700 font-bold">{{ __('pos.final_total_after_discount') }}</span>
                                        <span class="text-primary-600 font-extrabold text-sm" x-text="currencySymbol + ' ' + Math.max(0, (modalData.subtotal || 0) - (modalData.type === 'percentage' ? ((modalData.subtotal || 0) * ((modalData.amount || 0) / 100)) : (modalData.amount || 0))).toFixed(2)"></span>
                                    </div>
                                </div>

                                <div>
                                    <label class="text-[11px] font-bold text-gray-500 uppercase tracking-wide">{{ __('pos.discount_type') }}</label>
                                    <div class="flex mt-1.5 border border-gray-200 rounded-lg overflow-hidden p-0.5 bg-gray-50">
                                        <button @click="modalData.type = 'percentage'; modalData.error = ''" :class="modalData.type === 'percentage' ? 'bg-white text-primary-600 font-bold shadow-sm' : 'text-gray-500'" class="flex-1 py-1.5 text-sm rounded-md transition-all">{{ __('pos.percentage') }}</button>
                                        <button @click="modalData.type = 'fixed'; modalData.error = ''" :class="modalData.type === 'fixed' ? 'bg-white text-primary-600 font-bold shadow-sm' : 'text-gray-500'" class="flex-1 py-1.5 text-sm rounded-md transition-all">{{ __('pos.fixed_amount') }}</button>
                                    </div>
                                </div>
                                <div>
                                    <div class="flex justify-between items-center mb-1">
                                        <label class="text-[11px] font-bold text-gray-500 uppercase tracking-wide">{{ __('pos.discount_amount') }}</label>
                                        <!-- Max Allowed Global Discount Badge (Switches dynamically between % and Currency) -->
                                        <span class="text-[10px] font-extrabold text-gray-700 bg-gray-100 px-2 py-0.5 rounded border border-gray-200"
                                              x-text="modalData.type === 'percentage'
                                                  ? '{{ __('pos.max_allowed_discount', ['max' => '']) }}' + ((modalData.subtotal || 0) > 0 ? Math.max(0, (((modalData.subtotal || 0) - (modalData.minAllowedTotal || 0)) / (modalData.subtotal || 1)) * 100).toFixed(1) + '%' : '0%')
                                                  : '{{ __('pos.max_allowed_discount', ['max' => '']) }}' + Math.max(0, (modalData.subtotal || 0) - (modalData.minAllowedTotal || 0)).toFixed(2) + ' ' + currencySymbol">
                                        </span>
                                    </div>
                                    <div class="relative mt-1.5 flex items-center">
                                        <input type="number" x-model.number="modalData.amount" @input="modalData.error = ''" class="w-full border border-gray-200 rounded-lg py-2.5 text-sm font-bold outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary-500 text-center" min="0" :class="modalData.type === 'fixed' ? 'ps-8 pe-4' : 'ps-4 pe-8'">

                                        <span x-show="modalData.type === 'fixed'" class="absolute start-3 text-gray-400 text-sm font-bold" x-text="currencySymbol"></span>
                                        <span x-show="modalData.type === 'percentage'" class="absolute end-3 text-gray-400 text-sm font-bold">%</span>
                                    </div>
                                    <div x-show="modalData.type === 'percentage' && modalData.amount > 0" class="mt-1 text-center text-xs font-bold text-primary-600">
                                        = <span x-text="currencySymbol + ' ' + ((modalData.subtotal || 0) * ((modalData.amount || 0) / 100)).toFixed(2)"></span> {{ __('pos.discount_value') }}
                                    </div>
                                </div>
                            </div>
                            <div class="p-4 border-t border-gray-100 flex gap-2 bg-gray-50/50 rounded-b-xl">
                                <button @click="modalData.amount = 0; applyGlobalDiscount()" class="flex-1 bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 py-2.5 rounded-lg font-bold text-sm shadow-sm">{{ __('pos.clear_discount') }}</button>
                                <button @click="applyGlobalDiscount()" class="flex-1 bg-primary-600 hover:bg-primary-700 text-white py-2.5 rounded-lg font-bold text-sm shadow-sm">{{ __('pos.apply_discount') }}</button>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Shipping Modal -->
                <div x-show="activeModal === 'shipping'" class="fixed inset-0 bg-gray-900/50 z-[100] flex items-center justify-center" x-cloak>
                    <div class="bg-white rounded-xl shadow-xl w-full max-w-sm" @click.outside="closeModal()">
                            <div class="p-4 border-b border-gray-100 flex justify-between items-center bg-gray-50/50 rounded-t-xl">
                                <h3 class="font-bold text-gray-800">{{ __('pos.shipping') }}</h3>
                                <button @click="closeModal()" class="text-gray-400 hover:text-gray-600"><i class="ph ph-x text-lg"></i></button>
                            </div>
                            <div class="p-4 space-y-4">
                                <div x-show="modalData.error" class="p-3 bg-danger-50 text-danger-600 text-xs font-bold rounded-lg border border-danger-100 flex items-start gap-2" style="display: none;">
                                    <i class="ph ph-warning-circle text-base shrink-0"></i>
                                    <span x-text="modalData.error"></span>
                                </div>
                                <div>
                                    <div class="flex justify-between items-center mb-1">
                                        <label class="text-xs font-bold text-gray-500 uppercase">{{ __('pos.shipping_destination') }}</label>
                                        <button type="button" @click="showNewDestinationForm = !showNewDestinationForm; modalData.clearErrors();" class="text-xs font-bold text-primary-600 hover:text-primary-700 flex items-center gap-1 transition-colors">
                                            <i class="ph ph-plus text-sm"></i>
                                            <span x-text="showNewDestinationForm ? '{{ __('pos.cancel') }}' : '{{ __('pos.new_destination') }}'"></span>
                                        </button>
                                    </div>

                                    <!-- Inline New Destination Form -->
                                    <div x-show="showNewDestinationForm" x-transition class="p-3 mb-3 bg-primary-50/60 border border-primary-200 rounded-xl space-y-2.5">
                                        <div class="text-xs font-bold text-primary-800">{{ __('pos.create_shipping_destination') }}</div>
                                        <div>
                                            <label class="text-[10px] font-bold text-gray-500 uppercase">{{ __('pos.destination_name') }}</label>
                                            <input type="text"
                                                   x-model="newDestination.name"
                                                   @input="modalData.clearFieldError('name')"
                                                   placeholder="{{ __('pos.enter_destination_name') }}"
                                                   class="mt-1 w-full border rounded-lg p-2 text-xs font-medium outline-none transition-colors bg-white"
                                                   :class="modalData.hasError('name') ? 'border-danger-400 bg-danger-50/20 focus:border-danger-500' : 'border-gray-200 focus:border-primary-500'">
                                            <template x-if="modalData.hasError('name')">
                                                <div class="mt-1 space-y-0.5">
                                                    <template x-for="(errMsg, i) in modalData.getAllErrors('name')" :key="i">
                                                        <p class="text-[10px] text-danger-600 font-bold flex items-center gap-1">
                                                            <i class="ph ph-warning-circle"></i>
                                                            <span x-text="errMsg"></span>
                                                        </p>
                                                    </template>
                                                </div>
                                            </template>
                                        </div>
                                        <div>
                                            <label class="text-[10px] font-bold text-gray-500 uppercase">{{ __('pos.default_cost') }}</label>
                                            <div class="relative mt-1 flex items-center">
                                                <span class="absolute start-2.5 text-gray-400 text-xs font-bold" x-text="currencySymbol"></span>
                                                <input type="text"
                                                       inputmode="decimal"
                                                       x-model="newDestination.cost"
                                                       @input="modalData.clearFieldError('cost')"
                                                       class="w-full border rounded-lg py-1.5 ps-7 pe-2 text-xs font-medium outline-none transition-colors bg-white"
                                                       :class="modalData.hasError('cost') ? 'border-danger-400 bg-danger-50/20 focus:border-danger-500' : 'border-gray-200 focus:border-primary-500'">
                                            </div>
                                            <template x-if="modalData.hasError('cost')">
                                                <div class="mt-1 space-y-0.5">
                                                    <template x-for="(errMsg, i) in modalData.getAllErrors('cost')" :key="i">
                                                        <p class="text-[10px] text-danger-600 font-bold flex items-center gap-1">
                                                            <i class="ph ph-warning-circle"></i>
                                                            <span x-text="errMsg"></span>
                                                        </p>
                                                    </template>
                                                </div>
                                            </template>
                                        </div>
                                        <button type="button" @click="saveNewDestination()" :disabled="!newDestination.name" class="w-full bg-primary-600 hover:bg-primary-700 disabled:opacity-50 text-white py-1.5 rounded-lg text-xs font-bold shadow-sm transition-colors">
                                            {{ __('pos.save_destination') }}
                                        </button>
                                    </div>

                                    <select id="shippingDestinationSelect"
                                        x-model="modalData.destinationId"
                                        x-init="$watch('activeModal', val => { if(val === 'shipping') setTimeout(() => { if ($el) $el.value = modalData.destinationId || ''; }, 50) }); $watch('modalData.destinationId', val => { if ($el && val !== undefined) $el.value = val || ''; })"
                                        @change="onShippingDestinationChange($event.target.value)"
                                        class="w-full border border-gray-200 rounded-lg p-2.5 text-sm outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary-500 bg-white font-medium">
                                        <option value="">{{ __('pos.select_destination') }}</option>
                                        <template x-for="dest in $wire.shippingDestinationList" :key="dest.id">
                                            <option :value="String(dest.id)" :selected="String(modalData.destinationId) === String(dest.id)" x-text="dest.name + ' (' + currencySymbol + ' ' + parseFloat(dest.cost).toFixed(2) + ')'"></option>
                                        </template>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-xs font-bold text-gray-500 uppercase">{{ __('pos.shipping_cost') }}</label>
                                    <div class="relative mt-1">
                                        <span class="absolute start-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm font-bold" x-text="currencySymbol"></span>
                                        <input type="number" x-model.number="modalData.cost" @input="modalData.error = ''" class="w-full border border-gray-200 rounded-lg ps-8 p-2.5 text-sm font-bold outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary-500" min="0">
                                    </div>
                                </div>
                                <div>
                                    <label class="text-xs font-bold text-gray-500 uppercase">{{ __('pos.shipping_address') }}</label>
                                    <textarea x-model="modalData.address" rows="2" class="mt-1 w-full border border-gray-200 rounded-lg p-2.5 text-sm outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary-500"></textarea>
                                </div>
                            </div>
                            <div class="p-4 border-t border-gray-100 flex gap-2 bg-gray-50/50 rounded-b-xl">
                                <button @click="modalData.cost = 0; modalData.destinationId = ''; modalData.address = ''; applyShipping()" class="flex-1 bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 py-2.5 rounded-lg font-bold text-sm shadow-sm">{{ __('pos.clear_shipping') }}</button>
                                <button @click="applyShipping()" class="flex-1 bg-primary-600 hover:bg-primary-700 text-white py-2.5 rounded-lg font-bold text-sm shadow-sm">{{ __('pos.apply_shipping') }}</button>
                            </div>
                        </div>
                </div>

                <!-- Extra Items Modal -->
                <div x-show="activeModal === 'extraItems'" class="fixed inset-0 bg-gray-900/50 z-[100] flex items-center justify-center" x-cloak>
                    <template x-if="activeModal === 'extraItems'">
                        <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl" @click.outside="closeModal()">
                            <div class="p-4 border-b border-gray-100 flex justify-between items-center">
                                <h3 class="font-bold text-gray-800">{{ __('pos.extra_items') }}</h3>
                                <button @click="closeModal()" class="text-gray-400 hover:text-gray-600"><i class="ph ph-x"></i></button>
                            </div>

                            <div class="flex">
                                <!-- Left: Form to add -->
                                <div class="w-1/2 p-4 border-e border-gray-100 space-y-4">
                                    <div>
                                        <div class="flex justify-between items-center mb-1">
                                            <label class="text-xs font-bold text-gray-500 uppercase">{{ __('pos.select_preset') }}</label>
                                            <span class="text-[10px] text-gray-400 font-semibold bg-gray-100 px-1.5 py-0.5 rounded">{{ __('pos.template_autofill_optional') }}</span>
                                        </div>
                                        <select x-model="modalData.newItem.presetId" @change="
                                            const p = extraItemPresets.find(pr => pr.id == $event.target.value);
                                            if(p && modalData.newItem) {
                                                modalData.newItem.name = p.name;
                                                modalData.newItem.amount = parseFloat(p.amount) || 0;
                                                modalData.newItem.action_type = p.action_type;
                                                modalData.newItem.notes = p.notes;
                                            }
                                        " class="mt-1 w-full border border-gray-200 rounded-lg p-2.5 text-sm outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary-500 bg-white font-medium">
                                            <option value="">{{ __('pos.custom_item') }}</option>
                                            <template x-for="preset in extraItemPresets" :key="preset.id">
                                                <option :value="preset.id" x-text="preset.name + ' (' + (preset.action_type === 'addition' ? '+' : '-') + currencySymbol + ' ' + parseFloat(preset.amount).toFixed(2) + ')'"></option>
                                            </template>
                                        </select>
                                    </div>
                                    <div class="grid grid-cols-2 gap-2.5">
                                        <div class="col-span-2">
                                            <label class="text-xs font-bold text-gray-500 uppercase">{{ __('pos.item_name') }}</label>
                                            <input type="text" x-model="modalData.newItem.name" placeholder="{{ __('pos.item_name') }}" class="mt-1 w-full border border-gray-200 rounded-lg p-2.5 text-sm outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary-500 bg-white">
                                        </div>
                                        <div>
                                            <label class="text-xs font-bold text-gray-500 uppercase">{{ __('pos.item_type') }}</label>
                                            <select x-model="modalData.newItem.action_type" class="mt-1 w-full border border-gray-200 rounded-lg p-2.5 text-sm outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary-500 bg-white font-medium">
                                                <option value="addition">{{ __('pos.addition') }} (+)</option>
                                                <option value="subtraction">{{ __('pos.subtraction') }} (-)</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="text-xs font-bold text-gray-500 uppercase">{{ __('pos.amount') }}</label>
                                            <div class="relative mt-1 flex items-center">
                                                <span class="absolute start-3 text-gray-400 text-sm font-bold" x-text="currencySymbol"></span>
                                                <input type="number" x-model.number="modalData.newItem.amount" class="w-full border border-gray-200 rounded-lg py-2.5 text-sm outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary-500 text-center ps-8 pe-4 font-bold" min="0">
                                            </div>
                                        </div>
                                        <div class="col-span-2">
                                            <button @click="addExtraItem()" :disabled="!modalData.newItem?.name || (modalData.newItem?.amount || 0) <= 0" class="w-full bg-gray-800 hover:bg-gray-900 text-white py-2.5 rounded-lg font-bold disabled:opacity-50 mt-2 transition-all shadow-sm">{{ __('pos.add_extra_item') }}</button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Right: List of added items -->
                                <div class="w-1/2 p-4 bg-gray-50 max-h-[350px] overflow-y-auto">
                                    <template x-for="(item, idx) in (modalData.items || [])" :key="idx">
                                        <div class="bg-white p-3 rounded-lg shadow-sm border border-gray-200 mb-2 flex justify-between items-center">
                                            <div>
                                                <div class="font-bold text-sm text-gray-800" x-text="item.name"></div>
                                                <div class="text-xs font-semibold" :class="item.action_type === 'addition' ? 'text-primary-600' : 'text-danger-600'">
                                                    <span x-text="item.action_type === 'addition' ? '+' : '-'"></span> <span x-text="currencySymbol"></span><span x-text="item.amount"></span>
                                                </div>
                                            </div>
                                            <button @click="removeExtraItem(idx)" class="text-gray-400 hover:text-danger-500 bg-gray-50 hover:bg-danger-50 p-2 rounded-lg transition-colors">
                                                <i class="ph ph-trash"></i>
                                            </button>
                                        </div>
                                    </template>
                                    <div x-show="!modalData.items || modalData.items.length === 0" class="text-center text-gray-400 py-10 text-sm">
                                        {{ __('pos.no_extra_items') }}
                                    </div>
                                </div>
                            </div>

                            <div class="p-4 border-t border-gray-100 flex justify-end">
                                <button @click="applyExtraItems()" class="bg-primary-600 hover:bg-primary-700 text-white px-8 py-2.5 rounded-lg font-bold">{{ __('pos.apply_and_close') }}</button>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Confirm Clear Cart Modal -->
                <div x-show="activeModal === 'confirmClearCart'" class="fixed inset-0 bg-gray-900/50 z-[100] flex items-center justify-center p-4" x-cloak>
                    <template x-if="activeModal === 'confirmClearCart'">
                        <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm overflow-hidden" @click.outside="closeModal()">
                            <div class="p-6 text-center">
                                <div class="w-14 h-14 rounded-full bg-danger-50 text-danger-600 flex items-center justify-center mx-auto mb-4 border border-danger-100">
                                    <i class="ph ph-trash text-2xl font-bold"></i>
                                </div>
                                <h3 class="text-base font-extrabold text-gray-900 mb-2">{{ __('pos.clear_cart_title') }}</h3>
                                <p class="text-xs text-gray-500 leading-relaxed">{{ __('pos.clear_cart_message') }}</p>
                            </div>
                            <div class="p-4 bg-gray-50/70 border-t border-gray-100 flex gap-2.5">
                                <button @click="closeModal()" class="flex-1 bg-white border border-gray-200 hover:bg-gray-100 text-gray-700 py-2.5 rounded-xl font-bold text-xs shadow-sm transition-colors">{{ __('pos.cancel') }}</button>
                                <button @click="clearCart(); closeModal()" class="flex-1 bg-danger-600 hover:bg-danger-700 text-white py-2.5 rounded-xl font-bold text-xs shadow-sm transition-colors">{{ __('pos.confirm_clear') }}</button>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- New Customer Modal -->
                <div x-show="activeModal === 'newCustomer'" class="fixed inset-0 bg-gray-900/50 z-[100] flex items-center justify-center p-4" x-cloak>
                    <template x-if="activeModal === 'newCustomer'">
                        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden" @click.outside="closeModal()">
                            <div class="p-4 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                                <div class="flex items-center gap-2">
                                    <div class="w-8 h-8 rounded-full bg-primary-50 text-primary-600 flex items-center justify-center font-bold">
                                        <i class="ph ph-user-plus text-base"></i>
                                    </div>
                                    <h3 class="font-bold text-gray-800">{{ __('pos.create_customer') }}</h3>
                                </div>
                                <button @click="closeModal()" class="text-gray-400 hover:text-gray-600"><i class="ph ph-x text-lg"></i></button>
                            </div>

                            <!-- Error Banner -->
                            <div x-show="modalData.error" class="mx-4 mt-4 p-3 bg-danger-50 text-danger-600 text-xs font-bold rounded-lg border border-danger-100 flex items-start gap-2">
                                <i class="ph ph-warning-circle text-base shrink-0"></i>
                                <span x-text="modalData.error"></span>
                            </div>

                            <div class="p-5 space-y-3.5">
                                <div>
                                    <label class="text-xs font-bold text-gray-600 uppercase tracking-wide">{{ __('pos.customer_name') }} <span class="text-danger-500">*</span></label>
                                    <input type="text"
                                           x-model="modalData.customer.name"
                                           @input="modalData.clearFieldError('name')"
                                           placeholder="{{ __('pos.customer_name') }}"
                                           class="mt-1 w-full border rounded-xl p-2.5 text-sm font-medium outline-none transition-colors bg-white"
                                           :class="modalData.hasError('name') ? 'border-danger-400 bg-danger-50/20 focus:border-danger-500' : 'border-gray-200 focus:border-primary-500 focus:ring-1 focus:ring-primary-500'">
                                    <template x-if="modalData.hasError('name')">
                                        <div class="mt-1 space-y-0.5">
                                            <template x-for="(errMsg, i) in modalData.getAllErrors('name')" :key="i">
                                                <p class="text-[10px] text-danger-600 font-bold flex items-center gap-1">
                                                    <i class="ph ph-warning-circle"></i>
                                                    <span x-text="errMsg"></span>
                                                </p>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                                <div>
                                    <label class="text-xs font-bold text-gray-600 uppercase tracking-wide">{{ __('pos.customer_phone') }}</label>
                                    <input type="text"
                                           x-model="modalData.customer.phone"
                                           @input="modalData.clearFieldError('phone')"
                                           placeholder="{{ __('pos.customer_phone') }}"
                                           class="mt-1 w-full border rounded-xl p-2.5 text-sm font-medium outline-none transition-colors bg-white"
                                           :class="modalData.hasError('phone') ? 'border-danger-400 bg-danger-50/20 focus:border-danger-500' : 'border-gray-200 focus:border-primary-500 focus:ring-1 focus:ring-primary-500'">
                                    <template x-if="modalData.hasError('phone')">
                                        <div class="mt-1 space-y-0.5">
                                            <template x-for="(errMsg, i) in modalData.getAllErrors('phone')" :key="i">
                                                <p class="text-[10px] text-danger-600 font-bold flex items-center gap-1">
                                                    <i class="ph ph-warning-circle"></i>
                                                    <span x-text="errMsg"></span>
                                                </p>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                                <div>
                                    <label class="text-xs font-bold text-gray-600 uppercase tracking-wide">{{ __('pos.customer_email') }}</label>
                                    <input type="email"
                                           x-model="modalData.customer.email"
                                           @input="modalData.clearFieldError('email')"
                                           placeholder="{{ __('pos.customer_email') }}"
                                           class="mt-1 w-full border rounded-xl p-2.5 text-sm font-medium outline-none transition-colors bg-white"
                                           :class="modalData.hasError('email') ? 'border-danger-400 bg-danger-50/20 focus:border-danger-500' : 'border-gray-200 focus:border-primary-500 focus:ring-1 focus:ring-primary-500'">
                                    <template x-if="modalData.hasError('email')">
                                        <div class="mt-1 space-y-0.5">
                                            <template x-for="(errMsg, i) in modalData.getAllErrors('email')" :key="i">
                                                <p class="text-[10px] text-danger-600 font-bold flex items-center gap-1">
                                                    <i class="ph ph-warning-circle"></i>
                                                    <span x-text="errMsg"></span>
                                                </p>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                                <div>
                                    <label class="text-xs font-bold text-gray-600 uppercase tracking-wide">{{ __('pos.customer_address') }}</label>
                                    <textarea x-model="modalData.customer.address"
                                              @input="modalData.clearFieldError('address')"
                                              rows="2"
                                              placeholder="{{ __('pos.customer_address') }}"
                                              class="mt-1 w-full border rounded-xl p-2.5 text-sm font-medium outline-none transition-colors bg-white"
                                              :class="modalData.hasError('address') ? 'border-danger-400 bg-danger-50/20 focus:border-danger-500' : 'border-gray-200 focus:border-primary-500 focus:ring-1 focus:ring-primary-500'"></textarea>
                                    <template x-if="modalData.hasError('address')">
                                        <div class="mt-1 space-y-0.5">
                                            <template x-for="(errMsg, i) in modalData.getAllErrors('address')" :key="i">
                                                <p class="text-[10px] text-danger-600 font-bold flex items-center gap-1">
                                                    <i class="ph ph-warning-circle"></i>
                                                    <span x-text="errMsg"></span>
                                                </p>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            <div class="p-4 border-t border-gray-100 flex gap-2.5 bg-gray-50/50">
                                <button type="button" @click="closeModal()" class="flex-1 bg-white border border-gray-200 hover:bg-gray-100 text-gray-700 py-2.5 rounded-xl font-bold text-xs shadow-sm transition-colors">{{ __('pos.cancel') }}</button>
                                <button type="button" @click="saveNewCustomer()" :disabled="!modalData.customer?.name || isSavingCustomer" class="flex-1 bg-primary-600 hover:bg-primary-700 text-white py-2.5 rounded-xl font-bold text-xs shadow-sm transition-colors disabled:opacity-50 flex items-center justify-center gap-1.5">
                                    <i class="ph ph-spinner animate-spin text-sm" x-show="isSavingCustomer" x-cloak></i>
                                    <span>{{ __('pos.save_customer') }}</span>
                                </button>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- ================= CHECKOUT SETTLEMENT MODAL ================= -->
                <div x-show="activeModal === 'checkout'" class="fixed inset-0 bg-gray-900/60 backdrop-blur-xs z-[100] flex items-center justify-center p-4" x-cloak>
                    <template x-if="activeModal === 'checkout'">
                        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden border border-gray-100 flex flex-col max-h-[90vh]"
                             @click.outside="closeModal()">
                            <!-- Modal Header -->
                            <div class="px-6 py-4 border-b border-gray-100 flex justify-between items-center bg-gray-50/70">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-xl bg-primary-100 text-primary-600 flex items-center justify-center font-bold text-xl shadow-xs">
                                        <i class="ph ph-receipt"></i>
                                    </div>
                                    <div>
                                        <h3 class="font-extrabold text-gray-900 text-base leading-tight">{{ __('pos.checkout_title') }}</h3>
                                        <p class="text-xs text-gray-400 font-medium mt-0.5">
                                            <span x-text="cartItemCount"></span> {{ __('pos.items') }} • <span x-text="selectedCustomerName"></span>
                                        </p>
                                    </div>
                                </div>
                                <button type="button" @click="closeModal()" class="w-8 h-8 rounded-lg hover:bg-gray-200/60 text-gray-400 hover:text-gray-600 flex items-center justify-center transition-colors">
                                    <i class="ph ph-x text-lg"></i>
                                </button>
                            </div>

                            <!-- Modal Body -->
                            <div class="p-6 space-y-5 overflow-y-auto">
                                <!-- Total Payable Card -->
                                <div class="bg-gradient-to-br from-primary-600 to-indigo-700 rounded-2xl p-5 text-white shadow-lg shadow-primary-500/20 flex items-center justify-between">
                                    <div>
                                        <span class="text-xs font-semibold text-primary-100 uppercase tracking-wider block mb-1">{{ __('pos.total_payable') }}</span>
                                        <div class="text-3xl font-black tracking-tight flex items-baseline gap-1">
                                            <span class="text-xl font-bold opacity-80" x-text="currencySymbol"></span>
                                            <span x-text="cartTotal.toFixed(2)"></span>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-white/20 text-white backdrop-blur-md">
                                            <i class="ph ph-check-circle"></i>
                                            <span>{{ __('pos.tax_included') }}</span>
                                        </span>
                                    </div>
                                </div>

                                <!-- Payment Method Selector (Cash, Card, Split) -->
                                <div>
                                    <label class="text-xs font-bold text-gray-500 uppercase tracking-wider block mb-2">
                                        {{ __('pos.payment_method') }}
                                    </label>
                                    <div class="grid grid-cols-3 gap-3">
                                        <!-- Cash -->
                                        <button type="button"
                                                @click="selectCheckoutPaymentMethod('cash')"
                                                class="p-3.5 rounded-xl border-2 text-start transition-all relative flex flex-col justify-between h-24 cursor-pointer"
                                                :class="checkoutModalData.paymentMethod === 'cash' ? 'border-primary-600 bg-primary-50/60 shadow-xs' : 'border-gray-200 hover:border-gray-300 bg-white'">
                                            <div class="flex items-center justify-between">
                                                <span class="w-8 h-8 rounded-lg flex items-center justify-center text-lg transition-colors"
                                                      :class="checkoutModalData.paymentMethod === 'cash' ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-600'">
                                                    <i class="ph ph-money"></i>
                                                </span>
                                                <i class="ph ph-check-circle-fill text-primary-600 text-lg" x-show="checkoutModalData.paymentMethod === 'cash'"></i>
                                            </div>
                                            <div>
                                                <span class="font-bold text-sm text-gray-900 block leading-tight">{{ __('pos.payment_cash') }}</span>
                                                <span class="text-[11px] text-gray-400 font-medium">{{ __('pos.pay_at_counter') }}</span>
                                            </div>
                                        </button>

                                        <!-- Card -->
                                        <button type="button"
                                                @click="selectCheckoutPaymentMethod('card')"
                                                class="p-3.5 rounded-xl border-2 text-start transition-all relative flex flex-col justify-between h-24 cursor-pointer"
                                                :class="checkoutModalData.paymentMethod === 'card' ? 'border-primary-600 bg-primary-50/60 shadow-xs' : 'border-gray-200 hover:border-gray-300 bg-white'">
                                            <div class="flex items-center justify-between">
                                                <span class="w-8 h-8 rounded-lg flex items-center justify-center text-lg transition-colors"
                                                      :class="checkoutModalData.paymentMethod === 'card' ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-600'">
                                                    <i class="ph ph-credit-card"></i>
                                                </span>
                                                <i class="ph ph-check-circle-fill text-primary-600 text-lg" x-show="checkoutModalData.paymentMethod === 'card'"></i>
                                            </div>
                                            <div>
                                                <span class="font-bold text-sm text-gray-900 block leading-tight">{{ __('pos.payment_card') }}</span>
                                                <span class="text-[11px] text-gray-400 font-medium">{{ __('pos.card_terminal') }}</span>
                                            </div>
                                        </button>

                                        <!-- Split -->
                                        <button type="button"
                                                @click="selectCheckoutPaymentMethod('split')"
                                                class="p-3.5 rounded-xl border-2 text-start transition-all relative flex flex-col justify-between h-24 cursor-pointer"
                                                :class="checkoutModalData.paymentMethod === 'split' ? 'border-primary-600 bg-primary-50/60 shadow-xs' : 'border-gray-200 hover:border-gray-300 bg-white'">
                                            <div class="flex items-center justify-between">
                                                <span class="w-8 h-8 rounded-lg flex items-center justify-center text-lg transition-colors"
                                                      :class="checkoutModalData.paymentMethod === 'split' ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-600'">
                                                    <i class="ph ph-arrows-split"></i>
                                                </span>
                                                <i class="ph ph-check-circle-fill text-primary-600 text-lg" x-show="checkoutModalData.paymentMethod === 'split'"></i>
                                            </div>
                                            <div>
                                                <span class="font-bold text-sm text-gray-900 block leading-tight">{{ __('pos.payment_split') }}</span>
                                                <span class="text-[11px] text-gray-400 font-medium">{{ __('pos.multiple_methods') }}</span>
                                            </div>
                                        </button>
                                    </div>
                                </div>

                                <!-- Cash Settlement Breakdown -->
                                <div x-show="checkoutModalData.paymentMethod === 'cash'" class="space-y-4 pt-2 border-t border-gray-100" x-cloak>
                                    <div>
                                        <div class="flex items-center justify-between mb-1.5">
                                            <label class="text-xs font-bold text-gray-600 uppercase tracking-wide">
                                                {{ __('pos.amount_tendered') }}
                                            </label>
                                            <button type="button"
                                                    @click="setExactTendered()"
                                                    class="text-xs font-bold text-primary-600 hover:text-primary-700 bg-primary-50 hover:bg-primary-100 px-2.5 py-1 rounded-lg transition-colors cursor-pointer">
                                                {{ __('pos.exact_amount') }}
                                            </button>
                                        </div>
                                        <div class="relative">
                                            <span class="absolute inset-y-0 start-0 flex items-center ps-3.5 pointer-events-none text-gray-400 font-bold text-sm" x-text="currencySymbol"></span>
                                            <input type="number"
                                                   step="any"
                                                   min="0"
                                                   x-model.number="checkoutModalData.amountTendered"
                                                   class="w-full ps-9 pe-4 py-3 bg-gray-50 border-2 rounded-xl text-lg font-black text-gray-900 outline-none transition-colors"
                                                   :class="isCheckoutUnderpaid ? 'border-danger-400 bg-danger-50/20 focus:border-danger-500' : 'border-gray-200 focus:border-primary-500 focus:bg-white'"
                                                   placeholder="0.00" />
                                        </div>

                                        <!-- Quick Denominations -->
                                        <div class="flex flex-wrap gap-2 mt-2.5">
                                            <template x-for="denom in [5, 10, 20, 50, 100, 200]" :key="denom">
                                                <button type="button"
                                                        @click="addTenderedDenomination(denom)"
                                                        class="px-3 py-1 text-xs font-bold rounded-lg border border-gray-200 hover:border-primary-500 hover:text-primary-600 bg-white transition-colors cursor-pointer">
                                                    +<span x-text="denom"></span>
                                                </button>
                                            </template>
                                        </div>
                                    </div>

                                    <!-- Change Due & Shortage Badges -->
                                    <div class="grid grid-cols-2 gap-3">
                                        <div class="p-3.5 rounded-xl border transition-all"
                                             :class="changeDue > 0 ? 'bg-success-50/80 border-success-200 text-success-900' : 'bg-gray-50 border-gray-100 text-gray-400'">
                                            <span class="text-[11px] font-bold uppercase tracking-wider block mb-0.5">{{ __('pos.change_due') }}</span>
                                            <div class="text-xl font-black">
                                                <span class="text-sm font-bold opacity-80" x-text="currencySymbol"></span>
                                                <span x-text="changeDue.toFixed(2)"></span>
                                            </div>
                                        </div>

                                        <div class="p-3.5 rounded-xl border transition-all"
                                             :class="shortageAmount > 0 ? 'bg-danger-50/80 border-danger-200 text-danger-800' : 'bg-gray-50 border-gray-100 text-gray-400'">
                                            <span class="text-[11px] font-bold uppercase tracking-wider block mb-0.5">{{ __('pos.shortage_due') }}</span>
                                            <div class="text-xl font-black">
                                                <span class="text-sm font-bold opacity-80" x-text="currencySymbol"></span>
                                                <span x-text="shortageAmount.toFixed(2)"></span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Insufficient Tender Warning -->
                                    <div x-show="isCheckoutUnderpaid" class="p-3 bg-danger-50 rounded-xl border border-danger-200 text-danger-700 text-xs font-bold flex items-center gap-2" x-cloak>
                                        <i class="ph ph-warning-circle text-base shrink-0"></i>
                                        <span>{{ __('pos.tendered_insufficient') }}</span>
                                    </div>
                                </div>

                                <!-- Thermal Print Option -->
                                <div class="pt-2 border-t border-gray-100">
                                    <label class="flex items-center gap-3 p-3 rounded-xl border border-gray-200 hover:border-gray-300 bg-gray-50/50 cursor-pointer select-none transition-colors">
                                        <input type="checkbox"
                                               x-model="checkoutModalData.printReceipt"
                                               class="w-4 h-4 text-primary-600 rounded border-gray-300 focus:ring-primary-500">
                                        <div class="flex-1">
                                            <span class="text-xs font-bold text-gray-800 block">{{ __('pos.print_receipt') }}</span>
                                            <span class="text-[11px] text-gray-400">{{ __('pos.print_receipt_helper') }}</span>
                                        </div>
                                        <i class="ph ph-printer text-xl text-gray-500"></i>
                                    </label>
                                </div>
                            </div>

                            <!-- Modal Footer -->
                            <div class="p-5 border-t border-gray-100 flex items-center gap-3 bg-gray-50/70">
                                <button type="button"
                                        @click="closeModal()"
                                        class="px-5 py-3 rounded-xl border border-gray-200 hover:bg-gray-100 text-gray-700 font-bold text-sm transition-colors cursor-pointer">
                                    {{ __('pos.cancel') }}
                                </button>
                                <button type="button"
                                        @click="submitCheckout()"
                                        :disabled="isCheckoutBlocked"
                                        class="flex-1 bg-primary-600 hover:bg-primary-700 text-white py-3 rounded-xl font-bold text-sm shadow-md hover:shadow-lg transition-all flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed transform active:scale-[0.99] cursor-pointer">
                                    <i class="ph ph-spinner animate-spin text-lg" x-show="isProcessing" x-cloak></i>
                                    <i class="ph ph-check-circle text-lg" x-show="!isProcessing"></i>
                                    <span>{{ __('pos.place_order') }}</span>
                                    <span class="text-primary-200 font-normal ms-1" x-show="!isProcessing" x-text="'(' + currencySymbol + ' ' + cartTotal.toFixed(2) + ')'"></span>
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </template>
        </div>
    </div>

    <!-- Alpine.js POS State Engine -->
    <script>
        /**
         * Standardized client handler for Livewire RpcResponse envelopes.
         */
        const RpcHandler = {
            /**
             * Applies an RpcResponse envelope to the given Alpine modal state.
             *
             * @param {Object} response - The RpcResponse from Livewire
             * @param {Object} targetState - The modalData object
             * @param {Array<string>|null} knownFields - List of input field names present in the active form.
             *                                            If provided, errors matching these fields will ONLY
             *                                            appear under their inputs, not in the top banner.
             *                                            Unmapped errors (like store_id) will appear in targetState.error.
             * @returns {boolean} True if operation succeeded, false otherwise
             */
            handle(response, targetState, knownFields = null) {
                if (!response || typeof response !== 'object') {
                    targetState.error = '{{ __('pos.unexpected_error') }}';
                    targetState.errors = {};
                    return false;
                }

                if (response.success) {
                    targetState.error = '';
                    targetState.errors = {};
                    return true;
                }

                targetState.errors = response.errors || {};

                if (Array.isArray(knownFields) && knownFields.length > 0) {
                    const unmappedField = Object.keys(targetState.errors).find(f => !knownFields.includes(f));

                    if (unmappedField && Array.isArray(targetState.errors[unmappedField]) && targetState.errors[unmappedField].length > 0) {
                        targetState.error = targetState.errors[unmappedField][0];
                    } else if (Object.keys(targetState.errors).length === 0) {
                        targetState.error = response.message || '{{ __('pos.operation_failed') }}';
                    } else {
                        targetState.error = '';
                    }
                } else {
                    targetState.error = response.message
                        || this.getFirstError(response.errors)
                        || '{{ __('pos.operation_failed') }}';
                }

                return false;
            },

            /**
             * Extracts the first error message from the dictionary.
             */
            getFirstError(errors) {
                if (!errors || typeof errors !== 'object') return null;
                for (const field of Object.keys(errors)) {
                    if (Array.isArray(errors[field]) && errors[field].length > 0) {
                        return errors[field][0];
                    }
                }
                return null;
            }
        };

        /**
         * Helper to construct a fresh modalData state object with reactive error helpers.
         */
        function createDefaultModalData() {
            return {
                name: '',
                index: null,
                type: 'fixed',
                amount: 0,
                unitPrice: 0,
                isNegotiable: false,
                minAllowed: 0,
                minAllowedTotal: 0,
                subtotal: 0,
                error: '',
                errors: {},
                destinationId: '',
                cost: 0,
                address: '',
                customer: { name: '', phone: '', email: '', address: '' },
                newItem: { presetId: '', name: '', amount: 0, action_type: 'addition', notes: '' },
                items: [],

                hasError(field) {
                    return Boolean(this.errors && this.errors[field] && this.errors[field].length > 0);
                },
                getError(field) {
                    return this.hasError(field) ? this.errors[field][0] : '';
                },
                getAllErrors(field) {
                    return this.hasError(field) ? this.errors[field] : [];
                },
                clearFieldError(field) {
                    if (this.errors && this.errors[field]) {
                        const updated = { ...this.errors };
                        delete updated[field];
                        this.errors = updated;
                        if (Object.keys(this.errors).length === 0) {
                            this.error = '';
                        }
                    }
                },
                clearErrors() {
                    this.error = '';
                    this.errors = {};
                }
            };
        }

        document.addEventListener('alpine:init', () => {
            Alpine.data('posSystem', () => ({
                // Server-owned reference data — read directly from $wire (auto-synced by Livewire)
                get storeId() {
                    return this.$wire.storeId;
                },
                get storeName() {
                    return this.$wire.storeName;
                },
                get currencySymbol() {
                    return this.$wire.currencySymbol || '$';
                },
                get stores() {
                    return this.$wire.storeList || [];
                },
                get categories() {
                    return this.$wire.categoryList || [];
                },
                get customers() {
                    return this.$wire.customerList || [];
                },
                get shippingDestinations() {
                    return this.$wire.shippingDestinationList || [];
                },
                get paymentMethods() {
                    return this.$wire.paymentMethodList || [];
                },

                extraItemPresets: [], // Loaded on demand

                globalDiscountAmount: 0,
                globalDiscountType: 'fixed',

                shippingCost: 0,
                shippingDestinationId: null,
                shippingAddress: '',

                extraItems: [],

                cart: [],

                // Payment Method (Explicitly chosen in UI, defaults to cash)
                paymentMethod: 'cash',

                // Customer selection
                selectedCustomerId: null,
                selectedCustomerName: '{{ __('pos.walk_in') }}',

                // Category selection
                selectedCategoryId: null,

                // Local processing & Modal state
                isProcessing: false,
                isSavingCustomer: false,
                showSuccessModal: false,
                completedInvoiceNumber: '',
                completedInvoiceTotal: 0,

                // New modals state
                activeModal: null, // 'itemDiscount', 'extraItems', 'globalDiscount', 'shipping', 'confirmClearCart', 'newCustomer', 'checkout'
                showNewDestinationForm: false,
                newDestination: { name: '', cost: 0 },
                modalData: createDefaultModalData(),

                // Checkout Settlement State
                checkoutModalData: {
                    paymentMethod: 'cash',
                    amountTendered: 0,
                    printReceipt: true,
                },

                init() {
                    window.addEventListener('keydown', (e) => {
                        // Handle Escape to close active modal
                        if (e.key === 'Escape') {
                            if (this.showSuccessModal) {
                                this.closeSuccessModal();
                                return;
                            }
                            if (this.activeModal) {
                                this.closeModal();
                                return;
                            }
                        }
                        if (e.key === 'Enter') {
                            if (this.showSuccessModal) {
                                this.closeSuccessModal();
                                return;
                            }
                            if (this.activeModal === 'checkout' && !this.isCheckoutBlocked) {
                                e.preventDefault();
                                this.submitCheckout();
                                return;
                            }
                        }
                        // Don't intercept shortcuts if a modal is open
                        if (this.activeModal || this.showSuccessModal) return;

                        if (e.key === 'F2') {
                            e.preventDefault();
                            this.focusSearch();
                        }
                        if (e.key === 'F4') {
                            e.preventDefault();
                            this.confirmClearCart();
                        }
                        if (e.key === 'F8') {
                            e.preventDefault();
                            this.holdCartAction();
                        }
                    });

                    // Listen to Livewire checkout events
                    window.addEventListener('checkout-successful', (e) => {
                        this.handleCheckoutSuccess(e.detail[0] || e.detail);
                    });
                    window.addEventListener('cart-held-successful', (e) => {
                        this.handleCartHeld(e.detail ? (e.detail[0] || e.detail) : null);
                    });
                },

                get selectedCategoryName() {
                    if (!this.selectedCategoryId) return '{{ __('pos.all_categories') }}';
                    const cat = this.$wire.categoryList.find(c => c.id == this.selectedCategoryId);
                    return cat ? cat.name : '{{ __('pos.all_categories') }}';
                },

                get selectedCustomerInitials() {
                    if (!this.selectedCustomerName) return 'W';
                    return this.selectedCustomerName.charAt(0).toUpperCase();
                },

                get cartSubtotal() {
                    return this.cart.reduce((sum, item) => {
                        let activePrice = item.priceType === 'wholesale' ? item.wholesale_price : item.retail_price;
                        return sum + (activePrice * item.qty);
                    }, 0);
                },

                get cartItemsDiscountTotal() {
                    return this.cart.reduce((sum, item) => {
                        let activePrice = item.priceType === 'wholesale' ? item.wholesale_price : item.retail_price;
                        let itemDiscount = item.discountType === 'percentage'
                            ? (activePrice * (item.discountAmount / 100))
                            : (parseFloat(item.discountAmount) || 0);
                        return sum + (itemDiscount * item.qty);
                    }, 0);
                },

                get cartSubtotalAfterItemsDiscount() {
                    return Math.max(0, this.cartSubtotal - this.cartItemsDiscountTotal);
                },

                get cartGlobalDiscount() {
                    let base = this.cartSubtotalAfterItemsDiscount;
                    let discount = this.globalDiscountType === 'percentage'
                        ? (base * ((parseFloat(this.globalDiscountAmount) || 0) / 100))
                        : (parseFloat(this.globalDiscountAmount) || 0);
                    return Math.min(discount, base);
                },

                get cartTotalDiscounts() {
                    return this.cartItemsDiscountTotal + this.cartGlobalDiscount;
                },

                get extraItemsTotal() {
                    return this.extraItems.reduce((sum, item) => {
                        return item.action_type === 'addition' ? sum + item.amount : sum - item.amount;
                    }, 0);
                },

                get cartTotal() {
                    let baseAfterGlobal = this.cartSubtotalAfterItemsDiscount - this.cartGlobalDiscount;
                    let extras = this.extraItemsTotal;
                    let shipping = parseFloat(this.shippingCost) || 0;

                    return Math.max(0, baseAfterGlobal + extras + shipping);
                },

                get cartItemCount() {
                    return this.cart.reduce((sum, item) => sum + item.qty, 0);
                },

                get hasInvalidCartItems() {
                    return this.cart.some(item => {
                        const wholesaleViolated = item.priceType === 'wholesale' && item.wholesale_qty_threshold > 0 && item.qty < item.wholesale_qty_threshold;
                        const stockViolated = item.stock !== undefined && item.qty > item.stock;
                        return wholesaleViolated || stockViolated;
                    });
                },

                get changeDue() {
                    if (this.checkoutModalData.paymentMethod !== 'cash') return 0;
                    const tendered = parseFloat(this.checkoutModalData.amountTendered) || 0;
                    const diff = tendered - this.cartTotal;
                    return diff > 0 ? Math.round(diff * 100) / 100 : 0;
                },

                get shortageAmount() {
                    if (this.checkoutModalData.paymentMethod !== 'cash') return 0;
                    const tendered = parseFloat(this.checkoutModalData.amountTendered) || 0;
                    const diff = this.cartTotal - tendered;
                    return diff > 0 ? Math.round(diff * 100) / 100 : 0;
                },

                get isCheckoutUnderpaid() {
                    if (this.checkoutModalData.paymentMethod !== 'cash') return false;
                    const tendered = parseFloat(this.checkoutModalData.amountTendered) || 0;
                    return Math.round(tendered * 100) < Math.round(this.cartTotal * 100);
                },

                get isCheckoutBlocked() {
                    return this.cart.length === 0 || this.isProcessing || this.hasInvalidCartItems || this.isCheckoutUnderpaid;
                },

                // Customer selection & inline creation
                selectCustomer(c) {
                    if (!c) {
                        this.selectedCustomerId = null;
                        this.selectedCustomerName = '{{ __('pos.walk_in') }}';
                    } else {
                        this.selectedCustomerId = c.id;
                        this.selectedCustomerName = c.name;
                    }
                },

                openCustomerModal() {
                    this.openModal('newCustomer', {
                        customer: { name: '', phone: '', email: '', address: '' },
                        error: '',
                        errors: {}
                    });
                },

                async saveNewCustomer() {
                    if (!this.modalData.customer?.name) return;
                    this.isSavingCustomer = true;
                    this.modalData.clearErrors();

                    try {
                        const response = await this.$wire.createCustomer(this.modalData.customer);

                        if (!RpcHandler.handle(response, this.modalData, ['name', 'phone', 'email', 'address'])) {
                            return;
                        }

                        const created = response.data;
                        this.customers.push(created);
                        this.selectCustomer(created);
                        this.closeModal();

                    } catch (e) {
                        console.error('RPC Network Failure', e);
                        this.modalData.error = e?.message || '{{ __('pos.network_error') }}';
                    } finally {
                        this.isSavingCustomer = false;
                    }
                },

                selectCategory(id) {
                    this.selectedCategoryId = id;
                    this.$wire.set('categoryId', id);
                },

                addToCart(product) {
                    if (product.stock !== undefined && product.stock <= 0) {
                        return;
                    }

                    const existing = this.cart.find(item => item.variant_id === product.id);
                    if (existing) {
                        if (existing.stock !== undefined && existing.qty >= existing.stock) {
                            return;
                        }
                        existing.qty++;
                    } else {
                        this.cart.unshift({
                            variant_id: product.id,
                            name: product.name,
                            retail_price: parseFloat(product.price) || 0,
                            wholesale_price: parseFloat(product.wholesale_price) || parseFloat(product.price) || 0,
                            priceType: 'retail', // default
                            wholesale_enabled: !!product.wholesale_enabled,
                            retail_is_price_negotiable: !!product.retail_is_price_negotiable,
                            min_retail_price: parseFloat(product.min_retail_price) || 0,
                            wholesale_is_price_negotiable: !!product.wholesale_is_price_negotiable,
                            min_wholesale_price: parseFloat(product.min_wholesale_price) || 0,
                            wholesale_qty_threshold: parseFloat(product.wholesale_qty_threshold) || 0,
                            stock: product.stock !== undefined ? product.stock : 999999,
                            uom_name: product.uom_name || '',
                            qty: 1,
                            discountType: 'fixed',
                            discountAmount: 0,
                        });
                    }
                },

                updateQty(index, delta) {
                    if (!this.cart[index]) return;
                    let item = this.cart[index];

                    if (item.qty + delta <= 0) {
                        this.removeItem(index);
                        return;
                    }

                    if (delta > 0 && item.stock !== undefined && item.qty >= item.stock) {
                        return;
                    }

                    item.qty += delta;

                    // Note: Do NOT silently switch wholesale to retail if qty < threshold!
                    // An inline warning is displayed and checkout is blocked until resolved.
                },

                togglePriceType(index, type) {
                    let item = this.cart[index];
                    item.priceType = type;
                    item.discountAmount = 0; // reset discount to avoid invalid state
                },

                removeItem(index) {
                    this.cart.splice(index, 1);
                },

                confirmClearCart() {
                    if (this.cart.length === 0) return;
                    this.openModal('confirmClearCart');
                },

                clearCart() {
                    this.cart = [];
                    this.extraItems = [];
                    this.globalDiscountAmount = 0;
                    this.globalDiscountType = 'fixed';
                    this.shippingCost = 0;
                    this.shippingDestinationId = null;
                    this.shippingAddress = '';
                },

                // Modal helpers
                defaultModalData() {
                    return createDefaultModalData();
                },
                openModal(name, data = {}) {
                    this.activeModal = name;
                    const defaults = this.defaultModalData();
                    const clonedData = JSON.parse(JSON.stringify(data));
                    this.modalData = {
                        ...defaults,
                        ...clonedData,
                        newItem: {
                            ...defaults.newItem,
                            ...(clonedData.newItem || {})
                        },
                        items: clonedData.items ? [...clonedData.items] : []
                    };
                },
                closeModal() {
                    this.activeModal = null;
                    this.modalData = this.defaultModalData();
                },

                // 1. Item Discount
                promptItemDiscount(index) {
                    const item = this.cart[index];
                    if (!item) return;

                    const isNegotiable = item.priceType === 'wholesale' ? item.wholesale_is_price_negotiable : item.retail_is_price_negotiable;
                    const minAllowed = item.priceType === 'wholesale' ? item.min_wholesale_price : item.min_retail_price;
                    const unitPrice = item.priceType === 'wholesale' ? item.wholesale_price : item.retail_price;

                    this.openModal('itemDiscount', {
                        index: index,
                        name: item.name,
                        type: item.discountType || 'fixed',
                        amount: item.discountAmount || 0,
                        unitPrice: unitPrice,
                        isNegotiable: isNegotiable,
                        minAllowed: minAllowed,
                        error: ''
                    });
                },
                applyItemDiscount() {
                    const idx = this.modalData.index;
                    let amount = parseFloat(this.modalData.amount) || 0;
                    let calculatedFixedDiscount = this.modalData.type === 'percentage'
                        ? (this.modalData.unitPrice * (amount / 100))
                        : amount;

                    let finalPrice = this.modalData.unitPrice - calculatedFixedDiscount;

                    if (amount > 0 && Math.round(finalPrice * 100) < Math.round((this.modalData.minAllowed || 0) * 100)) {
                        this.modalData.error = `{{ __('pos.discount_exceeds_minimum') }} (${this.currencySymbol} ${(this.modalData.minAllowed || 0).toFixed(2)})`;
                        return; // Prevent applying
                    }

                    this.cart[idx].discountType = this.modalData.type;
                    this.cart[idx].discountAmount = amount;
                    this.closeModal();
                },

                // 2. Extra Items
                async openExtraItemsModal() {
                    if (this.extraItemPresets.length === 0) {
                        this.extraItemPresets = await this.$wire.getExtraItemPresets();
                    }
                    this.openModal('extraItems', {
                        items: [...this.extraItems],
                        newItem: { presetId: '', name: '', amount: 0, action_type: 'addition', notes: '' }
                    });
                },
                addExtraItem() {
                    let newItem = { ...this.modalData.newItem };

                    if (!newItem.name || parseFloat(newItem.amount) <= 0) return;

                    newItem.amount = parseFloat(newItem.amount);
                    this.modalData.items.push(newItem);

                    // Reset inputs
                    this.modalData.newItem = {
                        presetId: '',
                        name: '',
                        amount: 0,
                        action_type: 'addition',
                        notes: ''
                    };
                },
                removeExtraItem(idx) {
                    this.modalData.items.splice(idx, 1);
                },
                applyExtraItems() {
                    this.extraItems = [...this.modalData.items];
                    this.closeModal();
                },

                // 3. Global Discount
                openGlobalDiscountModal() {
                    let minAllowedTotal = this.cart.reduce((sum, item) => {
                        let minPrice = item.priceType === 'wholesale' ? item.min_wholesale_price : item.min_retail_price;
                        return sum + (minPrice * item.qty);
                    }, 0);

                    this.openModal('globalDiscount', {
                        type: this.globalDiscountType,
                        amount: this.globalDiscountAmount,
                        subtotal: this.cartSubtotalAfterItemsDiscount,
                        minAllowedTotal: minAllowedTotal,
                        error: ''
                    });
                },
                applyGlobalDiscount() {
                    let amount = parseFloat(this.modalData.amount) || 0;
                    let calculatedFixedDiscount = this.modalData.type === 'percentage'
                        ? (this.modalData.subtotal * (amount / 100))
                        : amount;

                    let finalTotal = this.modalData.subtotal - calculatedFixedDiscount;

                    if (amount > 0 && Math.round(finalTotal * 100) < Math.round(((this.modalData.minAllowedTotal || 0)) * 100)) {
                        this.modalData.error = `{{ __('pos.discount_exceeds_minimum_total') }} (${this.currencySymbol} ${(this.modalData.minAllowedTotal || 0).toFixed(2)})`;
                        return;
                    }

                    if (this.modalData.type === 'percentage' && amount > 100) amount = 100;
                    if (this.modalData.type === 'fixed' && amount > this.modalData.subtotal) amount = this.modalData.subtotal;

                    this.globalDiscountType = this.modalData.type;
                    this.globalDiscountAmount = amount;
                    this.closeModal();
                },

                // 4. Shipping
                openShippingModal() {
                    this.showNewDestinationForm = false;
                    this.newDestination = { name: '', cost: 0 };
                    this.openModal('shipping', {
                        destinationId: this.shippingDestinationId ? String(this.shippingDestinationId) : '',
                        cost: this.shippingCost,
                        address: this.shippingAddress || '',
                        error: '',
                        errors: {}
                    });
                },
                onShippingDestinationChange(val) {
                    this.modalData.destinationId = val ? String(val) : '';
                    this.modalData.clearErrors();
                    if (val) {
                        const d = this.shippingDestinations.find(x => String(x.id) === String(val));
                        if (d) {
                            this.modalData.cost = parseFloat(d.cost) || 0;
                            this.modalData.address = d.name || '';
                        }
                    } else {
                        this.modalData.cost = 0;
                        this.modalData.address = '';
                    }
                },
                async saveNewDestination() {
                    if (!this.newDestination.name) return;
                    this.modalData.clearErrors();

                    // todo uncomment this after manual checking the rpc response is working fine
                    {{--if (!this.storeId) {--}}
                    {{--    this.modalData.error = '{{ __('pos.select_store_first') }}';--}}
                    {{--    return;--}}
                    {{--}--}}

                    try {
                        const costVal = (this.newDestination.cost !== '' && this.newDestination.cost !== null && this.newDestination.cost !== undefined)
                            ? this.newDestination.cost
                            : null;

                        const response = await this.$wire.createShippingDestination({
                            name: this.newDestination.name,
                            cost: costVal,
                        });
                        console.log('RPC Response for createShippingDestination:', response);
                        if (!RpcHandler.handle(response, this.modalData, ['name', 'cost'])) {
                            return;
                        }

                        const created = response.data;
                        this.shippingDestinations.push(created);
                        this.modalData.destinationId = String(created.id);
                        this.modalData.cost = parseFloat(created.cost) || 0;
                        this.modalData.address = created.name;
                        this.newDestination = { name: '', cost: 0 };
                        this.showNewDestinationForm = false;

                        this.$nextTick(() => {
                            const selectEl = document.getElementById('shippingDestinationSelect');
                            if (selectEl) selectEl.value = String(created.id);
                        });

                    } catch (e) {
                        console.error('RPC Network Failure', e);
                        this.modalData.error = e?.message || '{{ __('pos.network_error') }}';
                    }
                },
                applyShipping() {
                    this.modalData.error = '';
                    let cost = parseFloat(this.modalData.cost) || 0;
                    let destId = this.modalData.destinationId ? parseInt(this.modalData.destinationId) : null;

                    if (cost > 0 && !destId) {
                        this.modalData.error = '{{ __('pos.shipping_destination_required') }}';
                        return;
                    }

                    this.shippingCost = cost;
                    this.shippingDestinationId = destId;
                    this.shippingAddress = this.modalData.address || '';
                    this.closeModal();
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

                // Checkout Modal Methods
                openCheckoutModal() {
                    if (this.cart.length === 0 || this.isProcessing || this.hasInvalidCartItems) return;
                    this.checkoutModalData = {
                        paymentMethod: this.paymentMethod || 'cash',
                        amountTendered: Math.round(this.cartTotal * 100) / 100,
                        printReceipt: true,
                    };
                    this.openModal('checkout');
                },

                selectCheckoutPaymentMethod(method) {
                    this.checkoutModalData.paymentMethod = method;
                    this.paymentMethod = method;
                    if (method === 'cash') {
                        const tendered = parseFloat(this.checkoutModalData.amountTendered) || 0;
                        if (tendered < this.cartTotal) {
                            this.checkoutModalData.amountTendered = Math.round(this.cartTotal * 100) / 100;
                        }
                    }
                },

                setExactTendered() {
                    this.checkoutModalData.amountTendered = Math.round(this.cartTotal * 100) / 100;
                },

                addTenderedDenomination(amount) {
                    const current = parseFloat(this.checkoutModalData.amountTendered) || 0;
                    this.checkoutModalData.amountTendered = Math.round((current + amount) * 100) / 100;
                },

                submitCheckout() {
                    if (this.isCheckoutBlocked) return;
                    this.paymentMethod = this.checkoutModalData.paymentMethod;
                    this.closeModal();
                    this.processPayment();
                },

                processPayment() {
                    if (this.cart.length === 0 || this.isProcessing || this.hasInvalidCartItems) return;
                    this.isProcessing = true;
                    // Format cart for backend matching the expected structure
                    const formattedCart = this.cart.map(item => ({
                        variant_id: item.variant_id,
                        qty: item.qty,
                        discount_type: item.discountType,
                        discount_amount: item.discountAmount,
                        price_type: item.priceType
                    }));

                    this.$wire.processCheckout(formattedCart, {
                        customer_id: this.selectedCustomerId || null,
                        store_id: this.storeId || null,
                        payment_method: this.paymentMethod,
                        global_discount_type: this.globalDiscountType,
                        global_discount_amount: parseFloat(this.globalDiscountAmount) || 0,
                        shipping_destination_id: this.shippingDestinationId || null,
                        shipping_cost: parseFloat(this.shippingCost) || 0,
                        shipping_address: this.shippingAddress || null,
                        extra_items: this.extraItems,
                    }).finally(() => {
                        this.isProcessing = false;
                    });
                },

                holdCartAction() {
                    if (this.cart.length === 0 || this.isProcessing || this.hasInvalidCartItems) return;
                    this.isProcessing = true;

                    const formattedCart = this.cart.map(item => ({
                        variant_id: item.variant_id,
                        qty: item.qty,
                        discount_type: item.discountType,
                        discount_amount: item.discountAmount,
                        price_type: item.priceType
                    }));

                    this.$wire.holdCart(formattedCart, {
                        customer_id: this.selectedCustomerId || null,
                        store_id: this.storeId || null,
                        payment_method: this.paymentMethod,
                        global_discount_type: this.globalDiscountType,
                        global_discount_amount: parseFloat(this.globalDiscountAmount) || 0,
                        shipping_destination_id: this.shippingDestinationId || null,
                        shipping_cost: parseFloat(this.shippingCost) || 0,
                        shipping_address: this.shippingAddress || null,
                        extra_items: this.extraItems,
                    }).finally(() => {
                        this.isProcessing = false;
                    });
                },

                handleCheckoutSuccess(detail) {
                    this.completedInvoiceNumber = detail?.invoice_number || 'INV-001';
                    this.completedInvoiceTotal = detail?.total || this.cartTotal;
                    const invoiceId = detail?.invoice_id;

                    if (this.checkoutModalData?.printReceipt && invoiceId) {
                        const printFrame = document.getElementById('receiptPrintFrame');
                        if (printFrame) {
                            printFrame.src = `/print/invoice/sale_invoice/${invoiceId}`;
                        }
                    }

                    this.showSuccessModal = true;
                    this.clearCart();
                },

                closeSuccessModal() {
                    this.showSuccessModal = false;
                    this.focusSearch();
                },

                handleCartHeld(detail) {
                    this.clearCart();
                    this.focusSearch();
                }
            }));
        });
    </script>
</div>
