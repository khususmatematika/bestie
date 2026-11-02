<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Imports\BlueprintRowsImport;
use InvalidArgumentException;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

/**
 * Membaca naskah soal dari DOCX (penanda "No. n"), PDF teks (penanda sama), atau XLSX/CSV (satu baris per nomor)
 * dan mengembalikan butir per nomor. Melempar InvalidArgumentException berpesan ramah bila berkas tak terbaca.
 */
final class NaskahReader
{
    public function __construct(
        private readonly DocxTextReader $docx,
        private readonly DocxStructureReader $structure,
        private readonly ExamDocxParser $exam,
        private readonly PdfTextReader $pdf,
        private readonly NaskahParser $parser,
        private readonly NaskahRowsParser $rowsParser,
        private readonly TypedNaskahParser $typed,
    ) {
    }

    /**
     * @param  string  $absolute  path berkas di disk
     * @param  string|null  $relative  path relatif disk 'local' (untuk pembacaan Excel); null = pakai $absolute
     * @return array{items: array<int, array<string, mixed>>, warnings: list<string>}
     */
    public function read(string $absolute, string $extension, ?string $relative = null): array
    {
        return match (mb_strtolower($extension)) {
            'docx' => $this->fromDocx($absolute),
            'pdf' => $this->fromPdf($absolute),
            'xlsx', 'csv' => $this->fromSheet($absolute, $relative),
            default => throw new InvalidArgumentException('Format naskah .'.$extension.' tidak didukung. Gunakan DOCX, XLSX/CSV, atau PDF.'),
        };
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, warnings: list<string>}
     */
    private function fromDocx(string $path): array
    {
        $blocks = $this->structure->read($path);

        // Format Word dengan penomoran otomatis (nomor dan pilihan tidak ada di teks mentah).
        if ($this->exam->supports($blocks)) {
            return $this->exam->parse($blocks);
        }

        $read = $this->docx->read($path);
        $parsed = $this->parser->parse($read['paragraphs']);

        if ($parsed['items'] === []) {
            $typed = $this->typed->parse($read['paragraphs']);

            if ($typed['items'] !== []) {
                $parsed = $typed;
            }
        }

        if ($read['images'] > 0) {
            $parsed['warnings'][] = "{$read['images']} gambar/objek pada naskah tidak diekstrak. Lampirkan gambar sebagai berkas lampiran atau jelaskan dalam teks soal.";
        }

        return $parsed;
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, warnings: list<string>}
     */
    private function fromPdf(string $path): array
    {
        $lines = $this->pdf->lines($path);
        $parsed = $this->parser->parse($lines);

        // Tanpa penanda "No. n": nomor dan pilihan berupa teks ("1.", "A.") hasil ekspor Word.
        if ($parsed['items'] === []) {
            $parsed = $this->typed->parse($lines);
        }

        $parsed['warnings'][] = 'Naskah dibaca dari PDF; periksa hasilnya karena tata letak PDF dapat mengubah urutan teks, dan gambar/rumus tidak terbaca.';

        return $parsed;
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, warnings: list<string>}
     */
    private function fromSheet(string $absolute, ?string $relative): array
    {
        try {
            /** @var list<array<string, mixed>> $rows */
            $rows = $relative !== null
                ? (Excel::toArray(new BlueprintRowsImport, $relative, 'local')[0] ?? [])
                : (Excel::toArray(new BlueprintRowsImport, $absolute)[0] ?? []);
        } catch (Throwable $e) {
            report($e);

            throw new InvalidArgumentException('Naskah tidak dapat dibaca. Pastikan memakai template XLSX/CSV dan tidak rusak.');
        }

        if (count($rows) > 300) {
            throw new InvalidArgumentException('Naskah melebihi 300 baris.');
        }

        return $this->rowsParser->parse($rows);
    }
}
