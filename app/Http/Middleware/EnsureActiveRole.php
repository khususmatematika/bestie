<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\ActiveRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Membatasi rute berdasarkan PERAN AKTIF (bukan sekadar peran yang dimiliki).
 * Pemakaian: ->middleware('active.role:admin') atau 'active.role:teacher,validator'.
 */
class EnsureActiveRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $active = ActiveRole::current($request);

        abort_unless($active !== null && in_array($active, $roles, true), 403, 'Anda tidak memiliki akses ke halaman ini.');

        return $next($request);
    }
}
