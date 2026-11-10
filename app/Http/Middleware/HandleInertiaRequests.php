<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\AssessmentPeriod;
use App\Models\User;
use App\Support\ActiveRole;
use App\Support\CurrentPeriod;
use App\Support\Impersonation;
use App\Support\Settings;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * Template root yang dimuat pada kunjungan pertama.
     *
     * @var string
     */
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Data yang dibagikan ke seluruh halaman.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'app' => [
                'name' => config('app.name'),
                'school' => Settings::get('school_name'),
            ],
            'auth' => fn (): ?array => $this->authProps($request),
            'context' => fn (): ?array => $request->user() ? $this->contextProps($request) : null,
            'notifications_unread' => fn (): int => $request->user() ? $request->user()->unreadNotifications()->count() : 0,
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'credential' => $request->session()->get('credential'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function authProps(Request $request): ?array
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            return null;
        }

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'nip' => $user->nip,
                'initials' => $user->initials(),
                'locale' => $user->locale,
                'theme' => $user->theme,
                'must_change_password' => $user->must_change_password,
            ],
            'roles' => $user->getRoleNames()->values()->all(),
            'active_role' => ActiveRole::current($request),
            'impersonating' => ($adminId = Impersonation::impersonatorId($request)) === null ? null : [
                'admin_name' => User::query()->whereKey($adminId)->value('name'),
                'expires_at' => ($at = Impersonation::expiresAt($request)) === null ? null : date('c', $at),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function contextProps(Request $request): array
    {
        $current = CurrentPeriod::resolve($request);

        $periods = AssessmentPeriod::query()
            ->with('semester.year')
            ->orderByDesc('id')
            ->get()
            ->map(static fn (AssessmentPeriod $period): array => [
                'id' => $period->id,
                'label' => $period->label(),
                'status' => $period->status->value,
                'semester_status' => $period->semester?->status->value,
            ])
            ->values()
            ->all();

        return [
            'period' => $current === null ? null : [
                'id' => $current->id,
                'label' => $current->label(),
                'status' => $current->status->value,
            ],
            'periods' => $periods,
        ];
    }
}
