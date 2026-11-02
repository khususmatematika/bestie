<?php

declare(strict_types=1);

namespace App\Services\Documents;

use InvalidArgumentException;
use Smalot\PdfParser\Parser;
use Throwable;

/**
 * Pembaca teks PDF (bukan hasil pindaian). Mengembalikan baris teks berurutan untuk naskah, dan baris tabel
 * (dikelompokkan berdasarkan posisi teks) untuk kisi-kisi. PDF hasil pindai/gambar tidak berisi teks sehingga tidak terbaca.
 */
final class PdfTextReader
{
    private const MAX_PAGES = 60;

    /**
     * @return list<string>
     */
    public function lines(string $path): array
    {
        $lines = [];

        foreach ($this->fragmentRows($path) as $row) {
            $text = trim(implode(' ', array_column($row, 't')));

            if ($text !== '') {
                $lines[] = $text;
            }
        }

        if ($lines === []) {
            throw new InvalidArgumentException('PDF tidak berisi teks yang dapat dibaca (mungkin hasil pindaian). Gunakan DOCX/XLSX atau isi manual.');
        }

        return $lines;
    }

    /**
     * Baris tabel kisi-kisi dengan kunci snake_case. Kolom ditentukan dari posisi judul kolom, dan tiap baris
     * dikelompokkan berdasarkan "jangkar" nomor soal (kolom No. Soal, atau No bila hanya satu), sehingga sel
     * multi-baris yang rata tengah ikut ke jangkar terdekat. Kolom yang sel-nya digabung (Capaian Pembelajaran,
     * Materi) dikelompokkan berdasarkan jangkar kolom "No." per kelompok.
     *
     * @return list<array<string, string>>
     */
    public function tableRows(string $path): array
    {
        $result = [];
        $headerFound = false;

        foreach ($this->pageFragments($path) as $fragments) {
            $header = $this->headerColumns($fragments);

            if ($header === null) {
                continue;
            }

            $headerFound = true;
            [$columns, $headerBottom] = $header;
            $anchorKey = isset($columns['no_soal']) ? 'no_soal' : 'no';
            $groupKey = isset($columns['no_soal']) && isset($columns['no']) ? 'no' : null;

            $body = array_values(array_filter($fragments, static fn (array $f): bool => $f['y'] < $headerBottom - 1));
            $columns = $this->columnEdges($columns, $body);
            $cells = [];

            foreach ($body as $f) {
                $cells[$this->columnFor($columns, $f['x'])][] = $f;
            }

            $anchors = $this->anchors($cells[$anchorKey] ?? []);

            if ($anchors === []) {
                continue;
            }

            $rows = [];

            foreach ($anchors as $i => $anchor) {
                $rows[$i] = [$anchorKey => (string) $anchor['n']];
            }

            $groupAnchors = $groupKey !== null ? $this->anchors($cells[$groupKey] ?? []) : [];

            foreach ($cells as $key => $list) {
                if ($key === $anchorKey) {
                    continue;
                }

                $merged = $groupKey !== null && in_array($key, [$groupKey, 'cp', 'capaian_pembelajaran', 'materi'], true);
                $bands = $merged && $groupAnchors !== [] ? $groupAnchors : $anchors;

                foreach ($list as $f) {
                    $index = $this->nearestBand($bands, $f['y']);
                    $target = $merged && $groupAnchors !== [] ? $this->firstRowOfGroup($anchors, $groupAnchors, $index) : $index;
                    $rows[$target][$key] = trim(($rows[$target][$key] ?? '').' '.$f['t']);
                }
            }

            ksort($rows);

            foreach ($rows as $row) {
                $result[] = $row;
            }
        }

        if (! $headerFound) {
            throw new InvalidArgumentException('Judul kolom (No, Indikator Soal, …) tidak ditemukan pada PDF kisi-kisi.');
        }

        return $result;
    }

    /**
     * Kolom dari baris judul: kata kunci judul diterjemahkan ke kunci kolom; "No" paling kiri = nomor kelompok,
     * "No" berikutnya = nomor soal.
     *
     * @param  list<array{x: float, y: float, t: string}>  $fragments
     * @return array{0: array<string, float>, 1: float}|null  [kolom => x awal, batas bawah judul (y)]
     */
    private function headerColumns(array $fragments): ?array
    {
        $words = [
            'capaian' => 'capaian_pembelajaran', 'cp' => 'capaian_pembelajaran', 'submateri' => 'submateri', 'sub' => 'submateri',
            'materi' => 'materi', 'indikator' => 'indikator_soal', 'level' => 'level_kognitif',
            'bentuk' => 'bentuk_soal', 'kunci' => 'kunci', 'bobot' => 'bobot',
        ];

        $columns = [];
        $noFragments = [];
        $headerTop = null;
        $headerBottom = null;

        foreach ($fragments as $f) {
            $label = mb_strtolower(trim($f['t']));
            $first = preg_split('/\s+/u', $label)[0] ?? '';

            if (preg_match('/^no\.?$/u', $first) === 1 && mb_strlen($label) <= 12) {
                $noFragments[] = $f;
            } elseif (isset($words[$first]) && mb_strlen($label) <= 30) {
                $columns[$words[$first]] ??= $f['x'];
                $headerTop = max($headerTop ?? $f['y'], $f['y']);
            }
        }

        if (! isset($columns['indikator_soal']) && ! isset($columns['capaian_pembelajaran'])) {
            return null;
        }

        usort($noFragments, static fn (array $a, array $b): int => $a['x'] <=> $b['x']);

        // Hanya "No" pada garis judul (sejajar dengan kata judul lain) yang dihitung.
        $noFragments = array_values(array_filter($noFragments, static fn (array $f): bool => abs($f['y'] - ($headerTop ?? $f['y'])) <= 40));

        if ($noFragments === []) {
            return null;
        }

        if (count($noFragments) === 1) {
            $columns['no'] = $noFragments[0]['x'];
        } else {
            $columns['no'] = $noFragments[0]['x'];
            $columns['no_soal'] = end($noFragments)['x'];
        }

        $columns['bentuk_soal'] ??= null;
        $columns = array_filter($columns, static fn (mixed $x): bool => $x !== null);

        // Batas bawah judul: baris judul terendah = sedikit di bawah kata "Soal"/"Kognitif" terbawah di kolom judul.
        $headerBottom = $headerTop;

        foreach ($fragments as $f) {
            if ($f['y'] <= $headerTop && $f['y'] >= $headerTop - 40 && preg_match('/^(soal|kognitif|\.|pembelajaran)$/iu', trim($f['t'])) === 1) {
                $headerBottom = min($headerBottom, $f['y']);
            }
        }

        asort($columns);

        return [$columns, $headerBottom];
    }

    /**
     * Teks judul kolom biasanya rata tengah sehingga posisinya tidak sama dengan tepi kolom. Tepi diperkirakan dari
     * badan tabel: teks Indikator rata kiri (x paling sering muncul), Capaian Pembelajaran juga rata kiri, sedangkan
     * kolom sempit di kanan (No. Soal, Level, Bentuk) dipisahkan di titik tengah antar judul.
     *
     * @param  array<string, float>  $columns
     * @param  list<array{x: float, y: float, t: string}>  $body
     * @return array<string, float>
     */
    private function columnEdges(array $columns, array $body): array
    {
        $edges = $columns;
        $anchorKey = isset($columns['no_soal']) ? 'no_soal' : 'no';
        $rightmost = $columns['no_soal'] ?? INF;

        // Bentuk/Level/No. Soal: titik tengah antar judul.
        $narrow = array_values(array_filter(['no_soal', 'level_kognitif', 'bentuk_soal'], static fn (string $k): bool => isset($columns[$k])));

        foreach ($narrow as $i => $key) {
            $edges[$key] = $i === 0 ? $columns[$key] - 22 : ($columns[$narrow[$i - 1]] + $columns[$key]) / 2;
        }

        // Indikator rata kiri: x paling sering di antara Submateri dan No. Soal.
        if (isset($columns['indikator_soal'])) {
            $from = ($columns['submateri'] ?? $columns['materi'] ?? 0) - 12;
            $histogram = [];

            foreach ($body as $f) {
                if ($f['x'] > $from && $f['x'] < ($edges['no_soal'] ?? INF) - 4 && preg_match('/^\d{1,3}$/', $f['t']) !== 1) {
                    $key = (string) round($f['x']);
                    $histogram[$key] = ($histogram[$key] ?? 0) + 1;
                }
            }

            arsort($histogram);
            $mode = array_key_first($histogram);
            $leftColumn = isset($columns['submateri']) ? 'submateri' : null;
            $candidate = $mode !== null && (int) $histogram[$mode] >= 2 ? (float) $mode - 2 : $columns['indikator_soal'];

            // Bila mode jatuh di Submateri (rata tengah jarang berulang), pakai judul Indikator.
            $edges['indikator_soal'] = $leftColumn !== null && $candidate < ($columns['submateri'] + 20) ? $columns['indikator_soal'] - 60 : $candidate;
        }

        if (isset($columns['submateri'])) {
            $edges['submateri'] = $columns['submateri'] - 12;
        }

        if (isset($columns['materi'])) {
            $edges['materi'] = $columns['materi'] - 12;
        }

        if (isset($columns['capaian_pembelajaran'])) {
            $left = [];

            foreach ($body as $f) {
                if ($f['x'] < ($columns['materi'] ?? $columns['indikator_soal'] ?? 999) - 30 && preg_match('/^\d{1,3}$/', $f['t']) !== 1) {
                    $key = (string) round($f['x']);
                    $left[$key] = ($left[$key] ?? 0) + 1;
                }
            }

            arsort($left);
            $edges['capaian_pembelajaran'] = $left !== [] ? (float) array_key_first($left) - 1 : $columns['capaian_pembelajaran'];
        }

        asort($edges);

        return $edges;
    }

    /**
     * @param  array<string, float>  $columns
     */
    private function columnFor(array $columns, float $x): string
    {
        $chosen = array_key_first($columns);

        foreach ($columns as $key => $start) {
            if ($x + 6 >= $start) {
                $chosen = $key;
            }
        }

        return (string) $chosen;
    }

    /**
     * @param  list<array{x: float, y: float, t: string}>  $list
     * @return list<array{n: int, y: float}>  diurutkan dari atas ke bawah
     */
    private function anchors(array $list): array
    {
        $anchors = [];

        foreach ($list as $f) {
            if (preg_match('/^\d{1,3}$/', trim($f['t'])) === 1) {
                $anchors[] = ['n' => (int) $f['t'], 'y' => $f['y']];
            }
        }

        usort($anchors, static fn (array $a, array $b): int => $b['y'] <=> $a['y']);

        return $anchors;
    }

    /**
     * @param  list<array{n: int, y: float}>  $anchors
     */
    private function nearestBand(array $anchors, float $y): int
    {
        $best = 0;
        $distance = INF;

        foreach ($anchors as $i => $anchor) {
            $d = abs($anchor['y'] - $y);

            if ($d < $distance) {
                $distance = $d;
                $best = $i;
            }
        }

        return $best;
    }

    /**
     * Indeks baris (jangkar nomor soal) pertama pada kelompok sel gabungan dengan indeks $group.
     *
     * @param  list<array{n: int, y: float}>  $rowAnchors
     * @param  list<array{n: int, y: float}>  $groupAnchors
     */
    private function firstRowOfGroup(array $rowAnchors, array $groupAnchors, int $group): int
    {
        // Batas kelompok = titik tengah antar jangkar kelompok; baris pertama = baris pertama di bawah batas atas kelompok.
        $upper = $group === 0 ? INF : ($groupAnchors[$group - 1]['y'] + $groupAnchors[$group]['y']) / 2;

        foreach ($rowAnchors as $i => $anchor) {
            if ($anchor['y'] < $upper) {
                return $i;
            }
        }

        return 0;
    }

    /**
     * @return list<list<array{x: float, y: float, t: string}>>  potongan teks per halaman
     */
    private function pageFragments(string $path): array
    {
        try {
            $pdf = (new Parser)->parseFile($path);
            $pages = array_slice($pdf->getPages(), 0, self::MAX_PAGES);
        } catch (Throwable $e) {
            report($e);

            throw new InvalidArgumentException('PDF tidak dapat dibaca. Pastikan berkas tidak rusak atau terlindungi kata sandi.');
        }

        $result = [];

        foreach ($pages as $page) {
            $fragments = [];

            foreach ($page->getDataTm() as $entry) {
                $text = trim((string) preg_replace('/\s+/u', ' ', (string) $entry[1]));

                if ($text === '' || preg_match('/^(halaman|hal\.?|page)\s*\d+(\s*(\/|dari|of)\s*\d+)?$/iu', $text) === 1) {
                    continue;
                }

                $fragments[] = ['x' => (float) $entry[0][4], 'y' => (float) $entry[0][5], 't' => $text];
            }

            $result[] = $fragments;
        }

        return $result;
    }

    /**
     * @return list<list<array{x: float, t: string}>>  baris-baris, tiap baris berisi potongan teks urut dari kiri
     */
    private function fragmentRows(string $path): array
    {
        try {
            $pdf = (new Parser)->parseFile($path);
            $pages = array_slice($pdf->getPages(), 0, self::MAX_PAGES);
        } catch (Throwable $e) {
            report($e);

            throw new InvalidArgumentException('PDF tidak dapat dibaca. Pastikan berkas tidak rusak atau terlindungi kata sandi.');
        }

        $rows = [];

        foreach ($pages as $page) {
            $buckets = [];

            foreach ($page->getDataTm() as $entry) {
                $text = trim((string) preg_replace('/\s+/u', ' ', (string) $entry[1]));

                // Lewati nomor halaman / footer ("Halaman 1", "1 / 3").
                if ($text === '' || preg_match('/^(halaman|hal\.?|page)\s*\d+(\s*(\/|dari|of)\s*\d+)?$/iu', $text) === 1) {
                    continue;
                }

                $x = (float) $entry[0][4];
                $y = (float) $entry[0][5];
                $bucket = (int) round($y / 3);

                $buckets[$bucket][] = ['x' => $x, 't' => $text];
            }

            krsort($buckets); // koordinat Y PDF naik ke atas: nilai besar = baris atas

            foreach ($buckets as $fragments) {
                usort($fragments, static fn (array $a, array $b): int => $a['x'] <=> $b['x']);
                $rows[] = $fragments;
            }
        }

        return $rows;
    }

    private function key(string $label): string
    {
        $key = trim((string) preg_replace('/[^a-z0-9]+/', '_', mb_strtolower($label)), '_');

        return $key === '' ? 'kolom' : $key;
    }
}
