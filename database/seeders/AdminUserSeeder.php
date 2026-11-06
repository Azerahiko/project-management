<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\Roles;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Create the default administrator user.
     */
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'System Administrator',
                'email' => 'admin@example.com',
                'password' => Hash::make('password'),
                'is_active' => true,
                'email_verified_at' => now(),
                'onboarding_password_changed_at' => now(),
                'onboarding_completed_at' => now(),
            ]
        );

        $admin->syncRoles(Roles::ADMINISTRATOR);

        $this->command?->info('Administrator created: admin@example.com / password');
        $this->command?->warn('⚠  Change the default password before deploying to production!');
    }
}
