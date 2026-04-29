<?php

namespace App\Filament\Resources\Stores\Schemas;

use App\Models\Store;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class StoreSchema
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Callout::make(__('store_settings.info'))
                    ->description(str('<li>'.__('store_settings.inheritance.callout_description').'</li> <li>'.__('store_settings.fields.compliance_note').'</li>')
                        ->toHtmlString())
                    ->icon('heroicon-m-information-circle')
                    ->info()
                    ->columnSpanFull(),

                Tabs::make('Settings')
                    ->persistTabInQueryString()

                    ->tabs([
                        Tab::make(__('app.general_information'))
                            ->schema([
                                Section::make(__('store_settings.sections.general'))
                                    ->description(__('store_settings.sections.general_description'))
                                    ->schema([
                                        TextInput::make('name_en')
                                            ->label(__('store_settings.fields.name_en'))
                                            ->required()
                                            ->maxLength(255),

                                        TextInput::make('name_ar')
                                            ->label(__('store_settings.fields.name_ar'))
                                            ->maxLength(255),

                                        TextInput::make('email')
                                            ->label(__('store_settings.fields.email'))
                                            ->email()
                                            ->maxLength(255),

                                        Toggle::make('is_active')
                                            ->label(__('app.active'))
                                            ->default(true),
                                    ])
                                    ->columns(2),

                                Section::make(__('store_settings.sections.contact'))
                                    ->description(__('store_settings.sections.contact_description'))
                                    ->schema([
                                        TextInput::make('phone')
                                            ->label(__('app.phone'))
                                            ->tel()
                                            ->hintAction(self::getPullAction('phone', 'phone'))
                                            ->nullable(),

                                        TextInput::make('whatsapp_number')
                                            ->label(__('store_settings.fields.whatsapp_number'))
                                            ->tel()
                                            ->helperText(__('store_settings.helpers.whatsapp_number'))
                                            ->hintAction(self::getPullAction('whatsapp_number', 'whatsapp_number'))
                                            ->nullable(),

                                        Textarea::make('address')
                                            ->label(__('app.address'))
                                            ->rows(3)
                                            ->hintAction(self::getPullAction('address', 'address'))
                                            ->nullable()
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(2),

                                Section::make(__('app.store_images'))
                                    ->schema([
                                        SpatieMediaLibraryFileUpload::make('images')
                                            ->label(__('app.store_images'))
                                            ->collection('images')
                                            ->multiple()
                                            ->reorderable()
                                            ->imageEditor()
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        Tab::make(__('app.working_hours'))
                            ->schema([
                                Section::make(__('store_settings.sections.working_hours'))
                                    ->description(__('store_settings.sections.working_hours_description'))
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
                                                            ->label(__('store_settings.fields.from'))
                                                            ->native(false)
                                                            ->displayFormat('h:i A')
                                                            ->format('H:i')
                                                            ->seconds(false)
                                                            ->required(),
                                                        TimePicker::make('to')
                                                            ->label(__('store_settings.fields.to'))
                                                            ->native(false)
                                                            ->displayFormat('h:i A')
                                                            ->format('H:i')
                                                            ->seconds(false)
                                                            ->required(),
                                                    ]),
                                            ])
                                            /*->hintAction(
                                                Action::make('pull_from_company')
                                                    ->label(__('store_settings.actions.pull_from_company'))
                                                    ->icon('heroicon-m-arrow-path')
                                                    ->action(function (Repeater $component, $record) {
                                                        $company = $record?->company ?? Auth::user()->company;

                                                        if ($company && ! empty($company->working_hours)) {
                                                            $component->state($company->working_hours);

                                                            Notification::make()
                                                                ->title(__('store_settings.notifications.pulled_from_company'))
                                                                ->success()
                                                                ->send();
                                                        } else {
                                                            Notification::make()
                                                                ->title(__('store_settings.notifications.no_company_setting'))
                                                                ->warning()
                                                                ->send();
                                                        }
                                                    }),
                                            )*/
                                            ->itemLabel(fn (array $state): ?string => isset($state['day']) ? __('app.days.'.$state['day']) : null)
                                            ->addActionLabel(__('store_settings.actions.add_day'))
                                            ->reorderable(false)
                                            ->collapsible()
                                            ->defaultItems(1)
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        Tab::make(__('app.receipt_settings'))
                            ->schema([
                                Section::make(__('store_settings.sections.receipt'))
                                    ->description(__('store_settings.sections.receipt_description'))
                                    ->schema([
                                        Textarea::make('receipt_header')
                                            ->label(__('store_settings.fields.receipt_header'))
                                            ->helperText(__('store_settings.helpers.receipt_header'))
                                            ->rows(3)
                                            ->hintAction(self::getPullAction('receipt_header', 'receipt_header'))
                                            ->nullable(),

                                        Textarea::make('receipt_footer')
                                            ->label(__('store_settings.fields.receipt_footer'))
                                            ->helperText(__('store_settings.helpers.receipt_footer'))
                                            ->rows(3)
                                            ->hintAction(self::getPullAction('receipt_footer', 'receipt_footer'))
                                            ->nullable(),

                                        Select::make('receipt_show_logo')
                                            ->label(__('store_settings.fields.show_logo'))
                                            ->helperText(__('store_settings.helpers.show_logo'))
                                            ->options([
                                                '1' => __('store_settings.inheritance.show'),
                                                '0' => __('store_settings.inheritance.hide'),
                                            ])
                                            ->required()
                                            ->hintAction(self::getPullAction('receipt_show_logo', 'receipt_show_logo', true))
                                            ->selectablePlaceholder(false),

                                        Select::make('receipt_show_vat_number')
                                            ->label(__('store_settings.fields.show_vat_number'))
                                            ->helperText(__('store_settings.helpers.show_vat_number'))
                                            ->options([
                                                '1' => __('store_settings.inheritance.show'),
                                                '0' => __('store_settings.inheritance.hide'),
                                            ])
                                            ->required()
                                            ->hintAction(self::getPullAction('receipt_show_vat_number', 'receipt_show_vat_number', true))
                                            ->selectablePlaceholder(false),

                                        Select::make('receipt_show_address')
                                            ->label(__('store_settings.fields.show_address'))
                                            ->helperText(__('store_settings.helpers.show_address'))
                                            ->options([
                                                '1' => __('store_settings.inheritance.show'),
                                                '0' => __('store_settings.inheritance.hide'),
                                            ])
                                            ->required()
                                            ->hintAction(self::getPullAction('receipt_show_address', 'receipt_show_address', true))
                                            ->selectablePlaceholder(false),
                                    ])
                                    ->columns(2),
                            ]),

                        Tab::make(__('app.localization'))
                            ->schema([
                                Section::make(__('store_settings.sections.regional'))
                                    ->description(__('store_settings.sections.regional_description'))
                                    ->schema([
                                        Select::make('timezone')
                                            ->label(__('store_settings.fields.timezone'))
                                            ->helperText(__('store_settings.helpers.timezone'))
                                            ->options(collect(timezone_identifiers_list())->mapWithKeys(fn ($tz) => [$tz => $tz]))
                                            ->searchable()
                                            ->default('Africa/Cairo')
                                            ->required()
                                            ->disabled()
                                            ->selectablePlaceholder(false),

                                        Select::make('locale')
                                            ->label(__('store_settings.fields.locale'))
                                            ->helperText(__('store_settings.helpers.locale'))
                                            ->options([
                                                'en' => 'English',
                                                'ar' => 'العربية',
                                            ])
                                            ->required()
                                            ->hintAction(self::getPullAction('locale', 'locale'))
                                            ->selectablePlaceholder(false),
                                    ])
                                    ->columns(2),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    protected static function getPullAction(string $field, string $companyField, bool $isBoolean = false): Action
    {
        return Action::make("pull_{$field}")
            ->label(__('store_settings.actions.pull_from_company'))
            ->icon('heroicon-m-arrow-path')
            ->tooltip(__('store_settings.actions.pull_tooltip'))
            ->action(function (Set $set, $record) use ($field, $companyField, $isBoolean) {
                /** @var Store|null $record */
                $company = $record?->company ?? Auth::user()->company;

                if ($company) {
                    $value = $company->{$companyField};

                    if ($value === null || $value === '') {
                        Notification::make()
                            ->title(__('store_settings.notifications.no_company_setting'))
                            ->warning()
                            ->send();

                        return;
                    }

                    if ($isBoolean) {
                        $value = $value ? '1' : '0';
                    }

                    $set($field, $value);

                    Notification::make()
                        ->title(__('store_settings.notifications.pulled_from_company'))
                        ->success()
                        ->send();
                } else {
                    Notification::make()
                        ->title(__('store_settings.notifications.no_company_setting'))
                        ->warning()
                        ->send();
                }
            });
    }
}
