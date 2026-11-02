<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\User\ChangePassword;
use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePasswordRequest;
use App\Support\ActiveRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PasswordController extends Controller
{
    /**
     * Halaman wajib ganti kata sandi (login pertama / setelah reset admin).
     */
    public function force(Request $request): Response|RedirectResponse
    {
        if (! $request->user()->must_change_password) {
            return redirect()->route(ActiveRole::homeRoute(ActiveRole::current($request)));
        }

        return Inertia::render('Auth/ForcePassword');
    }

    public function update(ChangePasswordRequest $request, ChangePassword $action): RedirectResponse
    {
        $action->handle($request->user(), $request->string('password')->toString());

        $request->session()->regenerate();

        return redirect()
            ->route(ActiveRole::homeRoute(ActiveRole::current($request)))
            ->with('success', 'Kata sandi berhasil diganti.');
    }
}
