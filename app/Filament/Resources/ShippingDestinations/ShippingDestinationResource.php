<?php

namespace App\Filament\Resources\ShippingDestinations;

use App\Filament\Resources\ShippingDestinations\Pages\ManageShippingDestinations;
use App\Models\ShippingDestination;
use App\Models\User;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class ShippingDestinationResource extends Resource
{
    protected static ?string $model = ShippingDestination::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    public static function getNavigationGroup(): ?string
    {
        return __('app.settings');
    }

    public static function getModelLabel(): string
    {
        return __('shipping.shipping_destination');
    }

    public static function getPluralModelLabel(): string
    {
        return __('shipping.destinations');
    }

    public static function getFormSchema(): array
    {
        /** @var User $user */
        $user = Auth::user();

        return [
            Select::make('store_id')
                ->label(__('app.store'))
                ->relationship('store', lang_suffix('name'))
                ->required()
                ->searchable(['name_en', 'name_ar'])
                ->preload()
                ->visible(fn () => $user?->isCompanyLevel()),
            Hidden::make('store_id')
                ->default($user->store_id)
                ->visible(fn () => $user?->isStoreLevel()),
            TextInput::make('name')
                ->label(__('shipping.destination_name'))
                ->required()
                ->maxLength(255),
            TextInput::make('cost')
                ->label(__('shipping.shipping_cost'))
                ->required()
                ->numeric()
                ->default(0)
                ->minValue(0)
                ->prefix($user->company->currency_symbol ?? 'ج.م'),
            Toggle::make('is_active')
                ->label(__('shipping.is_active'))
                ->default(true),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components(self::getFormSchema());
    }

    public static function table(Table $table): Table
    {
        /** @var User $user */
        $user = Auth::user();

        return $table
            ->recordActionsColumnLabel(__('app.actions'))
            ->columns([
                TextColumn::make(lang_suffix('store.name'))
                    ->label(__('app.store'))
                    ->sortable()
                    ->badge()
                    ->color('gray')
                    ->visible(fn () => $user->isCompanyLevel()),
                TextColumn::make('name')
                    ->label(__('shipping.destination_name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('cost')
                    ->label(__('shipping.shipping_cost'))
                    ->numeric()
                    ->formatStateUsing(fn (?string $state) => $state ? $state.' '.($user->company->currency_symbol ?? 'ج.م') : '—')
                    ->badge()
                    ->color('info')
                    ->sortable(),
                ToggleColumn::make('is_active')
                    ->label(__('shipping.is_active'))
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('store_id')
                    ->label(__('app.store'))
                    ->relationship('store', lang_suffix('name'))
                    ->searchable(['name_en', 'name_ar'])
                    ->preload()
                    ->visible(fn () => $user->isCompanyLevel()),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    DeleteAction::make(),
                ])->label(__('app.actions')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageShippingDestinations::route('/'),
        ];
    }
}
