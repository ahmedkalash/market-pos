<?php

namespace App\Filament\Pages\Auth;

use App\Actions\CreateDefaultCompanyRolesAction;
use App\Enums\Roles;
use App\Models\Company;
use App\Models\Plan;
use App\Models\User;
use App\Services\OtpService;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use DanHarrin\LivewireRateLimiting\WithRateLimiting;
use Filament\Actions\Action;
use Filament\Auth\Pages\Register;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class RegisterCompany extends Register
{
    use WithRateLimiting {
        rateLimit as traitRateLimit;
    }

    protected function rateLimit($maxAttempts, $decaySeconds = 60, $method = null, $component = null): void
    {
        if ($maxAttempts === 2) {
            $maxAttempts = 5;
        }

        $this->traitRateLimit($maxAttempts, $decaySeconds, $method, $component);
    }

    protected function isRegisterRateLimited(string $email): bool
    {
        if (blank($email)) {
            return false;
        }

        $rateLimitingKey = 'filament-register:'.sha1($email);

        if (RateLimiter::tooManyAttempts($rateLimitingKey, maxAttempts: 5)) {
            $this->getRateLimitedNotification(new TooManyRequestsException(
                static::class,
                'register',
                request()->ip(),
                RateLimiter::availableIn($rateLimitingKey),
            ))?->send();

            return true;
        }

        RateLimiter::hit($rateLimitingKey);

        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Wizard::make([
                    Step::make(__('app.company_info'))
                        ->schema([
                            TextInput::make('company_name_ar')
                                ->label(__('app.company_name_ar'))
                                ->required()
                                ->maxLength(255),
                            TextInput::make('company_name_en')
                                ->label(__('app.company_name_en'))
                                ->required()
                                ->maxLength(255),
                            TextInput::make('company_phone')
                                ->label(__('app.company_phone'))
                                ->tel()
                                ->required(),
                        ]),
                    Step::make(__('app.admin_account'))
                        ->schema([
                            $this->getNameFormComponent(),
                            $this->getEmailFormComponent(),
                            $this->getPasswordFormComponent(),
                            $this->getPasswordConfirmationFormComponent(),
                        ])
                        ->afterValidation($this->sendOtp(...)),
                    Step::make(__('app.email_verification'))
                        ->schema([
                            TextEntry::make('otp_instruction')
                                ->hiddenLabel()
                                ->state(__('app.otp_instruction', ['email' => $this->data['email'] ?? ''])),
                            TextInput::make('otp_code')
                                ->label(__('app.otp_code'))
                                ->required()
                                ->length(6)
                                ->numeric()
                                ->suffixAction(
                                    Action::make('resendOtp')
                                        ->label(__('app.resend_otp'))
                                        ->icon('heroicon-m-arrow-path')
                                        ->action($this->resendOtp(...))
                                ),
                        ]),
                ]),
            ]);
    }

    public function sendOtp(): void
    {
        $email = $this->data['email'];

        // Rate limit OTP generation (separate from registration rate limit)
        $rateLimitKey = 'otp-send-'.sha1($email);
        if (RateLimiter::tooManyAttempts($rateLimitKey, 3)) {
            FilamentNotification::make()
                ->title(__('app.too_many_otp_attempts'))
                ->danger()
                ->send();

            return;
        }

        app(OtpService::class)->generate($email);
        RateLimiter::hit($rateLimitKey, 60);

        FilamentNotification::make()
            ->title(__('app.otp_sent'))
            ->success()
            ->send();
    }

    public function resendOtp(): void
    {
        $this->sendOtp();
    }

    protected function handleRegistration(array $data): Model
    {
        // Validate OTP before proceeding
        $this->verifyOtpCode($data);

        return $this->wrapInDatabaseTransaction(function () use ($data) {
            // 1. Create Company
            $company = Company::create([
                'plan_id' => Plan::where('slug', 'trial')->first()?->id,
                'name_ar' => $data['company_name_ar'],
                'name_en' => $data['company_name_en'],
                'phone' => $data['company_phone'],
                'is_active' => true,
                'locale' => app()->getLocale(),
                'currency' => 'EGP',
                'vat_rate' => 14,
                'slug' => Str::slug($data['company_name_en']).'-'.rand(10000, 99999),
            ]);

            // Initialize standard roles and permissions for the new company
            app(CreateDefaultCompanyRolesAction::class)->execute($company);

            // 2. Create User
            /** @var User $user */
            $user = User::create([
                'company_id' => $company->id,
                'name' => $data['name'],
                'email' => Str::lower($data['email']),
                'password' => Hash::make($data['password']),
                'is_active' => true,
                'email_verified_at' => now(),
            ]);

            // 3. Assign COMPANY_ADMIN role (Scoping to this company)
            $role = Role::where('name', Roles::COMPANY_ADMIN->value)
                ->where('guard_name', 'web')
                ->where('company_id', $company->id)
                ->first();

            // Set Spatie team id for this transaction/session scope
            setPermissionsTeamId($company->id);

            $user->assignRole($role);

            return $user;
        });
    }

    private function verifyOtpCode(array $data): void
    {
        $isValid = app(OtpService::class)->verify($data['email'], $data['otp_code']);

        if (! $isValid) {
            FilamentNotification::make()
                ->title(__('app.invalid_otp'))
                ->danger()
                ->send();

            // Halt registration
            throw ValidationException::withMessages([
                'otp_code' => __('app.invalid_otp'),
            ]);
        }
    }
}
