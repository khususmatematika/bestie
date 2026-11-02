<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\CurrentPeriod;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Memastikan konteks periode asesmen selalu valid pada rute operasional.
 * Model terikat periode (M1) membaca konteks ini lewat $request->attributes.
 */
class ScopeAcademicPeriod
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set('period', CurrentPeriod::resolve($request));

        return $next($request);
    }
}
