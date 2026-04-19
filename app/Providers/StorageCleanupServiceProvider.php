<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class StorageCleanupServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap storage cleanup services.
     */
    public function boot(): void
    {
        // 1. Handle Attribute-Based File Cleanup (Standard FileUpload)
        // Optimized with early bails for non-file-bearing models.
        Event::listen('eloquent.deleting: *', function (string $event, array $models) {
            foreach ($models as $model) {
                if ($this->shouldCleanup($model) && $this->shouldCleanupOnDeletion($model)) {
                    $this->cleanupAttributeFiles($model);
                }
            }
        });

        Event::listen('eloquent.updating: *', function (string $event, array $models) {
            foreach ($models as $model) {
                if ($this->shouldCleanup($model)) {
                    $this->cleanupAttributeFilesOnUpdate($model);
                }
            }
        });

        // 2. Handle Spatie Media Library Cleanup for ForceDeletes
        // Spatie's InteractsWithMedia normally ignores soft-deleted models.
        // We'll hook into force-deletion specifically for media cleanup.
        Event::listen('eloquent.forceDeleted: *', function (string $event, array $models) {
            foreach ($models as $model) {
                if ($model instanceof HasMedia) {
                    // Explicitly clear all media collections to purge physical files.
                    $model->clearMediaCollection();
                }
            }
        });

        // 3. Spatie Media Model Record Listeners
        // These catch manual removals or updates directly on the Media record.

        // Handle physical file cleanup when a Media record is manually updated to a new file.
        // Also purges associated conversions to prevent stale artifacts.
        Event::listen('eloquent.updating: Spatie\MediaLibrary\MediaCollections\Models\Media', function (Media $media) {
            if ($media->isDirty('file_name') || $media->isDirty('disk')) {
                $oldFileName = $media->getOriginal('file_name');
                $oldDisk = $media->getOriginal('disk');
                $directory = (string) $media->id;

                $storage = Storage::disk($oldDisk);

                // 1. Delete the main old file
                if ($oldFileName && $storage->exists($directory.'/'.$oldFileName)) {
                    $storage->delete($directory.'/'.$oldFileName);
                }

                // 2. Deep purge the conversions directory
                // Spatie stores conversions in a subfolder. If the main file changed,
                // old conversions are definitely stale.
                if ($storage->exists($directory.'/conversions')) {
                    $storage->deleteDirectory($directory.'/conversions');
                }
            }
        });

        // Ensure numeric parent folders are scrubbed when a file is deleted.
        Event::listen('eloquent.deleted: Spatie\MediaLibrary\MediaCollections\Models\Media', function (Media $media) {
            $this->scrubMediaDirectory($media);
        });
    }

    /**
     * Delete files listed in the model's fileAttributes.
     */
    protected function cleanupAttributeFiles(Model $model): void
    {
        if (! method_exists($model, 'getFileAttributes')) {
            return;
        }

        foreach ($model->getFileAttributes() as $attribute) {
            $path = $model->getAttribute($attribute);
            if ($path && Storage::disk($this->getDisk($model, $attribute))->exists($path)) {
                Storage::disk($this->getDisk($model, $attribute))->delete($path);
            }
        }
    }

    /**
     * Delete old files when a file attribute is updated.
     */
    protected function cleanupAttributeFilesOnUpdate(Model $model): void
    {
        if (! method_exists($model, 'getFileAttributes')) {
            return;
        }

        foreach ($model->getFileAttributes() as $attribute) {
            if ($model->isDirty($attribute)) {
                $oldPath = $model->getOriginal($attribute);
                if ($oldPath && Storage::disk($this->getDisk($model, $attribute))->exists($oldPath)) {
                    Storage::disk($this->getDisk($model, $attribute))->delete($oldPath);
                }
            }
        }
    }

    /**
     * Scrub the parent numeric directory of deleted media if it's empty.
     */
    protected function scrubMediaDirectory(Media $media): void
    {
        $disk = $media->disk;
        $directory = $media->id;

        // Ensure we ARE using the disk specified in the media record
        if (Storage::disk($disk)->exists($directory)) {
            $files = Storage::disk($disk)->allFiles($directory);
            if (empty($files)) {
                Storage::disk($disk)->deleteDirectory($directory);
            }
        }
    }

    /**
     * Determine if the model should be considered for cleanup.
     * Prevents unnecessary checks on models that don't have file attributes.
     */
    protected function shouldCleanup(Model $model): bool
    {
        return method_exists($model, 'getFileAttributes');
    }

    /**
     * Determine if we should perform cleanup based on deletion type.
     */
    protected function shouldCleanupOnDeletion(Model $model): bool
    {
        // If the model does not have soft deletes, it is truly deleting.
        if (! method_exists($model, 'isForceDeleting')) {
            return true;
        }

        // Only cleanup if we are force deleting (per USER requirement).
        return $model->isForceDeleting();
    }

    /**
     * Get the storage disk for the attribute.
     * Defaulting to 'public' as per project convention unless customized.
     */
    protected function getDisk(Model $model, string $attribute): string
    {
        return property_exists($model, 'fileDisk') ? $model->fileDisk : 'public';
    }
}
