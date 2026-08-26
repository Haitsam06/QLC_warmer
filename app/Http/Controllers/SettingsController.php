<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Storage;

class SettingsController extends Controller
{
    /**
     * Memperbarui Username & Email
     */
    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'username' => 'required|string|min:4|max:50|alpha_num',
            'email'    => 'nullable|email|max:255',
            'photo'    => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        // Cek uniqueness
        if (User::where('username', $request->username)->where('id', '!=', $user->id)->exists()) {
            return back()->withErrors(['username' => 'Username sudah digunakan orang lain.'])->withInput();
        }

        if ($request->filled('email') && User::where('email', $request->email)->where('id', '!=', $user->id)->exists()) {
            return back()->withErrors(['email' => 'Email sudah terdaftar pada akun lain.'])->withInput();
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

        return back()->with('success', 'Informasi akun berhasil diperbarui.');
    }

    /**
     * Memperbarui Kata Sandi
     */
    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'], // Mengecek sandi lama
            'password' => ['required', 'confirmed', Password::defaults()],
        ], [
            'current_password.current_password' => 'Kata sandi saat ini salah.',
            'password.confirmed' => 'Konfirmasi kata sandi baru tidak cocok.'
        ]);

        Auth::user()->update([
            'password' => Hash::make($request->password),
        ]);

        return back()->with('success', 'Kata sandi berhasil diperbarui.');
    }
}