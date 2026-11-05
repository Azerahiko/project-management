<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\Roles;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DummyUserSeeder extends Seeder
{
    /**
     * Create sample users covering every non-administrator role.
     */
    public function run(): void
    {
        $users = [
            ['name' => 'Budi Santoso', 'email' => 'project.manager@example.com', 'role' => Roles::PROJECT_MANAGER],
            ['name' => 'Siti Rahayu', 'email' => 'team.leader@example.com', 'role' => Roles::TEAM_LEADER],
            ['name' => 'Andi Wijaya', 'email' => 'member@example.com', 'role' => Roles::MEMBER],
            ['name' => 'Dewi Lestari', 'email' => 'reviewer@example.com', 'role' => Roles::REVIEWER],
            ['name' => 'Rudi Hartono', 'email' => 'client@example.com', 'role' => Roles::CLIENT],
        ];

        foreach ($users as $data) {
            $user = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => Hash::make('password'),
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]
            );

            $user->syncRoles($data['role']);
        }

        $this->command?->info('Dummy users seeded (password: password).');
    }
}
