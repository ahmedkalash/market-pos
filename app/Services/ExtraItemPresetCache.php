<?php

namespace App\Services;

use App\Enums\InvoiceType;
use App\Models\InvoiceExtraItemPreset;
use Illuminate\Database\Eloquent\Collection;

/**
 * Request-scoped in-memory cache for InvoiceExtraItemPreset records.
 *
 * Instantiate once per request (or per Filament schema render) and pass the
 * same instance to every closure that needs preset data. This guarantees:
 *  - No redundant DB queries within a single request.
 *  - No cross-request state leakage (safe for Laravel Octane).
 *  - No static property poisoning between concurrent workers.
 */
class ExtraItemPresetCache
{
    /**
     * Collection-of-Collections keyed by:
     *  - InvoiceType::value  (e.g. 'sale_return') for type-scoped fetches
     *  - 'all'               for unscoped fetches
     *  - 'individual'        for single-ID fallback fetches
     *
     * @var Collection<string, Collection<int, InvoiceExtraItemPreset>>
     */
    private Collection $buckets;

    public function __construct()
    {
        $this->buckets = new Collection;
    }

    /**
     * Retrieve active Extra Item Presets using an instance-level memory cache.
     *
     * This method prevents redundant N+1 queries during Livewire render cycles.
     * The results are cached within the object instance and persist for its lifecycle.
     *
     * @param  int|null  $id  If provided, returns the single matching Preset. Otherwise returns a collection.
     * @param  InvoiceType|null  $type  If provided, scopes the returned collection to a specific invoice type.
     */
    public function get(?int $id = null, ?InvoiceType $type = null): Collection|InvoiceExtraItemPreset|null
    {
        // -----------------------------------------------------------------
        // SCENARIO 1: Caller wants an entire collection (no ID)
        // -----------------------------------------------------------------
        if ($id === null) {
            $typeKey = $type ? $type->value : 'all';

            if (! $this->buckets->has($typeKey)) {
                $query = InvoiceExtraItemPreset::query()->active();

                if ($type) {
                    $query->where('invoice_type', $type);
                }

                $this->buckets->put($typeKey, $query->get());
            }

            return $this->buckets->get($typeKey);
        }

        // -----------------------------------------------------------------
        // SCENARIO 2: Caller wants a specific preset by ID
        //             Search all already-loaded buckets first (0 DB hits).
        // -----------------------------------------------------------------
        foreach ($this->buckets as $bucket) {
            if ($preset = $bucket->firstWhere('id', $id)) {
                return $preset;
            }
        }

        // -----------------------------------------------------------------
        // SCENARIO 3: Fallback – ID not found in any cached bucket.
        //             Hit the DB once and store in the 'individual' bucket.
        // -----------------------------------------------------------------
        $preset = InvoiceExtraItemPreset::query()->active()->find($id);

        if ($preset) {
            if (! $this->buckets->has('individual')) {
                $this->buckets->put('individual', new Collection);
            }
            $this->buckets->get('individual')->push($preset);
        }

        return $preset;
    }
}
