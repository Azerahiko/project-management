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
     * Seed dummy tasks, activities, and comments for realistic dashboard, calendar, and timeline data.
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

        $allUsers = [$admin, $budi, $siti, $andi, $dewi];

        // 1. Project: Redesain Website Perusahaan
        $project1 = Project::updateOrCreate(
            ['name' => 'Redesain Website Perusahaan'],
            [
                'description' => 'Modernisasi antarmuka portal korporat, peningkatan performa SEO, dan integrasi katalog produk digital.',
                'status' => ProjectStatus::ACTIVE,
                'priority' => ProjectPriority::HIGH,
                'start_date' => Carbon::now()->startOfMonth()->subDays(7),
                'end_date' => Carbon::now()->startOfMonth()->addMonths(2),
                'owner_id' => $admin->id,
            ]
        );

        foreach ([$admin, $budi, $siti, $andi, $dewi] as $u) {
            ProjectMember::updateOrCreate(
                ['project_id' => $project1->id, 'user_id' => $u->id],
                ['role' => $u->id === $admin->id ? ProjectRole::OWNER : ProjectRole::MEMBER, 'joined_at' => now()]
            );
        }

        // 2. Project: Sistem Manajemen Inventaris & Logistik
        $project2 = Project::updateOrCreate(
            ['name' => 'Sistem Manajemen Inventaris & Logistik'],
            [
                'description' => 'Platform pergudangan otomatis dengan barcode scanner, pelacakan stok real-time, dan laporan opname terpadu.',
                'status' => ProjectStatus::ACTIVE,
                'priority' => ProjectPriority::MEDIUM,
                'start_date' => Carbon::now()->startOfMonth()->subDays(12),
                'end_date' => Carbon::now()->startOfMonth()->addMonths(3),
                'owner_id' => $budi->id,
            ]
        );

        foreach ([$admin, $budi, $siti, $andi, $dewi] as $u) {
            ProjectMember::updateOrCreate(
                ['project_id' => $project2->id, 'user_id' => $u->id],
                ['role' => $u->id === $budi->id ? ProjectRole::OWNER : ProjectRole::MEMBER, 'joined_at' => now()]
            );
        }

        // 3. Project: Migrasi Infrastruktur Server Cloud
        $project3 = Project::updateOrCreate(
            ['name' => 'Migrasi Infrastruktur Server Cloud'],
            [
                'description' => 'Peningkatan arsitektur deployment multi-region, kontainerisasi Docker Kubernetes, dan otomatisasi backup data.',
                'status' => ProjectStatus::ACTIVE,
                'priority' => ProjectPriority::CRITICAL,
                'start_date' => Carbon::now()->startOfMonth()->subDays(5),
                'end_date' => Carbon::now()->startOfMonth()->addWeeks(5),
                'owner_id' => $siti->id,
            ]
        );

        foreach ([$admin, $siti, $budi, $dewi] as $u) {
            ProjectMember::updateOrCreate(
                ['project_id' => $project3->id, 'user_id' => $u->id],
                ['role' => $u->id === $siti->id ? ProjectRole::OWNER : ProjectRole::MEMBER, 'joined_at' => now()]
            );
        }

        // 4. Project: Aplikasi Mobile Karyawan Internal
        $project4 = Project::updateOrCreate(
            ['name' => 'Aplikasi Mobile Karyawan Internal'],
            [
                'description' => 'Aplikasi smartphone untuk absensi berbasis geolokasi, pengajuan cuti instan, dan slip gaji digital terenkripsi.',
                'status' => ProjectStatus::ACTIVE,
                'priority' => ProjectPriority::HIGH,
                'start_date' => Carbon::now()->startOfMonth()->addDays(2),
                'end_date' => Carbon::now()->startOfMonth()->addMonths(2)->addWeeks(2),
                'owner_id' => $admin->id,
            ]
        );

        foreach ([$admin, $andi, $budi, $siti] as $u) {
            ProjectMember::updateOrCreate(
                ['project_id' => $project4->id, 'user_id' => $u->id],
                ['role' => $u->id === $admin->id ? ProjectRole::OWNER : ProjectRole::MEMBER, 'joined_at' => now()]
            );
        }

        // 5. Project: Portal Layanan Mandiri Klien
        $project5 = Project::updateOrCreate(
            ['name' => 'Portal Layanan Mandiri Klien'],
            [
                'description' => 'Pusat bantuan tiket terpadu, pemantauan status kontrak kerja, dan penagihan invoice otomatis.',
                'status' => ProjectStatus::ACTIVE,
                'priority' => ProjectPriority::MEDIUM,
                'start_date' => Carbon::now()->startOfMonth()->subWeeks(2),
                'end_date' => Carbon::now()->startOfMonth()->addMonths(1),
                'owner_id' => $budi->id,
            ]
        );

        foreach ([$admin, $budi, $dewi, $andi] as $u) {
            ProjectMember::updateOrCreate(
                ['project_id' => $project5->id, 'user_id' => $u->id],
                ['role' => $u->id === $budi->id ? ProjectRole::OWNER : ProjectRole::MEMBER, 'joined_at' => now()]
            );
        }

        // 6. Project: Sistem Kasir & Point of Sales (POS) Multi-Cabang
        $project6 = Project::updateOrCreate(
            ['name' => 'Sistem Kasir & Point of Sales (POS) Multi-Cabang'],
            [
                'description' => 'Aplikasi kasir offline-first dengan sinkronisasi cloud otomatis, integrasi printer thermal, dan kalkulasi diskon bertingkat.',
                'status' => ProjectStatus::ACTIVE,
                'priority' => ProjectPriority::HIGH,
                'start_date' => Carbon::now()->startOfMonth()->subDays(8),
                'end_date' => Carbon::now()->startOfMonth()->addMonths(2),
                'owner_id' => $siti->id,
            ]
        );

        foreach ([$admin, $siti, $andi, $budi, $dewi] as $u) {
            ProjectMember::updateOrCreate(
                ['project_id' => $project6->id, 'user_id' => $u->id],
                ['role' => $u->id === $siti->id ? ProjectRole::OWNER : ProjectRole::MEMBER, 'joined_at' => now()]
            );
        }

        // 7. Project: Dashboard Analitik & Pelaporan Eksekutif BI
        $project7 = Project::updateOrCreate(
            ['name' => 'Dashboard Analitik & Pelaporan Eksekutif BI'],
            [
                'description' => 'Visualisasi KPI performa penjualan, proyeksi arus kas operasional, dan integrasi data pipeline real-time.',
                'status' => ProjectStatus::ACTIVE,
                'priority' => ProjectPriority::CRITICAL,
                'start_date' => Carbon::now()->startOfMonth()->subDays(15),
                'end_date' => Carbon::now()->startOfMonth()->addMonths(1)->addDays(15),
                'owner_id' => $admin->id,
            ]
        );

        foreach ([$admin, $budi, $siti, $dewi] as $u) {
            ProjectMember::updateOrCreate(
                ['project_id' => $project7->id, 'user_id' => $u->id],
                ['role' => $u->id === $admin->id ? ProjectRole::OWNER : ProjectRole::MEMBER, 'joined_at' => now()]
            );
        }

        // Realistic task data mapped with distributed dates across November 2026 and surrounding months
        $currentMonth = Carbon::now()->startOfMonth();

        $tasksData = [
            // --- Project 1: Redesain Website ---
            [
                'project_id' => $project1->id,
                'title' => 'Inisialisasi Repositori & Desain Sistem Tailwind',
                'description' => 'Setup base styling, token warna, typography Instrument Sans, dan komponen reusable.',
                'status' => TaskStatus::DONE,
                'priority' => TaskPriority::HIGH,
                'assignee_id' => $admin->id,
                'creator_id' => $admin->id,
                'start_date' => $currentMonth->copy()->subDays(6),
                'due_date' => $currentMonth->copy()->addDays(1),
                'completed_at' => $currentMonth->copy()->addDays(1),
            ],
            [
                'project_id' => $project1->id,
                'title' => 'Desain Antarmuka Alur Checkout & Pembayaran',
                'description' => 'Membuat mockup interaktif dan slicing komponen pembayaran dengan integrasi QRIS dan VA.',
                'status' => TaskStatus::IN_PROGRESS,
                'priority' => TaskPriority::HIGH,
                'assignee_id' => $siti->id,
                'creator_id' => $budi->id,
                'start_date' => $currentMonth->copy()->addDays(1),
                'due_date' => $currentMonth->copy()->addDays(3), // Nov 4
            ],
            [
                'project_id' => $project1->id,
                'title' => 'Integrasi API Payment Gateway Midtrans & Xendit',
                'description' => 'Implementasi modul verifikasi webhook dan enkripsi payload transaksi backend.',
                'status' => TaskStatus::IN_PROGRESS,
                'priority' => TaskPriority::URGENT,
                'assignee_id' => $admin->id,
                'creator_id' => $budi->id,
                'start_date' => $currentMonth->copy()->addDays(2),
                'due_date' => $currentMonth->copy()->addDays(5), // Nov 6
            ],
            [
                'project_id' => $project1->id,
                'title' => 'Optimasi Query Database & Konfigurasi Cache Redis',
                'description' => 'Meningkatkan response time API katalog hingga di bawah 150ms dengan index komposit.',
                'status' => TaskStatus::REVIEW,
                'priority' => TaskPriority::MEDIUM,
                'assignee_id' => $andi->id,
                'creator_id' => $siti->id,
                'start_date' => $currentMonth->copy()->addDays(4),
                'due_date' => $currentMonth->copy()->addDays(8), // Nov 9
            ],
            [
                'project_id' => $project1->id,
                'title' => 'Pengujian Kompatibilitas Lintas Browser & Responsif',
                'description' => 'Uji coba performa dan rendering tampilan di Safari, Chrome, Firefox, dan Mobile Webview.',
                'status' => TaskStatus::TODO,
                'priority' => TaskPriority::LOW,
                'assignee_id' => $dewi->id,
                'creator_id' => $siti->id,
                'start_date' => $currentMonth->copy()->addDays(8),
                'due_date' => $currentMonth->copy()->addDays(13), // Nov 14
            ],
            [
                'project_id' => $project1->id,
                'title' => 'Penyusunan Konten Halaman Bantuan & FAQ Interaktif',
                'description' => 'Penulisan artikel dokumentasi solusi kendala umum dan tata cara pemesanan.',
                'status' => TaskStatus::TODO,
                'priority' => TaskPriority::LOW,
                'assignee_id' => $siti->id,
                'creator_id' => $budi->id,
                'start_date' => $currentMonth->copy()->addDays(14),
                'due_date' => $currentMonth->copy()->addDays(19), // Nov 20
            ],
            [
                'project_id' => $project1->id,
                'title' => 'Final Launching & Pengalihan DNS Domain Utama',
                'description' => 'Switching server production, verifikasi sertifikat SSL, dan monitoring uptime pasca rilis.',
                'status' => TaskStatus::TODO,
                'priority' => TaskPriority::URGENT,
                'assignee_id' => $admin->id,
                'creator_id' => $budi->id,
                'start_date' => $currentMonth->copy()->addDays(24),
                'due_date' => $currentMonth->copy()->addDays(29), // Nov 30
            ],

            // --- Project 2: Sistem Inventaris & Logistik ---
            [
                'project_id' => $project2->id,
                'title' => 'Perancangan Skema Database Multi-Gudang & SKU',
                'description' => 'Migrasi skema database relasional dengan constraint foreign key dan audit trail.',
                'status' => TaskStatus::DONE,
                'priority' => TaskPriority::URGENT,
                'assignee_id' => $budi->id,
                'creator_id' => $budi->id,
                'start_date' => $currentMonth->copy()->subDays(10),
                'due_date' => $currentMonth->copy()->addDays(2), // Nov 3
                'completed_at' => $currentMonth->copy()->addDays(2),
            ],
            [
                'project_id' => $project2->id,
                'title' => 'Implementasi Fitur Scan Barcode & QR Code Gudang',
                'description' => 'Integrasi kamera scanner menggunakan library mobile web dan validasi SKU instan.',
                'status' => TaskStatus::IN_PROGRESS,
                'priority' => TaskPriority::HIGH,
                'assignee_id' => $siti->id,
                'creator_id' => $budi->id,
                'start_date' => $currentMonth->copy()->addDays(2),
                'due_date' => $currentMonth->copy()->addDays(6), // Nov 7
            ],
            [
                'project_id' => $project2->id,
                'title' => 'Penyusunan Dokumentasi REST API Swagger & Postman',
                'description' => 'Spesifikasi endpoint otorisasi OAuth2, data barang masuk, mutasi stok, dan laporan.',
                'status' => TaskStatus::REVIEW,
                'priority' => TaskPriority::MEDIUM,
                'assignee_id' => $andi->id,
                'creator_id' => $budi->id,
                'start_date' => $currentMonth->copy()->addDays(5),
                'due_date' => $currentMonth->copy()->addDays(10), // Nov 11
            ],
            [
                'project_id' => $project2->id,
                'title' => 'Modul Penyesuaian Selisih Stok Opname Otomatis',
                'description' => 'Membuat algoritma kalkulasi selisih fisik barang dengan pencatatan pembukuan berkala.',
                'status' => TaskStatus::IN_PROGRESS,
                'priority' => TaskPriority::HIGH,
                'assignee_id' => $dewi->id,
                'creator_id' => $siti->id,
                'start_date' => $currentMonth->copy()->addDays(9),
                'due_date' => $currentMonth->copy()->addDays(15), // Nov 16
            ],
            [
                'project_id' => $project2->id,
                'title' => 'Integrasi Label Printer Bluetooth untuk Nomor Resi',
                'description' => 'Konektivitas Web Bluetooth API ke thermal label printer pengiriman logistik.',
                'status' => TaskStatus::TODO,
                'priority' => TaskPriority::MEDIUM,
                'assignee_id' => $andi->id,
                'creator_id' => $budi->id,
                'start_date' => $currentMonth->copy()->addDays(16),
                'due_date' => $currentMonth->copy()->addDays(21), // Nov 22
            ],
            [
                'project_id' => $project2->id,
                'title' => 'Pelatihan Operator Gudang & Simulasi Transaksi',
                'description' => 'Penyusunan modul pelatihan dan uji coba alur barang masuk-keluar bersama tim operasional.',
                'status' => TaskStatus::TODO,
                'priority' => TaskPriority::LOW,
                'assignee_id' => $budi->id,
                'creator_id' => $admin->id,
                'start_date' => $currentMonth->copy()->addDays(23),
                'due_date' => $currentMonth->copy()->addDays(27), // Nov 28
            ],

            // --- Project 3: Migrasi Infrastruktur Server Cloud ---
            [
                'project_id' => $project3->id,
                'title' => 'Audit Keamanan Arsitektur Jaringan & VPC Peering',
                'description' => 'Pemeriksaan port security group, subnet isolasi, dan sertifikat enkripsi transit data.',
                'status' => TaskStatus::DONE,
                'priority' => TaskPriority::URGENT,
                'assignee_id' => $siti->id,
                'creator_id' => $admin->id,
                'start_date' => $currentMonth->copy()->subDays(4),
                'due_date' => $currentMonth->copy()->addDays(1), // Nov 2
                'completed_at' => $currentMonth->copy()->addDays(1),
            ],
            [
                'project_id' => $project3->id,
                'title' => 'Penyusunan Template Infrastructure as Code (Terraform)',
                'description' => 'Automasi provisioning compute cluster, load balancer, dan database instance.',
                'status' => TaskStatus::IN_PROGRESS,
                'priority' => TaskPriority::HIGH,
                'assignee_id' => $admin->id,
                'creator_id' => $siti->id,
                'start_date' => $currentMonth->copy()->addDays(1),
                'due_date' => $currentMonth->copy()->addDays(4), // Nov 5
            ],
            [
                'project_id' => $project3->id,
                'title' => 'Konfigurasi Monitoring Grafana & Prometheus Alert',
                'description' => 'Setup dashboard metrik real-time CPU, memori, I/O disk, dan pengiriman alert ke Telegram tim.',
                'status' => TaskStatus::REVIEW,
                'priority' => TaskPriority::MEDIUM,
                'assignee_id' => $siti->id,
                'creator_id' => $admin->id,
                'start_date' => $currentMonth->copy()->addDays(6),
                'due_date' => $currentMonth->copy()->addDays(11), // Nov 12
            ],
            [
                'project_id' => $project3->id,
                'title' => 'Simulasi Disaster Recovery & Uji Otomatisasi Backup',
                'description' => 'Skenario failover database replika dan pemulihan snapshot cold storage cloud.',
                'status' => TaskStatus::TODO,
                'priority' => TaskPriority::URGENT,
                'assignee_id' => $admin->id,
                'creator_id' => $siti->id,
                'start_date' => $currentMonth->copy()->addDays(12),
                'due_date' => $currentMonth->copy()->addDays(17), // Nov 18
            ],
            [
                'project_id' => $project3->id,
                'title' => 'Migrasi Database Live dengan Zero Downtime',
                'description' => 'Sinkronisasi replikasi dua arah sebelum proses cutover domain dan DNS publik.',
                'status' => TaskStatus::TODO,
                'priority' => TaskPriority::URGENT,
                'assignee_id' => $admin->id,
                'creator_id' => $admin->id,
                'start_date' => $currentMonth->copy()->addDays(20),
                'due_date' => $currentMonth->copy()->addDays(25), // Nov 26
            ],

            // --- Project 4: Aplikasi Mobile Karyawan Internal ---
            [
                'project_id' => $project4->id,
                'title' => 'Wireframing Alur Presensi Berbasis GPS & Swafoto',
                'description' => 'Perancangan tata letak kamera swafoto, deteksi fake GPS, dan radius kantor.',
                'status' => TaskStatus::DONE,
                'priority' => TaskPriority::HIGH,
                'assignee_id' => $andi->id,
                'creator_id' => $budi->id,
                'start_date' => $currentMonth->copy()->addDays(1),
                'due_date' => $currentMonth->copy()->addDays(3), // Nov 4
                'completed_at' => $currentMonth->copy()->addDays(3),
            ],
            [
                'project_id' => $project4->id,
                'title' => 'Implementasi Modul Autentikasi Biometrik Face ID',
                'description' => 'Pemanfaatan LocalAuthentication API Android dan iOS dengan kunci tersimpan aman.',
                'status' => TaskStatus::IN_PROGRESS,
                'priority' => TaskPriority::HIGH,
                'assignee_id' => $andi->id,
                'creator_id' => $admin->id,
                'start_date' => $currentMonth->copy()->addDays(3),
                'due_date' => $currentMonth->copy()->addDays(7), // Nov 8
            ],
            [
                'project_id' => $project4->id,
                'title' => 'Integrasi Kalender Cuti Karyawan & Notifikasi Push',
                'description' => 'Sistem approval berjenjang kepada atasan langsung dengan notifikasi FCM realtime.',
                'status' => TaskStatus::IN_PROGRESS,
                'priority' => TaskPriority::MEDIUM,
                'assignee_id' => $siti->id,
                'creator_id' => $budi->id,
                'start_date' => $currentMonth->copy()->addDays(7),
                'due_date' => $currentMonth->copy()->addDays(12), // Nov 13
            ],
            [
                'project_id' => $project4->id,
                'title' => 'Enkripsi & Ekspor Slip Gaji Format PDF Berpasangan',
                'description' => 'Pembuatan dokumen PDF slip gaji dengan proteksi kata sandi tanggal lahir karyawan.',
                'status' => TaskStatus::REVIEW,
                'priority' => TaskPriority::HIGH,
                'assignee_id' => $dewi->id,
                'creator_id' => $budi->id,
                'start_date' => $currentMonth->copy()->addDays(11),
                'due_date' => $currentMonth->copy()->addDays(16), // Nov 17
            ],
            [
                'project_id' => $project4->id,
                'title' => 'Uji Coba Beta Internal Karyawan & Rilis TestFlight',
                'description' => 'Distribusi paket APK debug dan build iOS TestFlight untuk pengujian 50 karyawan terpilih.',
                'status' => TaskStatus::TODO,
                'priority' => TaskPriority::MEDIUM,
                'assignee_id' => $budi->id,
                'creator_id' => $admin->id,
                'start_date' => $currentMonth->copy()->addDays(18),
                'due_date' => $currentMonth->copy()->addDays(24), // Nov 25
            ],

            // --- Project 5: Portal Layanan Mandiri Klien ---
            [
                'project_id' => $project5->id,
                'title' => 'Desain Sistem Tiket Bantuan & Form Komplain Pelanggan',
                'description' => 'Kategorisasi tiket prioritas, penugasan otomatis ke agen customer service.',
                'status' => TaskStatus::DONE,
                'priority' => TaskPriority::MEDIUM,
                'assignee_id' => $dewi->id,
                'creator_id' => $budi->id,
                'start_date' => $currentMonth->copy()->subDays(5),
                'due_date' => $currentMonth->copy()->addDays(2), // Nov 3
                'completed_at' => $currentMonth->copy()->addDays(2),
            ],
            [
                'project_id' => $project5->id,
                'title' => 'Modul Tanda Tangan Digital Dokumen Perjanjian (SLA)',
                'description' => 'Integrasi kanvas penandatanganan online dengan audit hash SHA256.',
                'status' => TaskStatus::IN_PROGRESS,
                'priority' => TaskPriority::HIGH,
                'assignee_id' => $budi->id,
                'creator_id' => $admin->id,
                'start_date' => $currentMonth->copy()->addDays(3),
                'due_date' => $currentMonth->copy()->addDays(9), // Nov 10
            ],
            [
                'project_id' => $project5->id,
                'title' => 'Otomatisasi Penerbitan Faktur & Rekapitulasi Pajak PPN',
                'description' => 'Kalkulasi tarif pajak dan pengiriman email tagihan otomatis setiap awal bulan.',
                'status' => TaskStatus::IN_PROGRESS,
                'priority' => TaskPriority::URGENT,
                'assignee_id' => $dewi->id,
                'creator_id' => $budi->id,
                'start_date' => $currentMonth->copy()->addDays(8),
                'due_date' => $currentMonth->copy()->addDays(14), // Nov 15
            ],
            [
                'project_id' => $project5->id,
                'title' => 'Survey Kepuasan Pelanggan (CSAT) & Net Promoter Score',
                'description' => 'Widget popup interaktif saat tiket bantuan diselesaikan oleh agen dukungan.',
                'status' => TaskStatus::TODO,
                'priority' => TaskPriority::LOW,
                'assignee_id' => $andi->id,
                'creator_id' => $dewi->id,
                'start_date' => $currentMonth->copy()->addDays(15),
                'due_date' => $currentMonth->copy()->addDays(21), // Nov 22
            ],
            [
                'project_id' => $project5->id,
                'title' => 'Laporan Analisis Waktu Respon Dukungan & Resolusi Tiket',
                'description' => 'Penyajian grafik KPI rata-rata penanganan masalah pelanggan untuk evaluasi bulanan.',
                'status' => TaskStatus::TODO,
                'priority' => TaskPriority::MEDIUM,
                'assignee_id' => $budi->id,
                'creator_id' => $admin->id,
                'start_date' => $currentMonth->copy()->addDays(22),
                'due_date' => $currentMonth->copy()->addDays(28), // Nov 29
            ],

            // --- Explicit Overdue Tasks (Lewat Tenggat) ---
            [
                'project_id' => $project1->id,
                'title' => 'Audit Lisensi Font & Aset Grafis Korporat',
                'description' => 'Memastikan semua font, ikon grafis, dan aset media berlisensi komersial legal.',
                'status' => TaskStatus::IN_PROGRESS,
                'priority' => TaskPriority::URGENT,
                'assignee_id' => $siti->id,
                'creator_id' => $admin->id,
                'start_date' => Carbon::now()->subDays(6),
                'due_date' => Carbon::now()->subDays(2), // Overdue by 2 days
            ],
            [
                'project_id' => $project2->id,
                'title' => 'Kalibrasi Barcode Scanner Hardware Honeywell',
                'description' => 'Uji akurasi pemindaian label barcode 2D dalam kondisi pencahayaan minim gudang.',
                'status' => TaskStatus::TODO,
                'priority' => TaskPriority::HIGH,
                'assignee_id' => $andi->id,
                'creator_id' => $budi->id,
                'start_date' => Carbon::now()->subDays(7),
                'due_date' => Carbon::now()->subDays(3), // Overdue by 3 days
            ],
            [
                'project_id' => $project3->id,
                'title' => 'Patch Keamanan Kernel Linux CVE-2026-4412',
                'description' => 'Update patch keamanan darurat pada host server cluster staging dan verifikasi log audit.',
                'status' => TaskStatus::IN_PROGRESS,
                'priority' => TaskPriority::URGENT,
                'assignee_id' => $admin->id,
                'creator_id' => $siti->id,
                'start_date' => Carbon::now()->subDays(4),
                'due_date' => Carbon::now()->subDays(1), // Overdue by 1 day
            ],

            // --- Explicit Due Today Tasks (Tenggat Hari Ini) ---
            [
                'project_id' => $project1->id,
                'title' => 'Review PR Final Integrasi API Gateway Midtrans',
                'description' => 'Pemeriksaan kode penanganan callback status pembayaran sukses dan kadaluarsa.',
                'status' => TaskStatus::REVIEW,
                'priority' => TaskPriority::HIGH,
                'assignee_id' => $admin->id,
                'creator_id' => $budi->id,
                'start_date' => Carbon::now()->subDays(2),
                'due_date' => Carbon::now()->startOfDay(), // Due today!
            ],
            [
                'project_id' => $project6->id,
                'title' => 'Pengujian Transaksi Kasir Offline Storage Mode',
                'description' => 'Simulasi pemadaman internet saat kasir menginput 50 transaksi berturut-turut di IndexedDB.',
                'status' => TaskStatus::IN_PROGRESS,
                'priority' => TaskPriority::URGENT,
                'assignee_id' => $andi->id,
                'creator_id' => $siti->id,
                'start_date' => Carbon::now()->subDays(1),
                'due_date' => Carbon::now()->startOfDay(), // Due today!
            ],
            [
                'project_id' => $project7->id,
                'title' => 'Verifikasi Agregasi Data Omset Harian BI Dashboard',
                'description' => 'Validasi pencocokan angka total penjualan harian POS dengan neraca pembukuan akuntansi.',
                'status' => TaskStatus::IN_PROGRESS,
                'priority' => TaskPriority::HIGH,
                'assignee_id' => $dewi->id,
                'creator_id' => $admin->id,
                'start_date' => Carbon::now()->subDays(1),
                'due_date' => Carbon::now()->startOfDay(), // Due today!
            ],

            // --- Project 6: Sistem Kasir & Point of Sales (POS) Multi-Cabang ---
            [
                'project_id' => $project6->id,
                'title' => 'Desain Antarmuka Layar Sentuh Tablet Kasir',
                'description' => 'Tata letak grid produk visual, tombol kuantiti cepat, dan navigasi kategori cepat.',
                'status' => TaskStatus::DONE,
                'priority' => TaskPriority::HIGH,
                'assignee_id' => $siti->id,
                'creator_id' => $siti->id,
                'start_date' => Carbon::now()->subDays(7),
                'due_date' => Carbon::now()->subDays(1),
                'completed_at' => Carbon::now()->subDays(1),
            ],
            [
                'project_id' => $project6->id,
                'title' => 'Integrasi Thermal Printer Bluetooth 58mm & 80mm',
                'description' => 'Protokol cetak ESC/POS untuk receipt nota kasir, cetak logo toko, dan qr payment.',
                'status' => TaskStatus::IN_PROGRESS,
                'priority' => TaskPriority::URGENT,
                'assignee_id' => $andi->id,
                'creator_id' => $siti->id,
                'start_date' => Carbon::now(),
                'due_date' => Carbon::now()->addDays(1), // Tomorrow (soon)
            ],
            [
                'project_id' => $project6->id,
                'title' => 'Fitur Split Bill & Multi-Metode Pembayaran Kasir',
                'description' => 'Mendukung pembayaran parsial tunai ditambah kartu debit atau e-wallet dalam satu invoice.',
                'status' => TaskStatus::IN_PROGRESS,
                'priority' => TaskPriority::HIGH,
                'assignee_id' => $budi->id,
                'creator_id' => $siti->id,
                'start_date' => Carbon::now()->addDays(1),
                'due_date' => Carbon::now()->addDays(2), // 2 days away (soon)
            ],
            [
                'project_id' => $project6->id,
                'title' => 'Modul Manajemen Shift Kasir & Rekonsiliasi Kas Laci',
                'description' => 'Pencatatan kas modal awal, selisih uang tunai saat tutup buku, dan cetak laporan Z.',
                'status' => TaskStatus::TODO,
                'priority' => TaskPriority::MEDIUM,
                'assignee_id' => $dewi->id,
                'creator_id' => $siti->id,
                'start_date' => Carbon::now()->addDays(3),
                'due_date' => Carbon::now()->addDays(6), // 3-6 days away
            ],
            [
                'project_id' => $project6->id,
                'title' => 'Pengujian Beban Sinkronisasi Multi-Cabang Bersamaan',
                'description' => 'Stresstest sync data 20 toko retail secara paralel pada jam sibuk pergantian hari.',
                'status' => TaskStatus::TODO,
                'priority' => TaskPriority::HIGH,
                'assignee_id' => $admin->id,
                'creator_id' => $siti->id,
                'start_date' => Carbon::now()->addDays(8),
                'due_date' => Carbon::now()->addDays(14),
            ],

            // --- Project 7: Dashboard Analitik & Pelaporan Eksekutif BI ---
            [
                'project_id' => $project7->id,
                'title' => 'Rancang Data Warehouse Skema Star Schema',
                'description' => 'Pemodelan tabel dimensi waktu, cabang, produk, dan tabel fakta transaksi penjualan.',
                'status' => TaskStatus::DONE,
                'priority' => TaskPriority::HIGH,
                'assignee_id' => $admin->id,
                'creator_id' => $admin->id,
                'start_date' => Carbon::now()->subDays(10),
                'due_date' => Carbon::now()->subDays(2),
                'completed_at' => Carbon::now()->subDays(2),
            ],
            [
                'project_id' => $project7->id,
                'title' => 'Pipeline ETL Data Penjualan PostgreSQL ke BI Engine',
                'description' => 'Otomatisasi pembersihan dan agregasi data setiap 15 menit menggunakan background worker.',
                'status' => TaskStatus::IN_PROGRESS,
                'priority' => TaskPriority::URGENT,
                'assignee_id' => $admin->id,
                'creator_id' => $admin->id,
                'start_date' => Carbon::now(),
                'due_date' => Carbon::now()->addDays(2), // 2 days away (soon)
            ],
            [
                'project_id' => $project7->id,
                'title' => 'Widget Chart Interaktif Arus Kas & Burn Rate',
                'description' => 'Visualisasi tren pemasukan vs pengeluaran operasional dengan prediksi 30 hari ke depan.',
                'status' => TaskStatus::REVIEW,
                'priority' => TaskPriority::HIGH,
                'assignee_id' => $siti->id,
                'creator_id' => $admin->id,
                'start_date' => Carbon::now()->addDays(1),
                'due_date' => Carbon::now()->addDays(3), // 3 days away (soon)
            ],
            [
                'project_id' => $project7->id,
                'title' => 'Ekspor Laporan PDF Eksekutif Berkala Otomatis',
                'description' => 'Pengiriman ringkasan kinerja mingguan ke email para pemangku kepentingan setiap Senin pagi.',
                'status' => TaskStatus::TODO,
                'priority' => TaskPriority::MEDIUM,
                'assignee_id' => $dewi->id,
                'creator_id' => $admin->id,
                'start_date' => Carbon::now()->addDays(4),
                'due_date' => Carbon::now()->addDays(10),
            ],
            [
                'project_id' => $project7->id,
                'title' => 'Pemberian Akses Berbasis Role (RBAC) Data Finansial',
                'description' => 'Enkripsi kolom sensitif profit margin dan batasan visibilitas level manajemen.',
                'status' => TaskStatus::TODO,
                'priority' => TaskPriority::URGENT,
                'assignee_id' => $budi->id,
                'creator_id' => $admin->id,
                'start_date' => Carbon::now()->addDays(10),
                'due_date' => Carbon::now()->addDays(18),
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

        // Add Realistic Comments
        if (! empty($createdTasks)) {
            TaskComment::firstOrCreate(
                [
                    'task_id' => $createdTasks[1]->id,
                    'user_id' => $siti->id,
                    'content' => 'Mockup checkout sudah disesuaikan dengan instruksi klien. Desain responsif mobile siap direview.',
                ]
            );
            TaskComment::firstOrCreate(
                [
                    'task_id' => $createdTasks[1]->id,
                    'user_id' => $admin->id,
                    'content' => 'Bagus sekali. Pastikan tombol bayar tetap terlihat nyaman di perangkat layar kecil.',
                ]
            );
            TaskComment::firstOrCreate(
                [
                    'task_id' => $createdTasks[8]->id,
                    'user_id' => $andi->id,
                    'content' => 'Scanner barcode kamera sudah berhasil membaca format EAN-13 dan Code-128 dengan akurat.',
                ]
            );
            TaskComment::firstOrCreate(
                [
                    'task_id' => $createdTasks[14]->id,
                    'user_id' => $siti->id,
                    'content' => 'Koneksi ke Prometheus cluster sudah stabil, threshold alert disetel pada 85% penggunaan CPU.',
                ]
            );
        }

        // Create Realistic Task Activities
        if (count($createdTasks) >= 5) {
            $activities = [
                [
                    'task' => $createdTasks[1],
                    'user' => $admin,
                    'type' => 'task.updated',
                    'field' => 'status',
                    'old' => 'todo',
                    'new' => 'in_progress',
                    'desc' => "{$admin->name} memindahkan tugas ke status Sedang Dikerjakan.",
                    'time' => Carbon::now()->subMinutes(15),
                ],
                [
                    'task' => $createdTasks[1],
                    'user' => $siti,
                    'type' => 'comment.created',
                    'field' => null,
                    'old' => null,
                    'new' => null,
                    'desc' => "{$siti->name} mengirimkan catatan kemajuan desain komponen checkout.",
                    'time' => Carbon::now()->subMinutes(50),
                ],
                [
                    'task' => $createdTasks[3],
                    'user' => $andi,
                    'type' => 'task.updated',
                    'field' => 'status',
                    'old' => 'in_progress',
                    'new' => 'review',
                    'desc' => "{$andi->name} mengajukan tugas Optimasi Query untuk ditinjau.",
                    'time' => Carbon::now()->subHours(2),
                ],
                [
                    'task' => $createdTasks[8],
                    'user' => $siti,
                    'type' => 'task.updated',
                    'field' => 'status',
                    'old' => 'todo',
                    'new' => 'in_progress',
                    'desc' => "{$siti->name} memulai pengerjaan modul Scan Barcode Gudang.",
                    'time' => Carbon::now()->subHours(4),
                ],
                [
                    'task' => $createdTasks[0],
                    'user' => $admin,
                    'type' => 'task.updated',
                    'field' => 'status',
                    'old' => 'review',
                    'new' => 'done',
                    'desc' => "{$admin->name} menandai tugas Setup Tailwind selesai.",
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
        }

        // Seed realistic notifications for all users
        foreach ([$admin, $budi, $siti, $andi, $dewi] as $idx => $targetUser) {
            if ($targetUser->notifications()->count() === 0 && ! empty($createdTasks)) {
                $targetTask = $createdTasks[$idx % count($createdTasks)];
                $targetProject = Project::find($targetTask->project_id) ?? $project1;

                $targetUser->notify(new TaskAssigned(
                    $targetTask->id,
                    $targetTask->title,
                    $targetProject->id,
                    $targetProject->name,
                    "seed-notif-assign-{$targetUser->id}"
                ));

                $targetUser->notify(new TaskCommentAdded(
                    $targetTask->id,
                    $targetTask->title,
                    $targetProject->id,
                    $targetProject->name,
                    $targetTask->id,
                    'Detail spesifikasi dan checklist pengerjaan telah diperbarui.',
                    $admin->name
                ));

                $targetUser->notify(new TaskStatusChanged(
                    $targetTask->id,
                    $targetTask->title,
                    $targetProject->id,
                    $targetProject->name,
                    'todo',
                    'in_progress'
                ));

                $targetUser->notify(new ProjectMemberAdded(
                    $targetProject->id,
                    $targetProject->name,
                    'member',
                    $admin->name
                ));
            }
        }

        $this->command?->info('Dummy tasks, projects, calendar and timeline data successfully enriched!');
    }
}
