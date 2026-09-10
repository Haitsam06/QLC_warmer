<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Proteksi route berdasarkan role_name dari collection roles.
     *
     * Cara pakai di web.php:
     *   ->middleware('role:admin')
     *   ->middleware('role:admin,guru')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! Auth::check()) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
            }
            return redirect()->route('login');
        }

        // Load relasi role jika belum di-load
        $user     = Auth::user()->loadMissing('role');
        $roleName = $user->getRoleName();

        if (! in_array($roleName, $roles)) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['success' => false, 'message' => 'Unauthorized. Anda tidak memiliki akses ke resource ini.'], 403);
            }
            abort(403, 'Anda tidak memiliki akses ke halaman ini.');
        }

        return $next($request);
    }
}