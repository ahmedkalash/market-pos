<?php

namespace App\Filament\Resources\Vendors\Schemas;

use App\Models\User;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class VendorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make([
                    Section::make(__('vendor.vendor_information'))
                        ->description(__('vendor.vendor_information_helper'))
                        ->icon('heroicon-o-building-office')
                        ->schema([
                            TextInput::make('name')
                                ->label(__('vendor.name'))
                                ->placeholder(__('vendor.name_placeholder'))
                                ->required()
                                ->maxLength(255)
                                ->unique(
                                    table: 'vendors',
                                    column: 'name',
                                    ignoreRecord: true,
                                    modifyRuleUsing: function (Unique $rule) {
                                        /** @var User $user */
                                        $user = auth()->user();

                                        return $rule->where('company_id', $user->company_id);
                                    }
                                ),
                            TextInput::make('tax_number')
                                ->label(__('vendor.tax_number'))
                                ->placeholder(__('vendor.tax_number_placeholder'))
                                ->maxLength(50),
                        ])->columns(2),
                    Section::make(__('vendor.settings'))
                        ->schema([
                            Toggle::make('is_active')
                                ->label(__('vendor.is_active'))
                                ->helperText(__('vendor.is_active_helper'))
                                ->default(true),
                        ]),
                ]),

                Section::make(__('vendor.contact_details'))
                    ->description(__('vendor.contact_details_helper'))
                    ->icon('heroicon-o-phone')
                    ->schema([
                        TextInput::make('email')
                            ->label(__('vendor.email'))
                            ->email()
                            ->placeholder(__('vendor.email_placeholder'))
                            ->maxLength(255)
                            ->unique(
                                table: 'vendors',
                                column: 'email',
                                ignoreRecord: true,
                                modifyRuleUsing: function (Unique $rule) {
                                    /** @var User $user */
                                    $user = auth()->user();

                                    return $rule->where('company_id', $user->company_id)->whereNotNull('email');
                                }
                            ),
                        TextInput::make('phone')
                            ->label(__('vendor.phone'))
                            ->tel()
                            ->placeholder(__('vendor.phone_placeholder'))
                            ->unique(
                                table: 'vendors',
                                column: 'phone',
                                ignoreRecord: true,
                                modifyRuleUsing: function (Unique $rule) {
                                    /** @var User $user */
                                    $user = auth()->user();

                                    return $rule->where('company_id', $user->company_id)->whereNotNull('phone');
                                }
                            ),
                        Textarea::make('address')
                            ->label(__('vendor.address'))
                            ->placeholder(__('vendor.address_placeholder'))
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }
}
