<?php

namespace Database\Seeders;

use App\Models\Partner;
use App\Models\User;
use Illuminate\Database\Seeder;

class MitraSeeder extends Seeder
{
    public function run(): void
    {
        Partner::truncate();

        $profiles = [
            ['institution_name' => 'Yayasan Al-Ilmi Bekasi',          'contact_person' => 'Dr. Hendra Kusuma',    'phone' => '02112300001', 'status' => 'Active',   'email' => 'mitra1@qlc.id'],
            ['institution_name' => 'Pondok Pesantren Nurul Huda',      'contact_person' => 'K.H. Mahmud Hasyim',  'phone' => '02112300002', 'status' => 'Active',   'email' => 'mitra2@qlc.id'],
            ['institution_name' => 'CV. Berkah Mandiri Sejahtera',     'contact_person' => 'Ir. Reza Firmansyah', 'phone' => '02112300003', 'status' => 'Inactive', 'email' => 'mitra3@qlc.id'],
        ];

        $users = User::where('role_id', 'RL04')->get()->keyBy('email');

        foreach ($profiles as $p) {
            $user = $users[$p['email']] ?? null;
            Partner::create([
                'user_id'          => $user ? $user->id : null,
                'institution_name' => $p['institution_name'],
                'contact_person'   => $p['contact_person'],
                'phone'            => $p['phone'],
                'status'           => $p['status'],
                'mou_file_url'     => null,
            ]);
        }
    }
}
