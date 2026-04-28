<?php

namespace App\Models\Concerns;

use App\Models\Company;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Trait for models that belong to a company (tenant).
 *
 * Automatically applies a global scope to filter records by the
 * authenticated user's company_id, and auto-sets company_id on creation.
 */
trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope('company', function (Builder $query) {
            if (auth()->hasUser() && ! auth()->user()->isSuperAdmin()) {
                $query->where(
                    (new static)->getTable().'.company_id',
                    auth()->user()->company_id
                );
            }
        });

        static::creating(function (Model $model) {
            if (auth()->hasUser() && ! $model->company_id) {
                $model->company_id = auth()->user()->company_id;
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    #[Scope]
    public function filterByCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);

    }
}
