<?php

declare(strict_types=1);

namespace App\Services\Export;

use App\Enums\PackageStatus;
use App\Enums\QuestionType;
use App\Models\PackageFile;
use Illuminate\Support\Facades\Storage;
use App\Models\QuestionItem;
use App\Models\QuestionPackage;
use App\Services\Documents\DocxBuilder;
use App\Support\ExportFormat;
use App\Support\MatchingKey;
use App\Support\Settings;

/**
 * Menyusun naskah DOCX sebuah paket sesuai format Admin (FR-EXP-02, FR-EXP-04).
 * Mode "final": seluruh nomor + lembar kunci + kisi-kisi. Mode "validated": hanya nomor yang disetujui, dengan
 * keterangan waktu validasi/upload ulang, dan header "BELUM FINAL" selama paket belum Fix (FR-DLD-02, FR-DLD-03).
 */
final class PackageDocument
{
    /** Naikkan bila tata letak dokumen berubah agar naskah final lama dibuat ulang. */
    public const LAYOUT_VERSION = 3;

    /** Sidik format dokumen saat ini: format Admin, nama sekolah, logo kop, dan versi tata letak. */
    public static function fingerprint(): string
    {
        $logo = resource_path('images/kop-logo.png');

        return sha1(json_encode([
            ExportFormat::get(),
            (string) Settings::get('school_name'),
            is_file($logo) ? md5_file($logo) : null,
            self::LAYOUT_VERSION,
        ], JSON_UNESCAPED_UNICODE) ?: '');
    }

    /**
     * @param  list<int>|null  $numbers  batasi nomor (hanya mode validated)
     */
    public function build(QuestionPackage $package, string $path, bool $final, ?string $docNumber = null, ?array $numbers = null): void
    {
        $package->loadMissing(['subject:id,name,category', 'gradeLevel:id,name,phase,phase_elective', 'assessmentPeriod.semester.year', 'teacher:id,name', 'validator:id,name']);

        $format = ExportFormat::get();
        $isFinished = in_array($package->status, [PackageStatus::Fix, PackageStatus::Terkunci], true);

        $items = QuestionItem::query()
            ->with(['revisions', 'reviews'])
            ->where('package_id', $package->id)
            ->orderBy('number')
            ->get();

        if (! $final) {
            $items = $items->filter(static fn (QuestionItem $i): bool => $i->status->value === 'disetujui');

            if ($numbers !== null) {
                $items = $items->filter(static fn (QuestionItem $i): bool => in_array($i->number, $numbers, true));
            }
        }

        $items = $items->values();

        $doc = new DocxBuilder(
            $format['font'],
            $format['font_size'],
            $format['margin_cm'],
            $isFinished && $final ? null : 'BELUM FINAL',
            trim(($docNumber !== null ? $docNumber.' — ' : '').$format['footer_text'], ' —'),
        );

        $this->heading($doc, $package, $format, $final);

        $files = $this->imageFiles($items);

        foreach ($items as $item) {
            $this->item($doc, $item, ! $final, $files);
        }

        if ($items->isEmpty()) {
            $doc->paragraph('Belum ada nomor yang disetujui pada paket ini.', ['italic' => true]);
        }

        if ($final && $format['show_key_sheet']) {
            $doc->pageBreak()->paragraph('LEMBAR KUNCI JAWABAN & PEDOMAN PENSKORAN', ['bold' => true, 'align' => 'center', 'after' => 200]);
            $doc->table(
                array_merge([['No', 'Kunci / Pedoman penskoran', 'Bobot']], $items->map(fn (QuestionItem $i): array => [
                    (string) $i->number,
                    trim($this->keyText($i).($i->scoring_guide !== '' && $i->scoring_guide !== null ? "\n".$i->scoring_guide : '')) ?: '—',
                    isset($i->blueprint['bobot']) ? (string) $i->blueprint['bobot'] : '—',
                ])->all()),
                [10, 75, 15],
            );
        }

        if ($final && $format['show_blueprint']) {
            $doc->startLandscape()->paragraph(mb_strtoupper('KISI-KISI SOAL '.$package->assessmentPeriod->name), ['bold' => true, 'align' => 'center', 'after' => 160]);
            $doc->table([
                ['Mata pelajaran', $package->subject->name],
                ['Kelas / Fase', $this->classPhase($package)],
                ['Tahun pelajaran', $package->assessmentPeriod->semester->year->name.' — Semester '.$package->assessmentPeriod->semester->term->label()],
                ['Penyusun soal', $package->teacher->name.($package->teacher->nip ? ' (NIP. '.$package->teacher->nip.')' : '')],
            ], [22, 78], false);
            $doc->paragraph('', ['after' => 100]);
            $doc->table(
                array_merge([['No', 'Capaian Pembelajaran', 'Materi', 'Indikator soal', 'Level', 'Bentuk']], $items->map(static fn (QuestionItem $i): array => [
                    (string) $i->number,
                    (string) ($i->blueprint['cp'] ?? '—'),
                    (string) ($i->blueprint['materi'] ?? '—'),
                    (string) ($i->blueprint['indikator'] ?? '—'),
                    (string) ($i->blueprint['level'] ?? '—'),
                    $i->type->label(),
                ])->all()),
                [5, 26, 18, 31, 7, 13],
            );

            // Tanda tangan penulis soal.
            $doc->paragraph('', ['after' => 120]);
            $doc->paragraph('Penyusun soal,', ['size' => 10, 'align' => 'right', 'after' => 700]);
            $doc->paragraph($package->teacher->name, ['size' => 10, 'bold' => true, 'align' => 'right', 'after' => 0]);

            if ($package->teacher->nip) {
                $doc->paragraph('NIP. '.$package->teacher->nip, ['size' => 9, 'align' => 'right', 'after' => 0]);
            }
        }

        if ($final && $format['show_recap']) {
            $doc->startLandscape();
            RecapSection::render($doc, RecapData::build($package));
        }

        $doc->save($path);
    }

    private function classPhase(QuestionPackage $package): string
    {
        $category = $package->subject->category;
        $phase = $category !== null ? $package->gradeLevel->phaseFor($category) : $package->gradeLevel->phase;

        return 'Kelas '.$package->gradeLevel->name.($phase ? ' / '.(stripos($phase, 'fase') === 0 ? $phase : 'Fase '.$phase) : '');
    }

    private function keyText(QuestionItem $item): string
    {
        // Menjodohkan dengan pilihan bergambar: kunci ditulis sebagai nomor pilihan (sesuai daftar bernomor pada naskah).
        if ($item->type === QuestionType::Menjodohkan && str_contains((string) $item->answer_key, '⟦img:')) {
            $parsed = MatchingKey::parse((string) $item->answer_key);
            $numbers = array_flip($parsed['choices']);

            return implode("\n", array_map(
                static fn (string $letter, string $value): string => $letter.' = pilihan '.(($numbers[$value] ?? 0) + 1),
                array_keys($parsed['pairs']),
                array_values($parsed['pairs']),
            ));
        }

        // Menjodohkan: setiap kunci pada baris sendiri, pengecoh di baris terakhir.
        if ($item->type === QuestionType::Menjodohkan) {
            $parsed = MatchingKey::parse((string) $item->answer_key);
            $lines = [];

            foreach ($parsed['pairs'] as $letter => $value) {
                $lines[] = $letter.' = '.$value;
            }

            if ($parsed['distractors'] !== []) {
                $lines[] = 'Pengecoh: '.implode('; ', $parsed['distractors']);
            }

            if ($lines !== []) {
                return implode("\n", $lines);
            }
        }

        return $item->type === QuestionType::BenarSalah
            ? (['B' => 'Benar', 'S' => 'Salah'][$item->answer_key] ?? (string) $item->answer_key)
            : (string) $item->answer_key;
    }

    /**
     * @param  array<string, mixed>  $format
     */
    private function heading(DocxBuilder $doc, QuestionPackage $package, array $format, bool $final): void
    {
        KopHeader::apply($doc, $format);

        $period = $package->assessmentPeriod;

        $doc->paragraph(mb_strtoupper(($final ? 'NASKAH SOAL ' : 'SOAL TERVALIDASI ').$period->name), ['bold' => true, 'align' => 'center', 'after' => 160]);

        $rows = [
            ['Mata pelajaran', $package->subject->name],
            ['Kelas / Fase', $this->classPhase($package)],
            ['Tahun pelajaran', $period->semester->year->name.' — Semester '.$period->semester->term->label()],
            ['Penyusun', $package->teacher->name],
            ['Validator', ($package->validator?->name ?? '—').($package->is_self_validated ? ' (Divalidasi Mandiri)' : '')],
        ];

        if ($package->fixed_at !== null) {
            $rows[] = ['Dinyatakan Fix', RecapRows::stamp($package->fixed_at)];
        }

        $doc->table($rows, [28, 72], false);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, QuestionItem>  $items
     * @return \Illuminate\Support\Collection<int, PackageFile>
     */
    private function imageFiles($items)
    {
        $ids = $items->flatMap(static fn (QuestionItem $i): array => (array) ($i->images ?? []))->unique()->values()->all();

        return $ids === [] ? collect() : PackageFile::query()->whereIn('id', $ids)->get()->keyBy('id');
    }

    /**
     * @param  \Illuminate\Support\Collection<int, PackageFile>  $files
     */
    private function item(DocxBuilder $doc, QuestionItem $item, bool $withStamps, $files): void
    {
        $bobot = isset($item->blueprint['bobot']) ? ' (bobot '.$item->blueprint['bobot'].')' : '';

        $loader = static function (int $id) use ($files, $item): ?array {
            $file = in_array($id, (array) ($item->images ?? []), true) ? $files->get($id) : null;

            return $file !== null && Storage::disk('local')->exists($file->path)
                ? ['bytes' => (string) Storage::disk('local')->get($file->path), 'ext' => $file->extension()]
                : null;
        };

        $stem = (string) $item->stem;
        $doc->runs([['text' => $item->number.'. ', 'bold' => true], ...$doc->richRuns($stem, [], $loader)], ['align' => 'both', 'after' => 60]);

        // Gambar yang tidak dirujuk penanda pada teks ditempatkan di bawah soal.
        preg_match_all('/⟦img:(\d+)⟧/u', $stem.' '.implode(' ', array_filter((array) $item->options, 'is_string')).' '.(string) $item->answer_key, $used);

        foreach ((array) ($item->images ?? []) as $id) {
            if (! in_array((string) $id, $used[1], true) && ($img = $loader((int) $id)) !== null) {
                $doc->image($img['bytes'], $img['ext']);
            }
        }

        if ($item->type === QuestionType::BenarSalah && array_filter((array) $item->options, static fn (mixed $v): bool => trim((string) $v) !== '') !== []) {
            foreach ((array) $item->options as $letter => $text) {
                $doc->runs([['text' => $letter.'. '], ...$doc->richRuns((string) $text, [], $loader), ['text' => '  (Benar / Salah)', 'italic' => true]], ['indent' => 0.8, 'after' => 20]);
            }
        } elseif ($item->type === QuestionType::BenarSalah) {
            $doc->paragraph('Benar / Salah', ['italic' => true, 'indent' => 0.8, 'size' => 10]);
        } elseif ($item->type === QuestionType::Menjodohkan) {
            // Seperti tampilan BESTIE tanpa tali: pernyataan di kolom kiri, pilihan jawaban (pasangan benar + pengecoh,
            // tanpa membocorkan urutan kunci) di kolom kanan. Pilihan bergambar diberi nomor agar dapat dirujuk kunci.
            $choices = MatchingKey::parse((string) $item->answer_key)['choices'];
            $numbered = str_contains(implode(' ', $choices), '⟦img:');
            $statements = array_values(array_filter((array) $item->options, static fn (mixed $v): bool => trim((string) $v) !== ''));
            $letters = array_keys(array_filter((array) $item->options, static fn (mixed $v): bool => trim((string) $v) !== ''));
            $rows = [];

            for ($i = 0, $n = max(count($statements), count($choices)); $i < $n; $i++) {
                $rows[] = [
                    isset($statements[$i]) ? $letters[$i].'. '.$statements[$i] : '',
                    isset($choices[$i]) ? ($numbered ? ($i + 1).'. ' : '').$choices[$i] : '',
                ];
            }

            if ($rows !== []) {
                $doc->columns(['Pernyataan', 'Pilihan jawaban'], $rows, [46, 54], $loader);
            }
        } elseif ($item->type->hasOptions()) {
            foreach ((array) $item->options as $letter => $text) {
                $doc->runs([['text' => $letter.'. '], ...$doc->richRuns((string) $text, [], $loader)], ['indent' => 0.8, 'after' => 20]);
            }
        } else {
            $doc->paragraph($item->type->label().$bobot, ['italic' => true, 'indent' => 0.8, 'size' => max(8, 10)]);
        }

        if ($withStamps) {
            $latest = $item->revisions->sortBy('version')->last();
            $parts = ['Divalidasi: '.RecapRows::stamp($item->reviewed_at)];

            if ($latest !== null && $latest->version > 1) {
                $parts[] = 'diunggah ulang: '.RecapRows::stamp($latest->uploaded_at).' (v'.$latest->version.')';
            }

            $doc->paragraph(implode('; ', $parts), ['italic' => true, 'size' => 9, 'color' => '555555', 'indent' => 0.8, 'after' => 160]);
        } else {
            $doc->paragraph('', ['after' => 80]);
        }
    }
}
