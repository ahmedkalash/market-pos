<?php

namespace App\Filament\Pages\Auth;

use App\Enums\Roles;
use App\Models\Company;
use App\Models\Plan;
use App\Models\User;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use DanHarrin\LivewireRateLimiting\WithRateLimiting;
use Filament\Auth\Pages\Register;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
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
                        ]),
                ]),
            ]);
    }

    protected function handleRegistration(array $data): Model
    {
        return DB::transaction(function () use ($data) {
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
                'slug' => Str::slug($this->data['company_name_en']),
            ]);

            // 2. Create User
            /** @var User $user */
            $user = User::create([
                'company_id' => $company->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'is_active' => true,
            ]);

            // 3. Assign COMPANY_ADMIN role
            $role = Role::where('name', Roles::COMPANY_ADMIN->value)
                ->where('guard_name', 'web')
                ->first();

            // Set Spatie team id for this transaction/session scope
            setPermissionsTeamId($company->id);

            $user->assignRole($role);

            return $user;
        });
    }
}
