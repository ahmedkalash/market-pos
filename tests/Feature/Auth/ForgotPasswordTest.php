<?php

namespace Tests\Feature\Auth;

use App\Filament\Pages\Auth\ForgotPassword;
use App\Models\OtpVerification;
use App\Models\User;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

class ForgotPasswordTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('en');
    }

    public function test_forgot_password_page_is_accessible(): void
    {
        $this->get(route('filament.company.auth.password-reset.request'))
            ->assertSuccessful();
    }

    public function test_can_identify_user_and_send_otp(): void
    {
        Notification::fake();
        $email = 'identify-'.$this->faker->unique()->safeEmail();
        $user = User::factory()->create(['email' => $email]);

        RateLimiter::clear('otp-send-reset-'.sha1($email));

        Livewire::test(ForgotPassword::class)
            ->fillForm([
                'email' => strtoupper($email),
            ])
            ->call('identifyUser')
            ->assertHasNoFormErrors()
            ->assertNotified(
                FilamentNotification::make()
                    ->title(__('app.otp_sent'))
                    ->success()
            );

        $this->assertDatabaseHas('otp_verifications', [
            'identifier' => $email,
            'type' => 'email',
        ]);
    }

    public function test_cannot_identify_invalid_user(): void
    {
        Livewire::test(ForgotPassword::class)
            ->fillForm([
                'email' => 'nonexistent@example.com',
            ])
            ->call('identifyUser')
            ->assertHasFormErrors(['data.email']);
    }

    public function test_can_verify_otp_and_reset_password(): void
    {
        Notification::fake();
        $email = 'reset-'.$this->faker->unique()->safeEmail();
        $user = User::factory()->create(['email' => $email, 'password' => Hash::make('old-password')]);

        RateLimiter::clear('otp-send-reset-'.sha1($email));

        $livewire = Livewire::test(ForgotPassword::class)
            ->fillForm([
                'email' => $email,
            ])
            ->call('identifyUser');

        $otp = OtpVerification::where('identifier', $email)->first();
        $this->assertNotNull($otp, 'OTP should have been generated');

        $livewire->fillForm([
            'email' => $email,
            'otp_code' => $otp->code,
        ])
            ->call('verifyOtpCode')
            ->assertHasNoFormErrors();

        $livewire->fillForm([
            'email' => $email,
            'otp_code' => $otp->code,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])
            ->call('request')
            ->assertHasNoFormErrors()
            ->assertRedirect(filament()->getLoginUrl());

        $this->assertTrue(Hash::check('new-password-123', $user->refresh()->password));
    }

    public function test_otp_is_rate_limited(): void
    {
        Notification::fake();
        $email = 'rate-limit-'.$this->faker->unique()->safeEmail();
        $user = User::factory()->create(['email' => $email]);

        RateLimiter::clear('otp-send-reset-'.sha1($email));

        $livewire = Livewire::test(ForgotPassword::class)
            ->fillForm(['email' => $email]);

        // First 3 attempts should be fine
        for ($i = 0; $i < 3; $i++) {
            $livewire->call('sendOtp');
        }

        // 4th attempt should be rate limited
        $livewire->call('sendOtp')
            ->assertNotified(
                FilamentNotification::make()
                    ->title(__('app.too_many_otp_attempts'))
                    ->danger()
            );
    }

    public function test_can_identify_user_from_different_company_bypass_global_scope(): void
    {
        Notification::fake();
        $email = 'different-'.$this->faker->unique()->safeEmail();
        $user = User::factory()->create(['email' => $email]);

        RateLimiter::clear('otp-send-reset-'.sha1($email));

        Livewire::test(ForgotPassword::class)
            ->fillForm([
                'email' => $email,
            ])
            ->call('identifyUser')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('otp_verifications', [
            'identifier' => $email,
        ]);
    }
}
