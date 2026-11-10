<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ActiveRole;
use App\Support\ActivityLogger;
use App\Support\Impersonation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Ganti akun: admin masuk sebagai guru/validator untuk membantu atau memeriksa tampilan mereka, lalu kembali ke admin.
 * Setiap awal dan akhir tercatat pada log aktivitas atas nama admin.
 */
class ImpersonationController extends Controller
{
    /**
     * Cari akun guru/validator aktif (maks. 15) untuk menu "Ganti akun".
     */
    public function search(Request $request): JsonResponse
    {
        $term = trim($request->string('q')->toString());

        $users = User::query()
            ->teachers()
            ->whereDoesntHave('roles', static fn ($q) => $q->where('name', RoleName::Admin->value))
            ->where('is_active', true)
            ->whereKeyNot($request->user()->getKey())
            ->when($term !== '', static function ($q) use ($term): void {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';
                $q->where(static fn ($w) => $w->where('name', 'like', $like)->orWhere('nip', 'like', $like)->orWhere('email', 'like', $like));
            })
            ->with('roles:id,name')
            ->orderBy('name')
            ->limit(15)
            ->get();

        return response()->json([
            'data' => $users->map(static fn (User $u): array => [
                'id' => $u->id,
                'name' => $u->name,
                'nip' => $u->nip,
                'email' => $u->email,
                'roles' => array_values(array_intersect($u->getRoleNames()->all(), [RoleName::Teacher->value, RoleName::Validator->value])),
            ])->values(),
        ]);
    }

    public function start(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate(['role' => ['required', Rule::in([RoleName::Teacher->value, RoleName::Validator->value])]]);
        $admin = $request->user();

        abort_if(Impersonation::active($request), 403, 'Kembali ke akun admin terlebih dahulu.');
        abort_unless($user->hasRole($data['role']) && ! $user->isAdmin() && $user->is_active, 403, 'Akun tersebut tidak dapat dimasuki.');
        abort_if($user->is($admin), 422, 'Itu akun Anda sendiri.');

        ActivityLogger::log('auth.impersonate.start', $user, ['as_role' => $data['role']], $admin);

        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $request->session()->put(Impersonation::SESSION_KEY, $admin->getKey());
        $request->session()->put(Impersonation::EXPIRES_KEY, now()->addMinutes(Impersonation::ttlMinutes())->getTimestamp());
        $request->session()->put(ActiveRole::SESSION_KEY, $data['role']);

        return redirect()->route(ActiveRole::homeRoute($data['role']))->with('success', 'Anda masuk sebagai '.$user->name.'.');
    }

    public function leave(Request $request): RedirectResponse
    {
        return Impersonation::restoreAdmin($request) === null
            ? redirect()->route('login')
            : redirect()->route('admin.dashboard');
    }
}
