<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shared;

use App\Actions\User\ChangePassword;
use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\ProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user()->load('profile');

        return Inertia::render('Shared/Profile', [
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'nip' => $user->nip,
                'phone' => $user->profile?->phone,
                'locale' => $user->locale,
                'theme' => $user->theme,
                'last_login_at' => $user->last_login_at?->toIso8601String(),
            ],
        ]);
    }

    public function update(ProfileRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        return back()->with('success', 'Profil diperbarui.');
    }

    public function updatePassword(ChangePasswordRequest $request, ChangePassword $action): RedirectResponse
    {
        $action->handle($request->user(), $request->string('password')->toString());

        return back()->with('success', 'Kata sandi berhasil diganti.');
    }
}
