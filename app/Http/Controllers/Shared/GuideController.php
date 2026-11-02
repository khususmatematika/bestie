<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Support\ActiveRole;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Halaman panduan penggunaan BESTIE (semua peran). Isi panduan statis di sisi tampilan; server hanya
 * menentukan tab peran yang dibuka pertama kali.
 */
class GuideController extends Controller
{
    public function show(Request $request): Response
    {
        return Inertia::render('Shared/Guide', [
            'defaultRole' => ActiveRole::current($request),
        ]);
    }
}
