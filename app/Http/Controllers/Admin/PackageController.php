<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Package\ChangePackageValidator;
use App\Actions\Package\DeletePackageAsAdmin;
use App\Actions\Package\LockPackage;
use App\Actions\Package\RenumberPackageAsAdmin;
use App\Enums\PackageStatus;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ChangeValidatorRequest;
use App\Models\QuestionItem;
use App\Models\QuestionPackage;
use App\Models\Subject;
use App\Models\User;
use App\Support\CurrentPeriod;
use App\Support\PackagePresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Monitoring paket oleh admin: pantau status, kunci paket Fix, ganti validator (R-09).
 */
class PackageController extends Controller
{
    public function index(Request $request): Response
    {
        $period = CurrentPeriod::resolve($request);
        $status = (string) $request->query('status', '');
        $subjectId = (int) $request->query('subject_id', 0);
        $search = trim((string) $request->query('search', ''));

        $base = QuestionPackage::query()->when(
            $period !== null,
            fn ($q) => $q->where('assessment_period_id', $period->id),
            fn ($q) => $q->whereRaw('1 = 0'),
        );

        $counts = (clone $base)->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $packages = (clone $base)
            ->with(PackagePresenter::relations())
            ->when(PackageStatus::tryFrom($status) !== null, fn ($q) => $q->where('status', $status))
            ->when($subjectId > 0, fn ($q) => $q->where('subject_id', $subjectId))
            ->when($search !== '', fn ($q) => $q->where(
                fn ($w) => $w->where('title', 'like', "%{$search}%")
                    ->orWhereHas('teacher', fn ($t) => $t->where('name', 'like', "%{$search}%")),
            ))
            ->latest('id')
            ->paginate(15)
            ->withQueryString()
            ->through(function (QuestionPackage $p): array {
                $row = QuestionItem::query()->where('package_id', $p->id)->toBase()->selectRaw('count(*) as c, max(number) as m')->first();

                return PackagePresenter::summary($p) + ['number_gap' => $row !== null && (int) $row->c > 0 && (int) $row->c !== (int) $row->m];
            });

        return Inertia::render('Admin/Packages/Index', [
            'packages' => $packages,
            'counts' => collect(PackageStatus::cases())->map(fn (PackageStatus $s): array => [
                'value' => $s->value,
                'label' => $s->label(),
                'total' => (int) ($counts[$s->value] ?? 0),
            ])->values(),
            'filters' => ['status' => $status, 'subject_id' => $subjectId > 0 ? $subjectId : '', 'search' => $search],
            'subjects' => Subject::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'validators' => User::query()->role(RoleName::Validator->value)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function lock(Request $request, QuestionPackage $package, LockPackage $action): RedirectResponse
    {
        $action->handle($package, $request->user());

        return back()->with('success', 'Paket dikunci.');
    }

    public function renumber(Request $request, QuestionPackage $package, RenumberPackageAsAdmin $action): RedirectResponse
    {
        $map = $action->handle($package, $request->user());

        if ($map === []) {
            return back()->with('success', 'Nomor soal sudah berurutan.');
        }

        $text = collect($map)->map(fn (int $new, int $old): string => "{$old}→{$new}")->implode(', ');

        return back()->with('success', 'Nomor soal dirapikan: '.$text.'.');
    }

    public function changeValidator(ChangeValidatorRequest $request, QuestionPackage $package, ChangePackageValidator $action): RedirectResponse
    {
        $action->handle($package, $request->user(), $request->integer('validator_id'));

        return back()->with('success', 'Validator paket diganti.');
    }

    public function destroy(Request $request, QuestionPackage $package, DeletePackageAsAdmin $action): RedirectResponse
    {
        $action->handle($package, $request->user());

        return back()->with('success', 'Paket “'.$package->title.'” dihapus.');
    }
}
