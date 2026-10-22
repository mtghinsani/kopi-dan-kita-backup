<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Membatasi route untuk role tertentu, contoh `role:KARYAWAN,SUPER_ADMIN`.
 * Akun yang dinonaktifkan Super Admin dikeluarkan pada request berikutnya (BR-23).
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user->aktif) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                abort(401, 'Akun Anda dinonaktifkan.');
            }

            return redirect('/login')->withErrors(['email' => 'Akun Anda dinonaktifkan. Hubungi pengelola kedai.']);
        }

        abort_unless(in_array($user->role->value, $roles, true), 403, 'Anda tidak memiliki akses ke halaman ini.');

        return $next($request);
    }
}
