<?php

namespace App\Filament\Resources\PurchaseReturns\Schemas;

use App\Models\ProductBarcode;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class PurchaseReturnForm
{
    public static function configure(Schema $schema): Schema
    {
        /** @var User $user */
        $user = Auth::user()->load('company');

        return $schema->components([
            Section::make(__('purchase_return.purchase_return'))
                ->icon('heroicon-o-arrow-uturn-left')
                ->schema([
                    TextInput::make('invoice_number_input')
                        ->label(__('purchase_return.original_invoice_id'))
                        ->required()
                        ->live(onBlur: true)
                        ->afterStateUpdated(function ($state, Get $get, Set $set, $livewire) {
                            $set('items', []); // clear items
                            static::calcGrandTotal($get, $set);
                            $set('original_invoice_id', null);
                            $set('vendor_id', null);
                            $set('store_id', null);

                            if ($state) {
                                $invoice = PurchaseInvoice::query()
                                    ->returnable()
                                    ->where('invoice_number', $state)
                                    ->first();

                                if ($invoice) {
                                    $set('original_invoice_id', $invoice->id);
                                    $set('vendor_id', $invoice->vendor_id);
                                    $set('store_id', $invoice->store_id);
                                    Notification::make()->success()->title(__('purchase_return.invoice_found_success'))->send();
                                    $livewire->dispatch('play-sound-success');
                                } else {
                                    Notification::make()->warning()->title(__('purchase_return.invoice_not_found'))->send();
                                    $livewire->dispatch('play-sound-error');
                                }
                            }
                        })
                        ->formatStateUsing(fn ($record) => $record?->originalInvoice?->invoice_number)
                        ->dehydrated(false)
                        ->helperText(__('purchase_return.invoice_search_helper'))
                        ->prefixAction(
                            Action::make('search')
                                ->icon('heroicon-m-magnifying-glass')
                                ->label(__('purchase_return.search'))
                        )
                        ->columnSpanFull(),

                    Hidden::make('original_invoice_id')
                        ->required(),

                    Select::make('vendor_id')
                        ->label(__('purchase_return.vendor'))
                        ->relationship('vendor', 'name')
                        ->disabled()
                        ->dehydrated()
                        ->required(),

                    Select::make('store_id')
                        ->label(__('purchase_return.store'))
                        ->relationship('store', 'name_'.app()->getLocale())
                        ->disabled()
                        ->dehydrated()
                        ->required()
                        ->helperText(__('purchase_return.store_helper'))
                        ->visible(fn () => $user->isCompanyLevel()),

                    Hidden::make('store_id')
                        ->disabled()
                        ->dehydrated()
                        ->visible(fn () => $user->isStoreLevel()),

                    DatePicker::make('returned_at')
                        ->label(__('purchase_return.returned_at'))
                        ->required()
                        ->default(now()->toDateString())
                        ->maxDate(now()),

                    // TextInput::make('vendor_credit_ref')
                    //     ->label(__('purchase_return.vendor_credit_ref'))
                    //     ->maxLength(100)
                    //     ->placeholder('CN-2024-0099'),

                    Textarea::make('return_reason')
                        ->label(__('purchase_return.return_reason'))
                        ->required()
                        ->rows(2),
                ])->columns(2),

            Section::make(__('purchase_return.notes'))
                ->icon('heroicon-o-chat-bubble-left-ellipsis')
                ->schema([
                    Textarea::make('notes')
                        ->label(__('purchase_return.notes'))
                        ->rows(4)
                        ->columnSpanFull(),
                ]),

            Section::make(__('purchase_return.items'))
                ->icon('heroicon-o-arrow-uturn-left')
                ->columnSpanFull()
                ->headerActions([
                    Action::make('add_all_items')
                        ->label(__('purchase_return.return_all_items'))
                        ->icon('heroicon-o-bars-arrow-down')
                        ->color('primary')
                        ->action(function (Get $get, Set $set) {
                            $invoiceId = $get('original_invoice_id');
                            if (! $invoiceId) {
                                return;
                            }

                            $invoice = PurchaseInvoice::with(['items.variant.product', 'items.variant.barcodes'])->find($invoiceId);
                            if (! $invoice) {
                                return;
                            }

                            $items = [];
                            foreach ($invoice->items as $originalItem) {
                                $maxReturnable = $originalItem->remaining_returnable_quantity;
                                if ($maxReturnable > 0) {
                                    $barcodes = $originalItem->variant->barcodes->pluck('barcode')->toArray();
                                    $key = 'item_'.$originalItem->id;
                                    $locale = app()->getLocale();
                                    $productName = $originalItem->variant->product->{"name_$locale"};
                                    $variantName = $originalItem->variant->{"name_$locale"};
                                    $fullName = $variantName ? "{$productName} - {$variantName}" : $productName;

                                    $items[$key] = [
                                        'original_item_id' => $originalItem->id,
                                        'product_variant_id' => $originalItem->product_variant_id,
                                        'barcodes' => $barcodes,
                                        'product_name' => $fullName,
                                        'max_returnable' => $maxReturnable,
                                        'quantity' => $maxReturnable,
                                        'unit_cost' => (float) $originalItem->unit_cost,
                                        'line_total' => round($maxReturnable * (float) $originalItem->unit_cost, 2),
                                        'notes' => null,
                                    ];
                                }
                            }

                            $set('items', $items);

                            static::calcGrandTotal($get, $set);
                        })
                        ->visible(fn (Get $get) => filled($get('original_invoice_id'))),
                ])
                ->schema([
                    TextInput::make('barcode_scanner')
                        ->label(__('purchase_return.barcode_scanner'))
                        ->placeholder(__('purchase_return.scan_barcode'))
                        ->helperText(__('purchase_return.barcode_scanner_helper'))
                        ->autofocus()
                        ->extraInputAttributes([
                            'x-on:focus-barcode.window' => 'setTimeout(() => $el.focus(), 10)',
                        ])
                        ->live(onBlur: true)
                        ->prefixAction(
                            Action::make('search')
                                ->icon('heroicon-m-magnifying-glass')
                                ->label(__('purchase_return.search'))
                        )
                        ->afterStateUpdated(function ($state, Set $set, Get $get, $livewire) {
                            if (! $state) {
                                return;
                            }
                            $set('barcode_scanner', null);

                            $invoiceId = $get('original_invoice_id');
                            if (! $invoiceId) {
                                Notification::make()->warning()->title(__('purchase_return.select_original_invoice_first'))->send();
                                $livewire->dispatch('play-sound-error');
                                $livewire->dispatch('focus-barcode');

                                return;
                            }

                            $barcodeRecord = ProductBarcode::where('barcode', $state)->first();
                            if (! $barcodeRecord) {
                                Notification::make()->warning()->title(__('purchase_return.barcode_not_found'))->send();
                                $livewire->dispatch('play-sound-error');
                                $livewire->dispatch('focus-barcode');

                                return;
                            }

                            $originalItem = PurchaseInvoiceItem::with(['variant.product', 'variant.barcodes'])
                                ->where('purchase_invoice_id', $invoiceId)
                                ->where('product_variant_id', $barcodeRecord->product_variant_id)
                                ->first();

                            if (! $originalItem) {
                                Notification::make()->warning()->title(__('purchase_return.product_not_in_invoice'))->send();
                                $livewire->dispatch('play-sound-error');
                                $livewire->dispatch('focus-barcode');

                                return;
                            }

                            $maxReturnable = $originalItem->remaining_returnable_quantity;
                            if ($maxReturnable <= 0) {
                                Notification::make()->warning()->title(__('purchase_return.no_remaining_quantity'))->send();
                                $livewire->dispatch('play-sound-error');
                                $livewire->dispatch('focus-barcode');

                                return;
                            }

                            $items = $get('items') ?? [];
                            $key = 'item_'.$originalItem->id;

                            if (array_key_exists($key, $items)) {
                                Notification::make()->warning()->title(__('purchase_return.item_already_added'))->send();
                                $livewire->dispatch('play-sound-error');
                                $livewire->dispatch('focus-barcode');

                                return;
                            }

                            $barcodes = $originalItem->variant->barcodes->pluck('barcode')->toArray();

                            $locale = app()->getLocale();
                            $productName = $originalItem->variant->product->{"name_$locale"};
                            $variantName = $originalItem->variant->{"name_$locale"};
                            $fullName = $variantName ? "{$productName} - {$variantName}" : $productName;

                            $items[$key] = [
                                'original_item_id' => $originalItem->id,
                                'product_variant_id' => $originalItem->product_variant_id,
                                'barcodes' => $barcodes,
                                'product_name' => $fullName,
                                'quantity' => 1,
                                'unit_cost' => (float) $originalItem->unit_cost,
                                'line_total' => round(1 * (float) $originalItem->unit_cost, 2),
                                'max_returnable' => $maxReturnable,
                                'notes' => null,
                            ];

                            $set('items', $items);
                            static::calcGrandTotal($get, $set);
                            $livewire->dispatch('play-sound-success');
                            $livewire->dispatch('focus-barcode');
                        })
                        ->visible(fn (Get $get) => filled($get('original_invoice_id'))),

                    Repeater::make('items')
                        ->hiddenLabel()
                        ->compact()
                        ->deleteAction(
                            fn (Action $action) => $action->after(function (Get $get, Set $set) {
                                static::calcGrandTotal($get, $set);
                            })
                        )
                        ->itemLabel(function (array $state): ?HtmlString {
                            $barcodes = $state['barcodes'] ?? [];

                            if (empty($barcodes)) {
                                return null;
                            }

                            $badges = collect($barcodes)->map(function ($barcode) {
                                return "<span style='margin-inline-end: 0.5rem;' class='inline-flex items-center justify-center min-h-6 px-2 py-0.5 text-sm font-medium tracking-tight rounded-xl text-primary-700 bg-primary-50 ring-1 ring-inset ring-primary-600/10 dark:text-primary-400 dark:bg-primary-400/10 dark:ring-primary-400/30'>{$barcode}</span>";
                            })->implode('');

                            return new HtmlString("<div class='flex items-center'>".__('purchase_invoice.barcode').': '.$badges.'</div>');
                        })
                        ->schema([
                            Hidden::make('original_item_id')->required(),
                            Hidden::make('product_variant_id')->required(),
                            Hidden::make('max_returnable'),

                            TextInput::make('product_name')
                                ->label(__('purchase_return.product_name'))
                                ->dehydrated(false)
                                ->disabled()
                                ->columnSpan(3),

                            TextInput::make('quantity')
                                ->label(__('purchase_return.quantity'))
                                ->numeric()
                                ->required()
                                ->hintIcon('heroicon-m-information-circle', tooltip: __('purchase_return.quantity_tooltip'))
                                ->minValue(0.001)
                                ->step(0.001)
                                ->live(debounce: '500ms')
                                ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                    $max = (float) $get('max_returnable');
                                    if ((float) $state > $max) {
                                        $set('quantity', $max);
                                        Notification::make()->warning()->title(__('purchase_return.quantity_adjusted'))->send();
                                    }
                                    self::recalculateLine($get, $set);

                                    $items = $get('../../items') ?? [];
                                    $total = collect($items)->sum('line_total');
                                    $set('../../grand_total', $total);
                                })
                                ->suffix(fn (Get $get) => __('purchase_return.max_suffix').' '.$get('max_returnable'))
                                ->columnSpan(3),

                            TextInput::make('unit_cost')
                                ->label(__('purchase_return.unit_cost'))
                                ->numeric()
                                ->required()
                                ->hintIcon('heroicon-m-information-circle', tooltip: __('purchase_return.unit_cost_tooltip'))
                                ->prefix($user->company->currency_symbol ?? 'ج.م')
                                ->disabled()
                                ->dehydrated()
                                ->columnSpan(2),

                            TextInput::make('line_total')
                                ->label(__('purchase_return.line_total'))
                                ->numeric()
                                ->readOnly()
                                ->hintIcon('heroicon-m-information-circle', tooltip: __('purchase_return.line_total_tooltip'))
                                ->prefix($user->company->currency_symbol ?? 'ج.م')
                                ->columnSpan(2),

                            Textarea::make('notes')
                                ->label(__('purchase_return.item_notes'))
                                ->maxLength(255)
                                ->hintIcon('heroicon-m-information-circle', tooltip: __('purchase_return.notes_tooltip'))
                                ->columnSpan(2),
                        ])
                        ->columns(12)
                        ->addable(false)
                        ->reorderable(false)
                        ->cloneable(false)
                        ->defaultItems(0),

                    TextInput::make('grand_total')
                        ->label(__('purchase_return.grand_total'))
                        ->disabled()
                        ->dehydrated(false)
                        ->extraInputAttributes(['class' => 'text-xl font-bold'])
                        ->prefix($user->company->currency_symbol ?? 'ج.م')
                        ->afterStateHydrated(function (Get $get, Set $set) {
                            static::calcGrandTotal($get, $set);
                        })
                        ->columnSpanFull(),

                    TextEntry::make('audio_feedback')
                        ->hiddenLabel()
                        ->state(new HtmlString('
                            <div
                                x-data="{
                                    playSuccess() {
                                        let ctx = new (window.AudioContext || window.webkitAudioContext)();
                                        let osc = ctx.createOscillator();
                                        let gain = ctx.createGain();
                                        osc.connect(gain);
                                        gain.connect(ctx.destination);
                                        osc.type = \'sine\';
                                        osc.frequency.setValueAtTime(800, ctx.currentTime);
                                        gain.gain.setValueAtTime(0.1, ctx.currentTime);
                                        osc.start();
                                        gain.gain.exponentialRampToValueAtTime(0.00001, ctx.currentTime + 0.1);
                                        osc.stop(ctx.currentTime + 0.1);
                                    },
                                    playError() {
                                        let ctx = new (window.AudioContext || window.webkitAudioContext)();
                                        let osc = ctx.createOscillator();
                                        let gain = ctx.createGain();
                                        osc.connect(gain);
                                        gain.connect(ctx.destination);
                                        osc.type = \'sawtooth\';
                                        osc.frequency.setValueAtTime(150, ctx.currentTime);
                                        gain.gain.setValueAtTime(0.1, ctx.currentTime);
                                        osc.start();
                                        gain.gain.exponentialRampToValueAtTime(0.00001, ctx.currentTime + 0.3);
                                        osc.stop(ctx.currentTime + 0.3);
                                    }
                                }"
                                x-on:play-sound-success.window="playSuccess()"
                                x-on:play-sound-error.window="playError()"
                            ></div>
                        '))
                        ->columnSpanFull(),
                ]),
        ]);
    }

    /**
     * Recalculate line_total when qty or unit_cost changes.
     */
    private static function recalculateLine(Get $get, Set $set): void
    {
        $quantity = (float) ($get('quantity') ?? 0);
        $unitCost = (float) ($get('unit_cost') ?? 0);
        $lineTotal = round($quantity * $unitCost, 2);

        $set('line_total', $lineTotal);
    }

    private static function calcGrandTotal(Get $get, Set $set): void
    {
        $items = $get('items') ?? [];
        $total = collect($items)->sum('line_total');
        $set('grand_total', $total);
    }
}
