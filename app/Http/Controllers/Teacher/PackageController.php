<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teacher;

use App\Actions\Package\CreatePackage;
use App\Actions\Package\DeleteDraftPackage;
use App\Actions\Package\SubmitPackage;
use App\Enums\FileKind;
use App\Enums\PackageStatus;
use App\Enums\QuestionType;
use App\Exceptions\WorkflowException;
use App\Enums\ExtractionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\StorePackageRequest;
use App\Http\Requests\Teacher\SubmitPackageRequest;
use App\Models\AssessmentPeriod;
use App\Models\PackageFile;
use App\Models\QuestionItem;
use App\Models\QuestionPackage;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Services\ValidatorEligibility;
use App\Support\CurrentPeriod;
use App\Support\DownloadsPresenter;
use App\Support\PackagePresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Sisi guru: daftar paket, buat/unggah paket (langkah 3), detail + status per nomor, kirim ke validator.
 */
class PackageController extends Controller
{
    public function index(Request $request): Response
    {
        $period = CurrentPeriod::resolve($request);
        $status = (string) $request->query('status', '');

        $packages = QuestionPackage::query()
            ->with(PackagePresenter::relations())
            ->where('teacher_id', $request->user()->id)
            ->when(
                $period !== null,
                fn ($q) => $q->where('assessment_period_id', $period->id),
                fn ($q) => $q->whereRaw('1 = 0'),
            )
            ->when(PackageStatus::tryFrom($status) !== null, fn ($q) => $q->where('status', $status))
            ->latest('id')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (QuestionPackage $p): array => PackagePresenter::summary($p));

        return Inertia::render('Teacher/Packages/Index', [
            'packages' => $packages,
            'pending' => $this->pendingAssignments($request, $period)->values(),
            'can_create' => $period !== null && $period->isOpen() && ! $period->semester->isArchived(),
            'filters' => ['status' => $status],
            'statuses' => collect(PackageStatus::cases())->map(fn (PackageStatus $s): array => ['value' => $s->value, 'label' => $s->label()])->values(),
        ]);
    }

    public function create(Request $request): Response
    {
        $period = CurrentPeriod::resolve($request);

        return Inertia::render('Teacher/Packages/Create', [
            'assignments' => $this->pendingAssignments($request, $period)->values(),
            'preselected' => $request->integer('assignment') ?: null,
            'period' => $period === null ? null : [
                'id' => $period->id,
                'label' => $period->label(),
                'open' => $period->isOpen() && ! $period->semester->isArchived(),
                'upload_deadline' => $period->upload_deadline?->toDateString(),
            ],
            'max_mb' => (int) config('banksoal.upload.max_mb', 20),
        ]);
    }

    public function store(StorePackageRequest $request, CreatePackage $action): RedirectResponse
    {
        $period = CurrentPeriod::resolve($request);

        if ($period === null) {
            throw new WorkflowException('Belum ada periode asesmen. Hubungi panitia asesmen.');
        }

        $assignment = TeachingAssignment::query()->findOrFail($request->integer('assignment_id'));

        $package = $action->handle(
            $request->user(),
            $period,
            $assignment,
            $request->input('title'),
            $request->file('kisi_kisi'),
            $request->file('naskah'),
        );

        $hasFiles = $request->hasFile('kisi_kisi') || $request->hasFile('naskah');

        return redirect()
            ->route('teacher.packages.show', $package)
            ->with('success', $hasFiles
                ? 'Paket dibuat. Berkas sedang diekstrak per nomor; pastikan antrian berjalan.'
                : 'Paket dibuat. Unggah berkas atau tambahkan nomor secara manual.');
    }

    public function show(Request $request, QuestionPackage $package, ValidatorEligibility $eligibility): Response
    {
        Gate::authorize('manage', $package);

        $package->load([
            ...PackagePresenter::relations(),
            'files',
            'items.reviews.validator:id,name',
            'items.revisions.uploader:id,name',
        ]);

        $writable = $package->isWritable();
        $draft = $package->isDraft();

        /** @var Collection<int, PackageFile> $files */
        $files = $package->files;

        $validators = $draft
            ? $eligibility->for($package->assessmentPeriod->semester_id, $package->subject_id, $request->user()->id)
                ->map(fn (User $u): array => ['id' => $u->id, 'name' => $u->name, 'is_self' => $u->id === $request->user()->id])
                ->values()
            : collect();

        return Inertia::render('Teacher/Packages/Show', [
            'pkg' => PackagePresenter::summary($package) + [
                'can_edit' => $draft && $writable,
                'can_revise' => ! $draft && $writable,
                'readonly_reason' => $writable ? null : $this->readOnlyReason($package),
                'period_label' => $package->assessmentPeriod->label(),
                'validation_deadline' => $package->assessmentPeriod->validation_deadline?->toDateString(),
                'revision_deadline' => $package->assessmentPeriod->revision_deadline?->toDateString(),
            ],
            'downloads' => $draft ? null : DownloadsPresenter::for($package),
            'items' => $package->items->map(fn (QuestionItem $i): array => PackagePresenter::item($i))->values(),
            'files' => $files->map(fn (PackageFile $f): array => PackagePresenter::file($f))->values(),
            'extracting' => $files->contains(fn (PackageFile $f): bool => in_array($f->extraction_status, [ExtractionStatus::Pending, ExtractionStatus::Processing], true)),
            'validators' => $validators,
            'types' => collect(QuestionType::cases())->map(fn (QuestionType $t): array => ['value' => $t->value, 'label' => $t->label(), 'has_options' => $t->hasOptions()])->values(),
            'max_mb' => (int) config('banksoal.upload.max_mb', 20),
        ]);
    }

    public function update(Request $request, QuestionPackage $package): RedirectResponse
    {
        Gate::authorize('manage', $package);

        $data = $request->validate(['title' => ['required', 'string', 'max:180']]);

        if (! $package->isDraft()) {
            throw new WorkflowException('Judul hanya dapat diubah saat paket masih draft.');
        }

        $package->update(['title' => trim($data['title'])]);

        return back()->with('success', 'Judul paket disimpan.');
    }

    public function destroy(QuestionPackage $package, Request $request, DeleteDraftPackage $action): RedirectResponse
    {
        $action->handle($package, $request->user());

        return redirect()->route('teacher.packages.index')->with('success', 'Paket draft dihapus.');
    }

    public function submit(SubmitPackageRequest $request, QuestionPackage $package, SubmitPackage $action): RedirectResponse
    {
        $action->handle($package, $request->user(), $request->integer('validator_id'));

        return back()->with('success', 'Paket dikirim ke validator.');
    }

    /**
     * Penugasan mengajar pada semester periode terpilih yang belum punya paket di periode tersebut.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function pendingAssignments(Request $request, ?AssessmentPeriod $period): Collection
    {
        if ($period === null) {
            return collect();
        }

        $existing = QuestionPackage::query()
            ->where('assessment_period_id', $period->id)
            ->where('teacher_id', $request->user()->id)
            ->get(['subject_id', 'grade_level_id']);

        return TeachingAssignment::query()
            ->with(['subject:id,name,code', 'gradeLevel:id,name'])
            ->where('semester_id', $period->semester_id)
            ->where('teacher_id', $request->user()->id)
            ->get()
            ->reject(fn (TeachingAssignment $a): bool => $existing->contains(
                fn (QuestionPackage $p): bool => $p->subject_id === $a->subject_id && $p->grade_level_id === $a->grade_level_id,
            ))
            ->map(fn (TeachingAssignment $a): array => [
                'id' => $a->id,
                'subject' => $a->subject->name,
                'code' => $a->subject->code,
                'grade' => $a->gradeLevel->name,
            ])
            ->sortBy('subject')
            ->values();
    }

    private function readOnlyReason(QuestionPackage $package): string
    {
        return match (true) {
            $package->isLocked() => 'Paket sudah dikunci oleh admin dan tidak dapat diubah.',
            ! $package->assessmentPeriod->isOpen() => 'Periode asesmen sudah ditutup; paket bersifat hanya-baca.',
            default => 'Semester ini sudah diarsipkan; paket bersifat hanya-baca.',
        };
    }
}
