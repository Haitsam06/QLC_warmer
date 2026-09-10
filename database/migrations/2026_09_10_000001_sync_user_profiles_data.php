<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Teacher;
use App\Models\Parents;
use App\Models\Partner;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Sync Guru (Teachers)
        $teacherDefaults = [
            'guru1' => ['nama_guru' => 'Ahmad Fauzan',   'bidang' => 'Tahfidz', 'phone' => '081200001111'],
            'guru2' => ['nama_guru' => 'Siti Fatimah',    'bidang' => 'Tilawah', 'phone' => '081200002222'],
            'guru3' => ['nama_guru' => 'Muhammad Ridwan', 'bidang' => 'Yanbua',  'phone' => '081200003333'],
            'guru4' => ['nama_guru' => 'Nur Halimah',     'bidang' => 'Tahfidz', 'phone' => '081200004444'],
            'guru5' => ['nama_guru' => 'Hasan Basri',     'bidang' => 'Tilawah', 'phone' => '081200005555'],
        ];

        $teacherUsers = User::where('role_id', 'RL02')->get();
        foreach ($teacherUsers as $u) {
            $exists = Teacher::where('user_id', $u->id)->exists();
            if (!$exists) {
                $meta = $teacherDefaults[$u->username] ?? [
                    'nama_guru' => ucfirst($u->username),
                    'bidang'    => 'Tahfidz',
                    'phone'     => '0812' . str_pad((string) $u->id, 8, '0', STR_PAD_LEFT),
                ];
                Teacher::create([
                    'user_id'   => $u->id,
                    'nama_guru' => $meta['nama_guru'],
                    'bidang'    => $meta['bidang'],
                    'phone'     => $meta['phone'],
                    'email'     => $u->email,
                ]);
            }
        }

        // 2. Sync Wali Murid (Parents)
        $parentDefaults = [
            'wali1' => ['parent_name' => 'Budi Santoso', 'phone' => '082100001111', 'address' => 'Jl. Mawar No. 1, Bekasi'],
            'wali2' => ['parent_name' => 'Sri Wahyuni',   'phone' => '082100002222', 'address' => 'Jl. Melati No. 5, Bekasi'],
            'wali3' => ['parent_name' => 'Ahmad Mukhlas', 'phone' => '082100003333', 'address' => 'Jl. Anggrek No. 10, Depok'],
            'wali4' => ['parent_name' => 'Dewi Rahayu',   'phone' => '082100004444', 'address' => 'Jl. Kenanga No. 3, Bogor'],
            'wali5' => ['parent_name' => 'Eko Prasetyo',  'phone' => '082100005555', 'address' => 'Jl. Dahlia No. 7, Tangerang'],
        ];

        $parentUsers = User::where('role_id', 'RL03')->get();
        foreach ($parentUsers as $u) {
            $exists = Parents::where('user_id', $u->id)->exists();
            if (!$exists) {
                $meta = $parentDefaults[$u->username] ?? [
                    'parent_name' => ucfirst($u->username),
                    'phone'       => '0821' . str_pad((string) $u->id, 8, '0', STR_PAD_LEFT),
                    'address'     => 'Jl. QLC No. 1',
                ];
                Parents::create([
                    'user_id'     => $u->id,
                    'parent_name' => $meta['parent_name'],
                    'phone'       => $meta['phone'],
                    'address'     => $meta['address'],
                ]);
            }
        }

        // 3. Sync Mitra (Partners)
        $mitraDefaults = [
            'mitra1' => ['partner_name' => 'Yayasan Bina Insani',          'pic_name' => 'Dr. Hendra Wijaya',   'phone' => '083100001111', 'address' => 'Jl. Gatot Subroto No. 45, Jakarta'],
            'mitra2' => ['partner_name' => 'Pesantren Al-Ikhlas',          'pic_name' => 'Ust. Zainal Arifin',  'phone' => '083100002222', 'address' => 'Jl. Raya Bogor Km 30, Depok'],
            'mitra3' => ['partner_name' => 'Lembaga Pendidikan Al-Azhar', 'pic_name' => 'Dra. Hj. Nurul Huda', 'phone' => '083100003333', 'address' => 'Jl. Kemang Raya No. 12, Jakarta'],
        ];

        $mitraUsers = User::where('role_id', 'RL04')->get();
        foreach ($mitraUsers as $u) {
            $exists = Partner::where('user_id', $u->id)->exists();
            if (!$exists) {
                $meta = $mitraDefaults[$u->username] ?? [
                    'partner_name' => 'Mitra ' . ucfirst($u->username),
                    'pic_name'     => 'PIC ' . ucfirst($u->username),
                    'phone'        => '0831' . str_pad((string) $u->id, 8, '0', STR_PAD_LEFT),
                    'address'      => 'Jl. Kemitraan No. 1',
                ];
                Partner::create([
                    'user_id'      => $u->id,
                    'partner_name' => $meta['partner_name'],
                    'pic_name'     => $meta['pic_name'],
                    'phone'        => $meta['phone'],
                    'address'      => $meta['address'],
                ]);
            }
        }
    }

    public function down(): void
    {
        // No-op to preserve data
    }
};
