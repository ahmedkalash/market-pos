<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name_en' => 'Trial',
                'name_ar' => 'تجربة',
                'slug' => 'trial',
                'max_stores' => 1,
                'max_users' => 5,
                'price' => 0.00,
                'duration_days' => 14,
                'is_active' => true,
            ],
            [
                'name_en' => 'Basic',
                'name_ar' => 'أساسي',
                'slug' => 'basic',
                'max_stores' => 3,
                'max_users' => 20,
                'price' => 0.00,
                'duration_days' => 30,
                'is_active' => true,
            ],
            [
                'name_en' => 'Pro',
                'name_ar' => 'احترافي',
                'slug' => 'pro',
                'max_stores' => 10,
                'max_users' => 100,
                'price' => 0.00,
                'duration_days' => 30,
                'is_active' => true,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::firstOrCreate(
                ['slug' => $plan['slug']],
                $plan
            );
        }
    }
}
