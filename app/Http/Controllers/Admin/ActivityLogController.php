<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActivityLogController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $logs = ActivityLog::query()
            ->with('causer:id,name')
            ->when($search !== '', fn ($q) => $q->where('event', 'like', "%{$search}%"))
            ->latest('id')
            ->paginate(25)
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

        return Inertia::render('Admin/Logs', [
            'logs' => $logs,
            'filters' => ['search' => $search],
        ]);
    }
}
