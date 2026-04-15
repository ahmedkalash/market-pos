<?php

namespace App\Filament\Pages;

use App\Enums\CurrencyPosition;
use App\Enums\RoundingRule;
use App\Models\User;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
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
        return __('company_settings.title');
    }

    public function getTitle(): string
    {
        return __('company_settings.title');
    }

    /**
     * Authorization Control.
     * Business Rule: Only Company-Level users with 'update_setting' permission can access this page.
     * Store-level users are strictly forbidden from viewing company-wide settings.
     */
    public static function canAccess(): bool
    {
        /** @var User $user */
        $user = Auth::user();

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
                                            ->helperText(__('company_settings.name_en_helper'))
                                            ->required()
                                            ->maxLength(255),

                                        TextInput::make('name_ar')
                                            ->label(__('app.company_name_arabic'))
                                            ->helperText(__('company_settings.name_ar_helper'))
                                            ->maxLength(255),

                                        FileUpload::make('logo')
                                            ->label(__('app.logo'))
                                            ->helperText(__('company_settings.logo_helper'))
                                            ->image()
                                            ->directory('logos')
                                            ->disk('public'),

                                        TextInput::make('email')
                                            ->label(__('app.email'))
                                            ->helperText(__('company_settings.email_helper'))
                                            ->email()
                                            ->nullable(),

                                        TextInput::make('phone')
                                            ->label(__('app.phone'))
                                            ->helperText(__('company_settings.phone_helper'))
                                            ->tel()
                                            ->nullable(),

                                        Textarea::make('address')
                                            ->label(__('app.address'))
                                            ->helperText(__('company_settings.address_helper'))
                                            ->rows(3),

                                        TextInput::make('whatsapp_number')
                                            ->label(__('app.whatsapp_number'))
                                            ->tel()
                                            ->helperText(__('company_settings.whatsapp_number_helper'))
                                            ->nullable(),
                                    ])
                                    ->columns(2),
                            ]),

                        // tab: Scheduling
                        Tab::make(__('app.working_hours'))
                            ->schema([
                                Section::make(__('company_settings.sections.working_hours'))
                                    ->description(__('company_settings.sections.working_hours_description'))
                                    ->schema([
                                        Repeater::make('working_hours')
                                            ->label(__('store_settings.sections.working_hours'))
                                            ->schema([
                                                Grid::make(3)
                                                    ->schema([
                                                        Select::make('day')
                                                            ->label(__('store_settings.fields.day'))
                                                            ->options([
                                                                'saturday' => __('app.days.saturday'),
                                                                'sunday' => __('app.days.sunday'),
                                                                'monday' => __('app.days.monday'),
                                                                'tuesday' => __('app.days.tuesday'),
                                                                'wednesday' => __('app.days.wednesday'),
                                                                'thursday' => __('app.days.thursday'),
                                                                'friday' => __('app.days.friday'),
                                                            ])
                                                            ->required(),
                                                        TimePicker::make('from')
                                                            ->label(__('company_settings.fields.from'))
                                                            ->native(false)
                                                            ->displayFormat('h:i A')
                                                            ->format('H:i')
                                                            ->seconds(false)
                                                            ->required(),
                                                        TimePicker::make('to')
                                                            ->label(__('company_settings.fields.to'))
                                                            ->native(false)
                                                            ->displayFormat('h:i A')
                                                            ->format('H:i')
                                                            ->seconds(false)
                                                            ->required(),
                                                    ]),
                                            ])
                                            ->itemLabel(fn (array $state): ?string => isset($state['day']) ? __('app.days.'.$state['day']) : null)
                                            ->addActionLabel(__('store_settings.actions.add_day'))
                                            ->reorderable(false)
                                            ->collapsible()
                                            ->defaultItems(1)
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        // tab 2: Localization
                        Tab::make(__('app.localization'))
                            ->schema([
                                Section::make()
                                    ->schema([
                                        Select::make('timezone')
                                            ->label(__('app.timezone'))
                                            ->helperText(__('company_settings.timezone_helper'))
                                            ->options(collect(timezone_identifiers_list())->mapWithKeys(fn ($tz) => [$tz => $tz]))
                                            ->searchable()
                                            ->disabled(),

                                        Select::make('locale')
                                            ->label(__('app.default_language'))
                                            ->helperText(__('company_settings.locale_helper'))
                                            ->options(__('languages'))
                                            ->required(),

                                        Select::make('date_format')
                                            ->label(__('app.date_format'))
                                            ->helperText(__('company_settings.date_format_helper'))
                                            ->options(__('date_time_formats.date_formats'))
                                            ->required(),

                                        Select::make('time_format')
                                            ->label(__('app.time_format'))
                                            ->helperText(__('company_settings.time_format_helper'))
                                            ->options(__('date_time_formats.time_formats'))
                                            ->required(),
                                    ])
                                    ->columns(2),
                            ]),

                        // tab 3: Financials
                        Tab::make(__('app.financials'))
                            ->schema([
                                Section::make(__('company_settings.financial_preview_title'))
                                    ->description(__('company_settings.financial_preview_footer'))
                                    ->schema([
                                        TextEntry::make('standard_preview')
                                            ->label(__('company_settings.preview_label_standard'))
                                            ->state(fn (Get $get) => $this->formatPreview(1234.5678, $get))
                                            ->badge()
                                            ->color('info'),

                                        TextEntry::make('small_preview')
                                            ->label(__('company_settings.preview_label_small'))
                                            ->state(fn (Get $get) => $this->formatPreview(5.31, $get))
                                            ->badge()
                                            ->color('info'),

                                        TextEntry::make('psychological_preview')
                                            ->label(__('company_settings.preview_label_psychological'))
                                            ->state(fn (Get $get) => $this->formatPreview(0.99, $get))
                                            ->badge()
                                            ->color('info'),
                                    ])
                                    ->columns(3)
                                    ->compact(),

                                Section::make()
                                    ->schema([
                                        TextInput::make('currency')
                                            ->label(__('app.currency_code'))
                                            ->helperText(__('company_settings.currency_helper'))
                                            ->required()
                                            ->placeholder('EGP')
                                            ->disabled(),

                                        TextInput::make('currency_symbol')
                                            ->label(__('app.currency_symbol'))
                                            ->helperText(__('company_settings.currency_symbol_helper'))
                                            ->required()
                                            ->placeholder('ج.م')
                                            ->live(),

                                        Select::make('currency_position')
                                            ->label(__('app.currency_symbol_position'))
                                            ->helperText(__('company_settings.currency_position_helper'))
                                            ->options([
                                                CurrencyPosition::LEFT->value => __('app.before_amount'),
                                                CurrencyPosition::RIGHT->value => __('app.after_amount'),
                                            ])
                                            ->required()
                                            ->live(),

                                        Select::make('rounding_rule')
                                            ->label(__('app.cash_rounding_rule'))
                                            ->options([
                                                RoundingRule::NONE->value => __('app.no_rounding'),
                                                RoundingRule::NEAREST_025->value => __('app.nearest_025'),
                                                RoundingRule::NEAREST_050->value => __('app.nearest_050'),
                                                RoundingRule::NEAREST_100->value => __('app.nearest_100'),
                                            ])
                                            ->helperText(__('app.rounding_explanation_egypt'))
                                            ->required()
                                            ->live(),

                                        Select::make('thousand_separator')
                                            ->label(__('app.thousand_separator'))
                                            ->helperText(__('company_settings.thousand_separator_helper'))
                                            ->options([
                                                ',' => ',',
                                                '.' => '.',
                                            ])
                                            ->default(',')
                                            ->live(),

                                        Select::make('decimal_separator')
                                            ->label(__('app.decimal_separator'))
                                            ->helperText(__('company_settings.decimal_separator_helper'))
                                            ->options([
                                                ',' => ',',
                                                '.' => '.',
                                            ])
                                            ->default('.')
                                            ->live(),

                                        TextInput::make('decimal_precision')
                                            ->label(__('app.decimal_precision'))
                                            ->helperText(__('company_settings.decimal_precision_helper'))
                                            ->numeric()
                                            ->minValue(0)
                                            ->maxValue(4)
                                            ->default(2)
                                            ->live(),
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
                                            ->helperText(__('company_settings.vat_number_helper'))
                                            ->maxLength(50)
                                            ->nullable(),

                                        TextInput::make('vat_rate')
                                            ->label(__('app.default_v_a_t_rate'))
                                            ->helperText(__('company_settings.vat_rate_helper'))
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
                                        Textarea::make('receipt_header')
                                            ->label(__('app.receipt_header'))
                                            ->helperText(__('company_settings.receipt_header_helper'))
                                            ->rows(3),

                                        Textarea::make('receipt_footer')
                                            ->label(__('app.receipt_footer'))
                                            ->helperText(__('company_settings.receipt_footer_helper'))
                                            ->rows(3),

                                        TextInput::make('invoice_prefix')
                                            ->label(__('app.invoice_prefix'))
                                            ->helperText(__('company_settings.invoice_prefix_helper'))
                                            ->required()
                                            ->default('INV-'),

                                        Toggle::make('receipt_show_logo')
                                            ->label(__('app.show_logo_on_receipt'))
                                            ->helperText(__('company_settings.show_logo_helper'))
                                            ->default(true),

                                        Toggle::make('receipt_show_vat_number')
                                            ->label(__('app.show_tax_number_on_receipt'))
                                            ->helperText(__('company_settings.show_tax_number_helper'))
                                            ->default(true),

                                        Toggle::make('receipt_show_address')
                                            ->label(__('app.show_address_on_receipt'))
                                            ->helperText(__('company_settings.show_address_helper'))
                                            ->default(true),
                                    ])
                                    ->columns(2),
                            ]),
                    ]),
            ])
            ->statePath('data');
    }

    /**
     * Format a preview amount based on the current form state.
     */
    public function formatPreview(float $amount, Get $get): string
    {
        $symbol = $get('currency_symbol') ?? 'EGP';
        $position = $get('currency_position') ?? CurrencyPosition::LEFT->value;
        $precision = (int) ($get('decimal_precision') ?? 2);
        $thousandSep = $get('thousand_separator') ?? ',';
        $decimalSep = $get('decimal_separator') ?? '.';
        $rounding = $get('rounding_rule') ?? RoundingRule::NONE->value;

        // 1. Apply rounding rule
        switch ($rounding) {
            case RoundingRule::NEAREST_025->value:
                $amount = round($amount * 4) / 4;
                break;
            case RoundingRule::NEAREST_050->value:
                $amount = round($amount * 2) / 2;
                break;
            case RoundingRule::NEAREST_100->value:
                $amount = round($amount);
                break;
        }

        // 2. Format
        $formatted = number_format($amount, $precision, $decimalSep, $thousandSep);

        // 3. Position
        return $position === CurrencyPosition::LEFT->value
            ? "{$symbol} {$formatted}"
            : "{$formatted} {$symbol}";
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
                ->title(__('company_settings.settings_saved_successfully'))
                ->success()
                ->send();
        }
    }
}
