<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Permission;
use Illuminate\Database\Seeder;

class AdminRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create admin role
        $adminRole = Role::firstOrCreate(['name' => 'admin']);

        // Create permissions for admin role
        $permissions = [
            // User management permissions
            'view users',
            'create users',
            'edit users',
            'delete users',
            
            // Receipt management permissions
            'view all receipts',
            'edit all receipts',
            'delete all receipts',
            'process receipts',
            
            // Category management permissions
            'view categories',
            'create categories',
            'edit categories',
            'delete categories',
            
            // System permissions
            'access admin panel',
            'manage settings',
            'view reports',
            'export data',
        ];

        // Create permissions if they don't exist
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Assign all permissions to admin role
        $adminRole->syncPermissions($permissions);

        $this->command->info('Admin role created with permissions: ' . implode(', ', $permissions));
    }
}