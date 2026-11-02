<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Enums\QuestionType;
use App\Support\ItemContent;

/**
 * Menggabungkan hasil parsing naskah dan kisi-kisi menjadi butir per nomor yang sudah dinormalisasi.
 * Ketidakcocokan nomor dikembalikan sebagai peringatan untuk pratinjau.
 */
final class ItemAssembler
{
    /**
     * @param  array<int, array<string, mixed>>  $naskah  hasil NaskahParser::parse()['items']
     * @param  array<int, array<string, mixed>>  $blueprints  hasil BlueprintRowsParser::parse()['rows']
     * @return array{items: list<array{number: int, content: array<string, mixed>, images: list<string>}>, warnings: list<string>}
     */
    public function assemble(array $naskah, array $blueprints): array
    {
        $warnings = [];
        $numbers = array_values(array_unique([...array_keys($naskah), ...array_keys($blueprints)]));
        sort($numbers);

        $items = [];

        foreach ($numbers as $number) {
            $text = $naskah[$number] ?? null;
            $plan = $blueprints[$number] ?? null;

            if ($naskah !== [] && $text === null) {
                $warnings[] = "Nomor {$number} ada di kisi-kisi tetapi tidak ditemukan di naskah.";
            }

            if ($blueprints !== [] && $plan === null) {
                $warnings[] = "Nomor {$number} ada di naskah tetapi tidak ditemukan di kisi-kisi.";
            }

            $blueprint = $plan['blueprint'] ?? [];

            // Bobot pada naskah (jika ada) mengalahkan bobot kisi-kisi.
            if (isset($text['bobot']) && is_numeric($text['bobot']) && (float) $text['bobot'] > 0) {
                $blueprint['bobot'] = (float) $text['bobot'];
            }

            $textType = QuestionType::tryFrom((string) ($text['type'] ?? ''));
            $planType = QuestionType::tryFrom((string) ($plan['type'] ?? ''));
            $type = $textType ?? $planType ?? $this->infer($text);

            if ($textType !== null && $planType !== null && $textType !== $planType) {
                $warnings[] = "Nomor {$number}: bentuk soal di naskah ({$textType->label()}) berbeda dengan kisi-kisi ({$planType->label()}); dipakai bentuk dari naskah.";
            }

            $answerKey = (string) ($text['answer_key'] ?? '');

            if ($answerKey === '') {
                $answerKey = (string) ($plan['kunci'] ?? '');
            }

            $content = ItemContent::normalize([
                'type' => $type,
                'stem' => $text['stem'] ?? '',
                'options' => $text['options'] ?? [],
                'answer_key' => $answerKey,
                'scoring_guide' => $text['scoring_guide'] ?? '',
                'blueprint' => $blueprint,
            ]);

            if ($type === QuestionType::PilihanGanda && $content['answer_key'] !== '' && ! isset($content['options'][$content['answer_key']]) && $content['options'] !== []) {
                $warnings[] = "Nomor {$number}: kunci '{$content['answer_key']}' tidak sesuai dengan pilihan yang tersedia.";
            }

            $items[] = ['number' => (int) $number, 'content' => $content, 'images' => array_values((array) ($text['images'] ?? []))];
        }

        return ['items' => $items, 'warnings' => $warnings];
    }

    /**
     * @param  array<string, mixed>|null  $text
     */
    private function infer(?array $text): QuestionType
    {
        if (($text['options'] ?? []) !== [] && count($text['options']) >= 2) {
            return QuestionType::PilihanGanda;
        }

        return trim((string) ($text['scoring_guide'] ?? '')) !== '' ? QuestionType::Uraian : QuestionType::Isian;
    }
}
