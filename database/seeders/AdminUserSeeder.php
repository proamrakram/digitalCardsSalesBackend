<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\Roles;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $email = env('DEFAULT_ADMIN_EMAIL', 'admin@example.com');
        $password = env('DEFAULT_ADMIN_PASSWORD', 'ChangeMe_12345');
        $phone = env('DEFAULT_ADMIN_PHONE', '0599916672');

        $admin = User::firstOrCreate(
            [
                'email' => $email,
                'username' => 'admin',
            ],
            [
                'full_name' => 'Eng Amro Akram',
                'phone' => $phone,
                'password' => Hash::make($password),
                'role' => 'admin'
            ]
        );

        $user = User::create([
            'full_name' => 'testing',
            'phone' => "0599916675",
            'password' => Hash::make("mm2139539mm"),
            'role' => 'user',
            'email' => "",
            'username' => 'testing',
        ]);

        if (!$admin->hasRole(Roles::ADMIN)) {
            $admin->syncRoles([Roles::ADMIN]);
        }

        if (!$user->hasRole(Roles::USER)) {
            $user->syncRoles([Roles::USER]);
        }
    }
}
