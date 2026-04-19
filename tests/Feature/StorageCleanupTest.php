<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

class StorageCleanupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    /**
     * Test cleanup of standard attribute-based files on deletion.
     */
    public function test_attribute_based_files_are_deleted_on_force_delete(): void
    {
        $path = 'logos/test_logo.jpg';
        Storage::disk('public')->put($path, 'dummy content');

        $company = Company::factory()->create([
            'logo' => $path,
        ]);

        Storage::disk('public')->assertExists($path);

        // Force delete
        $company->forceDelete();

        Storage::disk('public')->assertMissing($path);
    }

    /**
     * Test that files are NOT deleted on soft delete.
     */
    public function test_attribute_based_files_persist_on_soft_delete(): void
    {
        $path = 'logos/test_logo.jpg';
        Storage::disk('public')->put($path, 'dummy content');

        $company = Company::factory()->create([
            'logo' => $path,
        ]);

        Storage::disk('public')->assertExists($path);

        // Soft delete
        $company->delete();

        Storage::disk('public')->assertExists($path);
    }

    /**
     * Test cleanup of old files when an attribute is updated.
     */
    public function test_attribute_based_files_are_deleted_on_update(): void
    {
        $oldPath = 'logos/old_logo.jpg';
        $newPath = 'logos/new_logo.jpg';

        Storage::disk('public')->put($oldPath, 'old content');
        Storage::disk('public')->put($newPath, 'new content');

        $company = Company::factory()->create([
            'logo' => $oldPath,
        ]);

        Storage::disk('public')->assertExists($oldPath);

        // Update logo
        $company->update(['logo' => $newPath]);

        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($newPath);
    }

    /**
     * Test cleanup of Spatie Media Library files on force delete.
     */
    public function test_spatie_media_is_deleted_on_force_delete(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->image('avatar.jpg');

        $media = $user->addMedia($file)->toMediaCollection('avatar');
        $directory = $media->id;

        Storage::disk('public')->assertExists($directory.'/avatar.jpg');

        // Force delete the user
        $user->forceDelete();

        Storage::disk('public')->assertMissing($directory.'/avatar.jpg');
        // The directory itself should be scrubbed if empty
        Storage::disk('public')->assertDirectoryEmpty($directory);
    }

    /**
     * Test that Spatie Media record updates purge old files and conversions.
     */
    public function test_spatie_media_update_purges_conversions(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->image('original.jpg');

        $media = $user->addMedia($file)->toMediaCollection('avatar');
        $directory = $media->id;

        // Manually ensure conversion folders exist for testing purged logic
        Storage::disk('public')->put($directory.'/conversions/thumb-original.jpg', 'thumb contents');

        Storage::disk('public')->assertExists($directory.'/conversions/thumb-original.jpg');

        // Simulate a manual update to the media record (e.g. renaming the file)
        // In our provider, this triggers the conversion purge.
        $media->update(['file_name' => 'updated.jpg']);

        // Assert old conversion folder is gone
        Storage::disk('public')->assertMissing($directory.'/conversions');
    }
}
