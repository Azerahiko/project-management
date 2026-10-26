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
            ['email' => 'admin@project.local'],
            [
                'name' => 'System Administrator',
                'email' => 'admin@project.local',
                'password' => Hash::make('password'),
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $admin->syncRoles(Roles::ADMINISTRATOR);

        $this->command?->info('Administrator created: admin@project.local / password');
        $this->command?->warn('⚠  Change the default password before deploying to production!');
    }
}
