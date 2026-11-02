<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Models\PackageFile;
use App\Models\QuestionPackage;
use App\Support\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Unduh berkas paket lewat controller terotorisasi (rules.md §6.2.3): guru pemilik, validator terkait, admin.
 */
class PackageFileController extends Controller
{
    public function show(Request $request, QuestionPackage $package, PackageFile $file): StreamedResponse|BinaryFileResponse
    {
        Gate::authorize('view', $package);

        abort_unless($file->package_id === $package->id, 404);
        abort_unless(Storage::disk('local')->exists($file->path), 404);

        // Gambar soal ditampilkan langsung di halaman (img), hanya untuk PNG/JPEG/GIF.
        if ($request->boolean('inline') && in_array($file->extension(), ['png', 'jpg', 'jpeg', 'gif'], true)) {
            return response()->file(Storage::disk('local')->path($file->path), [
                'Content-Type' => 'image/'.($file->extension() === 'jpg' ? 'jpeg' : $file->extension()),
                'Content-Disposition' => 'inline',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, max-age=3600',
            ]);
        }

        ActivityLogger::log('file.downloaded', $package, ['file' => $file->original_name]);

        return Storage::disk('local')->download($file->path, $file->original_name);
    }
}
