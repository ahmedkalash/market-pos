<?php

namespace Database\Seeders;

use App\Enums\InvoiceReturnStatus;
use App\Enums\PurchaseInvoiceStatus;
use App\Enums\PurchaseReturnStatus;
use App\Models\Brand;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\Store;
use App\Models\TaxClass;
use App\Models\UnitOfMeasure;
use App\Models\Vendor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoDevelopmentDataSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('slug', 'mool')->first();
        if (! $company) {
            return;
        }

        $nasrCity = Store::where('company_id', $company->id)->where('name_en', 'like', '%Nasr City%')->first();
        $maadi = Store::where('company_id', $company->id)->where('name_en', 'like', '%Maadi%')->first();
        $taxClass = TaxClass::where('company_id', $company->id)->first();

        // 2. Brands
        $brands = [];
        $brandNames = [
            'Samsung', 'Apple', 'Sony', 'LG', 'Dell',
            'HP', 'Lenovo', 'Asus', 'Acer', 'Microsoft',
            'Logitech', 'Razer', 'Corsair', 'Nike', 'Adidas',
            'Puma', 'Reebok', 'Under Armour', 'Nestle', 'Coca-Cola',
        ];

        foreach ($brandNames as $index => $brandName) {
            $brands[] = Brand::firstOrCreate([
                'company_id' => $company->id,
                'name_en' => $brandName,
            ], [
                'name_ar' => $brandName.' AR',
                'is_active' => true,
            ]);
        }

        $uomNasr = UnitOfMeasure::where('company_id', $company->id)->where('store_id', $nasrCity->id)->first();
        $uomMaadi = UnitOfMeasure::where('company_id', $company->id)->where('store_id', $maadi->id)->first();

        // 1. Categories
        $electronics = ProductCategory::firstOrCreate([
            'company_id' => $company->id,
            'store_id' => $nasrCity->id,
            'name_en' => 'Electronics',
            'name_ar' => 'إلكترونيات',
            'is_active' => true,
        ]);

        $apparel = ProductCategory::firstOrCreate([
            'company_id' => $company->id,
            'store_id' => $maadi->id,
            'name_en' => 'Apparel',
            'name_ar' => 'ملابس',
            'is_active' => true,
        ]);

        // 2. Vendors
        $vendors = [];
        $vendorNames = ['Tech Supplier', 'General Goods', 'Apparel Supplier'];
        foreach ($vendorNames as $index => $vName) {
            $vendors[] = Vendor::firstOrCreate([
                'company_id' => $company->id,
                'name' => $vName,
                'email' => Str::slug($vName).'@example.com',
                'phone' => '+2010'.rand(1000000, 9999999),
                'is_active' => true,
            ]);
        }

        // 3. Customers
        $customers = [];
        $customerNames = [
            'Ahmed Youssef', 'Mahmoud Ali', 'Sara Samir', 'Mona Zaki',
        ];

        foreach ($customerNames as $index => $cName) {
            $customers[] = Customer::firstOrCreate([
                'company_id' => $company->id,
                'name' => $cName,
                'email' => Str::slug($cName).'@example.com',
                'phone' => '+2011'.rand(1000000, 9999999),
                'is_active' => true,
            ]);
        }

        // 4. Products
        $productDefinitions = [
            [
                'store' => $nasrCity,
                'category' => $electronics,
                'name_en' => 'Smartphone X',
                'name_ar' => 'هاتف ذكي اكس',
                'uom' => $uomNasr,
                'variants' => [
                    ['name' => '64GB Black', 'retail' => 5000, 'wholesale' => 4500, 'min_r' => 4800, 'min_w' => 4400, 'w_qty' => 5, 'w_en' => true, 'r_neg' => true, 'w_neg' => true],
                    ['name' => '128GB Black', 'retail' => 6000, 'wholesale' => 5500, 'min_r' => 5800, 'min_w' => 5400, 'w_qty' => 5, 'w_en' => true, 'r_neg' => true, 'w_neg' => false],
                    ['name' => '256GB Black', 'retail' => 7500, 'wholesale' => 7000, 'min_r' => 7500, 'min_w' => 7000, 'w_qty' => 3, 'w_en' => true, 'r_neg' => false, 'w_neg' => false],
                    ['name' => '64GB White', 'retail' => 5000, 'wholesale' => null, 'min_r' => 4800, 'min_w' => null, 'w_qty' => 0, 'w_en' => false, 'r_neg' => true, 'w_neg' => false],
                    ['name' => '128GB White', 'retail' => 6000, 'wholesale' => null, 'min_r' => 6000, 'min_w' => null, 'w_qty' => 0, 'w_en' => false, 'r_neg' => false, 'w_neg' => false],
                ],
            ],
            [
                'store' => $nasrCity,
                'category' => $electronics,
                'name_en' => 'Laptop Pro',
                'name_ar' => 'لابتوب برو',
                'uom' => $uomNasr,
                'variants' => [
                    ['name' => '8GB RAM', 'retail' => 15000, 'wholesale' => 14000, 'min_r' => 14500, 'min_w' => 13500, 'w_qty' => 2, 'w_en' => true, 'r_neg' => true, 'w_neg' => true],
                    ['name' => '16GB RAM', 'retail' => 20000, 'wholesale' => 18000, 'min_r' => 19000, 'min_w' => 17500, 'w_qty' => 2, 'w_en' => true, 'r_neg' => true, 'w_neg' => true],
                    ['name' => '32GB RAM', 'retail' => 28000, 'wholesale' => 25000, 'min_r' => 28000, 'min_w' => 25000, 'w_qty' => 2, 'w_en' => true, 'r_neg' => false, 'w_neg' => false],
                    ['name' => '8GB Refurbished', 'retail' => 10000, 'wholesale' => null, 'min_r' => 9500, 'min_w' => null, 'w_qty' => 0, 'w_en' => false, 'r_neg' => true, 'w_neg' => false],
                    ['name' => '16GB Refurbished', 'retail' => 14000, 'wholesale' => null, 'min_r' => 14000, 'min_w' => null, 'w_qty' => 0, 'w_en' => false, 'r_neg' => false, 'w_neg' => false],
                ],
            ],
            [
                'store' => $nasrCity,
                'category' => $apparel,
                'name_en' => 'Cotton T-Shirt',
                'name_ar' => 'تيشيرت قطن',
                'uom' => $uomNasr,
                'variants' => [
                    ['name' => 'Small Red', 'retail' => 200, 'wholesale' => 150, 'min_r' => 180, 'min_w' => 140, 'w_qty' => 10, 'w_en' => true, 'r_neg' => true, 'w_neg' => true],
                    ['name' => 'Medium Red', 'retail' => 200, 'wholesale' => 150, 'min_r' => 180, 'min_w' => 140, 'w_qty' => 10, 'w_en' => true, 'r_neg' => true, 'w_neg' => true],
                    ['name' => 'Large Red', 'retail' => 200, 'wholesale' => 150, 'min_r' => 180, 'min_w' => 140, 'w_qty' => 10, 'w_en' => true, 'r_neg' => true, 'w_neg' => true],
                    ['name' => 'Small Blue', 'retail' => 220, 'wholesale' => 160, 'min_r' => 200, 'min_w' => 150, 'w_qty' => 10, 'w_en' => true, 'r_neg' => true, 'w_neg' => true],
                    ['name' => 'Medium Blue', 'retail' => 220, 'wholesale' => 160, 'min_r' => 200, 'min_w' => 150, 'w_qty' => 10, 'w_en' => true, 'r_neg' => true, 'w_neg' => true],
                ],
            ],
            [
                'store' => $maadi,
                'category' => $apparel,
                'name_en' => 'Winter Jacket',
                'name_ar' => 'جاكيت شتوي',
                'uom' => $uomMaadi,
                'variants' => [
                    ['name' => 'M Black', 'retail' => 1500, 'wholesale' => 1200, 'min_r' => 1400, 'min_w' => 1100, 'w_qty' => 5, 'w_en' => true, 'r_neg' => true, 'w_neg' => true],
                    ['name' => 'L Black', 'retail' => 1500, 'wholesale' => 1200, 'min_r' => 1400, 'min_w' => 1100, 'w_qty' => 5, 'w_en' => true, 'r_neg' => true, 'w_neg' => true],
                    ['name' => 'XL Black', 'retail' => 1500, 'wholesale' => 1200, 'min_r' => 1400, 'min_w' => 1100, 'w_qty' => 5, 'w_en' => true, 'r_neg' => true, 'w_neg' => true],
                    ['name' => 'M Brown', 'retail' => 1600, 'wholesale' => null, 'min_r' => 1600, 'min_w' => null, 'w_qty' => 0, 'w_en' => false, 'r_neg' => false, 'w_neg' => false],
                    ['name' => 'L Brown', 'retail' => 1600, 'wholesale' => null, 'min_r' => 1600, 'min_w' => null, 'w_qty' => 0, 'w_en' => false, 'r_neg' => false, 'w_neg' => false],
                ],
            ],
            [
                'store' => $maadi,
                'category' => $electronics,
                'name_en' => 'Wireless Mouse',
                'name_ar' => 'ماوس لاسلكي',
                'uom' => $uomMaadi,
                'variants' => [
                    ['name' => 'Standard Black', 'retail' => 300, 'wholesale' => 200, 'min_r' => 250, 'min_w' => 180, 'w_qty' => 20, 'w_en' => true, 'r_neg' => true, 'w_neg' => true],
                    ['name' => 'Standard White', 'retail' => 300, 'wholesale' => 200, 'min_r' => 250, 'min_w' => 180, 'w_qty' => 20, 'w_en' => true, 'r_neg' => true, 'w_neg' => true],
                    ['name' => 'Gaming RGB', 'retail' => 800, 'wholesale' => 600, 'min_r' => 700, 'min_w' => 550, 'w_qty' => 10, 'w_en' => true, 'r_neg' => true, 'w_neg' => true],
                    ['name' => 'Ergonomic', 'retail' => 1200, 'wholesale' => 1000, 'min_r' => 1200, 'min_w' => 1000, 'w_qty' => 5, 'w_en' => true, 'r_neg' => false, 'w_neg' => false],
                    ['name' => 'Travel Mini', 'retail' => 250, 'wholesale' => null, 'min_r' => 200, 'min_w' => null, 'w_qty' => 0, 'w_en' => false, 'r_neg' => true, 'w_neg' => false],
                ],
            ],
        ];

        $allVariants = [];

        foreach ($productDefinitions as $index => $pd) {
            $brandId = $brands[$index % count($brands)]->id;

            $product = Product::firstOrCreate([
                'store_id' => $pd['store']->id,
                'name_en' => $pd['name_en'],
            ], [
                'category_id' => $pd['category']->id,
                'tax_class_id' => $taxClass?->id,
                'brand_id' => $brandId,
                'name_ar' => $pd['name_ar'],
                'is_active' => true,
            ]);

            foreach ($pd['variants'] as $v) {
                $variant = ProductVariant::firstOrCreate([
                    'product_id' => $product->id,
                    'name_en' => $v['name'],
                ], [
                    'uom_id' => $pd['uom']?->id,
                    'name_ar' => $v['name'].' AR',
                    'retail_price' => $v['retail'],
                    'retail_is_price_negotiable' => $v['r_neg'],
                    'purchase_price' => $v['wholesale'] ? $v['wholesale'] * 0.8 : $v['retail'] * 0.8,
                    'wholesale_enabled' => $v['w_en'],
                    'wholesale_price' => $v['wholesale'],
                    'wholesale_is_price_negotiable' => $v['w_neg'],
                    'min_wholesale_price' => $v['min_w'],
                    'wholesale_qty_threshold' => $v['w_qty'],
                    'min_retail_price' => $v['min_r'],
                    'quantity' => 100,
                    'is_active' => true,
                ]);
                $allVariants[] = $variant;

                ProductBarcode::firstOrCreate([
                    'product_variant_id' => $variant->id,
                ], [
                    'barcode' => rand(100000000000, 999999999999),
                ]);
            }
        }

        // 4. Purchase Invoices & Returns
        $this->createPurchaseData($company, $nasrCity, $vendors[0], $allVariants);
        $this->createPurchaseData($company, $maadi, $vendors[1], $allVariants);
    }

    private function createPurchaseData($company, $store, $vendor, $variants)
    {
        $storeVariants = collect($variants)->filter(function ($v) use ($store) {
            return $v->product->store_id === $store->id;
        })->take(3)->values();

        if ($storeVariants->isEmpty()) {
            return;
        }

        // Draft Invoice
        $draftInvoice = PurchaseInvoice::create([
            'company_id' => $company->id,
            'store_id' => $store->id,
            'vendor_id' => $vendor->id,
            'invoice_number' => 'INV-DRAFT-'.$store->id.'-'.rand(100, 999),
            'vendor_invoice_ref' => 'V-REF-'.rand(1000, 9999),
            'total_before_tax' => 1000,
            'total_tax_amount' => 140,
            'total_amount' => 1140,
            'status' => PurchaseInvoiceStatus::Draft->value,
            'return_status' => InvoiceReturnStatus::None->value,
            'received_at' => now(),
        ]);

        PurchaseInvoiceItem::create([
            'purchase_invoice_id' => $draftInvoice->id,
            'product_variant_id' => $storeVariants[0]->id,
            'quantity' => 10,
            'unit_cost' => 100,
            'subtotal' => 1000,
            'tax_rate' => 14,
            'tax_amount' => 140,
            'line_total' => 1140,
        ]);

        // Finalized Invoice
        $finalInvoice = PurchaseInvoice::create([
            'company_id' => $company->id,
            'store_id' => $store->id,
            'vendor_id' => $vendor->id,
            'invoice_number' => 'INV-FINAL-'.$store->id.'-'.rand(100, 999),
            'vendor_invoice_ref' => 'V-REF-'.rand(1000, 9999),
            'total_before_tax' => 2000,
            'total_tax_amount' => 280,
            'total_amount' => 2280,
            'status' => PurchaseInvoiceStatus::Finalized->value,
            'return_status' => InvoiceReturnStatus::PartiallyReturned->value,
            'received_at' => now()->subDays(5),
            'finalized_at' => now()->subDays(4),
        ]);

        PurchaseInvoiceItem::create([
            'purchase_invoice_id' => $finalInvoice->id,
            'product_variant_id' => $storeVariants[1]->id,
            'quantity' => 20,
            'unit_cost' => 100,
            'subtotal' => 2000,
            'tax_rate' => 14,
            'tax_amount' => 280,
            'line_total' => 2280,
        ]);

        // Draft Return for Finalized Invoice
        $draftReturn = PurchaseReturn::create([
            'company_id' => $company->id,
            'store_id' => $store->id,
            'vendor_id' => $vendor->id,
            'original_invoice_id' => $finalInvoice->id,
            'return_number' => 'RET-DRAFT-'.$store->id.'-'.rand(100, 999),
            'return_reason' => 'Defective items',
            'status' => PurchaseReturnStatus::Draft->value,
            'total_before_tax' => 200,
            'total_tax_amount' => 28,
            'total_amount' => 228,
            'returned_at' => now(),
        ]);

        PurchaseReturnItem::create([
            'purchase_return_id' => $draftReturn->id,
            'original_item_id' => $finalInvoice->items()->first()->id,
            'product_variant_id' => $storeVariants[1]->id,
            'quantity' => 2,
            'unit_cost' => 100,
            'subtotal' => 200,
            'tax_rate' => 14,
            'tax_amount' => 28,
            'line_total' => 228,
        ]);

        // Finalized Return
        $finalReturn = PurchaseReturn::create([
            'company_id' => $company->id,
            'store_id' => $store->id,
            'vendor_id' => $vendor->id,
            'original_invoice_id' => $finalInvoice->id,
            'return_number' => 'RET-FINAL-'.$store->id.'-'.rand(100, 999),
            'return_reason' => 'Wrong specs',
            'status' => PurchaseReturnStatus::Finalized->value,
            'total_before_tax' => 100,
            'total_tax_amount' => 14,
            'total_amount' => 114,
            'returned_at' => now()->subDays(2),
            'finalized_at' => now()->subDays(1),
        ]);

        PurchaseReturnItem::create([
            'purchase_return_id' => $finalReturn->id,
            'original_item_id' => $finalInvoice->items()->first()->id,
            'product_variant_id' => $storeVariants[1]->id,
            'quantity' => 1,
            'unit_cost' => 100,
            'subtotal' => 100,
            'tax_rate' => 14,
            'tax_amount' => 14,
            'line_total' => 114,
        ]);
    }
}
