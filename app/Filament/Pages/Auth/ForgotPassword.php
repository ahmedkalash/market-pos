<?php

namespace App\Filament\Pages\Auth;

use App\Models\User;
use App\Services\OtpService;
use DanHarrin\LivewireRateLimiting\WithRateLimiting;
use Filament\Actions\Action;
use Filament\Auth\Pages\PasswordReset\RequestPasswordReset;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ForgotPassword extends RequestPasswordReset
{
    use WithRateLimiting {
        rateLimit as traitRateLimit;
    }

    public function mount(): void
    {
        parent::mount();

        $this->form->fill();
    }

    protected function rateLimit($maxAttempts, $decaySeconds = 60, $method = null, $component = null): void
    {
        if ($maxAttempts === 2) {
            $maxAttempts = 5;
        }

        $this->traitRateLimit($maxAttempts, $decaySeconds, $method, $component);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Wizard::make([
                    Step::make(__('app.email_address'))
                        ->schema([
                            $this->getEmailFormComponent()
                                ->validationAttribute(__('app.email')),
                        ])
                        ->afterValidation($this->identifyUser(...)),

                    Step::make(__('app.email_verification'))
                        ->schema([
                            TextEntry::make('otp_instruction')
                                ->hiddenLabel()
                                ->state(fn () => __('app.otp_instruction', ['email' => $this->data['email'] ?? ''])),
                            TextInput::make('otp_code')
                                ->label(__('app.otp_code'))
                                ->required()
                                ->length(6)
                                ->numeric()
                                ->suffixAction(
                                    Action::make('resendOtp')
                                        ->label(__('app.resend_otp'))
                                        ->icon('heroicon-m-arrow-path')
                                        ->action($this->sendOtp(...))
                                ),
                        ])
                        ->afterValidation($this->verifyOtpCode(...)),

                    Step::make(__('app.reset_password'))
                        ->schema([
                            TextInput::make('password')
                                ->label(__('app.new_password'))
                                ->password()
                                ->revealable()
                                ->required()
                                ->rule('confirmed')
                                ->rule('min:8'),
                            TextInput::make('password_confirmation')
                                ->label(__('app.confirm_new_password'))
                                ->password()
                                ->revealable()
                                ->required(),
                        ]),
                ])
                    ->submitAction(
                        Action::make('request')
                            ->label(__('app.reset_password'))
                            ->submit('request')
                    ),
            ])
            ->statePath('data');
    }

    public function identifyUser(): void
    {
        $email = Str::lower($this->data['email'] ?? '');

        // Identify user system-wide (ignoring company scope)
        $user = User::withoutGlobalScopes()->where('email', $email)->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'data.email' => __('app.user_not_found'),
            ]);
        }

        // If user is inactive, we still send OTP to avoid leaking account status if appropriate,
        // but verifyOtpCode or the final step should handle it.
        // For now, let's keep it simple as identifying the user.

        $this->sendOtp();
    }

    public function sendOtp(): void
    {
        $email = Str::lower($this->data['email'] ?? '');

        if (blank($email)) {
            return;
        }

        // Rate limit OTP generation
        $rateLimitKey = 'otp-send-reset-'.sha1($email);
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

    public function verifyOtpCode(): void
    {
        $email = Str::lower($this->data['email'] ?? '');
        $code = $this->data['otp_code'] ?? '';

        $isValid = app(OtpService::class)->verify($email, $code);

        if (! $isValid) {
            FilamentNotification::make()
                ->title(__('app.invalid_otp'))
                ->danger()
                ->send();

            throw ValidationException::withMessages([
                'data.otp_code' => __('app.invalid_otp'),
            ]);
        }
    }

    /**
     * Overriding the request() method to handle the actual password reset.
     */
    public function request(): void
    {
        $data = $this->form->getState();

        /** @var User $user */
        $user = User::withoutGlobalScopes()
            ->where('email', Str::lower($data['email']))
            ->first();

        if (! $user) {
            return;
        }

        $user->update([
            'password' => Hash::make($data['password']),
        ]);

        FilamentNotification::make()
            ->title(__('app.password_reset_success'))
            ->success()
            ->send();

        $this->redirect(filament()->getLoginUrl());
    }

    protected function getFormActions(): array
    {
        // Return empty array to hide the default "Request" button,
        // as the Wizard has its own submit action on the last step.
        return [];
    }
}
