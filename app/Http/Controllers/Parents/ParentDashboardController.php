<?php

namespace App\Http\Controllers\Parents;

use App\Http\Controllers\Controller;
use App\Models\Parents;
use App\Models\ProgressReport;
use App\Models\Program;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ParentDashboardController extends Controller
{
    public function index(): Response
    {
        $userId = Auth::user()->id;

        // ── Ambil semua anak ─────────────────────────────────
        $students   = Student::where('parent_id', $userId)->orderBy('created_at', 'desc')->get();
        $programIds = $students->pluck('program_id')->filter()->unique()->values()->toArray();
        $programs   = Program::whereIn('id', $programIds)->get()->keyBy(fn($p) => (string) $p->id);

        $children = $students->map(function ($doc) use ($programs) {
            $pid         = $doc->program_id ?? null;
            $programName = $pid && isset($programs[(string) $pid]) ? ($programs[(string) $pid]->name ?? null) : null;
            return [
                'id'                => $doc->id,
                'nama'              => $doc->nama          ?? '',
                'tempat_lahir'      => $doc->tempat_lahir  ?? '',
                'tanggal_lahir'     => $doc->tanggal_lahir ?? '',
                'usia'              => $doc->usia           ?? null,
                'program_id'        => $pid,
                'program_name'      => $programName,
                'enrollment_status' => $doc->enrollment_status ?? 'pending',
                'bukti_pembayaran'  => $doc->bukti_pembayaran  ?? null,
                'created_at'        => $doc->created_at?->format('Y-m-d H:i:s'),
            ];
        })->values()->toArray();

        $firstChild = count($children) > 0 ? [
            'nama'         => $children[0]['nama'],
            'program_name' => $children[0]['program_name'],
        ] : null;

        // ── Profil parent ────────────────────────────────────
        $parentDoc = Parents::where('user_id', $userId)->first();
        $profile   = $parentDoc ? [
            'parent_name' => $parentDoc->parent_name ?? '—',
            'phone'       => $parentDoc->phone       ?? '—',
            'address'     => $parentDoc->address     ?? '—',
        ] : null;

        // ── IDs anak aktif ────────────────────────────────────
        $activeChildIds = array_values(array_map(
            fn($c) => $c['id'],
            array_filter($children, fn($c) => $c['enrollment_status'] === 'active')
        ));

        $now          = now();
        $totalLaporan = 0;
        $totalHadir   = 0;
        $recentReports = [];

        $monthStart = $now->format('Y-m') . '-01';
        $monthEnd   = $now->format('Y-m-t');

        // ── Stats per anak aktif ─────────────────────────────
        $childrenStats = [];

        if (!empty($activeChildIds)) {
            // Satu query untuk semua laporan bulan ini (batch — tidak N+1)
            $monthReports = ProgressReport::whereIn('student_id', $activeChildIds)
                ->where('date', '>=', $monthStart)
                ->where('date', '<=', $monthEnd)
                ->get(['student_id', 'attendance', 'kualitas'])
                ->groupBy('student_id');

            foreach ($activeChildIds as $childId) {
                $group      = $monthReports->get($childId) ?? collect();
                $attCounts  = $group->countBy('attendance')->toArray();
                $qualCounts = $group->countBy('kualitas')->toArray();

                $childrenStats[$childId] = [
                    'attendance' => [
                        'hadir' => $attCounts['hadir'] ?? 0,
                        'izin'  => $attCounts['izin']  ?? 0,
                        'sakit' => $attCounts['sakit']  ?? 0,
                        'alpha' => $attCounts['alpha'] ?? 0,
                        'total' => $group->count(),
                    ],
                    'quality' => [
                        'sangat_lancar' => $qualCounts['sangat_lancar'] ?? 0,
                        'lancar'        => $qualCounts['lancar']        ?? 0,
                        'mengulang'     => $qualCounts['mengulang']     ?? 0,
                        'total'         => $group->count(),
                    ],
                ];
            }

            // ── Total laporan & catatan terbaru ───────────────────
            $totalLaporan = ProgressReport::whereIn('student_id', $activeChildIds)->count();
            $totalHadir   = ProgressReport::whereIn('student_id', $activeChildIds)
                ->where('attendance', 'hadir')
                ->count();

            $recentRaw = ProgressReport::whereIn('student_id', $activeChildIds)
                ->orderBy('date', 'desc')
                ->take(20)
                ->get();

            // Kumpulkan teacher IDs dari laporan, lalu query sekali (tidak Teacher::all())
            $teacherIds = $recentRaw->pluck('teacher_id')->filter()->unique()->values()->toArray();
            $teacherMap = empty($teacherIds) ? [] : $this->buildTeacherMap($teacherIds);

            $i = 1;
            $recentReports = $recentRaw->map(function ($r) use ($teacherMap, &$i) {
                return [
                    'id'            => $i++,
                    'student_id'    => $r->student_id    ?? '',
                    'date'          => $r->date          ?? '',
                    'report_type'   => $r->report_type   ?? '',
                    'kualitas'      => $r->kualitas      ?? '',
                    'teacher_name'  => $teacherMap[(string) ($r->teacher_id ?? '')] ?? 'Guru',
                    'teacher_notes' => $r->teacher_notes ?? '',
                ];
            })->values()->toArray();
        }

        $bulanId   = ['January'=>'Januari','February'=>'Februari','March'=>'Maret',
                       'April'=>'April','May'=>'Mei','June'=>'Juni','July'=>'Juli',
                       'August'=>'Agustus','September'=>'September','October'=>'Oktober',
                       'November'=>'November','December'=>'Desember'];
        $namaBulan = ($bulanId[$now->format('F')] ?? $now->format('F')) . ' ' . $now->format('Y');

        return Inertia::render('parents/Dashboard', [
            'anakList'       => $children,
            'first_child'    => $firstChild,
            'stats'          => [
                'total_anak'    => count($children),
                'total_laporan' => $totalLaporan,
                'total_hadir'   => $totalHadir,
            ],
            'bulan'          => $namaBulan,
            'children_stats' => $childrenStats,
            'recent_reports' => $recentReports,
            'profile'        => $profile,
        ]);
    }

    private function buildTeacherMap(array $ids): array
    {
        return Teacher::whereIn('id', $ids)
            ->get(['id', 'nama_guru'])
            ->keyBy(fn($t) => (string) $t->id)
            ->map(fn($t) => $t->nama_guru ?? '—')
            ->toArray();
    }
}
