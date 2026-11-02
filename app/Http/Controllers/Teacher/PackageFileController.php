<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teacher;

use App\Actions\Package\ReplacePackageFiles;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\ReplaceFilesRequest;
use App\Models\QuestionPackage;
use Illuminate\Http\RedirectResponse;

class PackageFileController extends Controller
{
    public function store(ReplaceFilesRequest $request, QuestionPackage $package, ReplacePackageFiles $action): RedirectResponse
    {
        $action->handle($package, $request->user(), $request->file('kisi_kisi'), $request->file('naskah'));

        return back()->with('success', 'Berkas diterima. Butir diekstrak ulang dan menggantikan nomor yang ada.');
    }
}
