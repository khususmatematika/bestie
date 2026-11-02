<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Akun hasil impor/reset wajib mengganti kata sandi sebelum memakai aplikasi (FR-USR-04).
 */
class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Akun yang dinonaktifkan admin langsung dikeluarkan, tanpa menunggu sesinya habis.
        if ($user !== null && $user->is_active === false) {
            auth()->guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('error', 'Akun Anda tidak aktif. Hubungi Admin.');
        }

        if ($user !== null && $user->must_change_password) {
            return redirect()->route('password.force');
        }

        return $next($request);
    }
}
