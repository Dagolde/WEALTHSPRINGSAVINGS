<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Group;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DevelopmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * This seeder creates test data for local development.
     */
    public function run(): void
    {
        $this->command->info('Seeding development data...');

        // Create admin user
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@ajo.test',
            'phone' => '+2348000000001',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
            'kyc_status' => 'verified',
            'wallet_balance' => 100000.00,
            'status' => 'active',
            'role' => 'admin',
        ]);
        $this->command->info("✓ Created admin user: {$admin->email}");

        // Create test users
        $users = User::factory()
            ->count(20)
            ->verified()
            ->withBalance(50000.00)
            ->create();
        $this->command->info("✓ Created {$users->count()} test users");

        // Create some unverified users
        $unverifiedUsers = User::factory()
            ->count(5)
            ->unverified()
            ->create();
        $this->command->info("✓ Created {$unverifiedUsers->count()} unverified users");

        // Create pending groups
        $pendingGroups = Group::factory()
            ->count(5)
            ->create(['created_by' => $users->random()->id]);
        $this->command->info("✓ Created {$pendingGroups->count()} pending groups");

        // Create active groups
        $activeGroups = Group::factory()
            ->count(3)
            ->active()
            ->create(['created_by' => $users->random()->id]);
        $this->command->info("✓ Created {$activeGroups->count()} active groups");

        // Create completed groups
        $completedGroups = Group::factory()
            ->count(2)
            ->completed()
            ->create(['created_by' => $users->random()->id]);
        $this->command->info("✓ Created {$completedGroups->count()} completed groups");

        $this->command->info('');
        $this->command->info('Development data seeded successfully!');
        $this->command->info('');
        $this->command->info('Test Credentials:');
        $this->command->info('  Admin: admin@ajo.test / password');
        $this->command->info('  Users: Use any generated email / password');
    }
}
