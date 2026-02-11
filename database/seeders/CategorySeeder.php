<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Hourly
        Category::firstOrCreate(
            ['type' => 'hourly', 'name' => 'Hourly Packages'],
            [
                'name_ar' => 'باقات الساعات',
                'description' => 'Internet cards sold by hours.',
            ]
        );

        // Monthly
        Category::firstOrCreate(
            ['type' => 'monthly', 'name' => 'Monthly Subscriptions'],
            [
                'name_ar' => 'اشتراكات شهرية',
                'description' => 'Internet cards sold by monthly subscriptions.',
            ]
        );
    }
}
