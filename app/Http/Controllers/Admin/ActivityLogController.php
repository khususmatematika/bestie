<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Support\PerPage;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActivityLogController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $userParam = (string) $request->query('user', '');
        $userId = ctype_digit($userParam) ? (int) $userParam : null;
        $system = $userParam === 'system';

        // Rincian hanya dimuat setelah satu pengguna dipilih; halaman awal hanya ringkasan per pengguna.
        $logs = ($userId === null && ! $system) ? null : ActivityLog::query()
            ->with('causer:id,name')
            ->when($userId !== null, fn ($q) => $q->where('causer_id', $userId))
            ->when($system, fn ($q) => $q->whereNull('causer_id'))
            ->when($search !== '', fn ($q) => $q->where('event', 'like', '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%'))
            ->latest('id')
            ->paginate(PerPage::resolve($request))
            ->withQueryString()
            ->through(fn (ActivityLog $l): array => [
                'id' => $l->id,
                'event' => $l->event,
                'causer' => $l->causer?->name ?? 'Sistem',
                'subject' => $l->subject_type ? class_basename($l->subject_type).' #'.$l->subject_id : null,
                'properties' => $l->properties,
                'ip' => $l->ip,
                'created_at' => $l->created_at?->toIso8601String(),
            ]);

        // Ringkasan per pengguna: jumlah aktivitas dan waktu terakhir; urut dari yang paling baru aktif.
        $grouped = ActivityLog::query()->toBase()
            ->selectRaw('causer_id, count(*) as total, max(id) as last_id')
            ->groupBy('causer_id')
            ->orderByDesc('last_id')
            ->limit(500)
            ->get();
        $lastRows = ActivityLog::query()->whereIn('id', $grouped->pluck('last_id')->all())->get(['id', 'event', 'created_at'])->keyBy('id');
        $names = User::query()->whereIn('id', $grouped->pluck('causer_id')->filter()->all())->pluck('name', 'id');

        $users = $grouped->map(fn ($g): array => [
            'id' => $g->causer_id === null ? 'system' : (string) $g->causer_id,
            'name' => $g->causer_id === null ? 'Sistem' : ($names[$g->causer_id] ?? 'Akun #'.$g->causer_id),
            'total' => (int) $g->total,
            'last_at' => $lastRows[$g->last_id]?->created_at?->toIso8601String(),
            'last_event' => $lastRows[$g->last_id]?->event,
        ])->values();

        return Inertia::render('Admin/Logs', [
            'logs' => $logs,
            'users' => $users,
            'filters' => ['search' => $search, 'user' => $system ? 'system' : ($userId !== null ? (string) $userId : '')],
        ]);
    }
}
