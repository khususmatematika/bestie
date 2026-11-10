<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Impersonation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Selama admin masuk sebagai akun lain, kata sandi dan profil akun itu tidak boleh diubah.
 */
class NotImpersonating
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(Impersonation::active($request), 403, 'Tindakan ini tidak tersedia saat Anda masuk sebagai akun lain. Kembali ke akun admin terlebih dahulu.');

        return $next($request);
    }
}
