<?php

namespace Database\Seeders;

use App\Models\Parents;
use App\Models\Program;
use App\Models\Student;
use Illuminate\Database\Seeder;

class StudentSeeder extends Seeder
{
    public function run(): void
    {
        Student::truncate();

        $parents  = Parents::orderBy('parent_name')->get();
        $programs = Program::orderBy('name')->get();

        if ($parents->isEmpty() || $programs->isEmpty()) {
            $this->command->warn('StudentSeeder: data parent atau program belum ada, skip.');
            return;
        }

        $data = [
            [
                'nama'              => 'Rizky Al-Faruq',
                'tempat_lahir'      => 'Jakarta',
                'tanggal_lahir'     => '2015-03-12',
                'usia'              => 10,
                'enrollment_status' => 'active',
                'parent_index'      => 0,
                'program_index'     => 0,
            ],
            [
                'nama'              => 'Nadia Putri Salsabila',
                'tempat_lahir'      => 'Bekasi',
                'tanggal_lahir'     => '2016-07-25',
                'usia'              => 9,
                'enrollment_status' => 'active',
                'parent_index'      => 1,
                'program_index'     => 3,
            ],
            [
                'nama'              => 'Muhammad Hafiz Firdaus',
                'tempat_lahir'      => 'Depok',
                'tanggal_lahir'     => '2014-11-08',
                'usia'              => 11,
                'enrollment_status' => 'active',
                'parent_index'      => 2,
                'program_index'     => 0,
            ],
            [
                'nama'              => 'Fatimah Az-Zahra',
                'tempat_lahir'      => 'Bogor',
                'tanggal_lahir'     => '2016-02-14',
                'usia'              => 9,
                'enrollment_status' => 'pending',
                'parent_index'      => 3,
                'program_index'     => 4,
            ],
            [
                'nama'              => 'Umar Abdillah Hakim',
                'tempat_lahir'      => 'Tangerang',
                'tanggal_lahir'     => '2015-09-30',
                'usia'              => 10,
                'enrollment_status' => 'active',
                'parent_index'      => 4,
                'program_index'     => 0,
            ],
        ];

        foreach ($data as $s) {
            $parent  = $parents[$s['parent_index']] ?? $parents->first();
            $program = $programs[$s['program_index']] ?? $programs->first();

            Student::create([
                'parent_id'         => $parent->id,
                'parent_name'       => $parent->parent_name,
                'program_id'        => $program->id,
                'nama'              => $s['nama'],
                'tempat_lahir'      => $s['tempat_lahir'],
                'tanggal_lahir'     => $s['tanggal_lahir'],
                'usia'              => $s['usia'],
                'enrollment_status' => $s['enrollment_status'],
                'bukti_pembayaran'  => null,
            ]);
        }
    }
}
