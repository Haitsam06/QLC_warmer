<?php

namespace App\Http\Controllers;

use App\Models\ProgressReport;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class TeacherController extends Controller
{
    private const ROLE_TEACHER = 'RL02';

    public function index(Request $request)
    {
        $search       = $request->query('search', '');
        $spesialisasi = $request->query('spesialisasi', '');
        $perPage      = max(1, min(100, (int) $request->query('per_page', 10)));
        $page         = (int) $request->query('page', 1);
        $skip         = ($page - 1) * $perPage;

        $query = Teacher::query();

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('nama_guru', 'ilike', '%' . $search . '%')
                  ->orWhere('phone', 'ilike', '%' . $search . '%')
                  ->orWhere('bidang', 'ilike', '%' . $search . '%');
            });
        }

        if (!empty($spesialisasi)) {
            $query->where('bidang', $spesialisasi);
        }

        $total    = $query->count();
        $teachers = $query->orderBy('nama_guru')->skip($skip)->take($perPage)->get();

        // Batch load usernames — hindari N+1
        $userIds = $teachers->pluck('user_id')->filter()->unique()->values()->toArray();
        $userMap = empty($userIds) ? collect() : User::whereIn('id', $userIds)
            ->get(['id', 'username', 'email'])
            ->keyBy(fn($u) => (string) $u->id);

        return response()->json([
            'success' => true,
            'data'    => $teachers->map(fn($t) => $this->formatTeacher($t, $userMap->get((string) ($t->user_id ?? '')))),
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
            'nama_guru'    => 'required|string|max:100',
            'phone'        => 'required|string|max:20',
            'spesialisasi' => 'required|string|max:100',
            'username'     => 'required|string|min:4|max:50|alpha_num',
            'password'     => 'required|string|min:8|max:100',
            'email'        => 'nullable|email|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        if (User::where('username', $request->username)->exists()) {
            return response()->json(['success' => false, 'message' => 'Username sudah digunakan.'], 409);
        }

        $email = $request->filled('email') ? $request->email : (strtolower($request->username) . '@qlc.id');

        if (User::where('email', $email)->exists()) {
            return response()->json(['success' => false, 'message' => 'Email sudah digunakan akun lain.'], 409);
        }

        if ($request->filled('phone') && Teacher::where('phone', $request->phone)->exists()) {
            return response()->json(['success' => false, 'message' => 'Nomor telepon sudah terdaftar pada guru lain.'], 409);
        }

        try {
            $user = User::create([
                'role_id'  => self::ROLE_TEACHER,
                'username' => $request->username,
                'password' => Hash::make($request->password),
                'email'    => $email,
                'photo'    => null,
            ]);

            $teacher = Teacher::create([
                'user_id'   => $user->id,
                'nama_guru' => $request->nama_guru,
                'phone'     => $request->phone,
                'email'     => $request->filled('email') ? $request->email : null,
                'bidang'    => $request->spesialisasi,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Guru & akun berhasil ditambahkan.',
                'data'    => $this->formatTeacher($teacher, $user),
            ], 201);
        } catch (\Exception $e) {
            if (isset($user) && $user->exists) {
                $user->delete();
            }
            Log::error('Teacher store error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan data guru: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function show(string $id)
    {
        $teacher = Teacher::find($id);

        if (!$teacher) {
            return response()->json(['success' => false, 'message' => 'Guru tidak ditemukan.'], 404);
        }

        $user = !empty($teacher->user_id) ? User::find($teacher->user_id) : null;
        return response()->json(['success' => true, 'data' => $this->formatTeacher($teacher, $user)]);
    }

    public function update(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'nama_guru'    => 'nullable|string|max:100',
            'phone'        => 'nullable|string|max:20',
            'spesialisasi' => 'nullable|string|max:100',
            'username'     => 'nullable|string|min:4|max:50|alpha_num',
            'email'        => 'nullable|email|max:100',
            'new_password' => 'nullable|string|min:8|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        $teacher = Teacher::find($id);

        if (!$teacher) {
            return response()->json(['success' => false, 'message' => 'Guru tidak ditemukan.'], 404);
        }

        if ($request->filled('phone') && Teacher::where('phone', $request->phone)->where('id', '!=', $teacher->id)->exists()) {
            return response()->json(['success' => false, 'message' => 'Nomor telepon sudah digunakan guru lain.'], 409);
        }

        $teacherUpdate = [];
        if ($request->filled('nama_guru'))    $teacherUpdate['nama_guru'] = $request->nama_guru;
        if ($request->filled('phone'))        $teacherUpdate['phone']     = $request->phone;
        if ($request->filled('spesialisasi')) $teacherUpdate['bidang']    = $request->spesialisasi;
        if ($request->has('email'))           $teacherUpdate['email']     = $request->email ?: null;
        if (!empty($teacherUpdate)) $teacher->update($teacherUpdate);

        $userId = $teacher->user_id ?? null;
        $user = null;
        if ($userId) {
            $user = User::find($userId);
            if ($user) {
                if ($request->filled('username') && User::where('username', $request->username)->where('id', '!=', $userId)->exists()) {
                    return response()->json(['success' => false, 'message' => 'Username sudah digunakan akun lain.'], 409);
                }
                if ($request->filled('email') && User::where('email', $request->email)->where('id', '!=', $userId)->exists()) {
                    return response()->json(['success' => false, 'message' => 'Email sudah digunakan akun lain.'], 409);
                }

                $userUpdate = [];
                if ($request->filled('username'))     $userUpdate['username'] = $request->username;
                if ($request->filled('email'))        $userUpdate['email']    = $request->email;
                if ($request->filled('new_password')) $userUpdate['password'] = Hash::make($request->new_password);
                if (!empty($userUpdate)) $user->update($userUpdate);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Data guru berhasil diperbarui.',
            'data'    => $this->formatTeacher($teacher->fresh(), $user ? $user->fresh() : null),
        ]);
    }

    public function destroy(string $id)
    {
        $teacher = Teacher::find($id);

        if (!$teacher) {
            return response()->json(['success' => false, 'message' => 'Guru tidak ditemukan.'], 404);
        }

        $teacherId = $teacher->id;
        $userId = $teacher->user_id ?? null;

        // Preserve data akademik: nullkan teacher_id agar riwayat siswa tidak hilang
        try {
            ProgressReport::where('teacher_id', $teacherId)->update(['teacher_id' => null]);
        } catch (\Exception $e) {
            Log::warning('ProgressReport update teacher_id failed: ' . $e->getMessage());
        }

        $teacher->delete();

        if (!empty($userId)) {
            User::find($userId)?->delete();
        }

        Log::info('audit.teacher_deleted', [
            'teacher_id' => $teacherId,
            'nama_guru'  => $teacher->nama_guru ?? '—',
            'by_admin'   => auth()->id(),
            'ip'         => request()->ip(),
        ]);

        return response()->json(['success' => true, 'message' => 'Guru berhasil dihapus.']);
    }

    public function resetPassword(string $id)
    {
        $teacher = Teacher::find($id);

        if (!$teacher) {
            return response()->json(['success' => false, 'message' => 'Guru tidak ditemukan.'], 404);
        }

        if (empty($teacher->user_id)) {
            return response()->json(['success' => false, 'message' => 'Guru tidak memiliki akun login.'], 400);
        }

        $user = User::find($teacher->user_id);
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Akun user guru tidak ditemukan.'], 404);
        }

        $newPassword = 'mieayambakso';
        $user->update(['password' => Hash::make($newPassword)]);

        Log::info('audit.password_reset', [
            'target'    => 'teacher',
            'target_id' => $teacher->id,
            'user_id'   => $teacher->user_id ?? null,
            'by_admin'  => auth()->id(),
            'ip'        => request()->ip(),
        ]);

        return response()->json([
            'success'      => true,
            'message'      => 'Password guru berhasil direset ke default (mieayambakso).',
            'new_password' => $newPassword,
        ]);
    }

    public function updateOwnProfile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|min:4|max:50|alpha_num',
            'email'    => 'nullable|email|max:100',
            'photo'    => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        $user = Auth::user();

        if (!$user) {
            return back()->withErrors(['auth' => 'User tidak ditemukan.']);
        }

        if (User::where('username', $request->username)->where('id', '!=', $user->id)->exists()) {
            return back()->withErrors(['username' => 'Username sudah digunakan.']);
        }

        if ($request->filled('email') && User::where('email', $request->email)->where('id', '!=', $user->id)->exists()) {
            return back()->withErrors(['email' => 'Email sudah digunakan akun lain.']);
        }

        $photoUrl = $user->photo ?? null;

        if ($request->hasFile('photo')) {
            if ($photoUrl && str_contains($photoUrl, '/storage/')) {
                $parsedPath = parse_url($photoUrl, PHP_URL_PATH);
                if ($parsedPath) {
                    Storage::disk('public')->delete(str_replace('/storage/', '', $parsedPath));
                }
            }
            $path     = $request->file('photo')->store('profile', 'public');
            $photoUrl = url('storage/' . $path);
        }

        $user->update([
            'username' => $request->username,
            'email'    => $request->email ?? $user->email,
            'photo'    => $photoUrl,
        ]);

        Auth::setUser($user->fresh());

        return back()->with('success', 'Profil berhasil diperbarui.');
    }

    public function updateOwnPassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password'         => 'required|min:8|confirmed',
        ]);

        $user = Auth::user();

        if (!Hash::check($request->current_password, $user->password)) {
            return back()->withErrors(['current_password' => 'Password saat ini salah.']);
        }

        $user->update(['password' => Hash::make($request->password)]);

        return back()->with('success', 'Password berhasil diperbarui.');
    }

    public function spesialisasiList()
    {
        $list = Teacher::whereNotNull('bidang')
            ->where('bidang', '!=', '')
            ->pluck('bidang')
            ->unique()
            ->sort()
            ->values()
            ->toArray();

        return response()->json(['success' => true, 'data' => $list]);
    }

    private function formatTeacher($doc, $user = null): array
    {
        return [
            'id'           => (string) ($doc->id ?? $doc->_id),
            'user_id'      => $doc->user_id ? (string) $doc->user_id : null,
            'username'     => $user?->username ?? null,
            'nama_guru'    => $doc->nama_guru ?? '',
            'phone'        => $doc->phone ?? '',
            'email'        => $doc->email ?? $user?->email ?? null,
            'spesialisasi' => $doc->bidang ?? '',
            'created_at'   => $doc->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}

