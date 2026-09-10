<?php

namespace App\Filament\Resources\InventoryMovements;

use App\Enums\AdjustmentReason;
use App\Enums\MovementDirection;
use App\Enums\MovementType;
use App\Filament\Resources\InventoryMovements\Pages\ManageInventoryMovements;
use App\Filament\Resources\PurchaseInvoices\PurchaseInvoiceResource;
use App\Filament\Resources\PurchaseReturns\PurchaseReturnResource;
use App\Filament\Resources\SaleInvoices\SaleInvoiceResource;
use App\Filament\Resources\SaleReturnInvoices\SaleReturnInvoiceResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\InventoryMovement;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class InventoryMovementResource extends Resource
{
    protected static ?string $model = InventoryMovement::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-queue-list';

    public static function getNavigationGroup(): ?string
    {
        return __('inventory.inventory');
    }

    public static function getNavigationLabel(): string
    {
        return __('inventory.inventory_movements');
    }

    public static function getModelLabel(): string
    {
        return __('inventory.inventory_movement');
    }

    public static function getPluralModelLabel(): string
    {
        return __('inventory.inventory_movements');
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getTranslatedReferenceType(string $type): string
    {
        $basename = class_basename($type);

        return match ($basename) {
            'SaleInvoice' => __('sale_invoice.sale_invoice'),
            'PurchaseInvoice' => __('purchase_invoice.purchase_invoice'),
            'SaleReturnInvoice' => __('app.sale_return'),
            'PurchaseReturn' => __('purchase_return.purchase_return'),
            default => $basename,
        };
    }

    public static function table(Table $table): Table
    {
        /** @var User $user */
        $user = auth()->user();

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('reference'))
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('app.created_at'))
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('store.name')
                    ->label(__('inventory.store'))
                    ->visible(fn () => $user->isCompanyLevel() || $user->isSuperAdmin())
                    ->searchable()
                    ->sortable(),

                TextColumn::make('variant.name_ar')
                    ->label(__('inventory.variant'))
                    ->description(fn (InventoryMovement $record) => $record->variant->name_en)
                    ->searchable(['name_en', 'name_ar'])
                    ->sortable(),

                TextColumn::make('type')
                    ->label(__('inventory.movement_type'))
                    ->badge()
                    ->formatStateUsing(fn (MovementType $state): string => __('inventory.'.$state->value))
                    ->color(fn (MovementType $state): string => $state->getColor())
                    ->sortable(),

                TextColumn::make('quantity')
                    ->label(__('inventory.quantity'))
                    ->numeric(2, locale: 'en')
                    ->color(fn (InventoryMovement $record): string => $record->direction === MovementDirection::In ? 'success' : 'danger')
                    ->icon(fn (InventoryMovement $record): string => $record->direction === MovementDirection::In ? 'heroicon-m-plus-circle' : 'heroicon-m-minus-circle')
                    ->sortable(),

                TextColumn::make('reason')
                    ->label(__('inventory.reason'))
                    ->formatStateUsing(fn (?AdjustmentReason $state): ?string => $state ? __('inventory.'.$state->value) : null)
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('reference_type')
                    ->label(__('inventory.reference'))
                    ->formatStateUsing(function (string $state, InventoryMovement $record) {
                        if (! $record->reference_type || ! $record->reference_id) {
                            return '—';
                        }
                        $translated = self::getTranslatedReferenceType($state);

                        $documentNumber = match (class_basename($state)) {
                            'SaleInvoice', 'PurchaseInvoice' => $record->reference?->invoice_number,
                            'SaleReturnInvoice', 'PurchaseReturn' => $record->reference?->return_number,
                            default => $record->reference_id,
                        };

                        $documentNumber = $documentNumber ?? $record->reference_id;

                        return "{$translated} #{$documentNumber}";
                    })
                    ->url(function (InventoryMovement $record): ?string {
                        if (! $record->reference_type || ! $record->reference_id) {
                            return null;
                        }

                        return match (class_basename($record->reference_type)) {
                            'SaleInvoice' => SaleInvoiceResource::getUrl('view', ['record' => $record->reference_id]),
                            'PurchaseInvoice' => PurchaseInvoiceResource::getUrl('view', ['record' => $record->reference_id]),
                            'SaleReturnInvoice' => SaleReturnInvoiceResource::getUrl('view', ['record' => $record->reference_id]),
                            'PurchaseReturn' => PurchaseReturnResource::getUrl('view', ['record' => $record->reference_id]),
                            default => null,
                        };
                    })
                    ->color('primary')
                    ->placeholder('—'),

                TextColumn::make('user.name')
                    ->label(__('inventory.user'))
                    ->placeholder('—')
                    ->searchable()
                    ->sortable()
                    ->url(fn (InventoryMovement $record): ?string => $record->user_id ? UserResource::getUrl('edit', ['record' => $record->user_id]) : null)
                    ->color('primary'),

                TextColumn::make('notes')
                    ->label(__('inventory.notes'))
                    ->limit(30)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('store_id')
                    ->label(__('inventory.store'))
                    ->relationship('store', 'name_en')
                    ->visible(fn () => $user->isCompanyLevel()),

                Filter::make('barcode')
                    ->label(__('inventory.barcode'))
                    ->schema([
                        TextInput::make('barcode')
                            ->label(__('inventory.barcode'))
                            ->hintIcon('heroicon-m-question-mark-circle', tooltip: __('inventory.barcode_tooltip'))
                            ->autofocus(),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query->when(
                            $data['barcode'],
                            fn (Builder $query, $barcode) => $query->whereHas('variant.barcodes', fn ($q) => $q->where('barcode', $barcode))
                        );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['barcode'] ?? null) {
                            $indicators[] = __('inventory.barcode').': '.$data['barcode'];
                        }

                        return $indicators;
                    }),

                SelectFilter::make('type')
                    ->label(__('inventory.movement_type'))
                    ->options(collect(MovementType::cases())->mapWithKeys(fn ($case) => [$case->value => __('inventory.'.$case->value)])),

                SelectFilter::make('user_id')
                    ->label(__('inventory.user'))
                    ->relationship('user', 'name'),

                SelectFilter::make('reason')
                    ->label(__('inventory.reason'))
                    ->options(collect(AdjustmentReason::cases())->mapWithKeys(fn ($case) => [$case->value => __('inventory.'.$case->value)]))
                    ->placeholder(__('app.all')),

                Filter::make('reference_type')
                    ->schema([
                        Select::make('reference_type')
                            ->label(__('inventory.reference_type'))
                            ->hintIcon('heroicon-m-question-mark-circle', tooltip: __('inventory.reference_type_tooltip'))
                            ->options(function () {
                                return InventoryMovement::query()
                                    ->whereNotNull('reference_type')
                                    ->distinct()
                                    ->pluck('reference_type')
                                    ->mapWithKeys(fn ($type) => [$type => self::getTranslatedReferenceType($type)]);
                            })
                            ->placeholder(__('app.all')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['reference_type'],
                            fn (Builder $query, $type): Builder => $query->where('reference_type', $type),
                        );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['reference_type'] ?? null) {
                            $indicators[] = __('inventory.reference_type').': '.self::getTranslatedReferenceType($data['reference_type']);
                        }

                        return $indicators;
                    }),

                Filter::make('created_at')
                    ->schema([
                        DatePicker::make('from')
                            ->label(__('inventory.from'))
                            ->hintIcon('heroicon-m-question-mark-circle', tooltip: __('inventory.from_tooltip'))
                            ->native(false)
                            ->format('Y-m-d')
                            ->displayFormat('Y-m-d'),
                        DatePicker::make('until')
                            ->label(__('inventory.to'))
                            ->hintIcon('heroicon-m-question-mark-circle', tooltip: __('inventory.to_tooltip'))
                            ->native(false)
                            ->format('Y-m-d')
                            ->displayFormat('Y-m-d'),
                    ])
                    ->columns(2)
                    ->columnSpan(2)
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from'] ?? null) {
                            $indicators[] = __('inventory.from').' '.Carbon::parse($data['from'])->format('d-m-Y');
                        }
                        if ($data['until'] ?? null) {
                            $indicators[] = __('inventory.to').' '.Carbon::parse($data['until'])->format('d-m-Y');
                        }

                        return $indicators;
                    }),

                Filter::make('quantity_threshold')
                    ->schema([
                        TextInput::make('min_qty')
                            ->label(__('inventory.min_quantity'))
                            ->numeric(),
                        TextInput::make('max_qty')
                            ->label(__('inventory.max_quantity'))
                            ->numeric(),
                    ])
                    ->columns(2)
                    ->columnSpan(2)
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['min_qty'],
                                fn (Builder $query, $qty): Builder => $query->where('quantity', '>=', $qty),
                            )
                            ->when(
                                $data['max_qty'],
                                fn (Builder $query, $qty): Builder => $query->where('quantity', '<=', $qty),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['min_qty'] ?? null) {
                            $indicators[] = __('inventory.min_quantity').' '.$data['min_qty'];
                        }
                        if ($data['max_qty'] ?? null) {
                            $indicators[] = __('inventory.max_quantity').' '.$data['max_qty'];
                        }

                        return $indicators;
                    }),
            ], FiltersLayout::AboveContent)
            ->filtersFormColumns(5)
            ->recordActions([])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageInventoryMovements::route('/'),
        ];
    }
}
