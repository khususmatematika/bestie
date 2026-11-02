<?php

declare(strict_types=1);

namespace App\Http\Controllers\Validator;

use App\Actions\Review\ApproveItem;
use App\Actions\Review\ReopenItem;
use App\Actions\Review\RequestItemChange;
use App\Enums\ItemStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Validator\ReviewDecisionRequest;
use App\Models\QuestionItem;
use App\Models\QuestionPackage;
use App\Services\Similarity\ItemSimilarity;
use App\Support\DownloadsPresenter;
use App\Support\PackagePresenter;
use App\Support\ReviewChecklist;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Halaman telaah per nomor dan keputusan validator (langkah 5-6; FR-REV-01..10).
 */
class ReviewController extends Controller
{
    public function show(QuestionPackage $package, ItemSimilarity $similarity, ?int $number = null): Response
    {
        Gate::authorize('review', $package);

        $package->load(PackagePresenter::relations());

        $nav = QuestionItem::query()
            ->where('package_id', $package->id)
            ->orderBy('number')
            ->get(['id', 'number', 'status', 'type', 'resubmitted_at']);

        abort_if($nav->isEmpty(), 404);

        $selected = $number ?? ($nav->first(
            static fn (QuestionItem $i): bool => in_array($i->status, [ItemStatus::BelumDitelaah, ItemStatus::Direvisi], true),
        ) ?? $nav->first())->number;

        $item = QuestionItem::query()
            ->where('package_id', $package->id)
            ->where('number', $selected)
            ->with(['reviews.validator:id,name', 'revisions.uploader:id,name'])
            ->firstOrFail();

        $writable = $package->isWritable();

        return Inertia::render('Validator/Review', [
            'pkg' => PackagePresenter::summary($package) + [
                'can_review' => $writable,
                'readonly_reason' => $writable ? null : ($package->isLocked() ? 'Paket sudah dikunci oleh admin.' : 'Periode ditutup atau semester diarsipkan; telaah bersifat hanya-baca.'),
                'validation_deadline' => $package->assessmentPeriod->validation_deadline?->toDateString(),
            ],
            'nav' => $nav->map(static fn (QuestionItem $i): array => [
                'number' => $i->number,
                'status' => $i->status->value,
                'status_label' => $i->status->label(),
                'revised' => $i->status === ItemStatus::Direvisi,
            ])->values(),
            'downloads' => DownloadsPresenter::for($package),
            'item' => PackagePresenter::item($item),
            'similar' => $similarity->for($item, $package),
            'checklist_items' => collect(ReviewChecklist::ITEMS)->map(static fn (string $label, string $key): array => ['key' => $key, 'label' => $label])->values(),
        ]);
    }

    public function approve(ReviewDecisionRequest $request, QuestionItem $item, ApproveItem $action): RedirectResponse
    {
        $action->handle($item, $request->user(), (array) $request->input('checklist', []), $request->input('note'));

        return $this->next($item, "Nomor {$item->number} disetujui.");
    }

    public function requestChange(ReviewDecisionRequest $request, QuestionItem $item, RequestItemChange $action): RedirectResponse
    {
        $action->handle($item, $request->user(), $request->input('note'), (array) $request->input('checklist', []));

        return $this->next($item, "Nomor {$item->number} diminta diganti. Guru akan melihat catatan Anda.");
    }

    public function reopen(ReviewDecisionRequest $request, QuestionItem $item, ReopenItem $action): RedirectResponse
    {
        $action->handle($item, $request->user(), $request->input('note'));

        return back()->with('success', "Nomor {$item->number} dibuka kembali dan dikembalikan ke guru.");
    }

    /**
     * Setelah keputusan, pindah ke nomor berikutnya yang masih perlu ditelaah (bila ada).
     */
    private function next(QuestionItem $item, string $message): RedirectResponse
    {
        $next = QuestionItem::query()
            ->where('package_id', $item->package_id)
            ->where('number', '>', $item->number)
            ->whereIn('status', [ItemStatus::BelumDitelaah->value, ItemStatus::Direvisi->value])
            ->orderBy('number')
            ->value('number');

        return redirect()
            ->route('validator.review', ['package' => $item->package_id, 'number' => $next ?? $item->number])
            ->with('success', $message);
    }
}
