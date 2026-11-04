<?php

namespace Database\Seeders;

use App\Enums\ProjectPriority;
use App\Enums\ProjectRole;
use App\Enums\ProjectStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Announcement;
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

class EnhancedDummySeeder extends Seeder
{
    /**
     * Seed extensive dummy data for all pages: dashboard, tasks, projects, calendar, timeline, collaboration.
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
        $rudi = User::where('email', 'client@project.local')->first() ?? $admin;

        $allUsers = [$admin, $budi, $siti, $andi, $dewi, $rudi];
        $coreTeam = [$admin, $budi, $siti, $andi, $dewi];

        // ============ ADDITIONAL USERS ============
        $extraUsers = [];
        $extraUserData = [
            ['name' => 'Ahmad Fauzi', 'email' => 'ahmad.fauzi@project.local', 'role' => 'member'],
            ['name' => 'Rina Marlina', 'email' => 'rina.marlina@project.local', 'role' => 'member'],
            ['name' => 'Doni Pratama', 'email' => 'doni.pratama@project.local', 'role' => 'member'],
            ['name' => 'Sari Indah', 'email' => 'sari.indah@project.local', 'role' => 'member'],
            ['name' => 'Bambang Wijaya', 'email' => 'bambang.wijaya@project.local', 'role' => 'team_leader'],
            ['name' => 'Lestari Putri', 'email' => 'lestari.putri@project.local', 'role' => 'reviewer'],
        ];

        foreach ($extraUserData as $data) {
            $user = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => bcrypt('password'),
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]
            );
            $user->syncRoles($data['role']);
            $extraUsers[] = $user;
        }

        $extendedTeam = array_merge($coreTeam, $extraUsers);

        // ============ ADDITIONAL PROJECTS (8-15) ============
        $projectsData = [
            [
                'name' => 'Platform E-Learning Korporat',
                'description' => 'Sistem manajemen pembelajaran internal dengan video streaming, kuis interaktif, sertifikasi, dan pelacakan progres karyawan.',
                'status' => ProjectStatus::ACTIVE,
                'priority' => ProjectPriority::HIGH,
                'start_date' => Carbon::now()->subDays(20),
                'end_date' => Carbon::now()->addMonths(3),
                'owner_id' => $budi->id,
                'members' => [$budi, $siti, $andi, $dewi, $extraUsers[0], $extraUsers[1]],
            ],
            [
                'name' => 'Sistem HRIS Terintegrasi Payroll',
                'description' => 'Modul kepegawaian lengkap: rekrutmen, onboarding, evaluasi performa, cuti, dan perhitungan gaji otomatis dengan BPJS & PPh21.',
                'status' => ProjectStatus::ACTIVE,
                'priority' => ProjectPriority::CRITICAL,
                'start_date' => Carbon::now()->subDays(30),
                'end_date' => Carbon::now()->addMonths(2),
                'owner_id' => $admin->id,
                'members' => [$admin, $budi, $dewi, $extraUsers[2], $extraUsers[4]],
            ],
            [
                'name' => 'Aplikasi CRM Penjualan B2B',
                'description' => 'Customer relationship management untuk tim sales: pipeline deal, manajemen kontak, forecasting, dan integrasi WhatsApp Business API.',
                'status' => ProjectStatus::ACTIVE,
                'priority' => ProjectPriority::HIGH,
                'start_date' => Carbon::now()->subDays(15),
                'end_date' => Carbon::now()->addMonths(4),
                'owner_id' => $siti->id,
                'members' => [$siti, $budi, $andi, $extraUsers[0], $extraUsers[3], $extraUsers[5]],
            ],
            [
                'name' => 'Modernisasi Sistem Legacy Accounting',
                'description' => 'Migrasi sistem akuntansi lama ke arsitektur microservices dengan API gateway, event-driven, dan compliance PSAK terbaru.',
                'status' => ProjectStatus::ACTIVE,
                'priority' => ProjectPriority::CRITICAL,
                'start_date' => Carbon::now()->subDays(40),
                'end_date' => Carbon::now()->addMonths(5),
                'owner_id' => $admin->id,
                'members' => [$admin, $siti, $dewi, $extraUsers[1], $extraUsers[4]],
            ],
            [
                'name' => 'Portal Vendor & Procurement Digital',
                'description' => 'E-procurement end-to-end: pengajuan pembelian, tender, evaluasi vendor, PO digital, dan three-way matching invoice.',
                'status' => ProjectStatus::DRAFT,
                'priority' => ProjectPriority::MEDIUM,
                'start_date' => Carbon::now()->addWeeks(2),
                'end_date' => Carbon::now()->addMonths(4),
                'owner_id' => $budi->id,
                'members' => [$budi, $andi, $extraUsers[2]],
            ],
            [
                'name' => 'Sistem Manajemen Aset & Inventaris IT',
                'description' => 'Lifecycle management hardware/software: procurement, deployment, maintenance, lisensi, dan disposal dengan barcode tracking.',
                'status' => ProjectStatus::ACTIVE,
                'priority' => ProjectPriority::MEDIUM,
                'start_date' => Carbon::now()->subDays(10),
                'end_date' => Carbon::now()->addMonths(2),
                'owner_id' => $dewi->id,
                'members' => [$dewi, $andi, $extraUsers[0], $extraUsers[3]],
            ],
            [
                'name' => 'Platform Komunikasi Internal (Slack-like)',
                'description' => 'Real-time messaging, channel terstruktur, voice/video call, file sharing, dan integrasi webhook untuk notifikasi sistem.',
                'status' => ProjectStatus::ON_HOLD,
                'priority' => ProjectPriority::LOW,
                'start_date' => Carbon::now()->subWeeks(3),
                'end_date' => Carbon::now()->addMonths(6),
                'owner_id' => $siti->id,
                'members' => [$siti, $andi, $extraUsers[1], $extraUsers[5]],
            ],
            [
                'name' => 'Dashboard ESG & Sustainability Reporting',
                'description' => 'Pelacakan emisi karbon, konsumsi energi, waste management, dan laporan keberlanjutan sesuai standar GRI & OJK.',
                'status' => ProjectStatus::ACTIVE,
                'priority' => ProjectPriority::HIGH,
                'start_date' => Carbon::now()->subDays(5),
                'end_date' => Carbon::now()->addMonths(3),
                'owner_id' => $admin->id,
                'members' => [$admin, $budi, $dewi, $extraUsers[4], $extraUsers[5]],
            ],
        ];

        $createdProjects = [];
        foreach ($projectsData as $pData) {
            $members = $pData['members'];
            unset($pData['members']);

            $project = Project::updateOrCreate(
                ['name' => $pData['name']],
                $pData
            );

            foreach ($members as $idx => $u) {
                ProjectMember::updateOrCreate(
                    ['project_id' => $project->id, 'user_id' => $u->id],
                    ['role' => $idx === 0 ? ProjectRole::OWNER : ProjectRole::MEMBER, 'joined_at' => now()]
                );
            }
            $createdProjects[] = $project;
        }

        // ============ EXTENSIVE TASK DATA ============
        $currentMonth = Carbon::now()->startOfMonth();
        $allProjects = array_merge(
            Project::whereIn('name', [
                'Redesain Website Perusahaan',
                'Sistem Manajemen Inventaris & Logistik',
                'Migrasi Infrastruktur Server Cloud',
                'Aplikasi Mobile Karyawan Internal',
                'Portal Layanan Mandiri Klien',
                'Sistem Kasir & Point of Sales (POS) Multi-Cabang',
                'Dashboard Analitik & Pelaporan Eksekutif BI',
            ])->get()->toArray(),
            $createdProjects
        );

        // Helper to get project by name
        $getProject = fn (string $name) => collect($allProjects)->firstWhere('name', $name)['id'] ?? $allProjects[0]['id'];

        $tasksData = [
            // ===== E-Learning Platform =====
            ['project' => 'Platform E-Learning Korporat', 'title' => 'Arsitektur Database Multi-Tenant Course', 'desc' => 'Desain skema kursus, enrollment, progress tracking, dan certificate dengan isolasi data per divisi.', 'status' => TaskStatus::DONE, 'priority' => TaskPriority::HIGH, 'assignee' => $budi, 'creator' => $budi, 'start' => -25, 'due' => -15, 'completed' => -15],
            ['project' => 'Platform E-Learning Korporat', 'title' => 'Integrasi Video Streaming (Mux/Cloudflare Stream)', 'desc' => 'Implementasi adaptive bitrate streaming, DRM, dan analytics engagement video pembelajaran.', 'status' => TaskStatus::IN_PROGRESS, 'priority' => TaskPriority::URGENT, 'assignee' => $siti, 'creator' => $budi, 'start' => -10, 'due' => 3],
            ['project' => 'Platform E-Learning Korporat', 'title' => 'Engine Kuis & Asesmen Adaptif', 'desc' => 'Logika soal acak, bobot nilai, passing grade dinamis, dan feedback otomatis per jawaban.', 'status' => TaskStatus::IN_PROGRESS, 'priority' => TaskPriority::HIGH, 'assignee' => $andi, 'creator' => $siti, 'start' => -5, 'due' => 8],
            ['project' => 'Platform E-Learning Korporat', 'title' => 'Sistem Sertifikat Digital dengan QR Verifikasi', 'desc' => 'Generate PDF sertifikat tamper-proof, QR code verifikasi publik, dan integrasi blockchain anchor.', 'status' => TaskStatus::REVIEW, 'priority' => TaskPriority::HIGH, 'assignee' => $dewi, 'creator' => $siti, 'start' => 2, 'due' => 12],
            ['project' => 'Platform E-Learning Korporat', 'title' => 'Dashboard Analytics Pembelajaran per Divisi', 'desc' => 'Visualisasi completion rate, average score, time spent, dan skill gap analysis untuk manajemen.', 'status' => TaskStatus::TODO, 'priority' => TaskPriority::MEDIUM, 'assignee' => $extraUsers[0], 'creator' => $budi, 'start' => 10, 'due' => 22],
            ['project' => 'Platform E-Learning Korporat', 'title' => 'Mobile Offline Sync untuk Kursus', 'desc' => 'Service worker caching, IndexedDB storage, dan background sync saat koneksi pulih untuk karyawan lapangan.', 'status' => TaskStatus::TODO, 'priority' => TaskPriority::MEDIUM, 'assignee' => $extraUsers[1], 'creator' => $siti, 'start' => 15, 'due' => 30],

            // ===== HRIS Payroll =====
            ['project' => 'Sistem HRIS Terintegrasi Payroll', 'title' => 'Modul Rekrutmen & ATS (Applicant Tracking)', 'desc' => 'Job posting, pipeline kandidat, interview scheduling, offer letter generator, dan onboarding checklist.', 'status' => TaskStatus::DONE, 'priority' => TaskPriority::HIGH, 'assignee' => $admin, 'creator' => $admin, 'start' => -35, 'due' => -20, 'completed' => -20],
            ['project' => 'Sistem HRIS Terintegrasi Payroll', 'title' => 'Kalkulasi Payroll Otomatis BPJS & PPh21', 'desc' => 'Engine perhitungan gaji pokok, tunjangan, overtime, BPJS Kesehatan/Ketenagakerjaan, dan PPh21 terbaru 2026.', 'status' => TaskStatus::IN_PROGRESS, 'priority' => TaskPriority::URGENT, 'assignee' => $dewi, 'creator' => $budi, 'start' => -15, 'due' => 5],
            ['project' => 'Sistem HRIS Terintegrasi Payroll', 'title' => 'Sistem Evaluasi Performa (OKR/KPI/360)', 'desc' => 'Goal setting, mid-year review, 360 feedback, calibration session, dan hasil terkait kenaikan gaji.', 'status' => TaskStatus::IN_PROGRESS, 'priority' => TaskPriority::HIGH, 'assignee' => $extraUsers[4], 'creator' => $admin, 'start' => -5, 'due' => 15],
            ['project' => 'Sistem HRIS Terintegrasi Payroll', 'title' => 'Self-Service Portal Cuti & Reimbursement', 'desc' => 'Pengajuan cuti multi-level approval, upload bukti reimburse, dan real-time balance tracking.', 'status' => TaskStatus::REVIEW, 'priority' => TaskPriority::MEDIUM, 'assignee' => $extraUsers[2], 'creator' => $dewi, 'start' => 5, 'due' => 18],
            ['project' => 'Sistem HRIS Terintegrasi Payroll', 'title' => 'Integrasi Bank untuk Transfer Gaji Massal', 'desc' => 'Format file transfer BCA/Mandiri/BRI, validasi rekening, dan reconciliation otomatis post-transfer.', 'status' => TaskStatus::TODO, 'priority' => TaskPriority::URGENT, 'assignee' => $budi, 'creator' => $admin, 'start' => 12, 'due' => 20],
            ['project' => 'Sistem HRIS Terintegrasi Payroll', 'title' => 'Laporan Wajib Lapor (SPT Masa, Tahunan, DTP)', 'desc' => 'Generate e-SPT PPh21, laporan realisasi insentif PPh21 DTP, dan e-Filing ke DJP.', 'status' => TaskStatus::TODO, 'priority' => TaskPriority::HIGH, 'assignee' => $dewi, 'creator' => $budi, 'start' => 20, 'due' => 35],

            // ===== CRM B2B =====
            ['project' => 'Aplikasi CRM Penjualan B2B', 'title' => 'Pipeline Deal Management dengan Kanban', 'desc' => 'Stage kustomisasi, probability weighting, deal aging, dan auto-action berdasarkan inactivity.', 'status' => TaskStatus::DONE, 'priority' => TaskPriority::HIGH, 'assignee' => $siti, 'creator' => $siti, 'start' => -20, 'due' => -8, 'completed' => -8],
            ['project' => 'Aplikasi CRM Penjualan B2B', 'title' => 'WhatsApp Business API Integration', 'desc' => 'Template message approval, chatbot FAQ, broadcast campaign, dan chat assignment ke sales.', 'status' => TaskStatus::IN_PROGRESS, 'priority' => TaskPriority::URGENT, 'assignee' => $andi, 'creator' => $budi, 'start' => -8, 'due' => 4],
            ['project' => 'Aplikasi CRM Penjualan B2B', 'title' => 'Email Tracking & Sequence Automation', 'desc' => 'Pixel tracking open/click, drip campaign builder, A/B testing subject, dan unsubscribe management.', 'status' => TaskStatus::IN_PROGRESS, 'priority' => TaskPriority::HIGH, 'assignee' => $extraUsers[3], 'creator' => $siti, 'start' => -3, 'due' => 10],
            ['project' => 'Aplikasi CRM Penjualan B2B', 'title' => 'Forecasting & Revenue Intelligence', 'desc' => 'AI-weighted pipeline forecast, win/loss analysis, competitor tracking, dan quota attainment dashboard.', 'status' => TaskStatus::REVIEW, 'priority' => TaskPriority::HIGH, 'assignee' => $extraUsers[0], 'creator' => $budi, 'start' => 3, 'due' => 14],
            ['project' => 'Aplikasi CRM Penjualan B2B', 'title' => 'Mobile App Sales Force (React Native)', 'desc' => 'Offline-first CRM mobile, geolocation check-in, voice note ke deal, dan camera scan business card.', 'status' => TaskStatus::TODO, 'priority' => TaskPriority::MEDIUM, 'assignee' => $extraUsers[5], 'creator' => $siti, 'start' => 12, 'due' => 28],
            ['project' => 'Aplikasi CRM Penjualan B2B', 'title' => 'Integrasi ERP untuk Quote-to-Cash', 'desc' => 'Sinkronisasi produk, harga, stok, dan pembuatan SO/Invoice otomatis dari closed-won deal.', 'status' => TaskStatus::TODO, 'priority' => TaskPriority::HIGH, 'assignee' => $budi, 'creator' => $admin, 'start' => 18, 'due' => 35],

            // ===== Legacy Accounting Modernization =====
            ['project' => 'Modernisasi Sistem Legacy Accounting', 'title' => 'Strangler Fig Pattern Migration Strategy', 'desc' => 'Identifikasi bounded context, API facade layer, dan paralel run old/new system dengan feature flags.', 'status' => TaskStatus::DONE, 'priority' => TaskPriority::URGENT, 'assignee' => $admin, 'creator' => $admin, 'start' => -45, 'due' => -25, 'completed' => -25],
            ['project' => 'Modernisasi Sistem Legacy Accounting', 'title' => 'Chart of Accounts Multi-Entity Redesign', 'desc' => 'Konsolidasi COA grup, mapping legacy ke new, dimension-based reporting, dan intercompany elimination.', 'status' => TaskStatus::IN_PROGRESS, 'priority' => TaskPriority::URGENT, 'assignee' => $siti, 'creator' => $admin, 'start' => -20, 'due' => 10],
            ['project' => 'Modernisasi Sistem Legacy Accounting', 'title' => 'Journal Entry Engine dengan Approval Workflow', 'desc' => 'Recurring journal, reversing entry, multi-currency revaluation, dan maker-checker approval matrix.', 'status' => TaskStatus::IN_PROGRESS, 'priority' => TaskPriority::HIGH, 'assignee' => $dewi, 'creator' => $siti, 'start' => -10, 'due' => 15],
            ['project' => 'Modernisasi Sistem Legacy Accounting', 'title' => 'Fixed Asset Management & Depreciation', 'desc' => 'Asset register, multiple depreciation methods, impairment testing, dan disposal gain/loss calculation.', 'status' => TaskStatus::REVIEW, 'priority' => TaskPriority::HIGH, 'assignee' => $extraUsers[1], 'creator' => $dewi, 'start' => 0, 'due' => 12],
            ['project' => 'Modernisasi Sistem Legacy Accounting', 'title' => 'Tax Compliance Engine (PPN, PPh23, PPh4(2))', 'desc' => 'E-Faktur integration, bukti potong otomatis, SPT masa PPN, dan koreksi nilai transaksi spesial.', 'status' => TaskStatus::TODO, 'priority' => TaskPriority::URGENT, 'assignee' => $extraUsers[4], 'creator' => $siti, 'start' => 8, 'due' => 22],
            ['project' => 'Modernisasi Sistem Legacy Accounting', 'title' => 'Financial Consolidation & Elimination', 'desc' => 'Multi-entity consolidation, currency translation, intercompany matching, dan elimination entries.', 'status' => TaskStatus::TODO, 'priority' => TaskPriority::HIGH, 'assignee' => $dewi, 'creator' => $admin, 'start' => 20, 'due' => 40],

            // ===== E-Procurement =====
            ['project' => 'Portal Vendor & Procurement Digital', 'title' => 'Vendor Registration & Qualification Portal', 'desc' => 'Self-registration vendor, document verification, risk scoring, dan approved vendor list (AVL).', 'status' => TaskStatus::TODO, 'priority' => TaskPriority::HIGH, 'assignee' => $budi, 'creator' => $budi, 'start' => 15, 'due' => 30],
            ['project' => 'Portal Vendor & Procurement Digital', 'title' => 'E-Tender & Reverse Auction Engine', 'desc' => 'RFP/RFQ publishing, bid submission, evaluation matrix, dan real-time reverse auction untuk kategori komoditas.', 'status' => TaskStatus::TODO, 'priority' => TaskPriority::HIGH, 'assignee' => $andi, 'creator' => $budi, 'start' => 25, 'due' => 45],
            ['project' => 'Portal Vendor & Procurement Digital', 'title' => 'Purchase Order Digital dengan 3-Way Match', 'desc' => 'PO generation, goods receipt (GR), invoice verification, dan auto-payment release post-match.', 'status' => TaskStatus::TODO, 'priority' => TaskPriority::MEDIUM, 'assignee' => $extraUsers[2], 'creator' => $budi, 'start' => 35, 'due' => 55],
            ['project' => 'Portal Vendor & Procurement Digital', 'title' => 'Contract Lifecycle Management', 'desc' => 'Authoring template, redlining, e-signature, amendment tracking, expiry alert, dan compliance checklist.', 'status' => TaskStatus::TODO, 'priority' => TaskPriority::MEDIUM, 'assignee' => $budi, 'creator' => $admin, 'start' => 45, 'due' => 70],

            // ===== IT Asset Management =====
            ['project' => 'Sistem Manajemen Aset & Inventaris IT', 'title' => 'Hardware Lifecycle: Procure to Retire', 'desc' => 'Purchase request, PO, receiving, deployment, maintenance scheduling, dan disposal certificate.', 'status' => TaskStatus::IN_PROGRESS, 'priority' => TaskPriority::HIGH, 'assignee' => $dewi, 'creator' => $dewi, 'start' => -8, 'due' => 5],
            ['project' => 'Sistem Manajemen Aset & Inventaris IT', 'title' => 'Software License Compliance & Optimization', 'desc' => 'Discovery installed software, license entitlement tracking, true-up preparation, dan SaaS spend optimization.', 'status' => TaskStatus::IN_PROGRESS, 'priority' => TaskPriority::HIGH, 'assignee' => $extraUsers[0], 'creator' => $dewi, 'start' => -5, 'due' => 8],
            ['project' => 'Sistem Manajemen Aset & Inventaris IT', 'title' => 'Barcode/QR Asset Tagging & Mobile Audit', 'desc' => 'Print label asset, mobile app scan untuk physical audit, discrepancy reporting, dan GPS location tagging.', 'status' => TaskStatus::REVIEW, 'priority' => TaskPriority::MEDIUM, 'assignee' => $extraUsers[3], 'creator' => $andi, 'start' => 2, 'due' => 10],
            ['project' => 'Sistem Manajemen Aset & Inventaris IT', 'title' => 'IT Service Catalog & Request Fulfillment', 'desc' => 'Standardized request models (laptop, access, software), SLA per category, dan approval workflow.', 'status' => TaskStatus::TODO, 'priority' => TaskPriority::MEDIUM, 'assignee' => $andi, 'creator' => $dewi, 'start' => 10, 'due' => 22],
            ['project' => 'Sistem Manajemen Aset & Inventaris IT', 'title' => 'CMDB & Dependency Mapping', 'desc' => 'Configuration items, relationship mapping (server-app-db), impact analysis untuk change management.', 'status' => TaskStatus::TODO, 'priority' => TaskPriority::HIGH, 'assignee' => $siti, 'creator' => $dewi, 'start' => 18, 'due' => 35],

            // ===== Internal Communication Platform =====
            ['project' => 'Platform Komunikasi Internal (Slack-like)', 'title' => 'Real-time Messaging Core (WebSocket/Server-Sent Events)', 'desc' => 'Channel, DM, thread, reaction, mention, search, dan message retention policy.', 'status' => TaskStatus::IN_PROGRESS, 'priority' => TaskPriority::HIGH, 'assignee' => $siti, 'creator' => $siti, 'start' => -25, 'due' => 15],
            ['project' => 'Platform Komunikasi Internal (Slack-like)', 'title' => 'Voice/Video Call dengan WebRTC', 'desc' => '1-on-1 call, group call, screen share, recording, dan bandwidth adaptation.', 'status' => TaskStatus::TODO, 'priority' => TaskPriority::MEDIUM, 'assignee' => $extraUsers[1], 'creator' => $siti, 'start' => 30, 'due' => 60],
            ['project' => 'Platform Komunikasi Internal (Slack-like)', 'title' => 'Bot Framework & Workflow Automation', 'desc' => 'Slash command, interactive component, scheduled reminder, dan integration webhook inbound/outbound.', 'status' => TaskStatus::TODO, 'priority' => TaskPriority::LOW, 'assignee' => $extraUsers[5], 'creator' => $andi, 'start' => 40, 'due' => 70],
            ['project' => 'Platform Komunikasi Internal (Slack-like)', 'title' => 'Data Residency & Compliance (UU PDP)', 'desc' => 'Encryption at rest/transit, data localization, audit log, retention policy, dan DSR workflow.', 'status' => TaskStatus::TODO, 'priority' => TaskPriority::HIGH, 'assignee' => $dewi, 'creator' => $admin, 'start' => 20, 'due' => 45],

            // ===== ESG Dashboard =====
            ['project' => 'Dashboard ESG & Sustainability Reporting', 'title' => 'Carbon Footprint Calculation Engine (Scope 1/2/3)', 'desc' => 'Emission factor database, activity data collection, calculation methodology GHG Protocol, dan uncertainty analysis.', 'status' => TaskStatus::IN_PROGRESS, 'priority' => TaskPriority::URGENT, 'assignee' => $admin, 'creator' => $admin, 'start' => -5, 'due' => 10],
            ['project' => 'Dashboard ESG & Sustainability Reporting', 'title' => 'Energy & Water Consumption Monitoring', 'desc' => 'IoT meter integration, real-time dashboard, anomaly detection, dan target vs actual tracking.', 'status' => TaskStatus::IN_PROGRESS, 'priority' => TaskPriority::HIGH, 'assignee' => $budi, 'creator' => $admin, 'start' => -2, 'due' => 12],
            ['project' => 'Dashboard ESG & Sustainability Reporting', 'title' => 'Waste Management & Circular Economy Tracking', 'desc' => 'Waste segregation logging, recycling rate, hazardous waste manifest, dan vendor disposal certification.', 'status' => TaskStatus::REVIEW, 'priority' => TaskPriority::HIGH, 'assignee' => $dewi, 'creator' => $budi, 'start' => 3, 'due' => 15],
            ['project' => 'Dashboard ESG & Sustainability Reporting', 'title' => 'GRI & OJK Reporting Template Generator', 'desc' => 'Standard disclosure mapping, data collection workflow, narrative builder, dan XBRL export untuk OJK.', 'status' => TaskStatus::TODO, 'priority' => TaskPriority::URGENT, 'assignee' => $extraUsers[4], 'creator' => $dewi, 'start' => 10, 'due' => 25],
            ['project' => 'Dashboard ESG & Sustainability Reporting', 'title' => 'Supplier ESG Assessment Portal', 'desc' => 'Questionnaire vendor, scoring model, risk heatmap, dan improvement action plan tracking.', 'status' => TaskStatus::TODO, 'priority' => TaskPriority::MEDIUM, 'assignee' => $extraUsers[5], 'creator' => $budi, 'start' => 20, 'due' => 40],
        ];

        // Convert relative dates to actual dates
        $finalTasks = [];
        foreach ($tasksData as $td) {
            $projectId = $getProject($td['project']);
            $data = [
                'project_id' => $projectId,
                'title' => $td['title'],
                'description' => $td['desc'],
                'status' => $td['status'],
                'priority' => $td['priority'],
                'assignee_id' => $td['assignee']->id,
                'creator_id' => $td['creator']->id,
                'start_date' => $currentMonth->copy()->addDays($td['start']),
                'due_date' => $currentMonth->copy()->addDays($td['due']),
            ];
            if (isset($td['completed'])) {
                $data['completed_at'] = $currentMonth->copy()->addDays($td['completed']);
            }
            $finalTasks[] = $data;
        }

        $createdTasks = [];
        foreach ($finalTasks as $data) {
            $task = Task::updateOrCreate(
                ['project_id' => $data['project_id'], 'title' => $data['title']],
                $data
            );
            $createdTasks[] = $task;
        }

        // ============ RICH COMMENTS ============
        $commentSamples = [
            ['task' => 1, 'user' => $siti, 'content' => 'Arsitektur multi-tenant sudah direview tim security. Perlu tambahkan row-level policy untuk isolasi data divisi.'],
            ['task' => 1, 'user' => $budi, 'content' => 'Setuju. Akan implementasikan RLS PostgreSQL minggu depan. Estimasi 2 hari kerja.'],
            ['task' => 2, 'user' => $andi, 'content' => 'Mux integration hampir selesai. Webhook untuk video.processed sudah handle. Sisa: adaptive bitrate testing di mobile.'],
            ['task' => 2, 'user' => $siti, 'content' => 'Bagus. Untuk DRM, pakai Widevine/PlayReady via Mux. Butuh klarifikasi budget license fee per bulan.'],
            ['task' => 3, 'user' => $dewi, 'content' => 'Engine kuis adaptive sudah jalan. Algoritma: difficulty naik 1 level setiap 3 jawaban benar berturut-turut.'],
            ['task' => 4, 'user' => $extraUsers[0], 'content' => 'Sertifikat PDF generate pakai DomPDF + QR code. Verifikasi publik jalan di route /verify/{hash}.'],
            ['task' => 5, 'user' => $extraUsers[4], 'content' => 'Dashboard analytics pakai Chart.js. Butuh data enrollment dari HRIS untuk filter per divisi.'],
            ['task' => 7, 'user' => $dewi, 'content' => 'Payroll engine core sudah handle BPJS Kesehatan (4%+1%) dan Ketenagakerjaan (JHT 3.7%+2%, JP 1%+0.3%, JKM 0.24%, JKK 0.11-1.74%).'],
            ['task' => 7, 'user' => $budi, 'content' => 'Perhitungan PPh21 pakai NETO method terbaru 2026 (PTKP TK/0 = 54jt). Butuh validasi ke konsultan pajak.'],
            ['task' => 8, 'user' => $extraUsers[4], 'content' => 'OKR module: objective bisa punya multiple key results. Progress KR rollup ke objective pakai weighted average.'],
            ['task' => 9, 'user' => $extraUsers[2], 'content' => 'Self-service cuti: approval chain otomatis dari org chart. Notifikasi FCM ke approver + email digest harian.'],
            ['task' => 13, 'user' => $andi, 'content' => 'WhatsApp BA integration: template HSM sudah approved 12 template. Chatbot FAQ pakai Dialogflow ES.'],
            ['task' => 14, 'user' => $extraUsers[3], 'content' => 'Email sequence builder drag-drop selesai. Tracking pixel 1x1px di footer. Unsubscribe one-click dengan token JWT.'],
            ['task' => 15, 'user' => $extraUsers[0], 'content' => 'Forecasting model: weighted pipeline (stage probability) + AI adjustment (historical win rate per rep). MAE ~12%.'],
            ['task' => 19, 'user' => $siti, 'content' => 'Strangler Fig: facade layer pakai Kong API Gateway. Legacy COBOL dibungkus REST wrapper. Feature flag pakai LaunchDarkly.'],
            ['task' => 20, 'user' => $dewi, 'content' => 'COA redesign: dimension-based (entity, department, project, cost center). Mapping legacy 1:many ke new structure.'],
            ['task' => 21, 'user' => $extraUsers[1], 'content' => 'Journal approval: maker-create, checker-approve. Recurring journal auto-post dengan schedule cron. Reversing entry otomatis next period.'],
            ['task' => 22, 'user' => $extraUsers[4], 'content' => 'Fixed asset: depreciation method configurable per asset class (straight-line, declining balance, units of production).'],
            ['task' => 23, 'user' => $extraUsers[4], 'content' => 'E-Faktur integration: pakai SDK DJP. QR code embed di PDF faktur. Butuh sertifikat elektronik perusahaan.'],
            ['task' => 25, 'user' => $budi, 'content' => 'Vendor portal: self-registration pakai invite token. KYC document upload (NPWP, SIUP, SKT, NIB). Risk scoring rule-based.'],
            ['task' => 27, 'user' => $extraUsers[2], 'content' => '3-way match: PO-GR-Invoice tolerance configurable (qty ±2%, price ±0%). Auto-release payment jika match.'],
            ['task' => 29, 'user' => $dewi, 'content' => 'Hardware lifecycle: barcode label pakai Code128. Mobile audit app pakai React Native + Expo. GPS capture saat scan.'],
            ['task' => 30, 'user' => $extraUsers[0], 'content' => 'License compliance: discovery agent pakai OCS Inventory. SaaS spend dari corporate card feed + API vendor (Google, Microsoft, Atlassian).'],
            ['task' => 31, 'user' => $extraUsers[3], 'content' => 'CMDB: CI types (server, database, application, network device). Relationship: runs_on, depends_on, connects_to. Impact analysis untuk CAB.'],
            ['task' => 33, 'user' => $admin, 'content' => 'Carbon engine: Scope 1 (direct fuel), Scope 2 (electricity), Scope 3 (travel, waste, procurement). Emission factor dari DEFRA 2026 & IPCC AR6.'],
            ['task' => 34, 'user' => $budi, 'content' => 'Energy monitoring: IoT meter Modbus TCP ke MQTT broker. Telegraf -> InfluxDB -> Grafana. Anomaly detection pakai Isolation Forest.'],
        ];

        foreach ($commentSamples as $cs) {
            if (isset($createdTasks[$cs['task']])) {
                TaskComment::firstOrCreate(
                    [
                        'task_id' => $createdTasks[$cs['task']]->id,
                        'user_id' => $cs['user']->id,
                        'content' => $cs['content'],
                    ]
                );
            }
        }

        // ============ RICH ACTIVITIES ============
        if (count($createdTasks) >= 10) {
            $activities = [];
            $activityTemplates = [
                ['task' => 1, 'user' => $budi, 'type' => 'task.updated', 'field' => 'status', 'old' => 'todo', 'new' => 'in_progress', 'desc' => '{user} memulai pengerjaan arsitektur database.', 'time' => -60],
                ['task' => 2, 'user' => $siti, 'type' => 'task.updated', 'field' => 'priority', 'old' => 'high', 'new' => 'urgent', 'desc' => '{user} menaikkan prioritas integrasi video streaming.', 'time' => -45],
                ['task' => 2, 'user' => $andi, 'type' => 'comment.created', 'field' => null, 'old' => null, 'new' => null, 'desc' => '{user} menambahkan catatan progres Mux integration.', 'time' => -30],
                ['task' => 3, 'user' => $dewi, 'type' => 'task.updated', 'field' => 'status', 'old' => 'todo', 'new' => 'in_progress', 'desc' => '{user} memulai engine kuis adaptif.', 'time' => -20],
                ['task' => 4, 'user' => $extraUsers[0], 'type' => 'task.updated', 'field' => 'status', 'old' => 'in_progress', 'new' => 'review', 'desc' => '{user} mengajukan sertifikat digital untuk review.', 'time' => -15],
                ['task' => 7, 'user' => $dewi, 'type' => 'task.updated', 'field' => 'status', 'old' => 'todo', 'new' => 'in_progress', 'desc' => '{user} memulai kalkulasi payroll BPJS & PPh21.', 'time' => -10],
                ['task' => 8, 'user' => $extraUsers[4], 'type' => 'task.updated', 'field' => 'status', 'old' => 'todo', 'new' => 'in_progress', 'desc' => '{user} memulai modul evaluasi performa OKR.', 'time' => -8],
                ['task' => 13, 'user' => $andi, 'type' => 'task.updated', 'field' => 'status', 'old' => 'todo', 'new' => 'in_progress', 'desc' => '{user} memulai integrasi WhatsApp Business API.', 'time' => -5],
                ['task' => 19, 'user' => $siti, 'type' => 'task.updated', 'field' => 'status', 'old' => 'review', 'new' => 'done', 'desc' => '{user} menyelesaikan strategi migrasi Strangler Fig.', 'time' => -3],
                ['task' => 20, 'user' => $dewi, 'type' => 'task.updated', 'field' => 'priority', 'old' => 'high', 'new' => 'critical', 'desc' => '{user} menaikkan prioritas COA redesign.', 'time' => -2],
                ['task' => 25, 'user' => $budi, 'type' => 'task.created', 'field' => null, 'old' => null, 'new' => null, 'desc' => '{user} membuat tugas vendor portal registration.', 'time' => -1],
                ['task' => 29, 'user' => $dewi, 'type' => 'comment.created', 'field' => null, 'old' => null, 'new' => null, 'desc' => '{user} berbagi update barcode asset tagging.', 'time' => 0],
                ['task' => 33, 'user' => $admin, 'type' => 'task.updated', 'field' => 'status', 'old' => 'todo', 'new' => 'in_progress', 'desc' => '{user} memulai kalkulasi carbon footprint Scope 1/2/3.', 'time' => 0],
            ];

            foreach ($activityTemplates as $at) {
                if (! isset($createdTasks[$at['task']])) {
                    continue;
                }
                $task = $createdTasks[$at['task']];
                $user = $at['user'];
                $desc = str_replace('{user}', $user->name, $at['desc']);

                $activities[] = [
                    'task_id' => $task->id,
                    'user_id' => $user->id,
                    'activity_type' => $at['type'],
                    'description' => $desc,
                    'field' => $at['field'],
                    'old_value' => $at['old'],
                    'new_value' => $at['new'],
                    'created_at' => Carbon::now()->addMinutes($at['time']),
                ];
            }

            foreach ($activities as $act) {
                TaskActivity::firstOrCreate(
                    [
                        'task_id' => $act['task_id'],
                        'user_id' => $act['user_id'],
                        'activity_type' => $act['activity_type'],
                        'description' => $act['description'],
                    ],
                    [
                        'field' => $act['field'],
                        'old_value' => $act['old_value'],
                        'new_value' => $act['new_value'],
                        'created_at' => $act['created_at'],
                    ]
                );
            }
        }

        // ============ ANNOUNCEMENTS ============
        $announcements = [
            [
                'title' => 'Peluncuran Platform E-Learning Korporat Fase 1',
                'body' => 'Mulai minggu depan, semua karyawan dapat mengakses kursus onboarding dan compliance training melalui platform baru. Link akses akan dikirim via email.',
                'is_pinned' => true,
                'published_at' => Carbon::now()->subDays(2),
                'user_id' => $admin->id,
            ],
            [
                'title' => 'Jadwal Cuti Bersama Tahun Baru 2027',
                'body' => 'Cuti bersama diperkirakan 29 Desember 2026 - 2 Januari 2027. Pengajuan cuti tambahan maksimal 17 November 2026 melalui HRIS.',
                'is_pinned' => true,
                'published_at' => Carbon::now()->subDays(5),
                'user_id' => $budi->id,
            ],
            [
                'title' => 'Update Kebijakan Keamanan Informasi (ISO 27001)',
                'body' => 'Semua karyawan wajib mengikuti security awareness training terbaru di E-Learning sebelum 30 November 2026. Sertifikat kelulusan akan diverifikasi otomatis.',
                'is_pinned' => false,
                'published_at' => Carbon::now()->subDays(10),
                'user_id' => $siti->id,
            ],
            [
                'title' => 'Perbaikan Jaringan Kantor Pusat - Minggu Depan',
                'body' => 'Akan ada maintenance jaringan pada Sabtu, 7 November 2026 pukul 22:00 - Minggu, 8 November 2026 pukul 06:00. Sistem mungkin tidak tersedia selama periode tersebut.',
                'is_pinned' => false,
                'published_at' => Carbon::now()->subDays(1),
                'user_id' => $andi->id,
            ],
            [
                'title' => 'Luncuran Fitur Baru CRM: WhatsApp Broadcast',
                'body' => 'Tim sales kini dapat mengirim broadcast WhatsApp ke lead dari dalam CRM. Template sudah disediakan. Training singkat hari Kamis pukul 14:00.',
                'is_pinned' => false,
                'published_at' => Carbon::now()->subHours(6),
                'user_id' => $budi->id,
            ],
        ];

        foreach ($announcements as $ann) {
            Announcement::updateOrCreate(
                ['title' => $ann['title']],
                $ann
            );
        }

        // ============ NOTIFICATIONS ============
        foreach ($extendedTeam as $idx => $targetUser) {
            if ($targetUser->notifications()->count() < 5) {
                for ($i = 0; $i < 3; $i++) {
                    $targetTask = $createdTasks[($idx + $i) % count($createdTasks)];
                    $targetProject = Project::find($targetTask->project_id) ?? $allProjects[0];

                    try {
                        $targetUser->notify(new TaskAssigned(
                            $targetTask->id,
                            $targetTask->title,
                            $targetProject->id,
                            $targetProject->name,
                            "seed-notif-assign-{$targetUser->id}-{$i}"
                        ));
                    } catch (\Exception $e) {
                        // Ignore duplicate notifications
                    }

                    try {
                        $targetUser->notify(new TaskCommentAdded(
                            $targetTask->id,
                            $targetTask->title,
                            $targetProject->id,
                            $targetProject->name,
                            $targetTask->id,
                            'Update terbaru pada tugas ini. Silakan cek detail.',
                            $allUsers[array_rand($allUsers)]->name
                        ));
                    } catch (\Exception $e) {
                        // Ignore duplicate notifications
                    }

                    try {
                        $targetUser->notify(new TaskStatusChanged(
                            $targetTask->id,
                            $targetTask->title,
                            $targetProject->id,
                            $targetProject->name,
                            'todo',
                            'in_progress'
                        ));
                    } catch (\Exception $e) {
                        // Ignore duplicate notifications
                    }
                }

                try {
                    $targetUser->notify(new ProjectMemberAdded(
                        $allProjects[0]['id'],
                        $allProjects[0]['name'],
                        'member',
                        $admin->name
                    ));
                } catch (\Exception $e) {
                    // Ignore duplicate notifications
                }
            }
        }

        $this->command?->info('Enhanced dummy data seeded: '.count($createdProjects).' projects, '.count($createdTasks).' tasks, '.count($extendedTeam).' users, announcements, comments, activities, notifications!');
    }
}
