<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Enums\QuestionType;
use App\Support\ItemContent;

/**
 * Memecah paragraf naskah soal standar menjadi butir per nomor (architecture.md §7.1).
 *
 * Format: baris penanda "No. {n}", lalu teks soal, pilihan "A. …", "Kunci: …",
 * "Pedoman: …", "Bentuk: …", "Bobot: …". Teks sebelum penanda pertama diabaikan.
 */
final class NaskahParser
{
    private const MARKER = '/^\s*(?:no|nomor)\.?\s*(\d{1,3})\s*[.:)]?\s*$/iu';

    private const KEY = '/^\s*(?:kunci(?:\s+jawaban)?|jawaban)\s*[:=]\s*(.*)$/iu';

    private const GUIDE = '/^\s*(?:pedoman(?:\s+penskoran)?|rubrik|penskoran)\s*[:=]\s*(.*)$/iu';

    private const SHAPE = '/^\s*bentuk(?:\s+soal)?\s*[:=]\s*(.*)$/iu';

    private const WEIGHT = '/^\s*bobot\s*[:=]\s*([\d.,]+)/iu';

    private const OPTION = '/^\s*\(?([A-Ea-e])[.)]\s+(.*)$/u';

    /**
     * @param  list<string>  $paragraphs
     * @return array{items: array<int, array<string, mixed>>, warnings: list<string>}
     */
    public function parse(array $paragraphs): array
    {
        $lines = [];

        foreach ($paragraphs as $paragraph) {
            foreach (explode("\n", $paragraph) as $line) {
                $lines[] = rtrim($line);
            }
        }

        $blocks = [];
        $warnings = [];
        $current = null;

        foreach ($lines as $line) {
            if (preg_match(self::MARKER, $line, $m) === 1) {
                $number = (int) $m[1];

                if (isset($blocks[$number])) {
                    $warnings[] = "Nomor {$number} muncul lebih dari sekali di naskah; kemunculan pertama yang dipakai.";
                    $current = null;

                    continue;
                }

                $blocks[$number] = [];
                $current = $number;

                continue;
            }

            if ($current !== null) {
                $blocks[$current][] = $line;
            }
        }

        $items = [];

        foreach ($blocks as $number => $blockLines) {
            $items[$number] = $this->parseBlock($number, $blockLines, $warnings);
        }

        ksort($items);

        return ['items' => $items, 'warnings' => $warnings];
    }

    /**
     * @param  list<string>  $lines
     * @param  list<string>  $warnings
     * @return array<string, mixed>
     */
    private function parseBlock(int $number, array $lines, array &$warnings): array
    {
        $stem = [];
        $options = [];
        $key = [];
        $guide = [];
        $shape = null;
        $weight = null;
        $target = 'stem';
        $lastLetter = null;

        foreach ($lines as $line) {
            if (preg_match(self::KEY, $line, $m) === 1) {
                $key = [trim($m[1])];
                $target = 'key';
            } elseif (preg_match(self::GUIDE, $line, $m) === 1) {
                $guide = [trim($m[1])];
                $target = 'guide';
            } elseif (preg_match(self::SHAPE, $line, $m) === 1) {
                $shape = trim($m[1]);
                $target = 'ignore';
            } elseif (preg_match(self::WEIGHT, $line, $m) === 1) {
                $weight = (float) str_replace(',', '.', $m[1]);
                $target = 'ignore';
            } elseif (preg_match(self::OPTION, $line, $m) === 1 && mb_strtoupper($m[1]) === $this->expectedLetter($lastLetter)) {
                $lastLetter = mb_strtoupper($m[1]);
                $options[$lastLetter] = trim($m[2]);
                $target = 'option';
            } else {
                match ($target) {
                    'stem' => $stem[] = $line,
                    'option' => $options[(string) $lastLetter] = trim($options[(string) $lastLetter]."\n".$line),
                    'key' => $key[] = $line,
                    'guide' => $guide[] = $line,
                    default => null,
                };
            }
        }

        $type = QuestionType::fromInput($shape);

        if ($shape !== null && $shape !== '' && $type === null) {
            $warnings[] = "Nomor {$number}: bentuk soal '{$shape}' tidak dikenali.";
        }

        $stemText = ItemContent::text(implode("\n", $stem), 20000);
        $keyText = ItemContent::text(implode("\n", $key), 1000);
        $guideText = ItemContent::text(implode("\n", $guide), 5000);

        if ($type === null && $stemText !== '' && count($options) >= 2) {
            $type = QuestionType::PilihanGanda;
        }

        if ($stemText === '') {
            $warnings[] = "Nomor {$number}: teks soal kosong.";
        }

        return [
            'stem' => $stemText,
            'options' => $options,
            'answer_key' => $keyText,
            'scoring_guide' => $guideText,
            'type' => $type?->value,
            'bobot' => $weight,
        ];
    }

    private function expectedLetter(?string $last): string
    {
        return $last === null ? 'A' : chr(ord($last) + 1);
    }
}
