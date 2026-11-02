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
            $text = $block['text'];
            $bare = trim(preg_replace('/⟦IMG:[^⟧]*⟧/u', '', $text) ?? $text);
            $list = $block['list'];

            if ($list === null && $text !== '' && ($type = $this->heading($block)) !== null) {
                $section = $type;
                $lastOptionList = null;

                continue;
            }

            // Stem: paragraf berdaftar angka tingkat 0.
            if ($list !== null && $list['fmt'] === 'decimal' && $this->startsQuestion($list, $current)) {
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
            if ($current['type'] === QuestionType::Menjodohkan && preg_match('/^\s*(?:pilihan\s+)?pengecoh\s*[:：]\s*(.+)$/iu', $text, $dm) === 1) {
                $current['distractors'] = [...$current['distractors'], ...array_values(array_filter(array_map('trim', explode(';', $dm[1])), static fn (string $d): bool => $d !== ''))];

                continue;
            }

            // Pilihan: paragraf berdaftar huruf.
            if ($list !== null && in_array($list['fmt'], ['upperLetter', 'lowerLetter'], true)) {
                if ($lastOptionList !== $list['num_id']) {
                    $current['letters'] = 0;
                    $lastOptionList = $list['num_id'];
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
            'stem' => [trim(implode(' ', array_map(static fn (string $p): string => '⟦IMG:'.$p.'⟧', $pendingImages)).' '.$block['text'])],
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
                        $options[$letter] = trim(preg_replace('/\s*\(\s*B\s*\/\s*S\s*\)\s*\.?\s*$/u', '', $text) ?? $text);

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
                    if (preg_match('/^(.*?)\s*\(([^()]+)\)\s*$/us', $text, $m) === 1) {
                        $options[$letter] = trim($m[1]);
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
     * @param  array<string, mixed>  $q
     * @return array{0: string, 1: string}  teks soal tanpa penanda "(B/S)" dan kuncinya (B/S)
     */
    private function trueFalse(array $q, string $stem): array
    {
        $key = $this->trueFalseFromBlocks($q['stem_blocks']);
        $clean = preg_replace('/\s*\(\s*B\s*\/\s*S\s*\)\s*\.?\s*$/u', '', $stem) ?? $stem;

        return [trim($clean), $key];
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     * @return string  'B', 'S', atau '' bila tidak ada huruf merah pada "(B/S)"
     */
    private function trueFalseFromBlocks(array $blocks): string
    {
        $key = '';

        foreach ($blocks as $block) {
            $runs = $block['runs'] ?? [];
            $count = count($runs);

            for ($i = 0; $i < $count; $i++) {
                if (! $runs[$i]['red']) {
                    continue;
                }

                $letter = mb_strtoupper(trim($runs[$i]['t']));
                $before = $i > 0 ? $runs[$i - 1]['t'] : '';
                $after = $i + 1 < $count ? $runs[$i + 1]['t'] : '';

                // "(B/" + [S merah] + ")"  atau  "(" + [B merah] + "/S)"
                if ($letter === 'S' && preg_match('/\(\s*B\s*\/\s*$/u', $before) === 1) {
                    $key = 'S';
                } elseif ($letter === 'B' && preg_match('/^\s*\/\s*S\s*\)/u', $after) === 1) {
                    $key = 'B';
                } elseif (in_array($letter, ['BENAR', 'SALAH'], true)) {
                    $key = $letter[0];
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
}
