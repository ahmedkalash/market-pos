<?php

namespace App\Models\Concerns;

/**
 * Trait HandlesFileStorage
 *
 * Models using this trait should define:
 * protected array $fileAttributes = ['column_name'];
 *
 * This allows the StorageCleanupServiceProvider to automatically manage file lifecycle
 * for attribute-based file storage (not just Media Library).
 */
trait HandlesFileStorage
{
    /**
     * Get the array of columns that store file paths.
     *
     * @return array<int, string>
     */
    public function getFileAttributes(): array
    {
        return property_exists($this, 'fileAttributes') ? $this->fileAttributes : [];
    }
}
