<?php

declare(strict_types=1);

namespace App\Services\Documents;

use App\Support\ItemContent;

/**
 * Huruf pilihan berikutnya yang diharapkan (A → B → … → E), agar teks seperti "B. ..." di dalam kalimat tidak dianggap pilihan.
 */
final class ItemContentLetters
{
    /**
     * @param  array<string, string>  $options
     */
    public static function next(array $options): ?string
    {
        return ItemContent::OPTION_LETTERS[count($options)] ?? null;
    }
}
