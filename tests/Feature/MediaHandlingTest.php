<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaHandlingTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_have_an_avatar(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $file = UploadedFile::fake()->image('avatar.jpg');

        $user->addMedia($file)
            ->toMediaCollection('avatar');

        $this->assertCount(1, $user->getMedia('avatar'));
        $this->assertEquals('avatar.jpg', $user->getFirstMedia('avatar')->file_name);

        // Check conversion (thumb)
        $this->assertTrue($user->getFirstMedia('avatar')->hasGeneratedConversion('thumb'));
    }

    public function test_store_can_have_multiple_images(): void
    {
        Storage::fake('public');

        $store = Store::factory()->create();
        $file1 = UploadedFile::fake()->image('store1.jpg');
        $file2 = UploadedFile::fake()->image('store2.jpg');

        $store->addMedia($file1)->toMediaCollection('images');
        $store->addMedia($file2)->toMediaCollection('images');

        $this->assertCount(2, $store->getMedia('images'));

        // Check conversion for first image
        $this->assertTrue($store->getFirstMedia('images')->hasGeneratedConversion('thumb'));
    }
}
