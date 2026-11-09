<?php

declare(strict_types=1);

namespace App\Services\Rendering;

/**
 * Menggambar teks Mandarin berpinyin (penanda ⟦rb:hanzi|pinyin⟧) menjadi gambar PNG memakai GD + font Noto Sans SC
 * bawaan aplikasi. Dipakai untuk naskah CBT, yang tidak membaca pinyin bergaya Word (ruby).
 *
 * Satu baris teks digambar sebagai satu gambar; baris panjang dilipat di dalam gambar pada lebar tetap (MAXW)
 * sehingga di layar ponsel gambar tidak perlu diperkecil dan huruf tetap terbaca.
 */
final class CjkRaster
{
    private const SCALE = 3;

    /** Ukuran hanzi (px layar). */
    private const BASE = 22;

    /** Ukuran pinyin (px layar). */
    private const RT = 13;

    /** Lebar maksimum gambar (px layar). Sengaja sempit agar pas di layar ponsel tanpa diperkecil. */
    private const MAXW = 360;

    private const PAD = 3;

    private const GAP = 7;

    /** Tanda baca yang tidak boleh membuka baris baru. */
    private const CLOSING = '，。！？、；：）》」』”’…,.!?;:)]';

    public static function hasRuby(string $text): bool
    {
        return str_contains($text, '⟦rb:');
    }

    public static function available(): bool
    {
        return function_exists('imagettftext') && is_file(self::font(false)) && is_file(self::font(true));
    }

    /**
     * @return array{png: string, w: int, h: int, descent: int}
     */
    public function line(string $text): array
    {
        $rows = $this->layout($this->atoms($text));
        $width = 0.0;
        $height = 0.0;
        $metrics = [];

        foreach ($rows as $row) {
            $ruby = array_filter($row, static fn (array $a): bool => $a['rt'] !== null) !== [];
            $top = $ruby ? self::RT * 1.3 : 0.0;
            $baseline = $top + self::BASE * 0.95;
            $h = $baseline + self::BASE * 0.25;
            $metrics[] = ['top' => $top, 'baseline' => $baseline, 'h' => $h, 'y' => $height];
            $width = max($width, array_sum(array_column($row, 'w')));
            $height += $h + self::GAP;
        }

        $height = max(0.0, $height - self::GAP);
        $s = self::SCALE;
        $w = max(1, (int) ceil(($width + 2 * self::PAD) * $s));
        $h = max(1, (int) ceil(($height + 2 * self::PAD) * $s));
        $img = imagecreatetruecolor($w, $h);

        if ($img === false) {
            throw new \RuntimeException('Gagal membuat kanvas gambar.');
        }

        imagealphablending($img, true);
        imageantialias($img, true);
        $white = (int) imagecolorallocate($img, 255, 255, 255);
        $black = (int) imagecolorallocate($img, 0, 0, 0);
        imagefilledrectangle($img, 0, 0, $w, $h, $white);

        foreach ($rows as $i => $row) {
            $m = $metrics[$i];
            $x = (float) self::PAD;
            $y = self::PAD + $m['y'];

            foreach ($row as $atom) {
                $baseW = $this->measure($atom['text'], self::BASE, $atom['bold']);
                $bx = $x + ($atom['w'] - $baseW) / 2;

                if ($atom['text'] !== '' && trim($atom['text']) !== '') {
                    $this->draw($img, $atom['text'], self::BASE, $bx, $y + $m['baseline'], $black, $atom['bold']);
                }

                if ($atom['rt'] !== null && $atom['rt'] !== '') {
                    $rtW = $this->measure($atom['rt'], self::RT, false);
                    $this->draw($img, $atom['rt'], self::RT, $x + ($atom['w'] - $rtW) / 2, $y + self::RT * 1.05, $black, false);
                }

                if ($atom['u'] && trim($atom['text']) !== '') {
                    imagesetthickness($img, $s);
                    imageline($img, (int) round($bx * $s), (int) round(($y + $m['baseline'] + 3) * $s), (int) round(($bx + $baseW) * $s), (int) round(($y + $m['baseline'] + 3) * $s), $black);
                }

                $x += $atom['w'];
            }
        }

        ob_start();
        imagepng($img);
        $bytes = (string) ob_get_clean();

        // Gambar sejajar dengan teks di sekitarnya pada baris pertama (huruf pilihan "A." berada di baris atas).
        $first = $metrics === [] ? 0.0 : (float) $metrics[0]['baseline'];

        return [
            'png' => $bytes,
            'w' => (int) round($w / $s),
            'h' => (int) round($h / $s),
            'descent' => max(0, (int) round($h / $s - self::PAD - $first)),
        ];
    }

    /**
     * @return list<array{text: string, rt: ?string, bold: bool, u: bool, w: float, nl: bool}>
     */
    private function atoms(string $text): array
    {
        $text = preg_replace(['/⟦img:\d+⟧/iu', '/<\/?(?:i|sub|sup)>/i', '/\\\\[()]/'], '', $text) ?? $text;
        $atoms = [];
        $bold = false;
        $under = false;
        $pos = 0;
        $re = '/⟦rb:([^|⟧]*)\|([^⟧]*)⟧|<(\/?)(b|u)>|(\R)|([\p{Latin}\p{N}\'’]+)|(\s+)|(.)/us';

        while ($pos < strlen($text) && preg_match($re, $text, $m, PREG_OFFSET_CAPTURE, $pos) === 1) {
            $pos = $m[0][1] + strlen($m[0][0]);

            if (str_starts_with($m[0][0], '⟦rb:')) {
                $base = $m[1][0];
                $rt = $m[2][0];
                $w = max($this->measure($base, self::BASE, $bold), $this->measure($rt, self::RT, false) + 2);
                $atoms[] = ['text' => $base, 'rt' => $rt, 'bold' => $bold, 'u' => $under, 'w' => $w, 'nl' => false];
            } elseif (($m[4][1] ?? -1) >= 0 && $m[4][0] !== '') {
                if ($m[3][0] !== '/') {
                    ${$m[4][0] === 'b' ? 'bold' : 'under'} = true;
                } else {
                    ${$m[4][0] === 'b' ? 'bold' : 'under'} = false;
                }
            } elseif (($m[5][1] ?? -1) >= 0 && $m[5][0] !== '') {
                $atoms[] = ['text' => '', 'rt' => null, 'bold' => false, 'u' => false, 'w' => 0.0, 'nl' => true];
            } elseif (($m[7][1] ?? -1) >= 0 && $m[7][0] !== '') {
                $atoms[] = ['text' => ' ', 'rt' => null, 'bold' => false, 'u' => false, 'w' => self::BASE * 0.3, 'nl' => false];
            } else {
                $chunk = ($m[6][1] ?? -1) >= 0 && $m[6][0] !== '' ? $m[6][0] : (($m[8][1] ?? -1) >= 0 ? $m[8][0] : '');

                if ($chunk !== '') {
                    $atoms[] = ['text' => $chunk, 'rt' => null, 'bold' => $bold, 'u' => $under, 'w' => $this->measure($chunk, self::BASE, $bold), 'nl' => false];
                }
            }
        }

        return $atoms;
    }

    /**
     * @param  list<array{text: string, rt: ?string, bold: bool, u: bool, w: float, nl: bool}>  $atoms
     * @return list<list<array{text: string, rt: ?string, bold: bool, u: bool, w: float, nl: bool}>>
     */
    private function layout(array $atoms): array
    {
        $rows = [[]];
        $used = 0.0;

        foreach ($atoms as $atom) {
            if ($atom['nl']) {
                $rows[] = [];
                $used = 0.0;

                continue;
            }

            $row = &$rows[array_key_last($rows)];
            $isSpace = $atom['text'] === ' ';
            $closing = $atom['text'] !== '' && mb_strpos(self::CLOSING, $atom['text']) !== false;

            if ($isSpace && $row === []) {
                unset($row);

                continue;
            }

            if ($row !== [] && ! $isSpace && ! $closing && $used + $atom['w'] > self::MAXW) {
                while ($row !== [] && $row[array_key_last($row)]['text'] === ' ') {
                    $used -= $row[array_key_last($row)]['w'];
                    array_pop($row);
                }

                unset($row);
                $rows[] = [];
                $used = 0.0;
                $row = &$rows[array_key_last($rows)];
            }

            $row[] = $atom;
            $used += $atom['w'];
            unset($row);
        }

        return array_values(array_filter($rows, static fn (array $r): bool => $r !== [])) ?: [[]];
    }

    private function measure(string $text, float $size, bool $bold): float
    {
        if ($text === '') {
            return 0.0;
        }

        $box = imagettfbbox($size * self::SCALE * 0.75, 0, self::font($bold), $text);

        return $box === false ? $size * mb_strlen($text) : max(abs($box[4] - $box[0]), 1) / self::SCALE + 1.0;
    }

    private function draw(\GdImage $img, string $text, float $size, float $x, float $baseline, int $color, bool $bold): void
    {
        imagettftext($img, $size * self::SCALE * 0.75, 0, (int) round($x * self::SCALE), (int) round($baseline * self::SCALE), $color, self::font($bold), $text);
    }

    private static function font(bool $bold): string
    {
        return dirname(__DIR__, 3).'/resources/fonts/NotoSansSC-'.($bold ? 'Bold' : 'Regular').'.otf';
    }
}
