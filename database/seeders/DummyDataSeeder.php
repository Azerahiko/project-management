<?php

namespace Database\Seeders;

use App\Enums\ProjectPriority;
use App\Enums\ProjectRole;
use App\Enums\ProjectStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskComment;
use App\Models\User;
use App\Notifications\ProjectMemberAdded;
use App\Notifications\TaskAssigned;
use App\Notifications\TaskCommentAdded;
use App\Notifications\TaskStatusChanged;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DummyDataSeeder extends Seeder
{
    /**
     * Seed dummy tasks, activities, and comments for realistic dashboard and workspace data.
     */
    public function run(): void
    {
        $admin = User::where('email', 'admin@project.local')->first();
        if (! $admin) {
            $this->command?->warn('Admin user not found, please run AdminUserSeeder first.');

            return;
        }

        $budi = User::where('email', 'project.manager@project.local')->first()
            ?? User::where('email', 'pm@project.local')->first()
            ?? $admin;

        $siti = User::where('email', 'team.leader@project.local')->first() ?? $admin;
        $andi = User::where('email', 'member@project.local')->first() ?? $admin;
        $dewi = User::where('email', 'reviewer@project.local')->first() ?? $admin;

        // Ensure active project exists
        $project1 = Project::firstOrCreate(
            ['name' => 'Redesain Website Perusahaan'],
            [
                'description' => 'Modernisasi antarmuka portal korporat, peningkatan performa SEO, dan integrasi katalog produk digital.',
                'status' => ProjectStatus::ACTIVE,
                'priority' => ProjectPriority::HIGH,
                'start_date' => Carbon::now()->subWeeks(3),
                'end_date' => Carbon::now()->addWeeks(5),
                'owner_id' => $admin->id,
            ]
        );

        // Ensure project members
        foreach ([$admin, $budi, $siti, $andi] as $u) {
            ProjectMember::firstOrCreate(
                ['project_id' => $project1->id, 'user_id' => $u->id],
                ['role' => $u->id === $admin->id ? ProjectRole::OWNER : ProjectRole::MEMBER, 'joined_at' => now()]
            );
        }

        // Project 2 (Inventory)
        $project2 = Project::firstOrCreate(
            ['name' => 'Sistem Manajemen Inventaris & Logistik'],
            [
                'description' => 'Platform pergudangan otomatis dengan barcode scanner, pelacakan stok real-time, dan laporan opname terpadu.',
                'status' => ProjectStatus::ACTIVE,
                'priority' => ProjectPriority::MEDIUM,
                'start_date' => Carbon::now()->subWeeks(1),
                'end_date' => Carbon::now()->addMonths(2),
                'owner_id' => $budi->id,
            ]
        );

        foreach ([$admin, $budi, $siti, $dewi] as $u) {
            ProjectMember::firstOrCreate(
                ['project_id' => $project2->id, 'user_id' => $u->id],
                ['role' => $u->id === $budi->id ? ProjectRole::OWNER : ProjectRole::MEMBER, 'joined_at' => now()]
            );
        }

        // Define sample tasks
        $tasksData = [
            [
                'project_id' => $project1->id,
                'title' => 'Desain Antarmuka Alur Checkout & Pembayaran',
                'description' => 'Membuat mockup interaktif dan slicing komponen pembayaran dengan integrasi QRIS dan Virtual Account.',
                'status' => TaskStatus::IN_PROGRESS,
                'priority' => TaskPriority::HIGH,
                'assignee_id' => $admin->id,
                'creator_id' => $budi->id,
                'start_date' => Carbon::now()->subDays(3),
                'due_date' => Carbon::now()->addDays(2),
            ],
            [
                'project_id' => $project1->id,
                'title' => 'Integrasi API Payment Gateway Midtrans & Xendit',
                'description' => 'Implementasi modul verifikasi webhook dan enkripsi payload transaksi backend.',
                'status' => TaskStatus::TODO,
                'priority' => TaskPriority::URGENT,
                'assignee_id' => $admin->id,
                'creator_id' => $budi->id,
                'start_date' => Carbon::now(),
                'due_date' => Carbon::now()->addDays(4),
            ],
            [
                'project_id' => $project1->id,
                'title' => 'Optimasi Query Database & Konfigurasi Cache Redis',
                'description' => 'Meningkatkan response time API katalog hingga di bawah 150ms dengan index komposit dan redis tags.',
                'status' => TaskStatus::REVIEW,
                'priority' => TaskPriority::MEDIUM,
                'assignee_id' => $admin->id,
                'creator_id' => $siti->id,
                'start_date' => Carbon::now()->subDays(5),
                'due_date' => Carbon::now()->addDays(5),
            ],
            [
                'project_id' => $project1->id,
                'title' => 'Pembaruan Sertifikat SSL dan Konfigurasi Firewall Cloudflare',
                'description' => 'Audit aturan WAF, pencegahan brute-force rate limiting, dan migrasi DNS tanpa downtime.',
                'status' => TaskStatus::IN_PROGRESS,
                'priority' => TaskPriority::URGENT,
                'assignee_id' => $admin->id,
                'creator_id' => $admin->id,
                'start_date' => Carbon::now()->subDays(4),
                'due_date' => Carbon::now()->subDay(), // Overdue task!
            ],
            [
                'project_id' => $project1->id,
                'title' => 'Inisialisasi Repositori & Konfigurasi Desain Sistem Tailwind',
                'description' => 'Setup base styling, token warna, typography Instrument Sans, dan komponen reusable.',
                'status' => TaskStatus::DONE,
                'priority' => TaskPriority::HIGH,
                'assignee_id' => $admin->id,
                'creator_id' => $admin->id,
                'start_date' => Carbon::now()->subWeeks(2),
                'due_date' => Carbon::now()->subDays(5),
                'completed_at' => Carbon::now()->subDays(5),
            ],
            [
                'project_id' => $project2->id,
                'title' => 'Implementasi Fitur Scan Barcode & QR Code Gudang',
                'description' => 'Integrasi kamera scanner menggunakan library mobile web dan validasi SKU instan.',
                'status' => TaskStatus::IN_PROGRESS,
                'priority' => TaskPriority::HIGH,
                'assignee_id' => $siti->id,
                'creator_id' => $budi->id,
                'start_date' => Carbon::now()->subDays(2),
                'due_date' => Carbon::now()->addDays(3),
            ],
            [
                'project_id' => $project2->id,
                'title' => 'Penyusunan Dokumentasi REST API Swagger & Postman',
                'description' => 'Spesifikasi endpoint otorisasi OAuth2, data barang masuk, mutasi stok, dan laporan bulanan.',
                'status' => TaskStatus::TODO,
                'priority' => TaskPriority::LOW,
                'assignee_id' => $andi->id,
                'creator_id' => $budi->id,
                'start_date' => Carbon::now(),
                'due_date' => Carbon::now()->addWeek(),
            ],
            [
                'project_id' => $project2->id,
                'title' => 'Testing Fungsionalitas Alur Stok Opname Otomatis',
                'description' => 'Membuat unit test dan feature test untuk penyesuaian selisih kuantitas fisik gudang.',
                'status' => TaskStatus::REVIEW,
                'priority' => TaskPriority::MEDIUM,
                'assignee_id' => $dewi->id,
                'creator_id' => $siti->id,
                'start_date' => Carbon::now()->subDays(3),
                'due_date' => Carbon::now()->addDays(4),
            ],
            [
                'project_id' => $project2->id,
                'title' => 'Perancangan Skema Database Multi-Gudang & Relasi SKU',
                'description' => 'Migrasi skema database relasional MySQL dengan constraint foreign key dan audit trail.',
                'status' => TaskStatus::DONE,
                'priority' => TaskPriority::URGENT,
                'assignee_id' => $budi->id,
                'creator_id' => $budi->id,
                'start_date' => Carbon::now()->subWeeks(1),
                'due_date' => Carbon::now()->subDays(2),
                'completed_at' => Carbon::now()->subDays(2),
            ],
        ];

        $createdTasks = [];
        foreach ($tasksData as $data) {
            $task = Task::updateOrCreate(
                [
                    'project_id' => $data['project_id'],
                    'title' => $data['title'],
                ],
                $data
            );
            $createdTasks[] = $task;
        }

        // Create Task Comments
        TaskComment::firstOrCreate(
            [
                'task_id' => $createdTasks[0]->id,
                'user_id' => $siti->id,
                'content' => 'Desain komponen checkout sudah diselaraskan dengan standar brand. Tombol konfirmasi sudah diperjelas.',
            ]
        );
        TaskComment::firstOrCreate(
            [
                'task_id' => $createdTasks[0]->id,
                'user_id' => $admin->id,
                'content' => 'Terima kasih, sedang saya integrasikan dengan flow validasi form di frontend.',
            ]
        );

        // Create Realistic Task Activities
        $activities = [
            [
                'task' => $createdTasks[0],
                'user' => $admin,
                'type' => 'task.updated',
                'field' => 'status',
                'old' => 'todo',
                'new' => 'in_progress',
                'desc' => "{$admin->name} mengubah status tugas menjadi Sedang Dikerjakan.",
                'time' => Carbon::now()->subMinutes(12),
            ],
            [
                'task' => $createdTasks[0],
                'user' => $siti,
                'type' => 'comment.created',
                'field' => null,
                'old' => null,
                'new' => null,
                'desc' => "{$siti->name} menambahkan catatan feedback pada desain checkout.",
                'time' => Carbon::now()->subMinutes(45),
            ],
            [
                'task' => $createdTasks[2],
                'user' => $admin,
                'type' => 'task.updated',
                'field' => 'status',
                'old' => 'in_progress',
                'new' => 'review',
                'desc' => "{$admin->name} mengajukan tugas Optimasi Query untuk ditinjau.",
                'time' => Carbon::now()->subHours(2),
            ],
            [
                'task' => $createdTasks[5],
                'user' => $budi,
                'type' => 'task.updated',
                'field' => 'priority',
                'old' => 'medium',
                'new' => 'high',
                'desc' => "{$budi->name} meningkatkan prioritas tugas Scan Barcode menjadi Tinggi.",
                'time' => Carbon::now()->subHours(4),
            ],
            [
                'task' => $createdTasks[4],
                'user' => $admin,
                'type' => 'task.updated',
                'field' => 'status',
                'old' => 'review',
                'new' => 'done',
                'desc' => "{$admin->name} menyelesaikan tugas Inisialisasi Repositori & Tailwind.",
                'time' => Carbon::now()->subDays(1),
            ],
        ];

        foreach ($activities as $act) {
            TaskActivity::firstOrCreate(
                [
                    'task_id' => $act['task']->id,
                    'user_id' => $act['user']->id,
                    'activity_type' => $act['type'],
                    'description' => $act['desc'],
                ],
                [
                    'field' => $act['field'],
                    'old_value' => $act['old'],
                    'new_value' => $act['new'],
                    'created_at' => $act['time'],
                ]
            );
        }

        // Seed realistic workspace notifications for users
        foreach ([$admin, $budi, $siti, $andi] as $idx => $targetUser) {
            if ($targetUser->notifications()->count() === 0) {
                $targetUser->notify(new TaskAssigned(
                    $createdTasks[$idx % count($createdTasks)]->id,
                    $createdTasks[$idx % count($createdTasks)]->title,
                    $project1->id,
                    $project1->name,
                    "seed-notif-assign-{$targetUser->id}"
                ));

                $targetUser->notify(new TaskCommentAdded(
                    $createdTasks[($idx + 1) % count($createdTasks)]->id,
                    $createdTasks[($idx + 1) % count($createdTasks)]->title,
                    $project1->id,
                    $project1->name,
                    $idx + 1,
                    'Dokumentasi teknis alur tugas telah diperbarui.',
                    $admin->name
                ));

                $targetUser->notify(new TaskStatusChanged(
                    $createdTasks[($idx + 2) % count($createdTasks)]->id,
                    $createdTasks[($idx + 2) % count($createdTasks)]->title,
                    $project1->id,
                    $project1->name,
                    'todo',
                    'in_progress'
                ));

                $targetUser->notify(new ProjectMemberAdded(
                    $project1->id,
                    $project1->name,
                    'member',
                    $admin->name
                ));
            }
        }

        $this->command?->info('Dummy tasks, comments, and activities successfully seeded!');
    }
}
