<?php

namespace Tests\Feature\Auth;

use App\Filament\Pages\Auth\EditProfile;
use App\Models\OtpVerification;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class EmailChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_send_otp_when_changing_email(): void
    {
        $user = User::factory()->create([
            'email' => 'old@example.com',
        ]);

        $this->actingAs($user);

        Livewire::test(EditProfile::class)
            ->set('data.email', 'new@example.com')
            ->call('sendOtp')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('otp_verifications', [
            'identifier' => 'new@example.com',
            'type' => 'email',
        ]);
    }

    public function test_email_change_requires_correct_otp(): void
    {
        $user = User::factory()->create([
            'email' => 'old@example.com',
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($user);

        // Generate OTP
        app(OtpService::class)->generate('new@example.com');
        $otp = OtpVerification::where('identifier', 'new@example.com')->first();

        // Attempt with wrong OTP
        Livewire::test(EditProfile::class)
            ->set('data.email', 'new@example.com')
            ->set('data.currentPassword', 'password')
            ->set('data.otp_code', '000000')
            ->call('save')
            ->assertHasErrors(['data.otp_code']);

        $this->assertEquals('old@example.com', $user->fresh()->email);

        // Attempt with correct OTP
        Livewire::test(EditProfile::class)
            ->set('data.email', 'new@example.com')
            ->set('data.currentPassword', 'password')
            ->set('data.otp_code', $otp->code)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEquals('new@example.com', $user->fresh()->email);
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_otp_field_is_visible_only_when_email_is_different(): void
    {
        $user = User::factory()->create([
            'email' => 'old@example.com',
        ]);

        $this->actingAs($user);

        Livewire::test(EditProfile::class)
            ->assertFormFieldIsHidden('otp_code')
            ->set('data.email', 'new@example.com')
            ->assertFormFieldIsVisible('otp_code')
            ->set('data.email', 'old@example.com')
            ->assertFormFieldIsHidden('otp_code');
    }
}
