<?php

namespace Database\Seeders;

use App\Models\MitraReport;
use App\Models\Partner;
use Illuminate\Database\Seeder;

class MitraReportSeeder extends Seeder
{
    public function run(): void
    {
        MitraReport::truncate();

        $partners = Partner::orderBy('institution_name')->get();

        if ($partners->isEmpty()) {
            $this->command->warn('MitraReportSeeder: data mitra belum ada, skip.');
            return;
        }

        $data = [
            [
                'partner_index' => 0,
                'title'         => 'Laporan Kegiatan MoU Triwulan I 2025',
                'date'          => '2025-04-01',
                'description'   => 'Laporan realisasi program kerja sama pada triwulan pertama tahun 2025.',
            ],
            [
                'partner_index' => 1,
                'title'         => 'Laporan Penyelenggaraan Halaqah Bersama',
                'date'          => '2025-04-15',
                'description'   => 'Dokumentasi kegiatan halaqah tahfidz bersama yang diselenggarakan di ponpes.',
            ],
            [
                'partner_index' => 2,
                'title'         => 'Evaluasi Program Magang Santri',
                'date'          => '2025-05-01',
                'description'   => 'Evaluasi program magang santri QLC di CV. Berkah Mandiri Sejahtera.',
            ],
            [
                'partner_index' => 0,
                'title'         => 'Laporan Dana Beasiswa Yayasan Al-Ilmi',
                'date'          => '2025-05-10',
                'description'   => 'Rincian penggunaan dana beasiswa yang disalurkan melalui Yayasan Al-Ilmi Bekasi.',
            ],
            [
                'partner_index' => 1,
                'title'         => 'Laporan Kunjungan Studi ke QLC',
                'date'          => '2025-05-20',
                'description'   => 'Dokumentasi kunjungan studi santri Ponpes Nurul Huda ke pusat QLC.',
            ],
        ];

        foreach ($data as $r) {
            $partner = $partners[$r['partner_index']] ?? $partners->first();

            MitraReport::create([
                'partner_id'  => $partner->id,
                'title'       => $r['title'],
                'date'        => $r['date'],
                'description' => $r['description'],
                'file_url'    => null,
                'file_path'   => null,
                'file_name'   => null,
                'file_type'   => null,
                'file_size'   => null,
                'uploaded_by' => $partner->user_id,
            ]);
        }
    }
}
