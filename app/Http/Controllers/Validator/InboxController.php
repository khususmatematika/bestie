<?php

declare(strict_types=1);

namespace App\Http\Controllers\Validator;

use App\Enums\ItemStatus;
use App\Enums\PackageStatus;
use App\Http\Controllers\Controller;
use App\Models\QuestionPackage;
use App\Models\Subject;
use App\Support\CurrentPeriod;
use App\Support\PackagePresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Inbox validator: paket yang memilih akun ini (langkah 4-5; FR-INB-01..04).
 */
class InboxController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $period = CurrentPeriod::resolve($request);

        $status = (string) $request->query('status', '');
        $subjectId = (int) $request->query('subject_id', 0);
        $search = trim((string) $request->query('search', ''));

        $base = QuestionPackage::query()
            ->where('validator_id', $user->id)
            ->whereIn('status', collect(PackageStatus::reviewable())->push(PackageStatus::Terkunci)->map(static fn (PackageStatus $s): string => $s->value)->all())
            ->when(
                $period !== null,
                fn ($q) => $q->where('assessment_period_id', $period->id),
                fn ($q) => $q->whereRaw('1 = 0'),
            );

        $counts = (clone $base)
            ->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $packages = (clone $base)
            ->with(PackagePresenter::relations())
            ->withCount(['items as revised_count' => fn ($q) => $q->where('status', ItemStatus::Direvisi->value)])
            ->when(PackageStatus::tryFrom($status) !== null, fn ($q) => $q->where('status', $status))
            ->when($subjectId > 0, fn ($q) => $q->where('subject_id', $subjectId))
            ->when($search !== '', fn ($q) => $q->where(
                fn ($w) => $w->where('title', 'like', "%{$search}%")
                    ->orWhereHas('teacher', fn ($t) => $t->where('name', 'like', "%{$search}%")),
            ))
            // Paket baru & yang menunggu cek ulang tampil lebih dulu.
            ->orderByRaw("case status when 'terkirim' then 0 when 'perlu_revisi' then 1 when 'ditelaah' then 2 when 'fix' then 3 else 4 end")
            ->latest('submitted_at')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (QuestionPackage $p): array => PackagePresenter::summary($p) + [
                'revised_count' => (int) $p->revised_count,
                'is_new' => $p->status === PackageStatus::Terkirim,
                'deadline' => $p->assessmentPeriod->validation_deadline?->toDateString(),
            ]);

        return Inertia::render('Validator/Inbox', [
            'packages' => $packages,
            'counts' => [
                'new' => (int) ($counts[PackageStatus::Terkirim->value] ?? 0),
                'in_review' => (int) (($counts[PackageStatus::Ditelaah->value] ?? 0) + ($counts[PackageStatus::PerluRevisi->value] ?? 0)),
                'fix' => (int) (($counts[PackageStatus::Fix->value] ?? 0) + ($counts[PackageStatus::Terkunci->value] ?? 0)),
            ],
            'filters' => ['status' => $status, 'subject_id' => $subjectId > 0 ? $subjectId : '', 'search' => $search],
            'statuses' => collect(PackageStatus::reviewable())
                ->push(PackageStatus::Terkunci)
                ->map(fn (PackageStatus $s): array => ['value' => $s->value, 'label' => $s->label()])
                ->values(),
            'subjects' => Subject::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
