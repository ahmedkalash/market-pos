<?php

namespace App\Filament\Resources\InventoryMovements;

use App\Enums\AdjustmentReason;
use App\Enums\MovementDirection;
use App\Enums\MovementType;
use App\Filament\Resources\InventoryMovements\Pages\ManageInventoryMovements;
use App\Models\InventoryMovement;
use App\Models\ProductVariant;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\ColumnManagerLayout;
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

    public static function table(Table $table): Table
    {
        /** @var User $user */
        $user = auth()->user();

        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('app.created_at'))
                    ->description(fn (InventoryMovement $record) => $record->created_at->format('h:i ') . ($record->created_at->format('a') === 'am' ? 'ص' : 'م'))
                    ->dateTime('d-m-Y')
                    ->sortable(),

                TextColumn::make('store.name_en')
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
                    ->color(fn (InventoryMovement $record): string => $record->direction === \App\Enums\MovementDirection::In ? 'success' : 'danger')
                    ->icon(fn (InventoryMovement $record): string => $record->direction === \App\Enums\MovementDirection::In ? 'heroicon-m-plus-circle' : 'heroicon-m-minus-circle')
                    ->sortable(),

                TextColumn::make('unit_cost')
                    ->label(__('inventory.unit_cost'))
                    ->headerTooltip(__('inventory.unit_cost_tooltip'))
                    ->money('EGP')
                    ->toggleable(),

                TextColumn::make('total_cost')
                    ->label(__('inventory.total_cost'))
                    ->headerTooltip(__('inventory.total_cost_tooltip'))
                    ->money('EGP')
                    ->color(fn (InventoryMovement $record): string => $record->direction === MovementDirection::In ? 'success' : 'danger'),

                TextColumn::make('reason')
                    ->label(__('inventory.reason'))
                    ->formatStateUsing(fn (?AdjustmentReason $state): ?string => $state ? __('inventory.'.$state->value) : null)
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('reference_type')
                    ->label(__('inventory.reference_type'))
                    ->formatStateUsing(fn (string $state) => class_basename($state))
                    ->placeholder('—'),
                // todo: remove this and add a link to the reference_type for the related item
                TextColumn::make('reference_id')
                    ->label(__('inventory.reference_id'))
                    ->placeholder('—'),

                TextColumn::make('user.name')
                    ->label(__('inventory.user'))
                    ->placeholder('—')
                    ->searchable()
                    ->sortable(),

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
                    ->visible(fn () => $user->isCompanyLevel() || $user->isSuperAdmin()),

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
                                    ->mapWithKeys(fn ($type) => [$type => class_basename($type)]);
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
                            $indicators[] = __('inventory.reference_type').': '.class_basename($data['reference_type']);
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
                            $indicators[] = __('inventory.from').' '. Carbon::parse($data['from'])->format('d-m-Y');
                        }
                        if ($data['until'] ?? null) {
                            $indicators[] = __('inventory.to').' '. Carbon::parse($data['until'])->format('d-m-Y');
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
