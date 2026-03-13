<?php

namespace Database\Seeders;

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
        // Check if admin already exists
        $existingAdmin = User::where('email', 'admin@ajo.test')->first();

        if ($existingAdmin) {
            // Update existing admin user to have admin role
            $existingAdmin->update(['role' => 'admin']);
            $this->command->info('✓ Updated existing admin user with admin role');
        } else {
            // Create new admin user
            $admin = User::create([
                'name' => 'Admin User',
                'email' => 'admin@ajo.test',
                'phone' => '+2348000000001',
                'password' => Hash::make('password'),
                'kyc_status' => 'verified',
                'wallet_balance' => 0,
                'status' => 'active',
                'role' => 'admin',
                'email_verified_at' => now(),
                'phone_verified_at' => now(),
            ]);

            $this->command->info('✓ Created admin user: ' . $admin->email);
        }

        $this->command->info('  Email: admin@ajo.test');
        $this->command->info('  Password: password');
    }
}
