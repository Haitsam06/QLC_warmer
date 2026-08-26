<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UsersSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command->warn('UsersSeeder dilewati di lingkungan production.');
            return;
        }

        User::truncate();

        $adminPass   = env('PASSWORD');
        $guruPass    = env('PASSWORD');
        $waliPass    = env('PASSWORD');
        $mitraPass   = env('PASSWORD');

        // Admin
        User::create([
            'role_id'  => 'RL01',
            'username' => 'admin',
            'email'    => 'admin@qlc.id',
            'password' => Hash::make($adminPass),
        ]);

        // Guru (5)
        $teachers = [
            ['username' => 'guru1', 'email' => 'guru1@qlc.id'],
            ['username' => 'guru2', 'email' => 'guru2@qlc.id'],
            ['username' => 'guru3', 'email' => 'guru3@qlc.id'],
            ['username' => 'guru4', 'email' => 'guru4@qlc.id'],
            ['username' => 'guru5', 'email' => 'guru5@qlc.id'],
        ];
        foreach ($teachers as $t) {
            User::create(array_merge($t, ['role_id' => 'RL02', 'password' => Hash::make($guruPass)]));
        }

        // Wali Murid (5)
        $parents = [
            ['username' => 'wali1', 'email' => 'wali1@qlc.id'],
            ['username' => 'wali2', 'email' => 'wali2@qlc.id'],
            ['username' => 'wali3', 'email' => 'wali3@qlc.id'],
            ['username' => 'wali4', 'email' => 'wali4@qlc.id'],
            ['username' => 'wali5', 'email' => 'wali5@qlc.id'],
        ];
        foreach ($parents as $p) {
            User::create(array_merge($p, ['role_id' => 'RL03', 'password' => Hash::make($waliPass)]));
        }

        // Mitra (3)
        $mitras = [
            ['username' => 'mitra1', 'email' => 'mitra1@qlc.id'],
            ['username' => 'mitra2', 'email' => 'mitra2@qlc.id'],
            ['username' => 'mitra3', 'email' => 'mitra3@qlc.id'],
        ];
        foreach ($mitras as $m) {
            User::create(array_merge($m, ['role_id' => 'RL04', 'password' => Hash::make($mitraPass)]));
        }

        $this->command->info("Seeder selesai. Password admin: {$adminPass}");
    }
}
