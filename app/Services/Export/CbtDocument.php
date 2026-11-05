<?php

declare(strict_types=1);

namespace App\Services\Export;

use App\Enums\PackageStatus;
use App\Enums\QuestionType;
use App\Models\PackageFile;
use App\Models\QuestionItem;
use App\Models\QuestionPackage;
use App\Services\Documents\DocxBuilder;
use App\Services\Rendering\MathRaster;
use App\Support\ItemContent;
use App\Support\MatchingKey;
use Illuminate\Support\Facades\Storage;

/**
 * Naskah soal berformat "Template Ujian CBT" sekolah (keyword @META-…, @KONTEN-…).
 * Hanya untuk paket Fix/Terkunci. Jenis soal BESTIE dipetakan ke jenis CBT:
 * PG→PG, PG kompleks→PGK, Benar/Salah→BENAR SALAH, Isian & Uraian→ESSAY.
 * Menjodohkan tidak ada di template CBT, sehingga diekspor sebagai ESSAY (pasangan ditulis pada soal dan kunci).
 */
final class CbtDocument
{
    /** @var array<string, array{bytes: string, ext: string, dw: float, dh: float, lower: float}|null> */
    private array $equations = [];

    private const RULE = '═════════════════════════════════════════════════════';

    public static function available(QuestionPackage $package): bool
    {
        return in_array($package->status, [PackageStatus::Fix, PackageStatus::Terkunci], true);
    }

    public function build(QuestionPackage $package, string $path): void
    {
        $items = QuestionItem::query()->where('package_id', $package->id)->orderBy('number')->get();
        $ids = $items->flatMap(static fn (QuestionItem $i): array => (array) ($i->images ?? []))->unique()->values()->all();
        $files = $ids === [] ? collect() : PackageFile::query()->whereIn('id', $ids)->get()->keyBy('id');

        $doc = new DocxBuilder('Times New Roman', 12, 2.54);

        $doc->paragraph(self::RULE, ['after' => 0]);
        $doc->paragraph('═══ AKHIR PANDUAN – JANGAN HAPUS BARIS INI ═══', ['after' => 0]);
        $doc->paragraph('', ['after' => 0]);
        $doc->paragraph(self::RULE, ['after' => 0]);
        $doc->paragraph('MULAI ISI SOAL DI BAWAH INI', ['after' => 0]);
        $doc->paragraph(self::RULE, ['after' => 120]);

        foreach ($items as $item) {
            $loader = static function (int $id) use ($files, $item): ?array {
                $file = in_array($id, (array) ($item->images ?? []), true) ? $files->get($id) : null;

                return $file !== null && Storage::disk('local')->exists($file->path)
                    ? ['bytes' => (string) Storage::disk('local')->get($file->path), 'ext' => $file->extension()]
                    : null;
            };

            $this->item($doc, $item, $loader);
            $doc->paragraph('', ['after' => 0]);
            $doc->paragraph(self::RULE, ['after' => 120]);
        }

        $doc->paragraph('SELESAI', ['align' => 'center', 'after' => 0]);
        $doc->paragraph(self::RULE, ['after' => 0]);
        $doc->save($path);
    }

    private function item(DocxBuilder $doc, QuestionItem $item, callable $loader): void
    {
        $bobot = $item->blueprint['bobot'] ?? null;
        $materi = trim((string) ($item->blueprint['materi'] ?? ''));
        $type = $item->type;

        $jenis = match ($type) {
            QuestionType::PilihanGanda => 'PG',
            QuestionType::PilihanGandaKompleks => 'PGK',
            QuestionType::BenarSalah => 'BENAR SALAH',
            default => 'ESSAY',
        };

        $doc->paragraph('@META-NOMOR: '.$item->number, ['after' => 0]);
        $doc->paragraph('@META-JENIS: '.$jenis, ['after' => 0]);
        $doc->paragraph('@META-MATERI: '.$materi, ['after' => 0]);
        $doc->paragraph('@META-NILAI: '.(is_numeric($bobot) && (float) $bobot > 0 ? rtrim(rtrim(number_format((float) $bobot, 2, '.', ''), '0'), '.') : '1'), ['after' => 0]);

        $options = array_filter((array) ($item->options ?? []), static fn ($v): bool => is_string($v) && trim($v) !== '');
        $key = trim((string) $item->answer_key);

        if ($type === QuestionType::PilihanGanda || $type === QuestionType::PilihanGandaKompleks) {
            $this->soal($doc, $item, (string) $item->stem, $loader);
            $doc->paragraph('@KONTEN-OPSI:', ['after' => 0]);
            $list = $doc->newList('upperLetter');

            foreach (array_slice($options, 0, 6, true) as $text) {
                $doc->runs($this->runs($doc, $text, $loader), ['numId' => $list, 'after' => 0]);
            }

            $letters = implode(',', array_map('trim', preg_split('/[,\s;]+/', mb_strtoupper($key)) ?: []));
            $doc->paragraph('@META-KUNCI: '.$letters, ['after' => 0]);

            return;
        }

        if ($type === QuestionType::BenarSalah) {
            $statements = [];
            $tf = [];

            if (preg_match_all('/([A-E])\s*=\s*([BS])/u', mb_strtoupper($key), $m, PREG_SET_ORDER) > 0) {
                foreach ($m as $pair) {
                    $tf[$pair[1]] = $pair[2] === 'B' ? 'BENAR' : 'SALAH';
                }
            }

            if ($options !== [] && $tf !== []) {
                $this->soal($doc, $item, (string) $item->stem, $loader);

                foreach ($options as $letter => $text) {
                    $statements[] = [$text, $tf[$letter] ?? 'SALAH'];
                }
            } else {
                $this->soal($doc, $item, 'Tentukan apakah pernyataan berikut benar atau salah!', $loader);
                $statements[] = [(string) $item->stem, str_starts_with(mb_strtoupper($key), 'B') ? 'BENAR' : 'SALAH'];
            }

            foreach ($statements as $i => [$text, $value]) {
                $doc->paragraph('', ['after' => 0]);
                $this->content($doc, '@KONTEN-PERNYATAAN-'.($i + 1).':', $text, $loader);
                $doc->paragraph('@PERNYATAAN-KUNCI-'.($i + 1).': '.$value, ['after' => 0]);
            }

            return;
        }

        if ($type === QuestionType::Menjodohkan) {
            $parsed = MatchingKey::parse($key);
            $stem = trim((string) $item->stem)."\n";

            foreach ($options as $letter => $text) {
                $stem .= "\n".$letter.'. '.$text;
            }

            $stem .= "\n\nPilihan jawaban:";

            foreach ($parsed['choices'] as $i => $choice) {
                $stem .= "\n".($i + 1).'. '.$choice;
            }

            $this->soal($doc, $item, $stem, $loader);

            $pairs = [];

            foreach ($parsed['pairs'] as $letter => $value) {
                $pairs[] = $letter.' = '.$value;
            }

            $this->content($doc, '@KONTEN-KUNCI:', implode('; ', $pairs), $loader);

            return;
        }

        // Isian / Uraian → ESSAY
        $this->soal($doc, $item, (string) $item->stem, $loader);
        $guide = trim((string) $item->scoring_guide);
        $essayKey = $key !== '' && $guide !== '' && $guide !== $key ? $key."\n".$guide : ($key !== '' ? $key : $guide);
        $this->content($doc, '@KONTEN-KUNCI:', $essayKey, $loader);
    }

    /**
     * @KONTEN-SOAL: bila soal bergambar, keyword berdiri sendiri, gambar tepat di bawahnya (tiap gambar satu paragraf),
     * lalu teks soal di bawah gambar. Tanpa gambar, teks mengikuti keyword pada baris yang sama (seperti template).
     * Gambar = yang dirujuk penanda pada teks soal + gambar soal yang tidak dirujuk penanda mana pun.
     */
    private function soal(DocxBuilder $doc, QuestionItem $item, string $text, callable $loader): void
    {
        preg_match_all('/⟦img:(\d+)⟧/u', $text, $inStem);
        $ids = array_map('intval', $inStem[1]);

        $used = ItemContent::referencedImageIds([
            'stem' => (string) $item->stem,
            'options' => (array) ($item->options ?? []),
            'answer_key' => (string) $item->answer_key,
            'scoring_guide' => (string) $item->scoring_guide,
        ]);

        foreach ((array) ($item->images ?? []) as $id) {
            if (! in_array((int) $id, $used, true)) {
                $ids[] = (int) $id;
            }
        }

        $images = [];

        foreach (array_values(array_unique($ids)) as $id) {
            if (($img = $loader($id)) !== null) {
                $images[] = $img;
            }
        }

        if ($images === []) {
            $this->content($doc, '@KONTEN-SOAL:', (string) preg_replace('/⟦img:\d+⟧/u', '', $text), $loader);

            return;
        }

        $doc->paragraph('@KONTEN-SOAL:', ['after' => 0]);

        foreach ($images as $img) {
            $doc->image($img['bytes'], $img['ext'], 9.0, 7.0);
        }

        $clean = trim((string) preg_replace('/[ \t]*⟦img:\d+⟧[ \t]*/u', ' ', $text));

        foreach (preg_split('/\R/u', $clean) ?: [] as $line) {
            if (trim($line) !== '') {
                $doc->runs($this->runs($doc, trim($line), $loader), ['after' => 0]);
            }
        }
    }

    /**
     * Keyword diikuti isi; baris baru menjadi paragraf baru dan gambar ditaruh pada paragraf sendiri (seperti template).
     */
    private function content(DocxBuilder $doc, string $keyword, string $text, callable $loader): void
    {
        $segments = preg_split('/(⟦img:\d+⟧)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
        $first = true;

        foreach ($segments === [] ? [''] : $segments as $segment) {
            if (str_starts_with($segment, '⟦img:')) {
                $doc->runs($this->runs($doc, $segment, $loader), ['after' => 60]);

                continue;
            }

            foreach (preg_split('/\R/u', $segment) ?: [''] as $line) {
                if ($first) {
                    $doc->runs([['text' => $keyword.' '], ...$this->runs($doc, ltrim($line), $loader)], ['after' => 0]);
                    $first = false;
                } elseif (trim($line) !== '') {
                    $doc->runs($this->runs($doc, $line, $loader), ['after' => 0]);
                }
            }
        }

        if ($first) {
            $doc->paragraph($keyword, ['after' => 0]);
        }
    }

    /**
     * Run untuk satu potongan teks. Rumus \( … \) digambar menjadi gambar PNG inline (bukan objek Equation Word)
     * karena CBT sekolah tidak membaca Equation; tabel sudah berupa gambar sejak naskah Word diunggah.
     *
     * @return list<array<string, mixed>>
     */
    private function runs(DocxBuilder $doc, string $text, callable $loader): array
    {
        $parts = preg_split('/(\\\\\(.+?\\\\\))/us', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [$text];
        $runs = [];
        $open = [];

        foreach ($parts as $part) {
            if (str_starts_with($part, '\\(') && str_ends_with($part, '\\)')) {
                $latex = trim(substr($part, 2, -2));
                $img = $latex === '' ? null : $this->equation($latex);

                if ($img === null) {
                    array_push($runs, ...$doc->richRuns(implode('', array_map(static fn (string $t): string => '<'.$t.'>', $open)).$latex, [], $loader));
                } else {
                    $runs[] = ['image' => $img['bytes'], 'ext' => 'png', 'dw' => $img['dw'], 'dh' => $img['dh'], 'lower' => $img['lower']];
                }

                continue;
            }

            array_push($runs, ...$doc->richRuns(implode('', array_map(static fn (string $t): string => '<'.$t.'>', $open)).$part, [], $loader));

            if (preg_match_all('/<(\/?)(b|i|u|sub|sup)>/i', $part, $tags, PREG_SET_ORDER) > 0) {
                foreach ($tags as $tag) {
                    $name = strtolower($tag[2]);
                    $open = $tag[1] === '/' ? array_values(array_diff($open, [$name])) : array_values(array_unique([...$open, $name]));
                }
            }
        }

        return $runs === [] ? [['text' => '']] : $runs;
    }

    /**
     * @return array{bytes: string, dw: float, dh: float, lower: float}|null
     */
    private function equation(string $latex): ?array
    {
        if (! array_key_exists($latex, $this->equations)) {
            try {
                $r = (new MathRaster)->equation($latex);
                $this->equations[$latex] = ['bytes' => $r['png'], 'ext' => 'png', 'dw' => (float) $r['w'], 'dh' => (float) $r['h'], 'lower' => (float) $r['descent']];
            } catch (\Throwable) {
                $this->equations[$latex] = null;
            }
        }

        $e = $this->equations[$latex];

        return $e === null ? null : ['bytes' => $e['bytes'], 'dw' => $e['dw'], 'dh' => $e['dh'], 'lower' => $e['lower']];
    }
}
