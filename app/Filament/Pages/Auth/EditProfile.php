<?php

namespace App\Filament\Pages\Auth;

use App\Models\User;
use App\Services\OtpService;
use Filament\Actions\Action;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class EditProfile extends BaseEditProfile
{
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                SpatieMediaLibraryFileUpload::make('avatar')
                    ->label(__('app.avatar'))
                    ->collection('avatar')
                    ->avatar()
                    ->imageEditor()
                    ->circleCropper(),
                $this->getNameFormComponent(),
                $this->getEmailFormComponent()
                    ->live()
                    ->suffixAction(
                        Action::make('sendOtp')
                            ->label(__('app.send_otp'))
                            ->icon('heroicon-m-paper-airplane')
                            ->action($this->sendOtp(...))
                            ->visible(fn (Get $get): bool => filled($get('email')) && $get('email') !== $this->getUser()->email)
                    ),
                TextInput::make('otp_code')
                    ->label(__('app.otp_code'))
                    ->length(6)
                    ->numeric()
                    ->required()
                    ->visible(fn (Get $get): bool => filled($get('email')) && $get('email') !== $this->getUser()->email)
                    ->dehydrated(false),
                TextInput::make('phone')
                    ->label(__('app.phone'))
                    ->tel()
                    ->maxLength(255),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent()
                    ->visible(true)
                    ->required(fn (Get $get): bool => filled($get('password'))),

                Section::make(__('app.roles_and_permissions'))
                    ->schema([
                        TextEntry::make('roles')
                            ->label(__('app.current_roles'))
                            ->badge()
                            ->color('primary')
                            ->state(fn (User $user) => $user->getRoleNames())
                            ->formatStateUsing(fn ($state) => __('app.roles.'.$state)),

                        TextEntry::make('permissions')
                            ->label(__('app.current_permissions'))
                            ->badge()
                            ->color('primary')
                            ->state(fn (User $user) => $user->getAllPermissions()->pluck('name'))
                            ->formatStateUsing(function ($state) {
                                $label = __('permissions.'.$state);

                                return $label === 'permissions.'.$state
                                    ? str($state)->replace('_', ' ')->title()
                                    : $label;
                            })
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->collapsed(false),
            ]);
    }

    public function sendOtp(): void
    {
        $email = $this->data['email'] ?? null;

        if (blank($email) || $email === $this->getUser()->email) {
            return;
        }

        // Validate email uniqueness
        if (User::query()->where('email', $email)->where('id', '!=', $this->getUser()->id)->exists()) {
            FilamentNotification::make()
                ->title(__('validation.unique', ['attribute' => __('app.email')]))
                ->danger()
                ->send();

            return;
        }

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

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        // validate the otp code
        $newEmail = $data['email'] ?? null;
        $currentEmail = $record->email;
        if ($newEmail && $newEmail !== $currentEmail) {
            $otpCode = $this->form->getRawState()['otp_code'] ?? null;

            if (blank($otpCode) || ! app(OtpService::class)->verify($newEmail, $otpCode)) {
                FilamentNotification::make()
                    ->title(__('app.invalid_otp'))
                    ->danger()
                    ->send();

                throw ValidationException::withMessages([
                    'data.otp_code' => __('app.invalid_otp'),
                ]);
            }

            $record->email_verified_at = now();
        }

        // continue the update process
        return parent::handleRecordUpdate($record, $data);
    }
}
