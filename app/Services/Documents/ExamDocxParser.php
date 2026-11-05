<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Enums\QuestionType;
use App\Support\ItemContent;
use App\Support\MatchingKey;

/**
 * Mengurai naskah soal yang disusun di Word dengan penomoran otomatis (format yang dipakai guru):
 *  - judul bagian (tebal, tanpa nomor): "Pilihan Ganda", "Pilihan Ganda Kompleks", "Benar/Salah", "Menjodohkan", "Isian", "Uraian";
 *  - butir soal = paragraf berdaftar angka (1., 2., …); nomor mengikuti urutan kemunculan di seluruh dokumen;
 *  - pilihan = paragraf berdaftar huruf (A., B., …), dihitung ulang tiap daftar baru;
 *  - paragraf bullet dan teks tanpa nomor sebelum pilihan = lanjutan teks soal (stimulus);
 *  - kunci jawaban = teks berwarna merah: pilihan bermerah (PG/PGK), huruf B atau S merah pada "(B/S)" (Benar/Salah),
 *    nilai "(…)" merah pada tiap pernyataan (Menjodohkan).
 */
final class ExamDocxParser
{
    private const HEADINGS = [
        'pilihanganda' => QuestionType::PilihanGanda,
        'pilihangandakompleks' => QuestionType::PilihanGandaKompleks,
        'benarsalah' => QuestionType::BenarSalah,
        'menjodohkan' => QuestionType::Menjodohkan,
        'isian' => QuestionType::Isian,
        'isiansingkat' => QuestionType::Isian,
        'uraian' => QuestionType::Uraian,
        'esai' => QuestionType::Uraian,
        'essay' => QuestionType::Uraian,
    ];

    /**
     * @param  list<array{text: string, bold: bool, list: array{num_id: int, level: int, fmt: string}|null, runs: list<array{t: string, red: bool}>, images: list<string>}>  $blocks
     */
    public function supports(array $blocks): bool
    {
        $numbered = 0;
        $lettered = 0;

        foreach ($blocks as $block) {
            if ($block['list'] === null) {
                continue;
            }

            // Nomor soal boleh berada pada tingkat daftar berapa pun (naskah hasil salin-tempel sering memakai tingkat 2/6).
            if ($block['list']['fmt'] === 'decimal') {
                $numbered++;
            } elseif (in_array($block['list']['fmt'], ['upperLetter', 'lowerLetter'], true)) {
                $lettered++;
            }
        }

        return $numbered >= 1 && ($lettered >= 1 || $this->hasHeading($blocks));
    }

    /**
     * @param  list<array{text: string, bold: bool, list: array{num_id: int, level: int, fmt: string}|null, runs: list<array{t: string, red: bool}>, images: list<string>}>  $blocks
     * @return array{items: array<int, array<string, mixed>>, warnings: list<string>}
     */
    public function parse(array $blocks): array
    {
        $warnings = [];
        $questions = [];
        $current = null;
        $section = null;
        $number = 0;
        $pendingImages = [];
        $lastOptionList = null;

        foreach ($blocks as $block) {
            $text = $block['rich'] ?? $block['text'];
            $bare = trim(preg_replace('/⟦IMG:[^⟧]*⟧/u', '', $text) ?? $text);
            $list = $block['list'];

            if ($list === null && $text !== '' && ($type = $this->heading($block)) !== null) {
                $section = $type;
                $lastOptionList = null;

                continue;
            }

            // Stem: paragraf berdaftar angka tingkat 0.
            if ($list !== null && $list['fmt'] === 'decimal' && $this->startsQuestion($list, $current) && ! $this->nestedInStem($list, $current)) {
                if ($current !== null) {
                    $questions[] = $current;
                }

                $number++;
                $current = $this->newQuestion($number, $section, $block, $pendingImages);
                $current['list_key'] = $list['num_id'].'/'.$list['level'];
                $pendingImages = [];
                $lastOptionList = null;

                continue;
            }

            if ($text === '' && $block['images'] === []) {
                $lastOptionList = null;

                continue;
            }

            if ($current === null) {
                // Judul dokumen atau gambar sebelum butir pertama.
                $pendingImages = [...$pendingImages, ...$block['images']];

                continue;
            }

            // Menjodohkan: baris "Pengecoh: a; b" = pilihan jawaban tambahan yang tidak dipakai pernyataan mana pun.
            if ($current['type'] === QuestionType::Menjodohkan && preg_match('/^\s*((?:<(?:b|i|u|sub|sup)>)*)\s*(?:pilihan\s+)?pengecoh\s*(?:<\/(?:b|i|u|sub|sup)>)*\s*[:：]\s*(?:<\/(?:b|i|u|sub|sup)>)*\s*(.*)$/iu', $text, $dm) === 1) {
                // Tag pembuka sebelum kata "Pengecoh" (mis. seluruh baris miring) ikut ditutup di akhir baris: buang penutupnya.
                $tail = $dm[2];
                preg_match_all('/<(b|i|u|sub|sup)>/i', $dm[1], $open);

                foreach (array_reverse($open[1]) as $t) {
                    $tail = preg_replace('/\s*<\/'.$t.'>\s*$/i', '', $tail) ?? $tail;
                }

                $current['distractors'] = [...$current['distractors'], ...$this->distractorList($tail)];
                $current['in_distractors'] = true;
                $current['images'] = [...$current['images'], ...$block['images']];

                continue;
            }

            // Gambar pengecoh pada paragraf berikutnya (baris "Pengecoh:" kosong atau gambar diletakkan di bawahnya).
            if ($current['type'] === QuestionType::Menjodohkan && ($current['in_distractors'] ?? false) && $list === null && $bare === '' && $block['images'] !== []) {
                $current['distractors'] = [...$current['distractors'], ...$this->distractorList($text)];
                $current['images'] = [...$current['images'], ...$block['images']];

                continue;
            }

            $current['in_distractors'] = false;

            // Pilihan: paragraf berdaftar huruf.
            if ($list !== null && in_array($list['fmt'], ['upperLetter', 'lowerLetter'], true)) {
                if ($lastOptionList !== $list['num_id']) {
                    $previous = $current['option_list'] ?? null;

                    // Daftar huruf sebelumnya yang lebih dalam / beda gaya (a. b. c. vs A. B. C.) adalah bagian soal, bukan pilihan.
                    if ($lastOptionList !== null && $current['options'] !== [] && $previous !== null
                        && ($previous['level'] > $list['level'] || ($previous['fmt'] !== $list['fmt'] && $previous['level'] >= $list['level']))) {
                        foreach ($current['options'] as $letter => $optText) {
                            $label = $previous['fmt'] === 'lowerLetter' ? mb_strtolower($letter) : $letter;
                            $current['stem'][] = $label.'. '.$optText;
                        }

                        $current['options'] = [];
                        $current['red'] = [];
                        $current['option_blocks'] = [];
                        $current['letters'] = 0;
                    } elseif ($lastOptionList === null || $current['options'] === []) {
                        $current['letters'] = 0;
                    }

                    $lastOptionList = $list['num_id'];
                    $current['option_list'] = $list;
                }

                $index = $current['letters']++;

                if ($index >= count(ItemContent::OPTION_LETTERS)) {
                    $warnings[] = "Nomor {$current['number']}: pilihan lebih dari 5, pilihan ke-".($index + 1).' diabaikan.';

                    continue;
                }

                $letter = ItemContent::OPTION_LETTERS[$index];
                $current['options'][$letter] = $text;
                $current['option_blocks'][$letter] = $block;
                $current['red'][$letter] = $this->hasRed($block);
                $current['images'] = [...$current['images'], ...$block['images']];

                continue;
            }

            // Gambar tanpa teks: milik butir ini bila belum ada pilihan, kalau tidak milik butir berikutnya.
            if ($bare === '') {
                if ($current['options'] === []) {
                    $current['stem'][] = $text;
                    $current['images'] = [...$current['images'], ...$block['images']];
                } else {
                    $pendingImages = [...$pendingImages, ...$block['images']];
                }

                continue;
            }

            if ($list !== null && $list['fmt'] !== 'bullet' && $current['options'] === []) {
                // Daftar bernomor/romawi di dalam soal: nomornya hilang pada teks mentah, jadi ditulis ulang.
                $key = $list['num_id'].'/'.$list['level'];
                $current['sub'][$key] = ($current['sub'][$key] ?? 0) + 1;
                $label = $this->listLabel($list['fmt'], $current['sub'][$key]);
                $current['stem'][] = $label.' '.$text;
                $current['stem_blocks'][] = $block;
                $current['images'] = [...$current['images'], ...$block['images']];

                continue;
            }

            if ($list !== null && $list['fmt'] === 'bullet') {
                $current['stem'][] = '• '.$text;
                $current['images'] = [...$current['images'], ...$block['images']];

                continue;
            }

            if ($current['options'] === []) {
                $current['stem'][] = $text;
                $current['stem_blocks'][] = $block;
                $current['images'] = [...$current['images'], ...$block['images']];
            } else {
                // Teks lanjutan setelah pilihan: gabungkan ke pilihan terakhir.
                $last = array_key_last($current['options']);
                $current['options'][$last] .= ' '.$text;
            }
        }

        if ($current !== null) {
            $questions[] = $current;
        }

        $items = [];

        foreach ($questions as $question) {
            $items[$question['number']] = $this->finish($question, $warnings);
        }

        if ($pendingImages !== [] && $questions !== []) {
            $warnings[] = 'Ada gambar setelah butir terakhir yang tidak terhubung ke nomor mana pun.';
        }

        return ['items' => $items, 'warnings' => $warnings];
    }


    /**
     * Daftar angka tingkat 0 berbeda dari daftar nomor soal, padahal soal bentuk berpilihan (PG/PG kompleks/menjodohkan)
     * belum punya pilihan: itu daftar bernomor di dalam soal, bukan soal baru.
     *
     * @param  array{num_id: int, level: int, fmt: string}  $list
     * @param  array<string, mixed>|null  $current
     */
    private function nestedInStem(array $list, ?array $current): bool
    {
        if ($current === null || $current['options'] !== [] || ($current['list_key'] ?? null) === $list['num_id'].'/'.$list['level']) {
            return false;
        }

        return $list['level'] > 0
            || in_array($current['type'], [QuestionType::PilihanGanda, QuestionType::PilihanGandaKompleks, QuestionType::Menjodohkan], true);
    }

    private function listLabel(string $fmt, int $n): string
    {
        $roman = static function (int $v): string {
            $out = '';

            foreach (['x' => 10, 'ix' => 9, 'v' => 5, 'iv' => 4, 'i' => 1] as $sym => $val) {
                while ($v >= $val) {
                    $out .= $sym;
                    $v -= $val;
                }
            }

            return $out;
        };

        return match ($fmt) {
            'lowerLetter' => chr(96 + (($n - 1) % 26) + 1).'.',
            'upperLetter' => chr(64 + (($n - 1) % 26) + 1).'.',
            'lowerRoman' => $roman($n).'.',
            'upperRoman' => strtoupper($roman($n)).'.',
            default => $n.'.',
        };
    }

    /**
     * Daftar bernomor desimal dianggap nomor soal bila: tingkat 0; atau belum ada soal; atau soal sebelumnya sudah punya
     * pilihan (nomor baru muncul setelah pilihan); atau daftar yang sama dengan nomor soal sebelumnya (soal tanpa pilihan).
     * Daftar angka bersarang di dalam stimulus (belum ada pilihan, daftar berbeda) tetap menjadi teks soal.
     *
     * @param  array{num_id: int, level: int, fmt: string}  $list
     * @param  array<string, mixed>|null  $current
     */
    private function startsQuestion(array $list, ?array $current): bool
    {
        return $list['level'] === 0
            || $current === null
            || $current['options'] !== []
            || ($current['list_key'] ?? null) === $list['num_id'].'/'.$list['level'];
    }

    /**
     * @param  array<string, mixed>  $block
     * @param  list<string>  $pendingImages
     * @return array<string, mixed>
     */
    private function newQuestion(int $number, ?QuestionType $section, array $block, array $pendingImages): array
    {
        return [
            'number' => $number,
            'type' => $section,
            'stem' => [trim(implode(' ', array_map(static fn (string $p): string => '⟦IMG:'.$p.'⟧', $pendingImages)).' '.($block['rich'] ?? $block['text']))],
            'stem_blocks' => [$block],
            'options' => [],
            'red' => [],
            'option_blocks' => [],
            'distractors' => [],
            'letters' => 0,
            'images' => [...$pendingImages, ...$block['images']],
        ];
    }

    /**
     * @param  array<string, mixed>  $q
     * @param  list<string>  $warnings
     * @return array<string, mixed>
     */
    private function finish(array $q, array &$warnings): array
    {
        $number = $q['number'];
        /** @var QuestionType|null $type */
        $type = $q['type'];
        $options = $q['options'];
        $stem = trim(implode("\n", $q['stem']));
        $key = '';

        if ($type === null) {
            $type = count($options) >= 2 ? QuestionType::PilihanGanda : QuestionType::Uraian;
            $warnings[] = "Nomor {$number}: bentuk soal tidak diketahui (tidak ada judul bagian); dianggap {$type->label()}.";
        }

        switch ($type) {
            case QuestionType::PilihanGanda:
            case QuestionType::PilihanGandaKompleks:
                $red = array_keys(array_filter($q['red']));
                $key = implode(',', $red);

                if ($red === []) {
                    $warnings[] = "Nomor {$number}: kunci belum ditandai (beri warna merah pada pilihan yang benar).";
                } elseif ($type === QuestionType::PilihanGanda && count($red) > 1) {
                    $warnings[] = "Nomor {$number}: pilihan ganda memiliki lebih dari satu pilihan bermerah (".implode(', ', $red).').';
                }

                if (count($options) < 2) {
                    $warnings[] = "Nomor {$number}: pilihan jawaban kurang dari 2.";
                }

                break;

            case QuestionType::BenarSalah:
                if ($options !== []) {
                    // Beberapa pernyataan (A, B, C, …) dengan "(B/S)" di tiap baris: kunci "A=B; B=S; C=B".
                    $pairs = [];

                    foreach ($options as $letter => $text) {
                        $answer = $this->trueFalseFromBlocks([$q['option_blocks'][$letter] ?? ['runs' => []]]);
                        $options[$letter] = trim(self::stripTfMarker($text));

                        if ($answer === '') {
                            $warnings[] = "Nomor {$number}: pernyataan {$letter} belum ditandai (warnai huruf B atau S pada \"(B/S)\" dengan merah).";

                            continue;
                        }

                        $pairs[] = $letter.'='.$answer;
                    }

                    $key = implode('; ', $pairs);

                    break;
                }

                [$stem, $key] = $this->trueFalse($q, $stem);

                if ($key === '') {
                    $warnings[] = "Nomor {$number}: kunci Benar/Salah tidak terbaca (tandai huruf B atau S pada \"(B/S)\" dengan warna merah).";
                }

                $options = [];

                break;

            case QuestionType::Menjodohkan:
                $pairs = [];

                foreach ($options as $letter => $text) {
                    if (preg_match('/^(.*?)\s*\(([^()]+)\)\s*((?:<\/(?:b|i|u|sub|sup)>)*)\s*$/us', $text, $m) === 1) {
                        $options[$letter] = trim($m[1]).$m[3];
                        $pairs[$letter] = trim($m[2]);
                    }
                }

                $key = MatchingKey::build($pairs, $q['distractors']);

                if ($pairs === []) {
                    $warnings[] = "Nomor {$number}: pasangan jawaban menjodohkan tidak terbaca (tulis nilainya dalam tanda kurung di akhir tiap pernyataan).";
                }

                break;

            default:
                $options = [];
        }

        return [
            'stem' => $stem,
            'options' => $options,
            'answer_key' => $key,
            'scoring_guide' => '',
            'type' => $type->value,
            'bobot' => null,
            'images' => array_values(array_unique($q['images'])),
        ];
    }


    /**
     * Membuang penanda "(B/S)" di akhir teks (boleh diapit penanda format <b>/<i>/<u>), mempertahankan penutup format.
     */
    private static function stripTfMarker(string $text): string
    {
        $t = '(?:<\/?(?:b|i|u|sub|sup)>)*';

        // Huruf boleh kecil; spasi (termasuk ganda/NBSP/tab) di mana pun diabaikan. Tanpa tanda kurung hanya dikenali
        // bila berdiri sendiri (bukan bagian kata seperti "Mb/s").
        $tail = '\s*\.?\s*((?:<\/(?:b|i|u|sub|sup)>)*)\s*$/iu';
        $paren = preg_replace('/\s*\(\s*'.$t.'B\s*'.$t.'\/\s*'.$t.'S\s*'.$t.'\)'.$tail, '$1', $text) ?? $text;

        if ($paren !== $text) {
            return $paren;
        }

        return preg_replace('/\s+(?<![\p{L}\p{N}\/])'.$t.'B\s*'.$t.'\/\s*'.$t.'S'.$t.$tail, '$1', $text) ?? $text;
    }

    /**
     * @param  array<string, mixed>  $q
     * @return array{0: string, 1: string}  teks soal tanpa penanda "(B/S)" dan kuncinya (B/S)
     */
    private function trueFalse(array $q, string $stem): array
    {
        $key = $this->trueFalseFromBlocks($q['stem_blocks']);
        return [trim(self::stripTfMarker($stem)), $key];
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     * @return string  'B', 'S', atau '' bila tidak ada huruf merah pada "(B/S)"
     */
    private function trueFalseFromBlocks(array $blocks): string
    {
        $key = '';

        foreach ($blocks as $block) {
            // Teks disusun ulang per karakter beserta warnanya: Word sering memecah "(B/S)" menjadi beberapa run
            // (ejaan, revisi, format), jadi pengecekan antar-run saja kadang gagal.
            $chars = [];
            $red = [];

            foreach ($block['runs'] ?? [] as $run) {
                foreach (mb_str_split((string) $run['t']) as $ch) {
                    $chars[] = $ch;
                    $red[] = (bool) $run['red'];
                }
            }

            $line = implode('', $chars);

            if (preg_match_all('/\(?\s*([BS])\s*[\/|]\s*([BS])\s*\)?/iu', $line, $m, PREG_OFFSET_CAPTURE | PREG_SET_ORDER) > 0) {
                $last = end($m);
                $first = mb_strlen(substr($line, 0, $last[1][1]));
                $second = mb_strlen(substr($line, 0, $last[2][1]));

                $last[1][0] = mb_strtoupper($last[1][0]);
                $last[2][0] = mb_strtoupper($last[2][0]);

                if ($last[1][0] !== $last[2][0]) {
                    $a = $red[$first] ?? false;
                    $b = $red[$second] ?? false;

                    if ($a xor $b) {
                        $key = $a ? $last[1][0] : $last[2][0];

                        continue;
                    }
                }
            }

            // Cadangan: kata BENAR/SALAH atau huruf tunggal B/S yang berwarna merah.
            foreach ($block['runs'] ?? [] as $run) {
                if ($run['red'] && in_array(mb_strtoupper(trim((string) $run['t'])), ['BENAR', 'SALAH'], true)) {
                    $key = mb_strtoupper(trim((string) $run['t']))[0];
                }
            }
        }

        return $key;
    }

    /**
     * @param  array{text: string, bold: bool, list: array{num_id: int, level: int, fmt: string}|null, runs: list<array{t: string, red: bool}>, images: list<string>}  $block
     */
    private function hasRed(array $block): bool
    {
        foreach ($block['runs'] as $run) {
            if ($run['red'] && trim($run['t']) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{text: string, bold: bool, list: array<string, mixed>|null, runs: list<array{t: string, red: bool}>, images: list<string>}  $block
     */
    private function heading(array $block): ?QuestionType
    {
        return self::sectionType($block['text']);
    }

    /**
     * Mengenali judul bagian soal ("Pilihan Ganda", "Benar/ Salah", …) dari teks paragraf.
     */
    public static function sectionType(string $text): ?QuestionType
    {
        if (mb_strlen($text) > 45) {
            return null;
        }

        // Buang awalan "A." / "I." / "Bagian 1" dan tanda baca.
        $key = preg_replace('/^(?:bagian\s+)?(?:[ivx]+|[a-e]|\d+)[.)]\s+/iu', '', $text) ?? $text;
        $key = preg_replace('/[^a-z]+/', '', mb_strtolower($key)) ?? '';

        return self::HEADINGS[$key] ?? null;
    }

    /**
     * @param  list<array{text: string, bold: bool, list: array<string, mixed>|null, runs: list<array{t: string, red: bool}>, images: list<string>}>  $blocks
     */
    private function hasHeading(array $blocks): bool
    {
        foreach ($blocks as $block) {
            if ($block['list'] === null && $block['text'] !== '' && $this->heading($block) !== null) {
                return true;
            }
        }

        return false;
    }

    /** Buang tag <b>/<i>/<u> yang tidak berpasangan (mis. pemisah ";" yang dicetak miring terpecah dua). */
    /**
     * Pecah isi baris pengecoh: dipisah titik koma, dan tiap gambar yang berdiri sendiri menjadi satu pengecoh.
     *
     * @return list<string>
     */
    private function distractorList(string $tail): array
    {
        $out = [];

        foreach (explode(';', $tail) as $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            $rest = trim(preg_replace('/⟦IMG:[^⟧]*⟧/u', '', $part) ?? $part);

            if ($rest === '' && preg_match_all('/⟦IMG:[^⟧]*⟧/u', $part, $m) > 0) {
                array_push($out, ...$m[0]);

                continue;
            }

            $out[] = $this->balanceTags($part);
        }

        return $out;
    }

    private function balanceTags(string $text): string
    {
        foreach (['b', 'i', 'u', 'sub', 'sup'] as $t) {
            $open = substr_count(strtolower($text), "<$t>");
            $close = substr_count(strtolower($text), "</$t>");

            if ($open === $close && ($open === 0 || stripos($text, "<$t>") < stripos($text, "</$t>"))) {
                continue;
            }

            $text = (string) preg_replace("#</?$t>#i", '', $text);
        }

        return trim($text);
    }
}
