<?php

namespace Database\Seeders;

use App\Enums\ProjectPriority;
use App\Enums\ProjectRole;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class ProjectSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::where('email', 'admin@projex.test')->first();
        $pm = User::where('email', 'pm@projex.test')->first();
        $member = User::where('email', 'member@projex.test')->first();

        // Create PM user if not exists
        if (! $pm) {
            $pm = User::create([
                'name' => 'Project Manager',
                'email' => 'pm@projex.test',
                'password' => bcrypt('password'),
                'is_active' => true,
                'email_verified_at' => now(),
            ]);
            $pm->assignRole('project_manager');
        }

        // Create member user if not exists
        if (! $member) {
            $member = User::create([
                'name' => 'Team Member',
                'email' => 'member@projex.test',
                'password' => bcrypt('password'),
                'is_active' => true,
                'email_verified_at' => now(),
            ]);
            $member->assignRole('member');
        }

        // Project 1: Active project owned by admin
        $project1 = Project::updateOrCreate(
            ['name' => 'Redesain Website Perusahaan'],
            [
                'description' => 'Proyek redesign website perusahaan dengan teknologi modern dan UI/UX yang lebih baik.',
                'status' => ProjectStatus::ACTIVE,
                'priority' => ProjectPriority::HIGH,
                'start_date' => Carbon::now()->subWeeks(4),
                'end_date' => Carbon::now()->addWeeks(4),
                'owner_id' => $admin->id,
            ]
        );

        ProjectMember::updateOrCreate(
            ['project_id' => $project1->id, 'user_id' => $admin->id],
            ['role' => ProjectRole::OWNER, 'joined_at' => $project1->created_at]
        );

        ProjectMember::updateOrCreate(
            ['project_id' => $project1->id, 'user_id' => $pm->id],
            ['role' => ProjectRole::MANAGER, 'joined_at' => $project1->created_at]
        );

        ProjectMember::updateOrCreate(
            ['project_id' => $project1->id, 'user_id' => $member->id],
            ['role' => ProjectRole::MEMBER, 'joined_at' => $project1->created_at]
        );

        // Project 2: Draft project owned by PM
        $project2 = Project::updateOrCreate(
            ['name' => 'Sistem Manajemen Inventaris'],
            [
                'description' => 'Pengembangan sistem manajemen inventaris untuk gudang dengan fitur barcode scanning.',
                'status' => ProjectStatus::DRAFT,
                'priority' => ProjectPriority::MEDIUM,
                'start_date' => Carbon::now()->addWeeks(2),
                'end_date' => Carbon::now()->addMonths(3),
                'owner_id' => $pm->id,
            ]
        );

        ProjectMember::updateOrCreate(
            ['project_id' => $project2->id, 'user_id' => $pm->id],
            ['role' => ProjectRole::OWNER, 'joined_at' => $project2->created_at]
        );

        ProjectMember::updateOrCreate(
            ['project_id' => $project2->id, 'user_id' => $member->id],
            ['role' => ProjectRole::MEMBER, 'joined_at' => $project2->created_at]
        );

        // Project 3: Completed project
        $project3 = Project::updateOrCreate(
            ['name' => 'Migrasi Server ke Cloud'],
            [
                'description' => 'Migrasi infrastruktur server on-premise ke cloud provider dengan zero downtime.',
                'status' => ProjectStatus::COMPLETED,
                'priority' => ProjectPriority::CRITICAL,
                'start_date' => Carbon::now()->subMonths(3),
                'end_date' => Carbon::now()->subMonth(),
                'owner_id' => $admin->id,
            ]
        );

        ProjectMember::updateOrCreate(
            ['project_id' => $project3->id, 'user_id' => $admin->id],
            ['role' => ProjectRole::OWNER, 'joined_at' => $project3->created_at]
        );

        ProjectMember::updateOrCreate(
            ['project_id' => $project3->id, 'user_id' => $pm->id],
            ['role' => ProjectRole::MANAGER, 'joined_at' => $project3->created_at]
        );

        // Project 4: On hold project
        $project4 = Project::updateOrCreate(
            ['name' => 'Aplikasi Mobile Internal'],
            [
                'description' => 'Pengembangan aplikasi mobile untuk karyawan internal (absensi, cuti, payroll).',
                'status' => ProjectStatus::ON_HOLD,
                'priority' => ProjectPriority::LOW,
                'start_date' => Carbon::now()->subWeeks(2),
                'end_date' => Carbon::now()->addMonths(4),
                'owner_id' => $pm->id,
            ]
        );

        ProjectMember::updateOrCreate(
            ['project_id' => $project4->id, 'user_id' => $pm->id],
            ['role' => ProjectRole::OWNER, 'joined_at' => $project4->created_at]
        );

        // Project 5: Archived project
        $project5 = Project::updateOrCreate(
            ['name' => 'Laporan Tahunan 2024'],
            [
                'description' => 'Penyusunan laporan performa dan keuangan tahunan 2024.',
                'status' => ProjectStatus::ARCHIVED,
                'priority' => ProjectPriority::MEDIUM,
                'start_date' => Carbon::now()->subMonths(6),
                'end_date' => Carbon::now()->subMonths(5),
                'owner_id' => $admin->id,
            ]
        );

        ProjectMember::updateOrCreate(
            ['project_id' => $project5->id, 'user_id' => $admin->id],
            ['role' => ProjectRole::OWNER, 'joined_at' => $project5->created_at]
        );

        $this->command?->info('Sample projects created with members.');
    }
}
