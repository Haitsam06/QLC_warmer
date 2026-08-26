<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        Role::truncate();

        Role::create([
            'id' => 'RL01',
            'role_name' => 'admin'
        ]);

        Role::create([
            'id' => 'RL02',
            'role_name' => 'teacher'
        ]);

        Role::create([
            'id' => 'RL03',
            'role_name' => 'parents'
        ]);
        
        Role::create([
            'id' => 'RL04',
            'role_name' => 'mitra'
        ]);
    }
}