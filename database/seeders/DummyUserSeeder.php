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
            ['name' => 'Budi Santoso', 'email' => 'project.manager@projex.test', 'role' => Roles::PROJECT_MANAGER],
            ['name' => 'Siti Rahayu', 'email' => 'team.leader@projex.test', 'role' => Roles::TEAM_LEADER],
            ['name' => 'Andi Wijaya', 'email' => 'member@projex.test', 'role' => Roles::MEMBER],
            ['name' => 'Dewi Lestari', 'email' => 'reviewer@projex.test', 'role' => Roles::REVIEWER],
            ['name' => 'Rudi Hartono', 'email' => 'client@projex.test', 'role' => Roles::CLIENT],
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
