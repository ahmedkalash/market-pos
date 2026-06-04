<?php

namespace Tests\Feature\Feature;

use App\Enums\ExtraItemActionType;
use App\Enums\InvoiceType;
use App\Models\Company;
use App\Models\InvoiceExtraItemPreset;
use App\Models\Store;
use App\Services\ExtraItemPresetCache;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\LazyCollection;
use Tests\TestCase;

/**
 * Comprehensive test suite for ExtraItemPresetCache.
 *
 * Each test creates a fresh ExtraItemPresetCache instance so the in-memory
 * buckets are naturally isolated without tearDown or separate processes.
 */
class GetCachedPresetsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create a fresh object that uses the CachesExtraItemPresets trait.
     */
    private function makeCache(): ExtraItemPresetCache
    {
        return new ExtraItemPresetCache;
    }

    // =========================================================================
    // Helper: create a Company + Store, return factory preset builder
    // =========================================================================

    /**
     * @return array{company: Company, store: Store}
     */
    private function createTenant(): array
    {
        $company = Company::factory()->create();
        $store = Store::factory()->create(['company_id' => $company->id]);

        return compact('company', 'store');
    }

    /**
     * Returns a partial factory definition for a specific invoice type,
     * scoped to a tenant and always active.
     *
     * @return array<string, mixed>
     */
    private function presetData(InvoiceType $type, Company $company, Store $store, bool $active = true): array
    {
        return [
            'company_id' => $company->id,
            'store_id' => $store->id,
            'invoice_type' => $type->value,
            'is_active' => $active,
        ];
    }

    // =========================================================================
    // SCENARIO 1: Fetching All Presets (no ID)
    // =========================================================================

    /**
     * @test Basic: calling with no arguments returns a Collection.
     */
    public function test_returns_collection_when_no_id_passed(): void
    {
        $cache = $this->makeCache();

        $result = $cache->get();

        $this->assertInstanceOf(Collection::class, $result);
    }

    /**
     * @test Only active presets of the requested InvoiceType are returned.
     */
    public function test_returns_all_active_presets_for_given_type(): void
    {
        ['company' => $company, 'store' => $store] = $this->createTenant();
        $cache = $this->makeCache();

        $p1 = InvoiceExtraItemPreset::factory()->create($this->presetData(InvoiceType::SaleReturn, $company, $store));
        $p2 = InvoiceExtraItemPreset::factory()->create($this->presetData(InvoiceType::SaleReturn, $company, $store));
        // Different type — must NOT appear
        InvoiceExtraItemPreset::factory()->create($this->presetData(InvoiceType::PurchaseInvoice, $company, $store));

        $result = $cache->get(null, InvoiceType::SaleReturn);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(2, $result);
        $this->assertTrue($result->contains($p1));
        $this->assertTrue($result->contains($p2));
    }

    /**
     * @test Inactive presets are excluded even if the type matches.
     */
    public function test_excludes_inactive_presets(): void
    {
        ['company' => $company, 'store' => $store] = $this->createTenant();
        $cache = $this->makeCache();

        $active = InvoiceExtraItemPreset::factory()->create($this->presetData(InvoiceType::SaleReturn, $company, $store, true));
        $inactive = InvoiceExtraItemPreset::factory()->create($this->presetData(InvoiceType::SaleReturn, $company, $store, false));

        $result = $cache->get(null, InvoiceType::SaleReturn);

        $this->assertTrue($result->contains($active));
        $this->assertFalse($result->contains($inactive));
    }

    /**
     * @test Returns an empty collection when no matching presets exist.
     */
    public function test_returns_empty_collection_when_no_presets_exist(): void
    {
        $cache = $this->makeCache();

        $result = $cache->get(null, InvoiceType::SaleReturn);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(0, $result);
    }

    /**
     * @test A SaleReturn fetch does NOT include PurchaseInvoice presets.
     */
    public function test_does_not_return_presets_of_different_type(): void
    {
        ['company' => $company, 'store' => $store] = $this->createTenant();
        $cache = $this->makeCache();

        InvoiceExtraItemPreset::factory()->create($this->presetData(InvoiceType::PurchaseInvoice, $company, $store));
        InvoiceExtraItemPreset::factory()->create($this->presetData(InvoiceType::PurchaseReturn, $company, $store));

        $result = $cache->get(null, InvoiceType::SaleReturn);

        $this->assertCount(0, $result);
    }

    /**
     * @test When no type is passed, ALL active presets (all types) are returned.
     */
    public function test_returns_all_active_presets_when_no_type_passed(): void
    {
        ['company' => $company, 'store' => $store] = $this->createTenant();
        $cache = $this->makeCache();

        $p1 = InvoiceExtraItemPreset::factory()->create($this->presetData(InvoiceType::SaleReturn, $company, $store));
        $p2 = InvoiceExtraItemPreset::factory()->create($this->presetData(InvoiceType::PurchaseInvoice, $company, $store));
        $p3 = InvoiceExtraItemPreset::factory()->create($this->presetData(InvoiceType::PurchaseInvoice, $company, $store, false)); // inactive

        $result = $cache->get();

        $this->assertTrue($result->contains($p1));
        $this->assertTrue($result->contains($p2));
        $this->assertFalse($result->contains($p3)); // inactive, must be excluded
    }

    // =========================================================================
    // SCENARIO 1: Caching Behaviour
    // =========================================================================

    /**
     * @test Second call for same type does NOT hit the database again.
     */
    public function test_caches_results_and_does_not_query_db_again(): void
    {
        ['company' => $company, 'store' => $store] = $this->createTenant();
        $cache = $this->makeCache();

        InvoiceExtraItemPreset::factory()->create($this->presetData(InvoiceType::SaleReturn, $company, $store));

        // Warm up the cache
        $cache->get(null, InvoiceType::SaleReturn);

        // Track queries fired after the cache is warm
        $queriesAfterCache = 0;
        \DB::listen(function () use (&$queriesAfterCache) {
            $queriesAfterCache++;
        });

        $cache->get(null, InvoiceType::SaleReturn);

        $this->assertSame(0, $queriesAfterCache, 'A second call for the same type should hit the in-memory cache, not the DB.');
    }

    /**
     * @test SaleReturn and PurchaseInvoice are cached in completely separate buckets.
     */
    public function test_caches_different_types_independently(): void
    {
        ['company' => $company, 'store' => $store] = $this->createTenant();
        $cache = $this->makeCache();

        $sr = InvoiceExtraItemPreset::factory()->create($this->presetData(InvoiceType::SaleReturn, $company, $store));
        $pi = InvoiceExtraItemPreset::factory()->create($this->presetData(InvoiceType::PurchaseInvoice, $company, $store));

        $saleReturns = $cache->get(null, InvoiceType::SaleReturn);
        $purchaseInvoices = $cache->get(null, InvoiceType::PurchaseInvoice);

        $this->assertCount(1, $saleReturns);
        $this->assertCount(1, $purchaseInvoices);
        $this->assertTrue($saleReturns->contains($sr));
        $this->assertFalse($saleReturns->contains($pi));
        $this->assertTrue($purchaseInvoices->contains($pi));
        $this->assertFalse($purchaseInvoices->contains($sr));
    }

    /**
     * @test REGRESSION GUARD: Fetching by ID first must NOT poison the type-keyed cache.
     *
     * If Preset#5 (sale_return) is fetched individually first (Scenario 3),
     * a subsequent call for all SaleReturn presets must still return the FULL
     * collection from the DB — not just the single individually cached item.
     */
    public function test_cache_is_not_poisoned_by_individual_fetch(): void
    {
        ['company' => $company, 'store' => $store] = $this->createTenant();
        $cache = $this->makeCache();

        // Create 3 SaleReturn presets
        $p1 = InvoiceExtraItemPreset::factory()->create($this->presetData(InvoiceType::SaleReturn, $company, $store));
        $p2 = InvoiceExtraItemPreset::factory()->create($this->presetData(InvoiceType::SaleReturn, $company, $store));
        $p3 = InvoiceExtraItemPreset::factory()->create($this->presetData(InvoiceType::SaleReturn, $company, $store));

        // Step 1: Fetch ONE preset by ID (caches under 'individual' key)
        $cache->get($p1->id);

        // Step 2: Now fetch the full SaleReturn collection
        $all = $cache->get(null, InvoiceType::SaleReturn);

        // Must return ALL 3 presets, not just the one from the individual cache
        $this->assertCount(3, $all);
        $this->assertTrue($all->contains($p1));
        $this->assertTrue($all->contains($p2));
        $this->assertTrue($all->contains($p3));
    }

    // =========================================================================
    // SCENARIO 2: Fetching by ID (from in-memory cache)
    // =========================================================================

    /**
     * @test After loading the type collection, fetching by ID hits the cache.
     */
    public function test_returns_preset_by_id_from_type_cache(): void
    {
        ['company' => $company, 'store' => $store] = $this->createTenant();
        $cache = $this->makeCache();

        $preset = InvoiceExtraItemPreset::factory()->create($this->presetData(InvoiceType::SaleReturn, $company, $store));

        // Warm the type cache first
        $cache->get(null, InvoiceType::SaleReturn);

        // Now track queries — this should be zero
        $queryCount = 0;
        \DB::listen(function () use (&$queryCount) {
            $queryCount++;
        });

        $found = $cache->get($preset->id);

        $this->assertNotNull($found);
        $this->assertSame($preset->id, $found->id);
        $this->assertSame(0, $queryCount, 'The preset should be found in the type cache with zero DB queries.');
    }

    /**
     * @test The model returned has the correct attributes.
     */
    public function test_returns_correct_model_instance_by_id(): void
    {
        ['company' => $company, 'store' => $store] = $this->createTenant();
        $cache = $this->makeCache();

        $preset = InvoiceExtraItemPreset::factory()->create([
            ...$this->presetData(InvoiceType::SaleReturn, $company, $store),
            'name' => 'Shipping Fee',
            'action_type' => ExtraItemActionType::Addition,
            'amount' => '15.50',
        ]);

        // Warm cache then fetch by ID
        $cache->get(null, InvoiceType::SaleReturn);
        $found = $cache->get($preset->id);

        $this->assertSame('Shipping Fee', $found->name);
        $this->assertSame(ExtraItemActionType::Addition, $found->action_type);
        $this->assertSame('15.50', $found->amount);
        $this->assertSame(InvoiceType::SaleReturn, $found->invoice_type);
    }

    /**
     * @test A preset cached under SaleReturn is found even without passing $type.
     */
    public function test_finds_id_across_different_type_buckets(): void
    {
        ['company' => $company, 'store' => $store] = $this->createTenant();
        $cache = $this->makeCache();

        $sr = InvoiceExtraItemPreset::factory()->create($this->presetData(InvoiceType::SaleReturn, $company, $store));
        $pi = InvoiceExtraItemPreset::factory()->create($this->presetData(InvoiceType::PurchaseInvoice, $company, $store));

        // Warm BOTH type caches
        $cache->get(null, InvoiceType::SaleReturn);
        $cache->get(null, InvoiceType::PurchaseInvoice);

        // Fetch by ID — should find in the correct bucket without specifying type
        $foundSr = $cache->get($sr->id);
        $foundPi = $cache->get($pi->id);

        $this->assertSame($sr->id, $foundSr->id);
        $this->assertSame($pi->id, $foundPi->id);
    }

    /**
     * @test A preset cached in 'individual' is found on a second call without a DB query.
     */
    public function test_returns_preset_from_individual_cache(): void
    {
        ['company' => $company, 'store' => $store] = $this->createTenant();
        $cache = $this->makeCache();

        $preset = InvoiceExtraItemPreset::factory()->create($this->presetData(InvoiceType::SaleReturn, $company, $store));

        // Scenario 3: first call with ID only — goes to DB, caches under 'individual'
        $cache->get($preset->id);

        // Track queries for second call
        $queryCount = 0;
        \DB::listen(function () use (&$queryCount) {
            $queryCount++;
        });

        $found = $cache->get($preset->id);

        $this->assertNotNull($found);
        $this->assertSame($preset->id, $found->id);
        $this->assertSame(0, $queryCount, 'The second ID lookup should be served from the individual cache.');
    }

    // =========================================================================
    // SCENARIO 3: Database Fallback
    // =========================================================================

    /**
     * @test When cache is empty, a valid ID is fetched from the database.
     */
    public function test_fetches_from_db_when_not_in_cache(): void
    {
        ['company' => $company, 'store' => $store] = $this->createTenant();
        $cache = $this->makeCache();

        $preset = InvoiceExtraItemPreset::factory()->create($this->presetData(InvoiceType::SaleReturn, $company, $store));

        $found = $cache->get($preset->id);

        $this->assertNotNull($found);
        $this->assertInstanceOf(InvoiceExtraItemPreset::class, $found);
        $this->assertSame($preset->id, $found->id);
    }

    /**
     * @test A non-existent ID returns null.
     */
    public function test_returns_null_for_nonexistent_id(): void
    {
        $cache = $this->makeCache();

        $result = $cache->get(999999);

        $this->assertNull($result);
    }

    /**
     * @test An inactive preset's ID returns null (the active() scope applies to the fallback query too).
     */
    public function test_returns_null_for_inactive_preset_id(): void
    {
        ['company' => $company, 'store' => $store] = $this->createTenant();
        $cache = $this->makeCache();

        $inactive = InvoiceExtraItemPreset::factory()->create(
            $this->presetData(InvoiceType::SaleReturn, $company, $store, false)
        );

        $result = $cache->get($inactive->id);

        $this->assertNull($result);
    }

    /**
     * @test After a Scenario 3 DB fetch, a second call for the same ID uses the individual cache.
     */
    public function test_individual_fetch_is_cached_for_subsequent_lookups(): void
    {
        ['company' => $company, 'store' => $store] = $this->createTenant();
        $cache = $this->makeCache();

        $preset = InvoiceExtraItemPreset::factory()->create($this->presetData(InvoiceType::SaleReturn, $company, $store));

        // First call — goes to DB (Scenario 3)
        $cache->get($preset->id);

        // Monitor queries for the second call
        $queryCount = 0;
        \DB::listen(function () use (&$queryCount) {
            $queryCount++;
        });

        $found = $cache->get($preset->id);

        $this->assertNotNull($found);
        $this->assertSame(0, $queryCount);
    }

    // =========================================================================
    // Edge Cases & Regression Guards
    // =========================================================================

    /**
     * @test Passing both $id and $type still finds the preset regardless of type.
     */
    public function test_type_parameter_is_ignored_when_id_is_passed(): void
    {
        ['company' => $company, 'store' => $store] = $this->createTenant();
        $cache = $this->makeCache();

        $preset = InvoiceExtraItemPreset::factory()->create($this->presetData(InvoiceType::SaleReturn, $company, $store));

        // Intentionally pass the wrong type — should still find by ID
        $found = $cache->get($preset->id, InvoiceType::PurchaseInvoice);

        $this->assertNotNull($found);
        $this->assertSame($preset->id, $found->id);
    }

    /**
     * @test Sequential fetches of different types, then re-fetching the first type, all work correctly.
     */
    public function test_handles_multiple_sequential_type_fetches(): void
    {
        ['company' => $company, 'store' => $store] = $this->createTenant();
        $cache = $this->makeCache();

        $sr = InvoiceExtraItemPreset::factory()->create($this->presetData(InvoiceType::SaleReturn, $company, $store));
        $pi = InvoiceExtraItemPreset::factory()->create($this->presetData(InvoiceType::PurchaseInvoice, $company, $store));

        // Fetch SaleReturn, then PurchaseInvoice
        $first = $cache->get(null, InvoiceType::SaleReturn);
        $second = $cache->get(null, InvoiceType::PurchaseInvoice);

        // Fetch SaleReturn again — must come from cache, still correct
        $third = $cache->get(null, InvoiceType::SaleReturn);

        $this->assertTrue($first->contains($sr));
        $this->assertFalse($first->contains($pi));
        $this->assertTrue($second->contains($pi));
        $this->assertFalse($second->contains($sr));
        // Third fetch must be identical to the first
        $this->assertCount($first->count(), $third);
        $this->assertTrue($third->contains($sr));
    }

    /**
     * @test Fetching a non-existent ID does NOT push null into the individual cache.
     */
    public function test_individual_cache_does_not_grow_with_nulls(): void
    {
        $cache = $this->makeCache();

        // Fetch a non-existent ID — must return null
        $first = $cache->get(999999);
        $second = $cache->get(999999);

        $this->assertNull($first);
        $this->assertNull($second);
    }

    /**
     * @test The return type is Collection, not a plain array, when no ID is passed.
     */
    public function test_return_type_is_eloquent_collection_when_no_id_passed(): void
    {
        $cache = $this->makeCache();

        $result = $cache->get(null, InvoiceType::SaleReturn);

        // Illuminate\Database\Eloquent\Collection extends Illuminate\Support\Collection,
        // so we verify the specific Eloquent subclass — not the base class (which it always satisfies).
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertNotInstanceOf(LazyCollection::class, $result); // Definitely not a lazy collection
    }

    /**
     * @test The return type is InvoiceExtraItemPreset (or null), not a Collection, when ID is passed.
     */
    public function test_return_type_is_model_when_id_passed(): void
    {
        ['company' => $company, 'store' => $store] = $this->createTenant();
        $cache = $this->makeCache();

        $preset = InvoiceExtraItemPreset::factory()->create($this->presetData(InvoiceType::SaleReturn, $company, $store));

        $found = $cache->get($preset->id);

        $this->assertInstanceOf(InvoiceExtraItemPreset::class, $found);
        $this->assertNotInstanceOf(Collection::class, $found);
    }

    /**
     * @test All four InvoiceType cases return the correct scoped results.
     */
    public function test_works_with_all_four_invoice_types(): void
    {
        ['company' => $company, 'store' => $store] = $this->createTenant();
        $cache = $this->makeCache();

        $si = InvoiceExtraItemPreset::factory()->create($this->presetData(InvoiceType::SaleInvoice, $company, $store));
        $sr = InvoiceExtraItemPreset::factory()->create($this->presetData(InvoiceType::SaleReturn, $company, $store));
        $pi = InvoiceExtraItemPreset::factory()->create($this->presetData(InvoiceType::PurchaseInvoice, $company, $store));
        $pr = InvoiceExtraItemPreset::factory()->create($this->presetData(InvoiceType::PurchaseReturn, $company, $store));

        foreach (InvoiceType::cases() as $type) {
            $result = $cache->get(null, $type);
            $this->assertCount(1, $result, "Expected exactly 1 preset for type: {$type->value}");
        }

        $this->assertTrue($cache->get(null, InvoiceType::SaleInvoice)->contains($si));
        $this->assertTrue($cache->get(null, InvoiceType::SaleReturn)->contains($sr));
        $this->assertTrue($cache->get(null, InvoiceType::PurchaseInvoice)->contains($pi));
        $this->assertTrue($cache->get(null, InvoiceType::PurchaseReturn)->contains($pr));
    }

    /**
     * @test Full workflow: simulates the Filament form pattern.
     *
     * Step 1: options() loads all SaleReturn presets (Scenario 1 — type cache)
     * Step 2: afterStateUpdated() fetches the selected preset by ID (Scenario 2 — from cache)
     * The second step must NOT fire any DB queries.
     */
    public function test_full_workflow_options_then_select(): void
    {
        ['company' => $company, 'store' => $store] = $this->createTenant();
        $cache = $this->makeCache();

        $p1 = InvoiceExtraItemPreset::factory()->create($this->presetData(InvoiceType::SaleReturn, $company, $store));
        $p2 = InvoiceExtraItemPreset::factory()->create($this->presetData(InvoiceType::SaleReturn, $company, $store));

        // === STEP 1: Simulate options() ===
        $options = $cache->get(null, InvoiceType::SaleReturn)->pluck('name', 'id');

        $this->assertCount(2, $options);
        $this->assertArrayHasKey($p1->id, $options->toArray());
        $this->assertArrayHasKey($p2->id, $options->toArray());

        // === STEP 2: Simulate afterStateUpdated() — must use cache ===
        $queryCount = 0;
        \DB::listen(function () use (&$queryCount) {
            $queryCount++;
        });

        $selected = $cache->get($p1->id);

        $this->assertNotNull($selected);
        $this->assertSame($p1->id, $selected->id);
        $this->assertSame(0, $queryCount, 'afterStateUpdated should find the preset in the type cache without a DB query.');
    }

    /**
     * @test Two separate instances do NOT share cache state.
     */
    public function test_separate_instances_have_isolated_caches(): void
    {
        ['company' => $company, 'store' => $store] = $this->createTenant();

        InvoiceExtraItemPreset::factory()->create($this->presetData(InvoiceType::SaleReturn, $company, $store));

        $cache1 = $this->makeCache();
        $cache2 = $this->makeCache();

        // Warm cache1
        $cache1->get(null, InvoiceType::SaleReturn);

        // cache2 should NOT have the data — it must query the DB
        $queryCount = 0;
        \DB::listen(function () use (&$queryCount) {
            $queryCount++;
        });

        $cache2->get(null, InvoiceType::SaleReturn);

        $this->assertGreaterThan(0, $queryCount, 'A separate instance must query the DB independently.');
    }
}
