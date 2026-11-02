<?php

declare(strict_types=1);

namespace App\Services\Documents;

use InvalidArgumentException;

/**
 * Mengubah tabel pada DOCX (baris pertama = judul kolom) menjadi baris berkunci snake_case,
 * sama bentuknya dengan hasil pembacaan Excel, agar parser kisi-kisi dapat dipakai ulang.
 */
final class DocumentRows
{
    /**
     * @param  list<list<list<string>>>  $tables
     * @return list<array<string, string>>
     */
    public static function fromTables(array $tables): array
    {
        foreach ($tables as $table) {
            if (count($table) < 2) {
                continue;
            }

            $headings = array_map(static fn (string $h): string => self::key($h), $table[0]);

            if (array_intersect(['no', 'no_soal', 'nomor', 'nomor_soal'], $headings) === []) {
                continue;
            }

            $rows = [];

            foreach (array_slice($table, 1) as $cells) {
                $row = [];

                foreach ($headings as $i => $key) {
                    $row[$key] = trim($cells[$i] ?? '');
                }

                $rows[] = $row;
            }

            return $rows;
        }

        throw new InvalidArgumentException('Tabel kisi-kisi dengan kolom "No" tidak ditemukan pada dokumen Word.');
    }

    private static function key(string $label): string
    {
        $key = trim((string) preg_replace('/[^a-z0-9]+/', '_', mb_strtolower($label)), '_');

        return $key === '' ? 'kolom' : $key;
    }
}
