<?php

namespace App\Http\Controllers;

use App\Models\Agenda;
use App\Models\Partner;
use App\Models\Program;
use App\Models\ProgressReport;
use App\Models\Student;
use App\Models\Teacher;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function dashboard(): Response
    {
        return Inertia::render('admin/Dashboard');
    }

    public function stats(): JsonResponse
    {
        return response()->json([
            'total_siswa'   => Student::where('enrollment_status', 'active')->count(),
            'total_pending' => Student::where('enrollment_status', 'pending')->count(),
            'total_guru'    => Teacher::count(),
            'total_program' => Program::count(),
            'total_mitra'   => Partner::where('status', 'Active')->count(),
        ]);
    }

    public function chart(): JsonResponse
    {
        $now   = Carbon::now();
        $start = $now->copy()->subMonths(5)->startOfMonth();
        $end   = $now->copy()->endOfMonth();

        $students = Student::where('created_at', '>=', $start)
                           ->where('created_at', '<=', $end)
                           ->get(['created_at']);

        $countByMonth = [];
        foreach ($students as $s) {
            $key = $s->created_at?->format('Y-m');
            if ($key) {
                $countByMonth[$key] = ($countByMonth[$key] ?? 0) + 1;
            }
        }

        $result = [];
        for ($i = 5; $i >= 0; $i--) {
            $month    = $now->copy()->subMonths($i);
            $result[] = [
                'name'      => $month->format('M'),
                'pendaftar' => $countByMonth[$month->format('Y-m')] ?? 0,
            ];
        }

        return response()->json($result);
    }

    public function upcomingAgenda(): JsonResponse
    {
        $today = Carbon::today()->format('Y-m-d');

        $data = Agenda::where('event_date', '>=', $today)
            ->orderBy('event_date')
            ->limit(3)
            ->get()
            ->map(function ($a) {
                $eventDate = Carbon::parse($a->event_date);
                return [
                    'id'    => $a->id,
                    'title' => $a->title ?? '—',
                    'date'  => $eventDate->format('d M Y'),
                    'type'  => $this->classifyAgenda($eventDate),
                ];
            });

        return response()->json($data);
    }

    public function pendingStudents(): JsonResponse
    {
        $students = Student::where('enrollment_status', 'pending')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        $programIds = $students->pluck('program_id')->filter()->unique()->values();
        $programs   = Program::whereIn('id', $programIds->toArray())
            ->get()
            ->keyBy(fn($p) => (string) $p->id);

        $data = $students->map(function ($doc) use ($programs) {
            $pid = (string) ($doc->program_id ?? '');
            return [
                'id'   => $doc->id,
                'nama' => $doc->nama ?? '—',
                'prog' => isset($programs[$pid]) ? ($programs[$pid]->name ?? '—') : '—',
                'date' => $doc->created_at ? $doc->created_at->format('d M') : '—',
            ];
        });

        return response()->json($data);
    }

    public function topReports(): JsonResponse
    {
        $today     = Carbon::today()->format('Y-m-d');
        $yesterday = Carbon::yesterday()->format('Y-m-d');

        $reports = ProgressReport::where('date', $today)
            ->where('attendance', 'hadir')
            ->whereIn('kualitas', ['sangat_lancar', 'lancar'])
            ->limit(5)
            ->get();

        if ($reports->isEmpty()) {
            $reports = ProgressReport::where('date', $yesterday)
                ->where('attendance', 'hadir')
                ->whereIn('kualitas', ['sangat_lancar', 'lancar'])
                ->limit(5)
                ->get();
        }

        if ($reports->isEmpty()) {
            return response()->json([]);
        }

        $studentIds = $reports->pluck('student_id')->filter()->unique()->values();
        $students   = Student::whereIn('id', $studentIds->toArray())
            ->get(['id', 'nama'])
            ->keyBy(fn($s) => (string) $s->id);

        $qualityOrder = ['sangat_lancar' => 0, 'lancar' => 1, 'mengulang' => 2];

        $data = $reports
            ->sortBy(fn($r) => $qualityOrder[$r->kualitas ?? 'mengulang'] ?? 2)
            ->map(function ($r) use ($students) {
                $sid = (string) $r->student_id;
                return [
                    'id'          => $r->id,
                    'student_id'  => $sid,
                    'nama'        => $students[$sid]->nama ?? '—',
                    'capaian'     => $r->hafalan_achievement ?? $r->hafalan_target ?? '—',
                    'report_type' => $r->report_type ?? null,
                    'kualitas'    => $r->kualitas ?? null,
                ];
            })
            ->values();

        return response()->json($data);
    }

    private function classifyAgenda(Carbon $date): string
    {
        $diff = Carbon::now()->diffInDays($date);
        return $diff <= 3 ? 'urgent' : ($diff <= 7 ? 'segera' : 'umum');
    }
}
