<?php

namespace Database\Seeders;

use App\Actions\CreateDefaultCompanyRolesAction;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

class DemoCompanySeeder extends Seeder
{
    public function run(): void
    {
        $trialPlan = Plan::where('slug', 'trial')->first();

        $company = Company::firstOrCreate(
            ['slug' => 'mool'],
            [
                'plan_id' => $trialPlan?->id,
                'name_en' => 'Mool',
                'name_ar' => 'مول',
                'slug' => 'mool',
                'email' => 'info@mool.test',
                'phone' => '+201000000000',
                'address' => 'Cairo, Egypt',
                'vat_number' => '123-456-789',
                'vat_rate' => 14.00,
                'currency' => 'EGP',
                'locale' => 'ar',
                'receipt_header' => 'مرحباً بكم في مول',
                'receipt_footer' => 'شكراً لتسوقكم',
                'receipt_show_logo' => true,
                'is_active' => true,
            ]
        );

        $storeNasrCity = Store::firstOrCreate(
            ['company_id' => $company->id, 'name_en' => 'Mool - Nasr City'],
            [
                'name_ar' => 'مول - مدينة نصر',
                'address' => 'Nasr City, Cairo',
                'phone' => '+201000000001',
                'email' => 'nasrcity@mool.test',
                'is_active' => true,
            ]
        );

        $storeMaadi = Store::firstOrCreate(
            ['company_id' => $company->id, 'name_en' => 'Mool - Maadi'],
            [
                'name_ar' => 'مول - المعادي',
                'address' => 'Maadi, Cairo',
                'phone' => '+201000000002',
                'email' => 'maadi@mool.test',
                'is_active' => true,
            ]
        );

        app(CreateDefaultCompanyRolesAction::class)->execute($company);
        app(PermissionRegistrar::class)->setPermissionsTeamId($company->id);

        // Company Admin
        $admin = User::firstOrCreate(
            ['email' => 'admin@mool.test'],
            [
                'company_id' => $company->id,
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'phone' => '+201000000010',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $admin->assignRole(\App\Enums\Roles::COMPANY_ADMIN->value);

        // Store Managers
        $managerNasr = User::firstOrCreate(
            ['email' => 'manager.nasrcity@mool.test'],
            [
                'company_id' => $company->id,
                'store_id' => $storeNasrCity->id,
                'name' => 'Nasr City Manager',
                'password' => Hash::make('password'),
                'phone' => '+201000000011',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $managerNasr->assignRole(\App\Enums\Roles::STORE_MANAGER->value);

        $managerMaadi = User::firstOrCreate(
            ['email' => 'manager.maadi@mool.test'],
            [
                'company_id' => $company->id,
                'store_id' => $storeMaadi->id,
                'name' => 'Maadi Manager',
                'password' => Hash::make('password'),
                'phone' => '+201000000012',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $managerMaadi->assignRole(\App\Enums\Roles::STORE_MANAGER->value);

        // Cashiers - Nasr City
        foreach (['Cashier NC 1', 'Cashier NC 2'] as $index => $name) {
            $cashier = User::firstOrCreate(
                ['email' => 'cashier.nc'.($index + 1).'@mool.test'],
                [
                    'company_id' => $company->id,
                    'store_id' => $storeNasrCity->id,
                    'name' => $name,
                    'password' => Hash::make('password'),
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]
            );
            $cashier->assignRole(\App\Enums\Roles::CASHIER->value);
        }

        // Cashiers - Maadi
        foreach (['Cashier Maadi 1', 'Cashier Maadi 2'] as $index => $name) {
            $cashier = User::firstOrCreate(
                ['email' => 'cashier.maadi'.($index + 1).'@mool.test'],
                [
                    'company_id' => $company->id,
                    'store_id' => $storeMaadi->id,
                    'name' => $name,
                    'password' => Hash::make('password'),
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]
            );
            $cashier->assignRole(\App\Enums\Roles::CASHIER->value);
        }

        // Accountant
        $accountant = User::firstOrCreate(
            ['email' => 'accountant@mool.test'],
            [
                'company_id' => $company->id,
                'name' => 'Accountant User',
                'password' => Hash::make('password'),
                'phone' => '+201000000020',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $accountant->assignRole(\App\Enums\Roles::ACCOUNTANT->value);
    }
}
