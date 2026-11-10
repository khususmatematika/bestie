<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teacher;

use App\Actions\Package\SaveDraftItem;
use App\Actions\Package\StorePastedImage;
use App\Actions\Package\UploadItemRevision;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\ItemContentRequest;
use App\Http\Requests\Teacher\ReviseFromDocumentRequest;
use App\Models\QuestionItem;
use App\Models\QuestionPackage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;

/**
 * Sisi guru: nomor soal pada paket draft (manual) dan unggah ulang nomor yang diminta diganti.
 */
class ItemController extends Controller
{
    public function store(Request $request, QuestionPackage $package, SaveDraftItem $action): RedirectResponse
    {
        Gate::authorize('manage', $package);

        $data = $request->validate(['number' => ['nullable', 'integer', 'between:1,200']]);

        $item = $action->add($package, $request->user(), isset($data['number']) ? (int) $data['number'] : null);

        return back()->with('success', "Nomor {$item->number} ditambahkan.");
    }

    public function update(ItemContentRequest $request, QuestionItem $item, SaveDraftItem $action): RedirectResponse
    {
        $action->update($item, $request->user(), Arr::except($request->validated(), ['attachment']));

        return back()->with('success', "Nomor {$item->number} disimpan.");
    }

    public function pasteImage(Request $request, QuestionItem $item, StorePastedImage $action): JsonResponse
    {
        $request->validate(['image' => ['required', 'file', 'max:3072']]);

        $file = $action->handle($item, $request->user(), $request->file('image'));

        return response()->json([
            'id' => $file->id,
            'url' => route('packages.files.download', [$item->package_id, $file->id]).'?inline=1',
        ]);
    }

    public function destroy(Request $request, QuestionItem $item, SaveDraftItem $action): RedirectResponse
    {
        $action->delete($item, $request->user());

        return back()->with('success', "Nomor {$item->number} dihapus; nomor sesudahnya bergeser naik.");
    }

    public function revise(ItemContentRequest $request, QuestionItem $item, UploadItemRevision $action): RedirectResponse
    {
        $action->handle(
            $item,
            $request->user(),
            Arr::except($request->validated(), ['attachment']),
            $request->file('attachment'),
        );

        return back()->with('success', "Nomor {$item->number} diunggah ulang dan menunggu cek ulang validator.");
    }

    public function reviseDocument(ReviseFromDocumentRequest $request, QuestionPackage $package, UploadItemRevision $action): RedirectResponse
    {
        $result = $action->fromDocument($package, $request->user(), $request->file('naskah'));

        $message = 'Nomor '.implode(', ', $result['revised']).' diunggah ulang.';

        if ($result['unchanged'] !== []) {
            $message .= ' Tidak berubah: '.implode(', ', $result['unchanged']).'.';
        }

        if ($result['ignored'] !== []) {
            $message .= ' Dilewati (tidak diminta diganti): '.implode(', ', $result['ignored']).'.';
        }

        return back()->with('success', $message);
    }
}
