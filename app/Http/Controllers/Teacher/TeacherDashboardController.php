<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Agenda;
use App\Models\Program;
use App\Models\ProgressReport;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class TeacherDashboardController extends Controller
{
    public function index(): Response
    {
        $userId = Auth::id();

        // ── Profil guru ───────────────────────────────────────
        $teacherDoc = Teacher::where('user_id', $userId)->first();
        $teacherId  = $teacherDoc ? $teacherDoc->id : null;

        $profile = $teacherDoc ? [
            'nama_guru' => $teacherDoc->nama_guru ?? '—',
            'phone'     => $teacherDoc->phone     ?? '—',
            'email'     => $teacherDoc->email     ?? '—',
            'bidang'    => $teacherDoc->bidang    ?? '—',
        ] : null;

        // ── Stats: total santri aktif ─────────────────────────
        $allStudents = Student::where('enrollment_status', 'active')->get(['id']);
        $totalSantri = $allStudents->count();
        $targetTercapai = 0;

        if ($totalSantri > 0) {
            $studentIds = $allStudents->pluck('id')->toArray();

            // Ambil laporan terbaru per siswa dalam satu query (batch — tidak N+1)
            $latestReports = ProgressReport::whereIn('student_id', $studentIds)
                ->orderBy('date', 'desc')
                ->get(['student_id', 'kualitas'])
                ->unique('student_id');

            $goodCount = $latestReports->filter(
                fn($r) => in_array($r->kualitas ?? '', ['sangat_lancar', 'lancar'], true)
            )->count();

            $targetTercapai = (int) round(($goodCount / $totalSantri) * 100);
        }

        // ── Agenda hari ini ───────────────────────────────────
        $today        = date('Y-m-d');
        $todayAgendas = Agenda::where('event_date', $today)
            ->orderBy('title')
            ->take(10)
            ->get()
            ->map(fn($ag) => [
                'id'     => $ag->id,
                'time'   => '—',
                'class'  => $ag->title       ?? '—',
                'type'   => $ag->description ?? '—',
                'room'   => $ag->location    ?? '—',
                'status' => 'Menunggu',
            ])->values()->toArray();

        // ── 5 laporan terbaru dari guru ini ───────────────────
        $recentReports = [];
        if ($teacherId) {
            $reports    = ProgressReport::where('teacher_id', $teacherId)
                ->orderBy('date', 'desc')
                ->take(5)
                ->get();

            $studentIds = $reports->pluck('student_id')->filter()->unique()->values()->toArray();
            $students   = Student::whereIn('id', $studentIds)->get()->keyBy(fn($s) => (string) $s->id);

            $programIds = $students->pluck('program_id')->filter()->unique()->values()->toArray();
            $programs   = empty($programIds) ? [] : Program::whereIn('id', $programIds)
                ->get(['id', 'name'])
                ->keyBy(fn($p) => (string) $p->id)
                ->map(fn($p) => $p->name ?? '—')
                ->toArray();

            $recentReports = $reports->map(function ($r) use ($students, $programs) {
                $sid        = (string) ($r->student_id ?? '');
                $studentDoc = $students[$sid] ?? null;
                $pid        = (string) ($studentDoc?->program_id ?? '');
                return [
                    'id'             => $r->id,
                    'student_name'   => $studentDoc?->nama      ?? 'Santri',
                    'class_name'     => $programs[$pid]          ?? '—',
                    'report_type'    => $r->report_type         ?? 'hafalan',
                    'surah_or_jilid' => $r->hafalan_target      ?? '—',
                    'ayat_or_hal'    => $r->hafalan_achievement ?? '—',
                    'kualitas'       => $r->kualitas            ?? 'lancar',
                ];
            })->values()->toArray();
        }

        return Inertia::render('teacher/Dashboard', [
            'stats'          => [
                'total_santri'    => $totalSantri,
                'target_tercapai' => $targetTercapai,
            ],
            'today_agendas'  => $todayAgendas,
            'recent_reports' => $recentReports,
            'profile'        => $profile,
        ]);
    }
}
