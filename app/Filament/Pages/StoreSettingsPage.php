<?php

namespace App\Filament\Pages;

use App\Models\Store;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class StoreSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    public static function getNavigationGroup(): ?string
    {
        return __('app.settings');
    }

    protected static ?int $navigationSort = 100;

    protected string $view = 'filament.pages.store-settings';

    public ?Store $store = null;

    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('store_settings.title');
    }

    public function getTitle(): string
    {
        return __('store_settings.title');
    }

    public static function canAccess(): bool
    {
        return Auth::user()?->store_id !== null && Auth::user()?->can('manage_store_settings');
    }

    public function mount(): void
    {
        $this->store = Auth::user()->store;

        if (! $this->store) {
            abort(404);
        }

        $this->form->fill($this->store->toArray());
    }

    public function form(Schema $schema): Schema
    {
        $company = $this->store?->company;

        return $schema
            ->statePath('data')
            ->components([
                Callout::make(__('store_settings.info'))
                    ->description(str('<li>'.__('store_settings.inheritance.callout_description').'</li> <li>'.__('store_settings.fields.compliance_note').'</li>')
                        ->toHtmlString())
                    ->icon('heroicon-m-information-circle')
                    ->info(),

                Tabs::make('Settings')
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
                                    ])
                                    ->columns(2),

                                Section::make(__('store_settings.sections.contact'))
                                    ->description(__('store_settings.sections.contact_description'))
                                    ->schema([
                                        TextInput::make('phone')
                                            ->label(__('app.phone'))
                                            ->tel()
                                            ->hintAction(
                                                Action::make('pull_phone')
                                                    ->label(__('store_settings.actions.pull_from_company'))
                                                    ->icon('heroicon-m-arrow-path')
                                                    ->action(fn (Set $set) => $set('phone', $company?->phone))
                                            )
                                            ->nullable(),

                                        TextInput::make('whatsapp_number')
                                            ->label(__('store_settings.fields.whatsapp_number'))
                                            ->tel()
                                            ->helperText(__('store_settings.helpers.whatsapp_number'))
                                            ->hintAction(
                                                Action::make('pull_whatsapp')
                                                    ->label(__('store_settings.actions.pull_from_company'))
                                                    ->icon('heroicon-m-arrow-path')
                                                    ->tooltip(__('store_settings.actions.pull_tooltip'))
                                                    ->action(fn (Set $set) => $set('whatsapp_number', $company?->whatsapp_number))
                                            )
                                            ->nullable(),

                                        Textarea::make('address')
                                            ->label(__('app.address'))
                                            ->rows(3)
                                            ->hintAction(
                                                Action::make('pull_address')
                                                    ->label(__('store_settings.actions.pull_from_company'))
                                                    ->icon('heroicon-m-arrow-path')
                                                    ->action(fn (Set $set) => $set('address', $company?->address))
                                            )
                                            ->nullable(),
                                    ])
                                    ->columns(2),
                            ]),

                        Tab::make(__('app.working_hours'))
                            ->schema([
                                Section::make(__('store_settings.sections.working_hours'))
                                    ->description(__('store_settings.sections.working_hours_description'))
                                    ->schema([
                                        Repeater::make('working_hours')
                                            ->label(__('store_settings.sections.working_hours'))
                                            /* ->hintAction(
                                                Action::make('pull_from_company')
                                                    ->label(__('store_settings.actions.pull_from_company'))
                                                    ->icon('heroicon-m-arrow-path')
                                                    ->action(function (Set $set) {
                                                        $company = Auth::user()->company;

                                                        if ($company?->working_hours) {
                                                            $set('working_hours', $company->working_hours);

                                                            Notification::make()
                                                                ->title(__('store_settings.notifications.pulled_from_company'))
                                                                ->success()
                                                                ->send();
                                                        } else {
                                                            Notification::make()
                                                                ->title(__('store_settings.notifications.no_company_hours'))
                                                                ->warning()
                                                                ->send();
                                                        }
                                                    }),
                                            ) */
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
                                            ->hintAction(
                                                Action::make('pull_header')
                                                    ->label(__('store_settings.actions.pull_from_company'))
                                                    ->icon('heroicon-m-arrow-path')
                                                    ->tooltip(__('store_settings.actions.pull_tooltip'))
                                                    ->action(fn (Set $set) => $set('receipt_header', $company?->receipt_header))
                                            )
                                            ->nullable(),

                                        Textarea::make('receipt_footer')
                                            ->label(__('store_settings.fields.receipt_footer'))
                                            ->helperText(__('store_settings.helpers.receipt_footer'))
                                            ->rows(3)
                                            ->hintAction(
                                                Action::make('pull_footer')
                                                    ->label(__('store_settings.actions.pull_from_company'))
                                                    ->icon('heroicon-m-arrow-path')
                                                    ->tooltip(__('store_settings.actions.pull_tooltip'))
                                                    ->action(fn (Set $set) => $set('receipt_footer', $company?->receipt_footer))
                                            )
                                            ->nullable(),

                                        Select::make('receipt_show_logo')
                                            ->label(__('store_settings.fields.show_logo'))
                                            ->helperText(__('store_settings.helpers.show_logo'))
                                            ->options([
                                                '1' => __('store_settings.inheritance.show'),
                                                '0' => __('store_settings.inheritance.hide'),
                                            ])
                                            ->required()
                                            ->hintAction(
                                                Action::make('pull_logo')
                                                    ->label(__('store_settings.actions.pull_from_company'))
                                                    ->icon('heroicon-m-arrow-path')
                                                    ->action(fn (Set $set) => $set('receipt_show_logo', $company?->receipt_show_logo ? '1' : '0'))
                                            )
                                            ->selectablePlaceholder(false),

                                        Select::make('receipt_show_vat_number')
                                            ->label(__('store_settings.fields.show_vat_number'))
                                            ->helperText(__('store_settings.helpers.show_vat_number'))
                                            ->options([
                                                '1' => __('store_settings.inheritance.show'),
                                                '0' => __('store_settings.inheritance.hide'),
                                            ])
                                            ->required()
                                            ->hintAction(
                                                Action::make('pull_vat')
                                                    ->label(__('store_settings.actions.pull_from_company'))
                                                    ->icon('heroicon-m-arrow-path')
                                                    ->action(fn (Set $set) => $set('receipt_show_vat_number', $company?->receipt_show_vat_number ? '1' : '0'))
                                            )
                                            ->selectablePlaceholder(false),

                                        Select::make('receipt_show_address')
                                            ->label(__('store_settings.fields.show_address'))
                                            ->helperText(__('store_settings.helpers.show_address'))
                                            ->options([
                                                '1' => __('store_settings.inheritance.show'),
                                                '0' => __('store_settings.inheritance.hide'),
                                            ])
                                            ->required()
                                            ->hintAction(
                                                Action::make('pull_address_toggle')
                                                    ->label(__('store_settings.actions.pull_from_company'))
                                                    ->icon('heroicon-m-arrow-path')
                                                    ->action(fn (Set $set) => $set('receipt_show_address', $company?->receipt_show_address ? '1' : '0'))
                                            )
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
                                            ->hintAction(
                                                Action::make('pull_locale')
                                                    ->label(__('store_settings.actions.pull_from_company'))
                                                    ->icon('heroicon-m-arrow-path')
                                                    ->action(fn (Set $set) => $set('locale', $company?->locale))
                                            )
                                            ->selectablePlaceholder(false),
                                    ])
                                    ->columns(2),
                            ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        try {
            Auth::user()->store->update($data);

            Notification::make()
                ->title(__('store_settings.settings_updated_successfully'))
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title(__('store_settings.error_updating_settings'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
