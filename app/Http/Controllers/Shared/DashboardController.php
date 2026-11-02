<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shared;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Enums\ItemStatus;
use App\Enums\PackageStatus;
use App\Models\AssessmentPeriod;
use App\Models\QuestionItem;
use App\Models\QuestionPackage;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Support\CurrentPeriod;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Dashboard M0: ringkasan struktur & akun. Statistik paket soal ditambahkan pada M1.
 */
class DashboardController extends Controller
{
    public function admin(Request $request): Response
    {
        $period = CurrentPeriod::resolve($request);
        $current = Semester::current();

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'teachers' => User::query()->teachers()->count(),
                'validators' => User::role(RoleName::Validator->value)->count(),
                'subjects' => Subject::query()->where('is_active', true)->count(),
                'periods_open' => AssessmentPeriod::query()->where('status', 'buka')->count(),
            ],
            'packages' => $this->statusCounts(QuestionPackage::query()->when(
                $period !== null,
                fn ($q) => $q->where('assessment_period_id', $period->id),
                fn ($q) => $q->whereRaw('1 = 0'),
            )),
            'subject_progress' => $this->subjectProgress($period),
            'missing_packages' => $this->missingPackages($period, $current),
            'semester' => $current === null ? null : [
                'id' => $current->id,
                'label' => $current->label(),
                'status' => $current->status->value,
            ],
            'deadlines' => $period === null ? [] : [
                ['label' => 'Tenggat unggah naskah', 'date' => $period->upload_deadline?->toDateString()],
                ['label' => 'Tenggat validasi', 'date' => $period->validation_deadline?->toDateString()],
                ['label' => 'Tenggat revisi', 'date' => $period->revision_deadline?->toDateString()],
            ],
            'checklist' => [
                ['label' => 'Tahun ajaran & semester aktif dibuat', 'done' => $current !== null, 'href' => route('admin.semesters.index')],
                ['label' => 'Periode asesmen dibuat', 'done' => AssessmentPeriod::query()->exists(), 'href' => route('admin.periods.index')],
                ['label' => 'Data mapel tersedia', 'done' => Subject::query()->exists(), 'href' => route('admin.subjects.index')],
                ['label' => 'Akun guru diimpor', 'done' => User::query()->teachers()->exists(), 'href' => route('admin.teachers.index')],
                ['label' => 'Validator ditunjuk', 'done' => User::role(RoleName::Validator->value)->exists(), 'href' => route('admin.validators.index')],
            ],
        ]);
    }

    public function validator(Request $request): Response
    {
        $period = CurrentPeriod::resolve($request);

        $packages = QuestionPackage::query()
            ->where('validator_id', $request->user()->id)
            ->whereIn('status', collect(PackageStatus::reviewable())->push(PackageStatus::Terkunci)->map(static fn (PackageStatus $s): string => $s->value)->all())
            ->when(
                $period !== null,
                fn ($q) => $q->where('assessment_period_id', $period->id),
                fn ($q) => $q->whereRaw('1 = 0'),
            );

        $counts = (clone $packages)->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $waiting = $period === null ? 0 : QuestionItem::query()
            ->whereIn('package_id', (clone $packages)->select('id'))
            ->where('status', ItemStatus::Direvisi->value)
            ->count();

        return Inertia::render('Validator/Dashboard', [
            'stats' => [
                'new' => (int) ($counts[PackageStatus::Terkirim->value] ?? 0),
                'in_review' => (int) (($counts[PackageStatus::Ditelaah->value] ?? 0) + ($counts[PackageStatus::PerluRevisi->value] ?? 0)),
                'fix' => (int) (($counts[PackageStatus::Fix->value] ?? 0) + ($counts[PackageStatus::Terkunci->value] ?? 0)),
                'rechecks' => $waiting,
            ],
            'period' => $period?->label(),
            'queue' => $period === null ? [] : $this->progressList(
                (clone $packages)->with(['teacher:id,name', 'subject:id,name', 'gradeLevel:id,name'])
            ),
            'deadline' => $period?->validation_deadline?->toDateString(),
        ]);
    }

    public function teacher(Request $request): Response
    {
        $semester = Semester::current();

        $assignments = $semester === null ? collect() : TeachingAssignment::query()
            ->with(['subject:id,name,code', 'gradeLevel:id,name'])
            ->where('semester_id', $semester->id)
            ->where('teacher_id', $request->user()->id)
            ->get()
            ->map(fn (TeachingAssignment $a): array => [
                'id' => $a->id,
                'subject' => $a->subject->name,
                'code' => $a->subject->code,
                'grade' => $a->gradeLevel->name,
            ]);

        $period = CurrentPeriod::resolve($request);

        $mine = QuestionPackage::query()
            ->where('teacher_id', $request->user()->id)
            ->when(
                $period !== null,
                fn ($q) => $q->where('assessment_period_id', $period->id),
                fn ($q) => $q->whereRaw('1 = 0'),
            );

        $counts = (clone $mine)->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $needChange = $period === null ? 0 : QuestionItem::query()
            ->whereIn('package_id', (clone $mine)->select('id'))
            ->where('status', ItemStatus::PerluGanti->value)
            ->count();

        return Inertia::render('Teacher/Dashboard', [
            'assignments' => $assignments->values(),
            'package_stats' => [
                'draft' => (int) ($counts[PackageStatus::Draft->value] ?? 0),
                'in_review' => (int) (($counts[PackageStatus::Terkirim->value] ?? 0) + ($counts[PackageStatus::Ditelaah->value] ?? 0) + ($counts[PackageStatus::PerluRevisi->value] ?? 0)),
                'fix' => (int) (($counts[PackageStatus::Fix->value] ?? 0) + ($counts[PackageStatus::Terkunci->value] ?? 0)),
                'items_to_revise' => $needChange,
            ],
            'packages' => $period === null ? [] : $this->progressList((clone $mine)->with(['teacher:id,name', 'subject:id,name', 'gradeLevel:id,name'])),
            'deadlines' => $period === null ? [] : [
                ['label' => 'Tenggat unggah naskah', 'date' => $period->upload_deadline?->toDateString()],
                ['label' => 'Tenggat revisi', 'date' => $period->revision_deadline?->toDateString()],
            ],
        ]);
    }

    /**
     * Jumlah paket per status (semua status ditampilkan, termasuk yang 0).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<QuestionPackage>  $query
     * @return list<array{value: string, label: string, total: int}>
     */
    private function statusCounts($query): array
    {
        $counts = $query->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return collect(PackageStatus::cases())->map(static fn (PackageStatus $s): array => [
            'value' => $s->value,
            'label' => $s->label(),
            'total' => (int) ($counts[$s->value] ?? 0),
        ])->values()->all();
    }

    /**
     * Daftar paket dengan kemajuan telaah per nomor.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<QuestionPackage>  $query
     * @return list<array<string, mixed>>
     */
    private function progressList($query): array
    {
        return $query
            ->withCount([
                'items',
                'items as approved_count' => fn ($q) => $q->where('status', ItemStatus::Disetujui->value),
                'items as pending_count' => fn ($q) => $q->whereIn('status', [ItemStatus::BelumDitelaah->value, ItemStatus::Direvisi->value]),
                'items as change_count' => fn ($q) => $q->where('status', ItemStatus::PerluGanti->value),
            ])
            ->orderByDesc('pending_count')
            ->latest('id')
            ->limit(12)
            ->get()
            ->map(static fn (QuestionPackage $p): array => [
                'id' => $p->id,
                'title' => $p->title,
                'subject' => $p->subject->name,
                'grade' => $p->gradeLevel->name,
                'teacher' => $p->teacher->name,
                'status' => $p->status->value,
                'total' => (int) $p->items_count,
                'approved' => (int) $p->approved_count,
                'pending' => (int) $p->pending_count,
                'change' => (int) $p->change_count,
            ])
            ->values()
            ->all();
    }

    /**
     * Kemajuan telaah per mapel pada periode terpilih (admin).
     *
     * @return list<array{subject: string, packages: int, fix: int, total: int, approved: int, percent: int}>
     */
    private function subjectProgress(?AssessmentPeriod $period): array
    {
        if ($period === null) {
            return [];
        }

        return QuestionPackage::query()
            ->where('assessment_period_id', $period->id)
            ->with('subject:id,name')
            ->withCount(['items', 'items as approved_count' => fn ($q) => $q->where('status', ItemStatus::Disetujui->value)])
            ->get()
            ->groupBy('subject_id')
            ->map(static function ($group): array {
                $total = (int) $group->sum('items_count');
                $approved = (int) $group->sum('approved_count');

                return [
                    'subject' => $group->first()->subject->name,
                    'packages' => $group->count(),
                    'fix' => $group->filter(static fn (QuestionPackage $p): bool => in_array($p->status, [PackageStatus::Fix, PackageStatus::Terkunci], true))->count(),
                    'total' => $total,
                    'approved' => $approved,
                    'percent' => $total > 0 ? (int) round($approved / $total * 100) : 0,
                ];
            })
            ->sortBy('subject')
            ->values()
            ->all();
    }

    /**
     * Penugasan guru pada semester aktif yang belum punya paket pada periode terpilih (maks. 8 ditampilkan).
     *
     * @return array{total: int, rows: list<array{teacher: string, subject: string, grade: string}>}
     */
    private function missingPackages(?AssessmentPeriod $period, ?Semester $semester): array
    {
        if ($period === null || $semester === null) {
            return ['total' => 0, 'rows' => []];
        }

        $have = QuestionPackage::query()->where('assessment_period_id', $period->id)->get(['teacher_id', 'subject_id', 'grade_level_id'])
            ->mapWithKeys(static fn (QuestionPackage $p): array => [$p->teacher_id.'-'.$p->subject_id.'-'.$p->grade_level_id => true]);

        $missing = TeachingAssignment::query()
            ->with(['teacher:id,name', 'subject:id,name', 'gradeLevel:id,name'])
            ->where('semester_id', $semester->id)
            ->get()
            ->reject(static fn (TeachingAssignment $a): bool => $have->has($a->teacher_id.'-'.$a->subject_id.'-'.$a->grade_level_id))
            ->values();

        return [
            'total' => $missing->count(),
            'rows' => $missing->take(8)->map(static fn (TeachingAssignment $a): array => [
                'teacher' => $a->teacher->name,
                'subject' => $a->subject->name,
                'grade' => $a->gradeLevel->name,
            ])->all(),
        ];
    }
}
