<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Services\Documents\BlueprintTemplateWorkbook;
use App\Services\Documents\NaskahTemplateDocument;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Unduhan template standar (kisi-kisi .xlsx dan naskah soal .docx). Dapat diakses semua peran yang sudah masuk.
 */
class TemplateController extends Controller
{
    public function blueprint(BlueprintTemplateWorkbook $workbook): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'kk').'.xlsx';
        $workbook->build($path);

        return response()->download($path, 'template-kisi-kisi.xlsx')->deleteFileAfterSend(true);
    }

    public function naskah(NaskahTemplateDocument $document): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'nsk').'.docx';
        $document->build($path);

        return response()
            ->download($path, 'template-naskah-soal.docx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])
            ->deleteFileAfterSend(true);
    }
}
