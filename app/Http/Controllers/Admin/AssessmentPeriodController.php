<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Academic\SetPeriodStatus;
use App\Enums\PeriodStatus;
use App\Enums\PeriodType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssessmentPeriodRequest;
use App\Models\AssessmentPeriod;
use App\Models\Semester;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AssessmentPeriodController extends Controller
{
    public function index(): Response
    {
        $periods = AssessmentPeriod::query()
            ->with('semester.year')
            ->orderByDesc('id')
            ->get()
            ->map(fn (AssessmentPeriod $p): array => [
                'id' => $p->id,
                'semester_id' => $p->semester_id,
                'name' => $p->name,
                'label' => $p->label(),
                'type' => $p->type->value,
                'type_label' => $p->type->label(),
                'upload_deadline' => $p->upload_deadline?->toDateString(),
                'validation_deadline' => $p->validation_deadline?->toDateString(),
                'revision_deadline' => $p->revision_deadline?->toDateString(),
                'status' => $p->status->value,
                'status_label' => $p->status->label(),
            ]);

        $semesters = Semester::query()
            ->with('year')
            ->orderByDesc('academic_year_id')
            ->orderByDesc('term')
            ->get()
            ->map(fn (Semester $s): array => ['id' => $s->id, 'label' => $s->label()]);

        return Inertia::render('Admin/Academic/Periods', [
            'periods' => $periods,
            'semesters' => $semesters,
            'types' => array_map(
                static fn (PeriodType $t): array => ['value' => $t->value, 'label' => $t->label()],
                PeriodType::cases(),
            ),
        ]);
    }

    public function store(AssessmentPeriodRequest $request): RedirectResponse
    {
        $period = AssessmentPeriod::query()->create($request->validated() + ['status' => PeriodStatus::Buka]);
        ActivityLogger::log('period.created', $period);

        return back()->with('success', 'Periode asesmen ditambahkan.');
    }

    public function update(AssessmentPeriodRequest $request, AssessmentPeriod $period): RedirectResponse
    {
        if (! $period->isOpen()) {
            return back()->with('error', 'Periode yang ditutup bersifat hanya-baca. Buka kembali terlebih dahulu.');
        }

        $period->update($request->validated());
        ActivityLogger::log('period.updated', $period);

        return back()->with('success', 'Periode diperbarui.');
    }

    public function toggleStatus(AssessmentPeriod $period, SetPeriodStatus $action): RedirectResponse
    {
        $next = $period->isOpen() ? PeriodStatus::Ditutup : PeriodStatus::Buka;
        $action->handle($period, $next);

        return back()->with('success', $next === PeriodStatus::Ditutup ? 'Periode ditutup.' : 'Periode dibuka kembali.');
    }

    public function destroy(AssessmentPeriod $period): RedirectResponse
    {
        if (! $period->canBeDeleted()) {
            return back()->with('error', 'Periode yang sudah memiliki paket soal tidak dapat dihapus.');
        }

        ActivityLogger::log('period.deleted', $period);
        $period->delete();

        return back()->with('success', 'Periode dihapus.');
    }
}
