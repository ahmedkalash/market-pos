<?php

namespace App\Actions;

use App\Models\Attribute;
use App\Models\Store;
use App\Models\UnitOfMeasure;

class SeedDefaultStoreCatalogSettingsAction
{
    /**
     * Seeds the default attributes and units of measure for a newly created store.
     */
    public function execute(Store $store): void
    {
        // 1. Seed Default Units of Measure
        $this->seedDefaultUnitsOfMeasure($store);

        // 2. Seed Default Attributes
        $this->seedDefaultAttributes($store);
    }

    private function seedDefaultUnitsOfMeasure(Store $store): void
    {
        $defaultUoms = config('company_unit_of_measurements', []);
        foreach ($defaultUoms as $uom) {
            UnitOfMeasure::create([
                'company_id' => $store->company_id,
                'store_id' => $store->id,
                'name_en' => $uom['name_en'],
                'name_ar' => $uom['name_ar'],
                'abbreviation_en' => $uom['abbreviation_en'],
                'abbreviation_ar' => $uom['abbreviation_ar'],
            ]);
        }
    }

    private function seedDefaultAttributes(Store $store): void
    {
        $defaultAttributes = config('company_attributes', []);
        foreach ($defaultAttributes as $attribute) {
            Attribute::create([
                'company_id' => $store->company_id,
                'store_id' => $store->id,
                'name_en' => $attribute['name_en'],
                'name_ar' => $attribute['name_ar'],
            ]);
        }
    }
}
