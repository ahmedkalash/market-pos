<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;

trait HasActiveScope
{
    /**
     * Scope a query to only include active records,
     * optionally including a specific ID even if inactive.
     */
    #[Scope]
    public function active(Builder $query, ?int $includeId = null): Builder
    {
        return $query->where(function (Builder $q) use ($includeId) {
            $q->where($this->qualifyColumn('is_active'), true);

            if ($includeId) {
                $q->orWhere($this->qualifyColumn('id'), $includeId);
            }
        });
    }
}
