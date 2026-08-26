<?php

namespace Database\Seeders;

use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Seeder;

class TeacherSeeder extends Seeder
{
    public function run(): void
    {
        Teacher::truncate();

        $profiles = [
            ['nama_guru' => 'Ahmad Fauzan',      'bidang' => 'Tahfidz',  'phone' => '081200001111', 'email' => 'guru1@qlc.id'],
            ['nama_guru' => 'Siti Fatimah',       'bidang' => 'Tilawah',  'phone' => '081200002222', 'email' => 'guru2@qlc.id'],
            ['nama_guru' => 'Muhammad Ridwan',    'bidang' => 'Yanbua',   'phone' => '081200003333', 'email' => 'guru3@qlc.id'],
            ['nama_guru' => 'Nur Halimah',        'bidang' => 'Tahfidz',  'phone' => '081200004444', 'email' => 'guru4@qlc.id'],
            ['nama_guru' => 'Hasan Basri',        'bidang' => 'Tilawah',  'phone' => '081200005555', 'email' => 'guru5@qlc.id'],
        ];

        $users = User::where('role_id', 'RL02')->get()->keyBy('email');

        foreach ($profiles as $p) {
            $user = $users[$p['email']] ?? null;
            Teacher::create([
                'user_id'   => $user ? $user->id : null,
                'nama_guru' => $p['nama_guru'],
                'bidang'    => $p['bidang'],
                'phone'     => $p['phone'],
                'email'     => $p['email'],
            ]);
        }
    }
}
