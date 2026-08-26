<?php

namespace App\Http\Controllers;

use App\Models\Parents;
use App\Models\ProgressReport;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ParentController extends Controller
{
    private const ROLE_PARENT = 'RL03';

    public function index(Request $request)
    {
        $search  = $request->query('search', '');
        $perPage = max(1, min(100, (int) $request->query('per_page', 10)));
        $page    = (int) $request->query('page', 1);
        $skip    = ($page - 1) * $perPage;

        $query = Parents::query();

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('parent_name', 'like', '%' . $search . '%')
                  ->orWhere('phone', 'like', '%' . $search . '%')
                  ->orWhere('address', 'like', '%' . $search . '%');
            });
        }

        $total   = $query->count();
        $parents = $query->orderBy('parent_name')->skip($skip)->take($perPage)->get();

        $userIds = $parents->pluck('user_id')->filter()->unique()->values()->toArray();
        $userMap = empty($userIds) ? collect() : User::whereIn('id', $userIds)
            ->get(['id', 'username', 'email'])
            ->keyBy(fn($u) => (string) $u->id);

        return response()->json([
            'success' => true,
            'data'    => $parents->map(fn($p) => $this->format($p, $userMap->get((string) ($p->user_id ?? '')))),
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
            'parent_name' => 'required|string|max:100',
            'phone'       => 'required|string|max:20',
            'address'     => 'required|string|max:255',
            'username'    => 'required|string|min:4|max:50|alpha_num',
            'password'    => 'required|string|min:8|max:100',
            'email'       => 'nullable|email|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        if (User::where('username', $request->username)->exists()) {
            return response()->json(['success' => false, 'message' => 'Username sudah digunakan.'], 409);
        }

        if ($request->email && User::where('email', $request->email)->exists()) {
            return response()->json(['success' => false, 'message' => 'Email sudah digunakan.'], 409);
        }

        if (Parents::where('phone', $request->phone)->exists()) {
            return response()->json(['success' => false, 'message' => 'Nomor telepon sudah terdaftar.'], 409);
        }

        $user = User::create([
            'role_id'  => self::ROLE_PARENT,
            'username' => $request->username,
            'password' => Hash::make($request->password),
            'email'    => $request->email ?? null,
            'photo'    => null,
        ]);

        try {
            $parent = Parents::create([
                'user_id'     => $user->id,
                'parent_name' => $request->parent_name,
                'phone'       => $request->phone,
                'address'     => $request->address,
            ]);
        } catch (\Exception $e) {
            $user->delete();
            return response()->json(['success' => false, 'message' => 'Gagal menyimpan data wali murid.'], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Wali murid berhasil ditambahkan.',
            'data'    => $this->format($parent),
        ], 201);
    }

    public function show(string $id)
    {
        $parent = Parents::find($id);

        if (!$parent) {
            return response()->json(['success' => false, 'message' => 'Wali murid tidak ditemukan.'], 404);
        }

        return response()->json(['success' => true, 'data' => $this->format($parent)]);
    }

    public function update(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'parent_name'  => 'nullable|string|max:100',
            'phone'        => 'nullable|string|max:20',
            'address'      => 'nullable|string|max:255',
            'username'     => 'nullable|string|min:4|max:50|alpha_num',
            'email'        => 'nullable|email|max:100',
            'new_password' => 'nullable|string|min:8|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $parent = Parents::find($id);

        if (!$parent) {
            return response()->json(['success' => false, 'message' => 'Wali murid tidak ditemukan.'], 404);
        }

        if ($request->filled('phone') && Parents::where('phone', $request->phone)->where('id', '!=', $parent->id)->exists()) {
            return response()->json(['success' => false, 'message' => 'Nomor telepon sudah digunakan wali murid lain.'], 409);
        }

        $parentUpdate = [];
        if ($request->filled('parent_name')) $parentUpdate['parent_name'] = $request->parent_name;
        if ($request->filled('phone'))       $parentUpdate['phone']       = $request->phone;
        if ($request->filled('address'))     $parentUpdate['address']     = $request->address;
        if (!empty($parentUpdate)) $parent->update($parentUpdate);

        $userId = $parent->user_id ?? null;
        if ($userId) {
            if ($request->filled('username') && User::where('username', $request->username)->where('id', '!=', $userId)->exists()) {
                return response()->json(['success' => false, 'message' => 'Username sudah digunakan akun lain.'], 409);
            }
            if ($request->filled('email') && User::where('email', $request->email)->where('id', '!=', $userId)->exists()) {
                return response()->json(['success' => false, 'message' => 'Email sudah digunakan akun lain.'], 409);
            }
        }

        $user = null;
        if ($userId) {
            $user = User::find($userId);
            if ($user) {
                $userUpdate = [];
                if ($request->filled('username'))     $userUpdate['username'] = $request->username;
                if ($request->filled('email'))        $userUpdate['email']    = $request->email;
                if ($request->filled('new_password')) $userUpdate['password'] = Hash::make($request->new_password);
                if (!empty($userUpdate)) $user->update($userUpdate);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Data wali murid berhasil diperbarui.',
            'data'    => $this->format($parent->fresh(), $user),
        ]);
    }

    public function updateOwnProfile(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return back()->withErrors(['general' => 'Unauthorized']);
        }

        $validator = Validator::make($request->all(), [
            'username' => 'required|string|min:4|max:50|alpha_num',
            'email'    => 'nullable|email|max:100',
            'photo'    => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        if (User::where('username', $request->username)->where('id', '!=', $user->id)->exists()) {
            return back()->withErrors(['username' => 'Username sudah digunakan.']);
        }

        if ($request->email && User::where('email', $request->email)->where('id', '!=', $user->id)->exists()) {
            return back()->withErrors(['email' => 'Email sudah digunakan.']);
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
            'email'    => $request->email,
            'photo'    => $photoUrl,
        ]);

        Auth::setUser($user->fresh());

        return back()->with('success', 'Profil berhasil diperbarui.');
    }

    public function resetPassword(string $id)
    {
        $parent = Parents::find($id);

        if (!$parent) {
            return response()->json(['success' => false, 'message' => 'Wali murid tidak ditemukan.'], 404);
        }

        if (empty($parent->user_id)) {
            return response()->json(['success' => false, 'message' => 'Akun wali murid tidak ditemukan.'], 404);
        }

        $user = User::find($parent->user_id);
        if ($user) {
            $newPassword = 'mieayambakso';
            $user->update(['password' => Hash::make($newPassword)]);
        }

        Log::info('audit.password_reset', [
            'target'    => 'parent',
            'target_id' => $parent->id,
            'user_id'   => $parent->user_id ?? null,
            'by_admin'  => auth()->id(),
            'ip'        => request()->ip(),
        ]);

        return response()->json([
            'success'      => true,
            'message'      => 'Password wali murid berhasil direset.',
            'new_password' => $newPassword ?? null,
        ]);
    }

    public function destroy(string $id)
    {
        $parent = Parents::find($id);

        if (!$parent) {
            return response()->json(['success' => false, 'message' => 'Wali murid tidak ditemukan.'], 404);
        }

        // Cascade: hapus semua student + progress report milik parent ini
        Student::where('parent_id', $parent->user_id)->each(function ($student) {
            ProgressReport::where('student_id', $student->id)->delete();
            if (!empty($student->bukti_pembayaran)) {
                $parsed = parse_url($student->bukti_pembayaran, PHP_URL_PATH);
                if ($parsed) {
                    Storage::disk('public')->delete(str_replace('/storage/', '', $parsed));
                }
            }
            $student->delete();
        });

        $parent->delete();

        if (!empty($parent->user_id)) {
            User::find($parent->user_id)?->delete();
        }

        Log::info('audit.parent_deleted', [
            'parent_id'   => $parent->id,
            'parent_name' => $parent->parent_name ?? '—',
            'by_admin'    => auth()->id(),
            'ip'          => request()->ip(),
        ]);

        return response()->json(['success' => true, 'message' => 'Wali murid dan akun berhasil dihapus.']);
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

    private function format($doc, $user = null): array
    {
        return [
            'id'          => $doc->id,
            'user_id'     => $doc->user_id ?? null,
            'username'    => $user?->username ?? null,
            'email'       => $user?->email ?? null,
            'parent_name' => $doc->parent_name,
            'phone'       => $doc->phone,
            'address'     => $doc->address,
            'created_at'  => $doc->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
