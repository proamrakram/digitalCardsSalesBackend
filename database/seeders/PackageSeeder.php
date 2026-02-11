<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Package;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PackageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $hourly = Category::where('type', 'hourly')->firstOrFail();
        $monthly = Category::where('type', 'monthly')->firstOrFail();

        // Hourly packages
        $hourlyPackages = [
            ['name' => 'Daily subscription 8 hours 2 MB', 'name_ar' => 'اشتراك يومي 8 ساعات 2 ميجا', 'duration' => '8 ساعات', 'price' => 1.00],
        ];

        foreach ($hourlyPackages as $p) {
            Package::firstOrCreate(
                [
                    'category_id' => $hourly->id,
                    'name' => $p['name']
                ],
                [
                    'name_ar' => $p['name_ar'],
                    'description' => 'Demo hourly package.',
                    'duration' => $p['duration'],
                    'price' => $p['price'],
                    'status' => 'active',
                    'type' => 'hourly',
                ]
            );
        }

        // Monthly packages
        $monthlyPackages = [
            ['name' => 'General monthly packages, 2 MB, 49 NIS', 'name_ar' => '​​​​​حزم عامة شهري 2 ميجا 49 شيكل', 'duration' => 'شهر', 'price' => 49.00],
        ];

        foreach ($monthlyPackages as $p) {
            Package::firstOrCreate(
                [
                    'category_id' => $monthly->id,
                    'name' => $p['name']
                ],
                [
                    'name_ar' => $p['name_ar'],
                    'description' => 'Demo monthly package.',
                    'duration' => $p['duration'],
                    'price' => $p['price'],
                    'status' => 'active',
                    'type' => 'monthly',
                ]
            );
        }
    }
}
