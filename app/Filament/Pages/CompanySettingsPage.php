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

    public function mount(): void
    {
        /** @var User $user */
        $user = Auth::user();
        $company = $user->company;

        $this->form->fill($company->toArray());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('app.general_information'))
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
                    ])
                    ->columns(2),

                Section::make(__('app.tax_settings'))
                    ->schema([
                        TextInput::make('vat_number')
                            ->label(__('app.tax_registration_number_t_r_n'))
                            ->maxLength(50),

                        TextInput::make('vat_rate')
                            ->label(__('app.v_a_t_rate'))
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(0.01)
                            ->suffix('%'),
                    ])
                    ->columns(2),

                Section::make(__('app.receipt_settings'))
                    ->schema([
                        Textarea::make('receipt_header')
                            ->label(__('app.receipt_header'))
                            ->rows(3)
                            ->helperText(__('app.text_displayed_at_the_top_of_receipts')),

                        Textarea::make('receipt_footer')
                            ->label(__('app.receipt_footer'))
                            ->rows(3)
                            ->helperText(__('app.text_displayed_at_the_bottom_of_receipts')),

                        Toggle::make('receipt_show_logo')
                            ->label(__('app.show_logo_on_receipt'))
                            ->default(true),
                    ])
                    ->columns(2),

                Section::make(__('app.regional_settings'))
                    ->schema([
                        Select::make('currency')
                            ->label(__('app.currency'))
                            ->options([
                                'EGP' => __('app.egyptian_pound_e_g_p'),
                                'SAR' => __('app.saudi_riyal_s_a_r'),
                                'AED' => __('app.u_a_e_dirham_a_e_d'),
                                'KWD' => __('app.kuwaiti_dinar_k_w_d'),
                            ])
                            ->required(),

                        Select::make('locale')
                            ->label(__('app.language'))
                            ->options([
                                'ar' => __('app.arabic'),
                                'en' => __('app.english'),
                            ])
                            ->required(),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        /** @var User $user */
        $user = Auth::user();
        $user->company->update($data);

        Notification::make()
            ->title(__('app.settings_saved_successfully'))
            ->success()
            ->send();
    }
}
