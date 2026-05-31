<?php

namespace App\Filament\Resources\InvoiceExtraItemPresets;

use App\Filament\Resources\InvoiceExtraItemPresets\Pages\ListInvoiceExtraItemPresets;
use App\Filament\Resources\InvoiceExtraItemPresets\Schemas\InvoiceExtraItemPresetForm;
use App\Filament\Resources\InvoiceExtraItemPresets\Tables\InvoiceExtraItemPresetsTable;
use App\Models\InvoiceExtraItemPreset;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class InvoiceExtraItemPresetResource extends Resource
{
    protected static ?string $model = InvoiceExtraItemPreset::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function getNavigationGroup(): ?string
    {
        return __('app.settings');
    }

    public static function getNavigationLabel(): string
    {
        return __('app.invoice_extra_item_presets');
    }

    public static function getModelLabel(): string
    {
        return __('app.invoice_extra_item_preset');
    }

    public static function getPluralModelLabel(): string
    {
        return __('app.invoice_extra_item_presets');
    }

    public static function form(Schema $schema): Schema
    {
        return InvoiceExtraItemPresetForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InvoiceExtraItemPresetsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInvoiceExtraItemPresets::route('/'),
//            'create' => CreateInvoiceExtraItemPreset::route('/create'),
//            'edit' => EditInvoiceExtraItemPreset::route('/{record}/edit'),
        ];
    }
}
