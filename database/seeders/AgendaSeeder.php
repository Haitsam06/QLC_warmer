<?php

namespace Database\Seeders;

use App\Models\Agenda;
use App\Models\User;
use Illuminate\Database\Seeder;

class AgendaSeeder extends Seeder
{
    public function run(): void
    {
        Agenda::truncate();

        $admin = User::where('role_id', 'RL01')->first();
        $userId = $admin ? $admin->id : null;

        $items = [
            [
                'title'             => 'Halaqah Tahfidz Bulanan',
                'event_date'        => '2025-06-07',
                'description'       => 'Sesi halaqah tahfidz rutin untuk seluruh santri aktif. Dihadiri oleh semua pengajar dan wali murid.',
                'location'          => 'Aula Utama QLC',
                'registration_link' => null,
                'visibility'        => 'umum',
            ],
            [
                'title'             => 'Pelatihan Guru Metode Yanbu\'a',
                'event_date'        => '2025-06-14',
                'description'       => 'Pelatihan intensif bagi para guru dalam penerapan metode Yanbu\'a secara efektif.',
                'location'          => 'Ruang Kelas B',
                'registration_link' => null,
                'visibility'        => 'keduanya',
            ],
            [
                'title'             => 'Seminar Kemitraan Lembaga 2025',
                'event_date'        => '2025-06-21',
                'description'       => 'Forum diskusi antara QLC dan lembaga mitra mengenai program kolaborasi tahun 2025.',
                'location'          => 'Hotel Grand Bekasi',
                'registration_link' => null,
                'visibility'        => 'mitra',
            ],
            [
                'title'             => 'Wisuda Hafidz Angkatan VII',
                'event_date'        => '2025-07-05',
                'description'       => 'Upacara wisuda bagi santri yang telah menyelesaikan target hafalan 30 juz.',
                'location'          => 'Gedung Serbaguna QLC',
                'registration_link' => 'https://wa.me/6281234567890',
                'visibility'        => 'umum',
            ],
            [
                'title'             => 'Evaluasi Program Semester Genap',
                'event_date'        => '2025-07-12',
                'description'       => 'Rapat evaluasi akhir semester genap bersama seluruh tenaga pengajar dan mitra strategis.',
                'location'          => 'Ruang Rapat QLC',
                'registration_link' => null,
                'visibility'        => 'keduanya',
            ],
        ];

        foreach ($items as $item) {
            Agenda::create(array_merge($item, ['user_id' => $userId]));
        }
    }
}
