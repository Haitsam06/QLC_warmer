<?php

namespace Database\Seeders;

use App\Models\ProgressReport;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Database\Seeder;

class ProgressReportSeeder extends Seeder
{
    public function run(): void
    {
        ProgressReport::truncate();

        $students = Student::orderBy('nama')->get();
        $teachers = Teacher::orderBy('nama_guru')->get();

        if ($students->isEmpty() || $teachers->isEmpty()) {
            $this->command->warn('ProgressReportSeeder: data siswa atau guru belum ada, skip.');
            return;
        }

        $data = [
            [
                'student_index'      => 0,
                'teacher_index'      => 0,
                'date'               => '2025-05-20',
                'attendance'         => 'hadir',
                'report_type'        => 'hafalan',
                'kualitas'           => 'sangat_lancar',
                'hafalan_target'     => 'Al-Baqarah 1-10',
                'hafalan_achievement'=> 'Al-Baqarah 1-10',
                'teacher_notes'      => 'Santri sangat fokus dan lancar dalam hafalan hari ini.',
            ],
            [
                'student_index'      => 1,
                'teacher_index'      => 1,
                'date'               => '2025-05-21',
                'attendance'         => 'hadir',
                'report_type'        => 'tilawah',
                'kualitas'           => 'lancar',
                'hafalan_target'     => 'Al-Fatihah & Al-Ikhlas',
                'hafalan_achievement'=> 'Al-Fatihah & Al-Ikhlas',
                'teacher_notes'      => 'Murajaah berjalan baik, tajwid perlu sedikit perbaikan.',
            ],
            [
                'student_index'      => 2,
                'teacher_index'      => 0,
                'date'               => '2025-05-22',
                'attendance'         => 'izin',
                'report_type'        => null,
                'kualitas'           => null,
                'hafalan_target'     => null,
                'hafalan_achievement'=> null,
                'teacher_notes'      => 'Santri izin karena sakit ringan.',
            ],
            [
                'student_index'      => 3,
                'teacher_index'      => 2,
                'date'               => '2025-05-23',
                'attendance'         => 'hadir',
                'report_type'        => 'hafalan',
                'kualitas'           => 'mengulang',
                'hafalan_target'     => 'Ali Imran 1-5',
                'hafalan_achievement'=> 'Ali Imran 1-3',
                'teacher_notes'      => 'Progres baik, perlu penguatan pada ayat 4 dan 5.',
            ],
            [
                'student_index'      => 4,
                'teacher_index'      => 1,
                'date'               => '2025-05-24',
                'attendance'         => 'hadir',
                'report_type'        => 'yanbua',
                'kualitas'           => 'sangat_lancar',
                'hafalan_target'     => 'Juz 30',
                'hafalan_achievement'=> 'Juz 30',
                'teacher_notes'      => 'Santri menyelesaikan murajaah Juz 30 dengan sangat baik.',
            ],
        ];

        foreach ($data as $r) {
            $student = $students[$r['student_index']] ?? $students->first();
            $teacher = $teachers[$r['teacher_index']] ?? $teachers->first();

            ProgressReport::create([
                'student_id'          => $student->id,
                'teacher_id'          => $teacher->id,
                'date'                => $r['date'],
                'attendance'          => $r['attendance'],
                'report_type'         => $r['report_type'],
                'kualitas'            => $r['kualitas'],
                'hafalan_target'      => $r['hafalan_target'],
                'hafalan_achievement' => $r['hafalan_achievement'],
                'teacher_notes'       => $r['teacher_notes'],
                'created_by'          => 'teacher',
            ]);
        }
    }
}
