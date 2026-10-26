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
            ['name' => 'Budi Santoso', 'email' => 'project.manager@project.local', 'role' => Roles::PROJECT_MANAGER],
            ['name' => 'Siti Rahayu', 'email' => 'team.leader@project.local', 'role' => Roles::TEAM_LEADER],
            ['name' => 'Andi Wijaya', 'email' => 'member@project.local', 'role' => Roles::MEMBER],
            ['name' => 'Dewi Lestari', 'email' => 'reviewer@project.local', 'role' => Roles::REVIEWER],
            ['name' => 'Rudi Hartono', 'email' => 'client@project.local', 'role' => Roles::CLIENT],
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
