<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\Parents;
use App\Models\ProgressReport;
use App\Models\Program;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class StudentController extends Controller
{
    public function index(Request $request)
    {
        $search    = $request->query('search', '');
        $status    = $request->query('status', '');
        $programId = $request->query('program_id', '');
        $perPage   = max(1, min(100, (int) $request->query('per_page', 10)));
        $page      = (int) $request->query('page', 1);
        $skip      = ($page - 1) * $perPage;

        $query = Student::query();

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', '%' . $search . '%')
                  ->orWhere('tempat_lahir', 'like', '%' . $search . '%');
            });
        }

        if (!empty($status))    $query->where('enrollment_status', $status);
        if (!empty($programId)) $query->where('program_id', $programId);

        $total    = $query->count();
        $students = $query->orderBy('nama')->skip($skip)->take($perPage)->get();

        $programIds = $students->pluck('program_id')->filter()->unique()->values();
        $programs   = Program::whereIn('id', $programIds->toArray())
            ->get()
            ->keyBy(fn($p) => (string) $p->id);

        return response()->json([
            'success' => true,
            'data'    => $students->map(fn($s) => $this->format($s, $programs)),
            'meta'    => [
                'total'     => $total,
                'page'      => $page,
                'per_page'  => $perPage,
                'last_page' => (int) ceil($total / $perPage),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'parent_id'         => 'required',
            'program_id'        => 'required',
            'nama'              => 'required|string|max:100',
            'usia'              => 'required|integer|min:1|max:30',
            'tempat_lahir'      => 'required|string|max:100',
            'tanggal_lahir'     => 'required|date_format:Y-m-d',
            'enrollment_status' => 'required|in:active,inactive,pending',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $parent = Parents::where('user_id', $request->parent_id)->first();
        if (!$parent) {
            return response()->json(['success' => false, 'message' => 'Wali murid tidak ditemukan.'], 404);
        }

        $program = Program::find($request->program_id);
        if (!$program) {
            return response()->json(['success' => false, 'message' => 'Program tidak ditemukan.'], 404);
        }

        $student = Student::create([
            'parent_id'         => $request->parent_id,
            'parent_name'       => $parent->parent_name ?? null,
            'program_id'        => $request->program_id,
            'nama'              => $request->nama,
            'usia'              => (int) $request->usia,
            'tempat_lahir'      => $request->tempat_lahir,
            'tanggal_lahir'     => $request->tanggal_lahir,
            'enrollment_status' => $request->enrollment_status,
        ]);

        if ($request->enrollment_status === 'active') {
            Notification::sendToRole(
                'teacher',
                'pendaftaran',
                'Santri Baru Aktif',
                "Santri baru \"{$request->nama}\" telah terdaftar aktif di {$program->name}.",
                null
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Siswa berhasil ditambahkan.',
            'data'    => $this->format($student),
        ], 201);
    }

    public function show(string $id)
    {
        $student = Student::find($id);

        if (!$student) {
            return response()->json(['success' => false, 'message' => 'Siswa tidak ditemukan.'], 404);
        }

        // Pre-load relasi agar format() tidak melakukan query individual (N+1)
        $programs = collect();
        if (!empty($student->program_id)) {
            $programs = Program::whereIn('id', [$student->program_id])
                ->get()
                ->keyBy(fn($p) => (string) $p->id);
        }

        return response()->json(['success' => true, 'data' => $this->format($student, $programs)]);
    }

    public function update(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'parent_id'         => 'required',
            'program_id'        => 'required',
            'nama'              => 'required|string|max:100',
            'usia'              => 'required|integer|min:1|max:30',
            'tempat_lahir'      => 'required|string|max:100',
            'tanggal_lahir'     => 'required|date_format:Y-m-d',
            'enrollment_status' => 'required|in:active,inactive,pending',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $student = Student::find($id);
        if (!$student) {
            return response()->json(['success' => false, 'message' => 'Siswa tidak ditemukan.'], 404);
        }

        $oldStatus = $student->enrollment_status;

        $parent = Parents::where('user_id', $request->parent_id)->first();
        if (!$parent) {
            return response()->json(['success' => false, 'message' => 'Wali murid tidak ditemukan.'], 404);
        }

        $program = Program::find($request->program_id);
        if (!$program) {
            return response()->json(['success' => false, 'message' => 'Program tidak ditemukan.'], 404);
        }

        $student->update([
            'parent_id'         => $request->parent_id,
            'parent_name'       => $parent->parent_name ?? null,
            'program_id'        => $request->program_id,
            'nama'              => $request->nama,
            'usia'              => (int) $request->usia,
            'tempat_lahir'      => $request->tempat_lahir,
            'tanggal_lahir'     => $request->tanggal_lahir,
            'enrollment_status' => $request->enrollment_status,
        ]);

        if ($request->enrollment_status === 'active' && $oldStatus !== 'active') {
            Notification::sendToRole(
                'teacher',
                'pendaftaran',
                'Santri Baru Aktif',
                "Santri \"{$request->nama}\" telah disetujui dan aktif di {$program->name}.",
                null
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Data siswa berhasil diperbarui.',
            'data'    => $this->format($student->fresh()),
        ]);
    }

    public function destroy(string $id)
    {
        $student = Student::find($id);

        if (!$student) {
            return response()->json(['success' => false, 'message' => 'Siswa tidak ditemukan.'], 404);
        }

        $studentId = $student->id;

        // Cascade: hapus semua laporan progress terkait siswa ini
        ProgressReport::where('student_id', $studentId)->delete();

        if (!empty($student->bukti_pembayaran)) {
            $parsed = parse_url($student->bukti_pembayaran, PHP_URL_PATH);
            if ($parsed) {
                Storage::disk('public')->delete(str_replace('/storage/', '', $parsed));
            }
        }

        $student->delete();

        Log::info('audit.student_deleted', [
            'student_id' => $studentId,
            'nama'       => $student->nama ?? '—',
            'by_admin'   => auth()->id(),
            'ip'         => request()->ip(),
        ]);

        return response()->json(['success' => true, 'message' => 'Data siswa berhasil dihapus.']);
    }

    public function options(Request $request)
    {
        $search = trim($request->query('search', ''));

        $parentQuery = Parents::orderBy('parent_name');
        if ($search !== '') {
            $parentQuery->where('parent_name', 'like', '%' . $search . '%');
        }

        $parents = $parentQuery->limit(50)->get()->map(fn($p) => [
            'id'    => $p->user_id,
            'label' => $p->parent_name . ' — ' . ($p->phone ?? ''),
        ]);

        $programs = Program::orderBy('name')->get(['id', 'name'])->map(fn($p) => [
            'id'    => $p->id,
            'label' => $p->name ?? (string) $p->id,
        ]);

        return response()->json([
            'success'  => true,
            'parents'  => $parents,
            'programs' => $programs,
        ]);
    }

    private function format($doc, $programs = null): array
    {
        $pid         = (string) ($doc->program_id ?? '');
        $programName = ($programs && isset($programs[$pid])) ? ($programs[$pid]->name ?? null) : null;

        return [
            'id'                => $doc->id,
            'parent_id'         => $doc->parent_id ?? null,
            'parent_name'       => $doc->parent_name ?? null,
            'program_id'        => $doc->program_id ?? null,
            'program_name'      => $programName,
            'nama'              => $doc->nama,
            'usia'              => $doc->usia ?? null,
            'tempat_lahir'      => $doc->tempat_lahir,
            'tanggal_lahir'     => $doc->tanggal_lahir,
            'enrollment_status' => $doc->enrollment_status,
            'bukti_pembayaran'  => $doc->bukti_pembayaran ?? null,
            'created_at'        => $doc->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
