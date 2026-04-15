<?php

namespace App\Filament\Pages;

use App\Models\User;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class CompanySettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    public static function getNavigationGroup(): ?string
    {
        return __('app.settings');
    }

    protected static ?int $navigationSort = 99;

    protected string $view = 'filament.pages.company-settings';

    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('app.company_settings');
    }

    public function getTitle(): string
    {
        return __('app.company_settings');
    }

    /**
     * Authorization Control.
     * Business Rule: Only Company-Level users with 'update_setting' permission can access this page.
     * Store-level users are strictly forbidden from viewing company-wide settings.
     */
    public static function canAccess(): bool
    {
        /** @var User $user */
        $user = auth()->user();

        return $user &&
               $user->isCompanyLevel() &&
               $user->hasPermissionTo('update_setting');
    }

    public function mount(): void
    {
        /** @var User $user */
        $user = Auth::user();
        $company = $user->company;

        if (! $company) {
            abort(404);
        }

        $this->form->fill($company->toArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Settings')
                    ->tabs([
                        // tab 1: General
                        Tab::make(__('app.general_information'))
                            ->schema([
                                Section::make()
                                    ->schema([
                                        TextInput::make('name_en')
                                            ->label(__('app.company_name_english'))
                                            ->required()
                                            ->maxLength(255),

                                        TextInput::make('name_ar')
                                            ->label(__('app.company_name_arabic'))
                                            ->maxLength(255),

                                        FileUpload::make('logo')
                                            ->label(__('app.logo'))
                                            ->image()
                                            ->directory('logos')
                                            ->disk('public'),

                                        TextInput::make('email')
                                            ->label(__('app.email'))
                                            ->email(),

                                        TextInput::make('phone')
                                            ->label(__('app.phone'))
                                            ->tel(),

                                        Textarea::make('address')
                                            ->label(__('app.address'))
                                            ->rows(3),

                                        TextInput::make('whatsapp_number')
                                            ->label(__('app.whatsapp_number'))
                                            ->tel()
                                            ->helperText(__('app.shown_on_receipt_for_customer_support')),
                                    ])
                                    ->columns(2),
                            ]),

                        // tab 2: Localization
                        Tab::make(__('app.localization'))
                            ->schema([
                                Section::make()
                                    ->schema([
                                        Select::make('timezone')
                                            ->label(__('app.timezone'))
                                            ->options(collect(timezone_identifiers_list())->mapWithKeys(fn ($tz) => [$tz => $tz]))
                                            ->searchable()
                                            ->required(),

                                        Select::make('locale')
                                            ->label(__('app.default_language'))
                                            ->options(__('languages'))
                                            ->required(),

                                        Select::make('date_format')
                                            ->label(__('app.date_format'))
                                            ->options(__('date_time_formats.date_formats'))
                                            ->required(),

                                        Select::make('time_format')
                                            ->label(__('app.time_format'))
                                            ->options(__('date_time_formats.time_formats'))
                                            ->required(),
                                    ])
                                    ->columns(2),
                            ]),

                        // tab 3: Financials
                        Tab::make(__('app.financials'))
                            ->schema([
                                Section::make()
                                    ->schema([
                                        TextInput::make('currency')
                                            ->label(__('app.currency_code'))
                                            ->required()
                                            ->placeholder('EGP')
                                            ->disabled(),

                                        TextInput::make('currency_symbol')
                                            ->label(__('app.currency_symbol'))
                                            ->required()
                                            ->placeholder('ج.م'),


                                        Select::make('currency_position')
                                            ->label(__('app.currency_symbol_position'))
                                            ->options([
                                                \App\Enums\CurrencyPosition::LEFT->value => __('app.before_amount'),
                                                \App\Enums\CurrencyPosition::RIGHT->value => __('app.after_amount'),
                                            ])
                                            ->required(),

                                        Select::make('rounding_rule')
                                            ->label(__('app.cash_rounding_rule'))
                                            ->options([
                                                \App\Enums\RoundingRule::NONE->value => __('app.no_rounding'),
                                                \App\Enums\RoundingRule::NEAREST_025->value => __('app.nearest_025'),
                                                \App\Enums\RoundingRule::NEAREST_050->value => __('app.nearest_050'),
                                                \App\Enums\RoundingRule::NEAREST_100->value => __('app.nearest_100'),
                                            ])
                                            ->helperText(__('app.rounding_explanation_egypt'))
                                            ->required(),

                                        TextInput::make('thousand_separator')
                                            ->label(__('app.thousand_separator'))
                                            ->maxLength(1)
                                            ->default(','),

                                        TextInput::make('decimal_separator')
                                            ->label(__('app.decimal_separator'))
                                            ->maxLength(1)
                                            ->default('.'),

                                        TextInput::make('decimal_precision')
                                            ->label(__('app.decimal_precision'))
                                            ->numeric()
                                            ->minValue(0)
                                            ->maxValue(4)
                                            ->default(2),
                                    ])
                                    ->columns(2),
                            ]),

                        // tab 4: Taxation
                        Tab::make(__('app.taxation'))
                            ->schema([
                                Section::make()
                                    ->schema([
                                        TextInput::make('tax_label')
                                            ->label(__('app.tax_label'))
                                            ->helperText(__('app.e_g_vat_tax_custom_string'))
                                            ->required()
                                            ->default('VAT'),

                                        TextInput::make('vat_number')
                                            ->label(__('app.tax_registration_number_t_r_n'))
                                            ->maxLength(50),

                                        TextInput::make('vat_rate')
                                            ->label(__('app.default_v_a_t_rate'))
                                            ->numeric()
                                            ->minValue(0)
                                            ->maxValue(100)
                                            ->step(0.01)
                                            ->suffix('%'),

                                        Toggle::make('tax_is_inclusive')
                                            ->label(__('app.prices_include_tax'))
                                            ->helperText(__('app.tax_inclusive_explanation'))
                                            ->default(false),
                                    ])
                                    ->columns(2),
                            ]),

                        // tab 5: Receipt & Invoicing
                        Tab::make(__('app.receipt_invoicing'))
                            ->schema([
                                Section::make()
                                    ->schema([
                                        TextInput::make('invoice_prefix')
                                            ->label(__('app.invoice_prefix'))
                                            ->required()
                                            ->default('INV-'),

                                        TextInput::make('invoice_next_number')
                                            ->label(__('app.next_invoice_number'))
                                            ->numeric()
                                            ->required()
                                            ->default(1),

                                        Textarea::make('receipt_header')
                                            ->label(__('app.receipt_header'))
                                            ->rows(3),

                                        Textarea::make('receipt_footer')
                                            ->label(__('app.receipt_footer'))
                                            ->rows(3),

                                        Toggle::make('receipt_show_logo')
                                            ->label(__('app.show_logo_on_receipt'))
                                            ->default(true),

                                        Toggle::make('receipt_show_vat_number')
                                            ->label(__('app.show_tax_number_on_receipt'))
                                            ->default(true),

                                        Toggle::make('receipt_show_address')
                                            ->label(__('app.show_address_on_receipt'))
                                            ->default(true),

//                                        Toggle::make('enable_zatca_qr')
//                                            ->label(__('app.enable_e_invoicing_q_r_zatca'))
//                                            ->helperText(__('app.zatca_future_proofing_toggle'))
//                                            ->default(false),
                                    ])
                                    ->columns(2),
                            ]),
                    ]),
            ])
            ->statePath('data');
    }

    /**
     * Persist the settings to the database.
     */
    public function save(): void
    {
        $data = $this->form->getState();

        /** @var User $user */
        $user = Auth::user();

        if ($user->company) {
            $user->company->update($data);

            Notification::make()
                ->title(__('app.settings_saved_successfully'))
                ->success()
                ->send();
        }
    }
}
