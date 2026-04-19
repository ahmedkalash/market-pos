<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

function test_direct_media_update()
{
    echo "Testing Direct Media Model Update...\n";
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('original.jpg');
    $media = $user->addMedia($file)->toMediaCollection('avatar');

    $oldPath = $media->getPath();
    $dir = $media->id;

    if (file_exists($oldPath)) {
        echo "Original media created.\n";
    }

    // Simulate direct model update to a new filename
    // (In reality, you'd move the file first, but we want to see if the OLD one is deleted)
    echo "Updating media record file_name...\n";
    $media->update(['file_name' => 'updated.jpg']);

    if (! file_exists($oldPath)) {
        echo "SUCCESS: Original physical file deleted on Media record update.\n";
    } else {
        echo "FAILED: Original physical file STILL EXISTS.\n";
    }

    // Test Deletion & Scrubbing
    echo "Deleting media record...\n";
    $media->delete();

    if (! Storage::disk('public')->exists($dir)) {
        echo "SUCCESS: Parent numeric directory ($dir) scrubbed.\n";
    } else {
        echo "FAILED: Parent numeric directory ($dir) still exists.\n";
    }

    $user->forceDelete();
}

try {
    test_direct_media_update();
} catch (Exception $e) {
    echo 'ERROR: '.$e->getMessage()."\n";
}
