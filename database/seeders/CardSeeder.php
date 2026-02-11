<?php

namespace Database\Seeders;

use App\Models\Card;
use App\Models\Package;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CardSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // عدد البطاقات لكل باقة (قابل للتعديل)
        // $cardsPerPackage = 120;

        // $packages = Package::query()->where('status', 'active')->get();

        // foreach ($packages as $package) {
        //     // لا تكرر إذا كان لديك مخزون بالفعل
        //     $existing = Card::where('package_id', $package->id)->count();
        //     if ($existing >= $cardsPerPackage) {
        //         continue;
        //     }

        //     $toCreate = $cardsPerPackage - $existing;

        //     for ($i = 0; $i < $toCreate; $i++) {
        //         Card::create([
        //             'package_id' => $package->id,
        //             'user_id' => null,
        //             'username' => 'U' . Str::upper(Str::random(8)),
        //             'password' => 'P' . Str::upper(Str::random(8)),
        //             'status' => 'available',
        //         ]);
        //     }
        // }
    }
}
