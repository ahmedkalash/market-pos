<?php

namespace App\Filament\Resources\InvoiceExtraItemPresets\Schemas;

use App\Enums\ExtraItemActionType;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class InvoiceExtraItemPresetForm
{
    public static function configure(Schema $schema): Schema
    {
        /** @var User $user */
        $user = Auth::user()->load('company');

        return $schema
            ->components([
                Section::make(__('app.details'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('app.name'))
                            ->required()
                            ->maxLength(255),
                        Select::make('action_type')
                            ->label(__('extra_item.action_type'))
                            ->options(ExtraItemActionType::class)
                            ->required(),
                        TextInput::make('amount')
                            ->label(__('app.amount'))
                            ->required()
                            ->prefix($user->company->currency_symbol ?? 'ج.م')
                            ->numeric()
                            ->minValue(0),
                        Select::make('invoice_type')
                            ->label(__('extra_item.invoice_type'))
                            ->options([
                                'sale_invoice' => __('app.sale_invoice'),
                                'sale_return' => __('app.sale_return'),
                                'purchase_invoice' => __('app.purchase_invoice'),
                                'purchase_return' => __('app.purchase_return'),
                            ])
                            ->required(),
                        Toggle::make('is_refundable')
                            ->label(__('extra_item.is_refundable'))
                            ->default(false),
                        Toggle::make('is_active')
                            ->label(__('app.is_active'))
                            ->default(true),
                        Textarea::make('notes')
                            ->label(__('app.notes'))
                            ->maxLength(65535)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }
}
