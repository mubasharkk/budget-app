<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create admin user
        $adminUser = User::firstOrCreate(
            ['email' => 'm.khokhar@social-gizmo.com'],
            [
                'name' => 'Mubashar Khokhar',
                'password' => Hash::make('click123'),
                'email_verified_at' => now(),
            ]
        );

        // Assign admin role to the user
        $adminRole = Role::where('name', 'admin')->first();

        if ($adminRole && !$adminUser->hasRole('admin')) {
            $adminUser->assignRole('admin');
            $this->command->info('Admin user created and assigned admin role');
        } else {
            $this->command->info('Admin user already exists or admin role not found');
        }
    }
}
