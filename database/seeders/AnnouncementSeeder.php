<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Database\Seeder;

class AnnouncementSeeder extends Seeder
{
    /**
     * Seed sample team announcements.
     */
    public function run(): void
    {
        $admin = User::where('email', 'admin@project.local')->first();
        $pm = User::where('email', 'project.manager@project.local')->first();

        if (! $admin) {
            return;
        }

        $announcements = [
            [
                'author' => $admin,
                'title' => 'Selamat Datang di Tasks Flow',
                'body' => "Halo tim!\n\nWorkspace baru kita sudah aktif. Gunakan menu Timeline Gantt untuk melihat jadwal proyek, dan halaman Pengumuman Tim ini untuk mendapatkan informasi penting seputar pekerjaan.\n\nJangan ragu menyampaikan kendala melalui papan kolaborasi.",
                'is_pinned' => true,
                'published_at' => now()->subDays(2),
            ],
            [
                'author' => $pm ?? $admin,
                'title' => 'Pengingat Sprint Review Minggu Ini',
                'body' => "Sprint review akan dilaksanakan hari Jumat pukul 14.00 WIB.\n\nMohon setiap pemilik tugas memperbarui status tugasnya sebelum sesi dimulai agar diskusi berjalan lebih efektif.",
                'is_pinned' => false,
                'published_at' => now()->subDay(),
            ],
            [
                'author' => $admin,
                'title' => 'Pembaruan Panduan Penulisan Tugas',
                'body' => "Mulai minggu ini, setiap tugas baru wajib memiliki tanggal mulai dan tenggat.\n\nHal ini membantu tim melihat beban kerja dan dependensi dengan lebih akurat di timeline proyek.",
                'is_pinned' => false,
                'published_at' => now()->subHours(6),
            ],
        ];

        foreach ($announcements as $announcement) {
            Announcement::firstOrCreate(
                ['title' => $announcement['title']],
                [
                    'user_id' => $announcement['author']->id,
                    'body' => $announcement['body'],
                    'is_pinned' => $announcement['is_pinned'],
                    'published_at' => $announcement['published_at'],
                ]
            );
        }

        $this->command?->info('Sample team announcements seeded.');
    }
}
