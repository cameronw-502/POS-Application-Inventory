<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;

class RoleAndPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            'view users',
            'create users',
            'edit users',
            'delete users',
            // Add inventory permissions
            'view inventory',
            'create inventory',
            'edit inventory',
            'delete inventory',
            // Add more permissions as needed
            'manage api keys',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Create roles and assign permissions
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $adminRole->syncPermissions(Permission::all());

        $userRole = Role::firstOrCreate(['name' => 'user']);
        $userRole->syncPermissions(['view users', 'view inventory']);
        
        // Add a cashier role for POS users
        $cashierRole = Role::firstOrCreate(['name' => 'cashier']);
        $cashierRole->syncPermissions([
            'view inventory',
            'edit inventory', // For updating stock levels
        ]);

        // Assign admin role to our admin user
        $admin = User::where('email', 'test@testemail.com')->first();
        if ($admin) {
            $admin->assignRole('admin');
        }
    }
}
