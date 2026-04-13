<?php

namespace Tests\Feature\Auth;

use App\Filament\Pages\Auth\EditProfile;
use App\Models\OtpVerification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_accessible(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = $this->get(EditProfile::getUrl());

        $response->assertSuccessful();
    }

    public function test_user_can_update_profile_information(): void
    {
        $user = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
            'phone' => '1234567890',
        ]);

        $this->actingAs($user);

        $livewire = Livewire::test(EditProfile::class)
            ->fillForm([
                'name' => 'New Name',
                'email' => 'new@example.com',
                'phone' => '0987654321',
            ])
            ->call('sendOtp');

        $otp = OtpVerification::where('identifier', 'new@example.com')->first();
        $this->assertNotNull($otp);

        $livewire->fillForm([
            'otp_code' => $otp->code,
        ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'New Name',
            'email' => 'new@example.com',
            'phone' => '0987654321',
        ]);
    }
}
