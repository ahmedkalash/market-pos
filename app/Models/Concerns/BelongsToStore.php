<?php

namespace App\Models\Concerns;

use App\Models\Store;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Trait for models that belong to a specific store within a company.
 *
 * Automatically applies a global scope to filter records by the
 * authenticated user's store_id, unless they are an admin.
 */
trait BelongsToStore
{
    public static function bootBelongsToStore(): void
    {
        static::addGlobalScope('store', function (Builder $query) {
            if (auth()->hasUser()) {
                $user = auth()->user();

                // Skip scoping for Super Admins
                if ($user->isSuperAdmin()) {
                    return;
                }

                // If Company-level staff, scope by company across all their stores
                if ($user->isCompanyLevel()) {
                    $query->whereHas('store', fn ($q) => $q->where('company_id', $user->company_id));

                    return;
                }

                $query->where(
                    (new static)->getTable().'.store_id',
                    $user->store_id
                );
            }
        });

        static::creating(function (Model $model) {
            if (auth()->hasUser() && ! $model->store_id) {
                $user = auth()->user();

                // Only auto-fill if the user is actually assigned to a store
                if ($user->store_id) {
                    $model->store_id = $user->store_id;
                }
            }
        });
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    #[Scope]
    public function filterByStore(Builder $query, int $storeId): Builder
    {
        return $query->where('store_id', $storeId);

    }
}
