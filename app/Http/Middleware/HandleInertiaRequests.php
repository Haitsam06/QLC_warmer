<?php

namespace App\Http\Middleware;

use App\Models\Parents;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        $displayName = null;
        $roleName    = null;
        if ($user) {
            $user->load('role');
            $roleName = $user->getRoleName();

            // Query parent hanya untuk role parents — tidak untuk admin/teacher/mitra
            if ($roleName === 'parents') {
                $parent      = Parents::where('user_id', $user->id)->first();
                $displayName = $parent?->parent_name ?? $user->username;
            } else {
                $displayName = $user->username;
            }
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id'      => $user->id,
                    '_id'     => (string) $user->id,
                    'name'    => $displayName,
                    'username' => $user->username,
                    'email'   => $user->email,
                    'photo'   => $user->photo
                        ? $user->photo . '?v=' . md5($user->photo)
                        : null,
                    'role_id' => (string) $user->role_id,
                    'role'    => $roleName,
                ] : null,
            ],
        ];
    }
}