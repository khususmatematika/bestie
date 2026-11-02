<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shared;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Support\ActiveRole;
use App\Support\CurrentPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContextController extends Controller
{
    /**
     * Ganti peran aktif (FR-USR-06). Divalidasi ulang di server.
     */
    public function switchRole(Request $request): RedirectResponse
    {
        $data = $request->validate(['role' => ['required', Rule::enum(RoleName::class)]]);

        abort_unless(ActiveRole::switchTo($request, $data['role']), 403, 'Anda tidak memiliki peran tersebut.');

        return redirect()->route(ActiveRole::homeRoute($data['role']));
    }

    /**
     * Ganti periode asesmen / semester yang sedang dilihat (FR-ACD-06).
     */
    public function switchPeriod(Request $request): RedirectResponse
    {
        $data = $request->validate(['period_id' => ['required', 'integer']]);

        abort_unless(CurrentPeriod::set($request, (int) $data['period_id']), 404);

        return back();
    }
}
