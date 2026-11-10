<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Impersonation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sesi "ganti akun" dibatasi waktu: lewat batas, admin dikembalikan otomatis ke akunnya sendiri.
 */
class ImpersonationTimeout
{
    public function handle(Request $request, Closure $next): Response
    {
        // Sisa kunci penyamaran pada sesi tanpa login tidak boleh dipakai untuk masuk sebagai admin.
        if (Impersonation::active($request) && $request->user() === null) {
            $request->session()->forget([Impersonation::SESSION_KEY, Impersonation::EXPIRES_KEY]);

            return $next($request);
        }

        if (! Impersonation::expired($request)) {
            return $next($request);
        }

        if (Impersonation::restoreAdmin($request, 'expired') === null) {
            return redirect()->route('login');
        }

        return redirect()->route('admin.dashboard')->with('error', 'Sesi ganti akun berakhir, Anda dikembalikan ke akun admin.');
    }
}
