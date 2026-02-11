<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Permissions
        $permissions = config('system.permissions');

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Roles
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'sanctum']);
        $user  = Role::firstOrCreate(['name' => 'user', 'guard_name' => 'sanctum']);

        // Assign permissions
        $admin->givePermissionTo(Permission::all());

        $user->givePermissionTo([
            'view user dashboard',
            'user create order',
            'user view packages',
            'user show package',
            'user show cards package',
            'user own orders',
            'view card credentials'
        ]);
    }
}
