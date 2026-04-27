<?php

namespace App\Filament\Resources\Products\RelationManagers;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\ProductVariant;
use App\Models\User;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class VariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'variants';

    public array $tempVariantAttributes = [];

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('app.variants');
    }

    public static function getModelLabel(): ?string
    {
        return __('app.variant');
    }

    public static function getPluralModelLabel(): ?string
    {
        return __('app.variants');
    }

    public function form(Schema $schema): Schema
    {
        /** @var User $user */
        $user = auth()->user();
        $companyId = $user->company_id;

        return $schema
            ->components([
                Section::make(__('app.variant_details'))
                    ->schema([
                        TextInput::make('name_en')
                            ->label(__('app.variant_name_en'))
                            ->helperText(__('app.variant_name_helper'))
                            ->required()
                            ->maxLength(255),

                        TextInput::make('name_ar')
                            ->label(__('app.variant_name_ar'))
                            ->helperText(__('app.variant_name_helper'))
                            ->required()
                            ->maxLength(255),

                        Select::make('uom_id')
                            ->label(__('app.unit_of_measure'))
                            ->helperText(__('app.uom_helper'))
                            ->relationship('unitOfMeasure', 'name_'.app()->getLocale(), fn (Builder $query) => $query->where('company_id', $companyId))
                            ->required()
                            ->searchable()
                            ->preload(),

                    ])->columns(2),

                Section::make(__('app.pricing'))
                    ->schema([
                        TextInput::make('price')
                            ->label(__('app.price'))
                            ->helperText(__('app.price_helper'))
                            ->numeric()
                            ->minValue(0)
                            ->required()
                            ->prefix(fn () => $user->company->currency_symbol),

                        Toggle::make('price_is_negotiable')
                            ->label(__('app.price_is_negotiable'))
                            ->helperText(__('app.price_is_negotiable_helper'))
                            ->live()
                            ->default(false),

                        TextInput::make('minimum_price')
                            ->label(__('app.minimum_price'))
                            ->helperText(__('app.minimum_price_helper'))
                            ->numeric()
                            ->minValue(0)
                            ->required(fn (Get $get) => (bool) $get('price_is_negotiable'))
                            ->prefix(fn () => $user->company->currency_symbol)
                            ->visible(fn (Get $get) => (bool) $get('price_is_negotiable')),
                    ])
                    ->columns(),

                Section::make(__('app.inventory'))
                    ->schema([
                        TextInput::make('quantity')
                            ->label(__('app.quantity'))
                            ->helperText(__('app.quantity_helper'))
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->required(),

                        TextInput::make('low_stock_threshold')
                            ->label(__('app.low_stock_threshold'))
                            ->helperText(__('app.low_stock_threshold_helper'))
                            ->numeric()
                            ->minValue(0)
                            ->nullable(),

                        Toggle::make('is_active')
                            ->label(__('app.active'))
                            ->helperText(__('app.is_active_helper'))
                            ->default(true),
                    ])
                    ->columns(3),

                Section::make(__('app.barcodes').': '.__('app.barcode_input_helper'))
                    ->schema([
                        Repeater::make('barcodes')
                            ->hiddenLabel()
                            ->relationship('barcodes')
                            ->schema([
                                TextInput::make('barcode')
                                    ->hiddenLabel()
                                    ->placeholder(__('app.barcode'))
                                    ->required()
                                    ->maxLength(255)
                                    ->unique('product_barcodes', 'barcode', ignoreRecord: true),
                            ])
                            ->grid(3)
                            ->addActionLabel(__('app.add_barcode'))
                            ->reorderable(false)
                            ->columnSpanFull(),
                    ])
                    ->compact(),

                Section::make()
                    ->schema([
                        Repeater::make('variant_attributes')
                            ->reorderable(false)
                            ->grid(2)
                            ->label(__('app.attributes'))
                            ->schema([
                                Select::make('attribute_id')
                                    ->label(__('app.attribute'))
                                    ->helperText(__('app.attribute_helper'))
                                    ->options(function () use ($companyId) {
                                        return Attribute::where('company_id', $companyId)->pluck('name_'.app()->getLocale(), 'id');
                                    })
                                    ->live()
                                    ->required()
                                    ->createOptionForm([
                                        TextInput::make('name_en')
                                            ->label(__('app.name_english'))
                                            ->required(),
                                        TextInput::make('name_ar')
                                            ->label(__('app.name_arabic'))
                                            ->required(),
                                    ])
                                    ->createOptionUsing(function (array $data) use ($companyId) {
                                        $attribute = Attribute::create([
                                            'company_id' => $companyId,
                                            'name_en' => $data['name_en'],
                                            'name_ar' => $data['name_ar'],
                                        ]);

                                        return $attribute->id;
                                    }),

                                Select::make('attribute_value_id')
                                    ->label(__('app.value'))
                                    ->helperText(__('app.attribute_value_item_helper'))
                                    ->options(function (Get $get) {
                                        if (! $get('attribute_id')) {
                                            return [];
                                        }

                                        return AttributeValue::where('attribute_id', $get('attribute_id'))->pluck('value_'.app()->getLocale(), 'id');
                                    })
                                    ->required()
                                    ->createOptionForm([
                                        TextInput::make('value_en')
                                            ->label(__('app.value_en'))
                                            ->required(),
                                        TextInput::make('value_ar')
                                            ->label(__('app.value_ar'))
                                            ->required(),
                                    ])
                                    ->createOptionUsing(function (array $data, Get $get) {
                                        $value = AttributeValue::create([
                                            'attribute_id' => $get('attribute_id'),
                                            'value_en' => $data['value_en'],
                                            'value_ar' => $data['value_ar'],
                                        ]);

                                        return $value->id;
                                    })
                                    ->disabled(fn (Get $get) => ! $get('attribute_id')),
                            ])
                            ->columns(2)
                            ->columnSpanFull()
                            ->addActionLabel(__('app.add_attribute'))
                            ->helperText(__('app.attribute_values_helper')),
                    ])->columnSpanFull(),

            ]);
    }

    public function table(Table $table): Table
    {
        /** @var User $user */
        $user = auth()->user();

        return $table
            ->recordTitleAttribute('name_'.app()->getLocale())
            ->recordActionsColumnLabel(__('app.actions'))
            ->columns([
                TextColumn::make('name_ar')
                    ->label(__('app.name'))
                    ->description(fn (ProductVariant $record): string => $record->name_en)
                    ->searchable(['name_en', 'name_ar'])
                    ->sortable(),

                TextColumn::make('attributeValues.value_'.app()->getLocale())
                    ->label(__('app.attributes'))
                    ->badge()
                    ->color('primary')
                    ->listWithLineBreaks(),

                TextColumn::make('price')
                    ->label(__('app.price'))
                    ->formatStateUsing(fn (?string $state) => $state.' '. $user->company->currency_symbol)
                    ->badge()
                    ->color('success')
                    ->sortable(),

                TextColumn::make('quantity')
                    ->label(__('app.stock'))
                    ->numeric(2)
                    ->sortable()
                    ->color(fn (ProductVariant $record): ?string => $record->isLowStock() ? 'danger' : null),

                TextColumn::make('unitOfMeasure.abbreviation_'.app()->getLocale())
                    ->label(__('app.uom'))
                    ->badge()
                    ->color('gray'),

                IconColumn::make('price_is_negotiable')
                    ->label(__('app.negotiable'))
                    ->boolean()
                    ->trueIcon(Heroicon::CheckBadge)
                    ->falseIcon(Heroicon::XMark),

                IconColumn::make('is_active')
                    ->label(__('app.active'))
                    ->boolean(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->modalWidth(Width::SevenExtraLarge)
                    ->label(__('app.add_variant'))
                    ->mutateDataUsing(function (array $data): array {
                        $this->tempVariantAttributes = $data['variant_attributes'] ?? [];
                        unset($data['variant_attributes']);

                        return $data;
                    })
                    ->after(function (Model $record) {
                        $valueIds = collect($this->tempVariantAttributes)->pluck('attribute_value_id')->filter();
                        $record->attributeValues()->sync($valueIds);
                    }),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()
                        ->modalWidth(Width::SevenExtraLarge)
                        ->mutateRecordDataUsing(function (array $data, Model $record): array {
                            $data['variant_attributes'] = $record->attributeValues->map(function ($value) {
                                return [
                                    'attribute_id' => $value->attribute_id,
                                    'attribute_value_id' => $value->id,
                                ];
                            })->toArray();

                            return $data;
                        })
                        ->mutateDataUsing(function (array $data): array {
                            $this->tempVariantAttributes = $data['variant_attributes'] ?? [];
                            unset($data['variant_attributes']);

                            return $data;
                        })
                        ->after(function (Model $record) {
                            $valueIds = collect($this->tempVariantAttributes)->pluck('attribute_value_id')->filter();

                            $record->attributeValues()->sync($valueIds);
                        }),
                    DeleteAction::make(),
                ]),

            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
