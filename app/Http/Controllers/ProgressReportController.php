<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\Parents;
use App\Models\ProgressReport;
use App\Models\Program;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ProgressReportController extends Controller
{
    /* ═══════════════════════════════════════════════════════════
     | TEACHER ROUTES
     | Prefix: /api/teacher
     ═══════════════════════════════════════════════════════════ */

    public function teacherStudents(): JsonResponse
    {
        $teacher = $this->resolveTeacher();
        if (!$teacher) {
            return response()->json(['message' => 'Profil guru tidak ditemukan.'], 404);
        }

        $students   = Student::where('enrollment_status', 'active')->orderBy('nama')->get();
        $studentIds = $students->map(fn($s) => (string) $s->_id)->toArray();

        $programMap    = $this->buildProgramMap($students);
        $lastReportMap = $this->buildLastReportMap($studentIds, (string) $teacher->_id);

        $data = $students->map(function ($s) use ($programMap, $lastReportMap) {
            $sid = (string) $s->_id;
            return [
                'id'         => $sid,
                'nama'       => $s->nama ?? '',
                'program'    => $programMap[$s->program_id ?? ''] ?? '—',
                'lastReport' => $lastReportMap[$sid] ?? null,
            ];
        })->values();

        return response()->json($data);
    }

    public function teacherStudentReports(string $studentId): JsonResponse
    {
        $teacher = $this->resolveTeacher();
        if (!$teacher) {
            return response()->json(['message' => 'Profil guru tidak ditemukan.'], 404);
        }

        $student = Student::find($studentId);
        if (!$student) {
            return response()->json(['message' => 'Siswa tidak ditemukan.'], 404);
        }

        if ($student->enrollment_status !== 'active') {
            return response()->json(['message' => 'Siswa tidak aktif.'], 403);
        }

        $data = ProgressReport::where('student_id', $studentId)
            ->where('teacher_id', (string) $teacher->_id)
            ->orderBy('date', 'desc')
            ->get()
            ->map(fn($r) => $this->formatReport($r));

        return response()->json($data);
    }

    public function teacherStore(Request $request): JsonResponse
    {
        $teacher = $this->resolveTeacher();
        if (!$teacher) {
            return response()->json(['message' => 'Profil guru tidak ditemukan.'], 404);
        }

        $validated = $this->validateReport($request);

        $student = Student::find($validated['student_id']);
        if (!$student) {
            return response()->json(['message' => 'Siswa tidak ditemukan.'], 404);
        }

        $duplicate = ProgressReport::where('student_id', $validated['student_id'])
            ->where('teacher_id', (string) $teacher->_id)
            ->where('date', $validated['date'])
            ->exists();

        if ($duplicate) {
            return response()->json(['message' => 'Laporan untuk siswa ini pada tanggal tersebut sudah ada.'], 409);
        }

        $isAbsent = $this->isAbsent($validated);

        $report = ProgressReport::create(array_merge($this->reportFields($validated, $isAbsent), [
            'student_id' => $validated['student_id'],
            'teacher_id' => (string) $teacher->_id,
            'created_by' => 'teacher',
        ]));

        $this->notifyParent($student, $teacher, $validated, $isAbsent);

        return response()->json($this->formatReport($report), 201);
    }

    public function teacherShow(string $id): JsonResponse
    {
        $teacher = $this->resolveTeacher();
        if (!$teacher) {
            return response()->json(['message' => 'Profil guru tidak ditemukan.'], 404);
        }

        $report = ProgressReport::find($id);
        if (!$report) {
            return response()->json(['message' => 'Laporan tidak ditemukan.'], 404);
        }

        if ((string) $report->teacher_id !== (string) $teacher->_id) {
            return response()->json(['message' => 'Akses ditolak.'], 403);
        }

        return response()->json($this->formatReport($report));
    }

    public function teacherUpdate(Request $request, string $id): JsonResponse
    {
        $teacher = $this->resolveTeacher();
        if (!$teacher) {
            return response()->json(['message' => 'Profil guru tidak ditemukan.'], 404);
        }

        $report = ProgressReport::find($id);
        if (!$report) {
            return response()->json(['message' => 'Laporan tidak ditemukan.'], 404);
        }
        if ((string) $report->teacher_id !== (string) $teacher->_id) {
            return response()->json(['message' => 'Kamu tidak memiliki izin untuk mengubah laporan ini.'], 403);
        }

        $validated = $this->validateReport($request, requireStudent: false);
        $isAbsent  = $this->isAbsent($validated);

        $report->update($this->reportFields($validated, $isAbsent));

        return response()->json($this->formatReport($report->fresh()));
    }

    public function teacherDestroy(string $id): JsonResponse
    {
        $teacher = $this->resolveTeacher();
        if (!$teacher) {
            return response()->json(['message' => 'Profil guru tidak ditemukan.'], 404);
        }

        $report = ProgressReport::find($id);
        if (!$report) {
            return response()->json(['message' => 'Laporan tidak ditemukan.'], 404);
        }
        if ((string) $report->teacher_id !== (string) $teacher->_id) {
            return response()->json(['message' => 'Kamu tidak memiliki izin untuk menghapus laporan ini.'], 403);
        }

        $report->delete();
        return response()->json(['message' => 'Laporan berhasil dihapus.']);
    }

    /* ═══════════════════════════════════════════════════════════
     | PARENT ROUTES
     | Prefix: /api/parent
     ═══════════════════════════════════════════════════════════ */

    public function parentChildren(): JsonResponse
    {
        $userId = (string) Auth::id();
        $parent = Parents::where('user_id', $userId)->first();
        if (!$parent) {
            return response()->json(['message' => 'Profil wali murid tidak ditemukan.'], 404);
        }

        $students   = Student::where('parent_id', $userId)->orderBy('nama')->get();
        $programMap = $this->buildProgramMap($students);

        $data = $students->map(function ($s) use ($programMap) {
            return [
                'id'                => (string) $s->_id,
                'nama'              => $s->nama ?? '',
                'program_name'      => $programMap[$s->program_id ?? ''] ?? '—',
                'enrollment_status' => $s->enrollment_status ?? 'pending',
            ];
        })->values();

        return response()->json($data);
    }

    public function parentChildReports(string $studentId): JsonResponse
    {
        $userId = (string) Auth::id();
        $parent = Parents::where('user_id', $userId)->first();
        if (!$parent) {
            return response()->json(['message' => 'Profil wali murid tidak ditemukan.'], 404);
        }

        $student = Student::find($studentId);
        if (!$student) {
            return response()->json(['message' => 'Siswa tidak ditemukan.'], 404);
        }

        if ((string) $student->parent_id !== (string) $parent->user_id) {
            return response()->json(['message' => 'Akses ditolak.'], 403);
        }

        $status = $student->enrollment_status ?? 'pending';
        if ($status !== 'active') {
            return response()->json([
                'message' => $status === 'pending'
                    ? 'Pendaftaran anak Anda sedang dalam proses verifikasi. Laporan belum tersedia.'
                    : 'Akun anak Anda tidak aktif. Hubungi admin untuk informasi lebih lanjut.',
                'locked'  => true,
                'status'  => $status,
            ], 403);
        }

        $reports = ProgressReport::where('student_id', $studentId)
            ->orderBy('date', 'desc')
            ->get();

        $teacherIds = $reports->pluck('teacher_id')->filter()->unique()->values()->toArray();
        $teacherMap = $this->buildTeacherMap($teacherIds);

        $data = $reports->map(function ($r) use ($teacherMap) {
                $report = $this->formatReport($r);
                $report['teacher_name'] = $teacherMap[$report['teacher_id']] ?? 'Admin';
                return $report;
            })->values();

        return response()->json($data);
    }

    /* ═══════════════════════════════════════════════════════════
     | ADMIN ROUTES
     | Prefix: /api/admin/progress
     ═══════════════════════════════════════════════════════════ */

    public function adminOptions(): JsonResponse
    {
        $students = Student::where('enrollment_status', 'active')
            ->orderBy('nama')
            ->get(['id', 'nama'])
            ->map(fn($s) => ['id' => $s->id, 'label' => $s->nama]);

        $teachers = Teacher::orderBy('nama_guru')
            ->get(['id', 'nama_guru'])
            ->map(fn($t) => ['id' => $t->id, 'label' => $t->nama_guru ?? '—']);

        $programs = Program::orderBy('name')
            ->get(['id', 'name'])
            ->map(fn($p) => ['id' => $p->id, 'label' => $p->name ?? '—']);

        return response()->json(compact('students', 'teachers', 'programs'));
    }

    public function adminStudents(Request $request): JsonResponse
    {
        $search    = trim($request->query('search', ''));
        $programId = trim($request->query('program_id', ''));

        $query = Student::where('enrollment_status', 'active');

        if ($search !== '') {
            $query->where('nama', 'like', '%' . $search . '%');
        }
        if ($programId !== '') {
            $query->where('program_id', $programId);
        }

        $students   = $query->orderBy('nama')->get();
        $studentIds = $students->pluck('id')->map(fn($id) => (string) $id)->toArray();

        $programMap    = $this->buildProgramMap($students);
        $lastReportMap = $this->buildLastReportMap($studentIds);

        $data = $students->map(function ($s) use ($programMap, $lastReportMap) {
            $sid = (string) $s->id;
            return [
                'id'         => $sid,
                'nama'       => $s->nama ?? '',
                'program'    => $programMap[(string) ($s->program_id ?? '')] ?? '—',
                'program_id' => $s->program_id ?? null,
                'lastReport' => $lastReportMap[$sid] ?? null,
            ];
        })->values();

        return response()->json($data);
    }

    public function adminStudentReports(string $studentId): JsonResponse
    {
        if (!Student::find($studentId)) {
            return response()->json(['message' => 'Siswa tidak ditemukan.'], 404);
        }

        $reports = ProgressReport::where('student_id', $studentId)
            ->orderBy('date', 'desc')
            ->get();

        $teacherIds = $reports->pluck('teacher_id')->filter()->unique()->values()->toArray();
        $teacherMap = $this->buildTeacherMap($teacherIds);

        $data = $reports->map(function ($r) use ($teacherMap) {
                $report = $this->formatReport($r);
                $report['teacher_name'] = $teacherMap[$report['teacher_id']] ?? '—';
                return $report;
            })->values();

        return response()->json($data);
    }

    public function adminReports(Request $request): JsonResponse
    {
        $search    = trim($request->query('search', ''));
        $programId = trim($request->query('program_id', ''));
        $perPage   = max(1, min(100, (int) $request->query('per_page', 20)));
        $page      = max(1, (int) $request->query('page', 1));
        $skip      = ($page - 1) * $perPage;

        $studentIdFilter = null;
        if ($search !== '' || $programId !== '') {
            $sQuery = Student::where('enrollment_status', 'active');
            if ($search !== '') {
                $sQuery->where('nama', 'like', '%' . $search . '%');
            }
            if ($programId !== '') {
                $sQuery->where('program_id', $programId);
            }
            $studentIdFilter = $sQuery->get(['id'])->pluck('id')->map(fn($id) => (string) $id)->toArray();

            if (empty($studentIdFilter)) {
                return response()->json([
                    'data' => [],
                    'meta' => ['total' => 0, 'page' => $page, 'per_page' => $perPage, 'last_page' => 1],
                ]);
            }
        }

        $query = ProgressReport::query();
        if ($studentIdFilter !== null) {
            $query->whereIn('student_id', $studentIdFilter);
        }

        $total   = $query->count();
        $reports = $query->orderBy('date', 'desc')->skip($skip)->take($perPage)->get();

        $allStudentIds = $reports->pluck('student_id')->filter()->unique()->values()->toArray();
        $allTeacherIds = $reports->pluck('teacher_id')->filter()->unique()->values()->toArray();

        $studentNameMap      = $this->buildStudentNameMap($allStudentIds);
        $teacherMap          = $this->buildTeacherMap($allTeacherIds);
        $programByStudentMap = $this->buildProgramByStudentMap($allStudentIds);

        $data = $reports->map(function ($r) use ($studentNameMap, $teacherMap, $programByStudentMap) {
            $report                 = $this->formatReport($r);
            $sid                    = $report['student_id'];
            $tid                    = $report['teacher_id'];
            $report['student_name'] = $studentNameMap[$sid] ?? '—';
            $report['teacher_name'] = $teacherMap[$tid]     ?? '—';
            $report['program']      = $programByStudentMap[$sid] ?? '—';
            return $report;
        })->values();

        return response()->json([
            'data' => $data,
            'meta' => [
                'total'     => $total,
                'page'      => $page,
                'per_page'  => $perPage,
                'last_page' => (int) ceil($total / max($perPage, 1)),
            ],
        ]);
    }

    public function adminStore(Request $request): JsonResponse
    {
        $validated = $this->validateReport($request, withTeacher: true);

        $student = Student::find($validated['student_id']);
        if (!$student) {
            return response()->json(['message' => 'Siswa tidak ditemukan.'], 404);
        }

        $isAbsent = $this->isAbsent($validated);

        $report = ProgressReport::create(array_merge($this->reportFields($validated, $isAbsent), [
            'student_id' => $validated['student_id'],
            'teacher_id' => $validated['teacher_id'] ?? null,
            'created_by' => 'admin',
        ]));

        $this->notifyParent($student, null, $validated, $isAbsent);

        return response()->json($this->formatReport($report), 201);
    }

    public function adminUpdate(Request $request, string $id): JsonResponse
    {
        $report = ProgressReport::find($id);
        if (!$report) {
            return response()->json(['message' => 'Laporan tidak ditemukan.'], 404);
        }

        $validated = $this->validateReport($request, withTeacher: true, requireStudent: false);
        $isAbsent  = $this->isAbsent($validated);

        $report->update(array_merge($this->reportFields($validated, $isAbsent), [
            'teacher_id' => $validated['teacher_id'] ?? null,
        ]));

        return response()->json($this->formatReport($report->fresh()));
    }

    public function adminDestroy(string $id): JsonResponse
    {
        $report = ProgressReport::find($id);

        if (!$report) {
            return response()->json(['message' => 'Laporan tidak ditemukan.'], 404);
        }

        $report->delete();
        return response()->json(['message' => 'Laporan berhasil dihapus.']);
    }

    /* ═══════════════════════════════════════════════════════════
     | PRIVATE HELPERS
     ═══════════════════════════════════════════════════════════ */

    private function resolveTeacher(): ?object
    {
        return Teacher::where('user_id', (string) Auth::id())->first();
    }

    private function notifyParent(
        $student,
        $teacher,
        array $validated,
        bool $isAbsent
    ): void {
        try {
            $parentUserId = (string) ($student->parent_id ?? '');
            if (empty($parentUserId)) return;

            $studentName = $student->nama ?? 'Anak Anda';
            $teacherName = $teacher ? ($teacher->nama_guru ?? 'Guru') : 'Admin QLC';
            $date        = $validated['date'] ?? now()->format('Y-m-d');
            $attendance  = $validated['attendance'] ?? 'hadir';

            if ($isAbsent) {
                $attendanceLabel = match ($attendance) {
                    'izin'  => 'izin',
                    'sakit' => 'sakit',
                    'alpha' => 'tidak hadir tanpa keterangan',
                    default => $attendance,
                };
                $title   = "Ketidakhadiran: {$studentName}";
                $message = "{$studentName} tercatat {$attendanceLabel} pada {$date}.";
            } else {
                $kualitasLabel = match ($validated['kualitas'] ?? null) {
                    'sangat_lancar' => 'Sangat Lancar',
                    'lancar'        => 'Lancar',
                    'mengulang'     => 'Mengulang',
                    default         => '—',
                };
                $title   = "Laporan Progress: {$studentName}";
                $message = "Laporan {$date} oleh {$teacherName}. "
                        . "Jenis: " . strtoupper($validated['report_type'] ?? '—') . ", "
                        . "Kualitas: {$kualitasLabel}.";
            }

            Notification::send(
                userId:  $parentUserId,
                type:    'progress',
                title:   $title,
                message: $message,
                link:    '?tab=laporan',
            );
        } catch (\Exception $e) {
            \Log::warning('Gagal mengirim notifikasi ke wali murid: ' . $e->getMessage());
        }
    }

    private function isAbsent(array $validated): bool
    {
        return in_array($validated['attendance'], ['izin', 'sakit', 'alpha']);
    }

    private function reportFields(array $validated, bool $isAbsent): array
    {
        return [
            'date'                => $validated['date'],
            'attendance'          => $validated['attendance'],
            'report_type'         => $isAbsent ? null : ($validated['report_type']         ?? null),
            'kualitas'            => $isAbsent ? null : ($validated['kualitas']            ?? null),
            'hafalan_target'      => $isAbsent ? null : ($validated['hafalan_target']      ?? null),
            'hafalan_achievement' => $isAbsent ? null : ($validated['hafalan_achievement'] ?? null),
            'teacher_notes'       => $validated['teacher_notes'] ?? null,
        ];
    }

    private function validateReport(Request $request, bool $withTeacher = false, bool $requireStudent = true): array
    {
        $rules = [
            'date'                => 'required|date_format:Y-m-d|before_or_equal:today',
            'attendance'          => ['required', Rule::in(['hadir', 'izin', 'sakit', 'alpha'])],
            'report_type'         => ['nullable', Rule::in(['hafalan', 'tilawah', 'yanbua'])],
            'kualitas'            => ['nullable', Rule::in(['sangat_lancar', 'lancar', 'mengulang'])],
            'hafalan_target'      => 'nullable|string|max:255',
            'hafalan_achievement' => 'nullable|string|max:255',
            'teacher_notes'       => 'nullable|string|max:2000',
        ];

        if ($requireStudent) $rules['student_id'] = 'required|string';
        if ($withTeacher)    $rules['teacher_id'] = 'nullable|string';

        return $request->validate($rules);
    }

    private function buildLastReportMap(array $studentIds, ?string $teacherId = null): array
    {
        if (empty($studentIds)) return [];

        $query = ProgressReport::whereIn('student_id', $studentIds)->orderBy('date', 'desc');
        if ($teacherId !== null) {
            $query->where('teacher_id', $teacherId);
        }

        $map = [];
        foreach ($query->limit(count($studentIds) * 50)->get() as $r) {
            $sid = (string) $r->student_id;
            if (!isset($map[$sid])) {
                $map[$sid] = $this->formatReport($r);
            }
        }
        return $map;
    }

    private function buildProgramMap($students): array
    {
        $ids = $students->pluck('program_id')->filter()->unique()->values()->toArray();
        if (empty($ids)) return [];
        return Program::whereIn('id', $ids)->get()
            ->keyBy(fn($p) => (string) $p->id)
            ->map(fn($p) => $p->name ?? '—')
            ->toArray();
    }

    private function buildStudentNameMap(array $studentIds): array
    {
        if (empty($studentIds)) return [];
        return Student::whereIn('id', $studentIds)
            ->get(['id', 'nama'])
            ->keyBy(fn($s) => (string) $s->id)
            ->map(fn($s) => $s->nama ?? '—')
            ->toArray();
    }

    private function buildTeacherMap(array $teacherIds): array
    {
        if (empty($teacherIds)) return [];

        return Teacher::whereIn('id', $teacherIds)
            ->get(['id', 'nama_guru'])
            ->keyBy(fn($t) => (string) $t->id)
            ->map(fn($t) => $t->nama_guru ?? '—')
            ->toArray();
    }

    private function buildProgramByStudentMap(array $studentIds): array
    {
        if (empty($studentIds)) return [];

        $students   = Student::whereIn('id', $studentIds)->get(['id', 'program_id']);
        $pidBySid   = $students->keyBy(fn($s) => (string) $s->id)->map(fn($s) => $s->program_id)->toArray();
        $programIds = array_values(array_unique(array_filter(array_values($pidBySid))));

        $nameMap = [];
        if (!empty($programIds)) {
            $nameMap = Program::whereIn('id', $programIds)->get()
                ->keyBy(fn($p) => (string) $p->id)
                ->map(fn($p) => $p->name ?? '—')
                ->toArray();
        }

        $result = [];
        foreach ($pidBySid as $sid => $pid) {
            $result[$sid] = $pid ? ($nameMap[$pid] ?? '—') : '—';
        }
        return $result;
    }

    private function formatReport($doc): array
    {
        return [
            'id'                  => (string) $doc->_id,
            'student_id'          => (string) ($doc->student_id          ?? ''),
            'teacher_id'          => (string) ($doc->teacher_id          ?? ''),
            'date'                => $doc->date                 ?? null,
            'attendance'          => $doc->attendance           ?? null,
            'report_type'         => $doc->report_type          ?? null,
            'kualitas'            => $doc->kualitas             ?? null,
            'hafalan_target'      => $doc->hafalan_target       ?? null,
            'hafalan_achievement' => $doc->hafalan_achievement  ?? null,
            'teacher_notes'       => $doc->teacher_notes        ?? null,
            'created_at'          => $doc->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
