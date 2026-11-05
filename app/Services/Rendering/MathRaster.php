<?php

declare(strict_types=1);

namespace App\Services\Rendering;

use InvalidArgumentException;

/**
 * Menggambar rumus (subset LaTeX yang sama dengan LatexToOmml) dan teks bercampur rumus menjadi gambar PNG
 * memakai GD + font STIX bawaan aplikasi. Dipakai agar naskah untuk CBT sekolah tidak berisi objek Equation/tabel
 * Word (yang tidak terbaca CBT), melainkan gambar biasa.
 *
 * Kotak (box): ['w' lebar, 'a' tinggi di atas garis dasar, 'd' kedalaman di bawah garis dasar, 'ops' perintah gambar].
 * Koordinat perintah relatif terhadap sudut kiri garis dasar kotak: x ke kanan, y ke bawah (negatif = di atas garis dasar).
 *
 * @phpstan-type Box array{w: float, a: float, d: float, ops: list<array<string, mixed>>}
 */
final class MathRaster
{
    private const SYMBOLS = [
        'alpha' => 'α', 'beta' => 'β', 'gamma' => 'γ', 'delta' => 'δ', 'epsilon' => 'ε', 'varepsilon' => 'ε', 'zeta' => 'ζ', 'eta' => 'η',
        'theta' => 'θ', 'vartheta' => 'ϑ', 'iota' => 'ι', 'kappa' => 'κ', 'lambda' => 'λ', 'mu' => 'μ', 'nu' => 'ν', 'xi' => 'ξ', 'pi' => 'π',
        'rho' => 'ρ', 'sigma' => 'σ', 'tau' => 'τ', 'upsilon' => 'υ', 'phi' => 'φ', 'varphi' => 'φ', 'chi' => 'χ', 'psi' => 'ψ', 'omega' => 'ω',
        'Gamma' => 'Γ', 'Delta' => 'Δ', 'Theta' => 'Θ', 'Lambda' => 'Λ', 'Xi' => 'Ξ', 'Pi' => 'Π', 'Sigma' => 'Σ', 'Phi' => 'Φ', 'Psi' => 'Ψ', 'Omega' => 'Ω',
        'cdot' => '·', 'times' => '×', 'div' => '÷', 'pm' => '±', 'mp' => '∓', 'leq' => '≤', 'le' => '≤', 'geq' => '≥', 'ge' => '≥', 'neq' => '≠', 'ne' => '≠',
        'approx' => '≈', 'equiv' => '≡', 'infty' => '∞', 'circ' => '∘', 'degree' => '°', 'angle' => '∠', 'to' => '→', 'rightarrow' => '→', 'leftarrow' => '←',
        'Rightarrow' => '⇒', 'Leftarrow' => '⇐', 'Leftrightarrow' => '⇔', 'leftrightarrow' => '↔', 'in' => '∈', 'notin' => '∉', 'cup' => '∪', 'cap' => '∩',
        'subset' => '⊂', 'subseteq' => '⊆', 'supset' => '⊃', 'emptyset' => '∅', 'varnothing' => '∅', 'ldots' => '…', 'cdots' => '⋯', 'sim' => '∼', 'parallel' => '∥',
        'perp' => '⊥', 'triangle' => '△', 'mid' => '∣', 'partial' => '∂', 'nabla' => '∇', 'forall' => '∀', 'exists' => '∃', 'because' => '∵', 'therefore' => '∴',
        'backslash' => '\\', 'ast' => '∗', 'propto' => '∝', 'cong' => '≅', 'prime' => '′', 'wedge' => '∧', 'vee' => '∨', 'neg' => '¬',
    ];

    private const NARY = ['sum' => '∑', 'prod' => '∏', 'int' => '∫', 'iint' => '∬', 'iiint' => '∭', 'oint' => '∮', 'bigcup' => '⋃', 'bigcap' => '⋂'];

    private const FUNCS = ['sin', 'cos', 'tan', 'cot', 'sec', 'csc', 'log', 'ln', 'exp', 'det', 'gcd', 'max', 'min', 'arcsin', 'arccos', 'arctan', 'sinh', 'cosh', 'tanh'];

    /** Operator relasi/biner yang diberi spasi di kiri-kanannya. */
    private const SPACED = ['=', '+', '−', '-', '<', '>', '≤', '≥', '≠', '≈', '≡', '×', '÷', '±', '∓', '→', '←', '⇒', '⇐', '⇔', '↔', '∈', '∉', '∪', '∩', '⊂', '⊆', '⊃', '∼', '∥', '⊥', '∝', '≅', '∧', '∨', '·'];

    private const SCALE = 3;

    /** Ukuran dasar huruf (px layar) agar sepadan dengan teks 11 pt Word. */
    private const BASE = 16;

    /** @var list<string> */
    private array $t = [];

    private int $i = 0;

    private float $size = 45.0;

    /**
     * Rumus tunggal menjadi PNG. Mengembalikan lebar/tinggi dalam piksel layar (tanpa faktor skala)
     * dan kedalaman di bawah garis dasar, agar bisa disejajarkan dengan teks.
     *
     * @return array{png: string, w: int, h: int, descent: int}
     */
    public function equation(string $latex): array
    {
        $box = $this->math($latex, self::BASE * self::SCALE);

        return $this->png($box, 3);
    }

    /**
     * Teks bercampur rumus (\( … \)) pada satu baris menjadi kotak.
     *
     * @return array{w: float, a: float, d: float, ops: list<array<string, mixed>>}
     */
    public function line(string $text, float $size, bool $bold = false): array
    {
        $parts = preg_split('/\\\\\((.+?)\\\\\)/us', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$text];
        $boxes = [];

        foreach ($parts as $index => $part) {
            if ($part === '') {
                continue;
            }

            $boxes[] = $index % 2 === 1 ? $this->math($part, $size) : $this->plain($part, $size, $bold);
        }

        return $this->row($boxes);
    }

    /**
     * Tabel menjadi PNG. Tiap sel berisi teks (boleh memuat \( … \)) dan rentang kolom.
     *
     * @param  list<list<array{text: string, span?: int, bold?: bool}>>  $rows
     * @return array{png: string, w: int, h: int, descent: int}
     */
    public function table(array $rows): array
    {
        $size = (float) (self::BASE * self::SCALE);
        $padX = $size * 0.55;
        $padY = $size * 0.35;
        $maxCell = $size * 24;
        $cols = 0;
        $cells = [];

        foreach ($rows as $r => $row) {
            $c = 0;

            foreach ($row as $cell) {
                $span = max(1, (int) ($cell['span'] ?? 1));
                $lines = [];

                foreach (explode("\n", $cell['text']) as $text) {
                    foreach ($this->wrap(trim($text), $size, (bool) ($cell['bold'] ?? false), $maxCell) as $box) {
                        $lines[] = $box;
                    }
                }

                $cells[$r][] = ['col' => $c, 'span' => $span, 'lines' => $lines];
                $c += $span;
            }

            $cols = max($cols, $c);
        }

        if ($cols === 0) {
            throw new InvalidArgumentException('Tabel kosong.');
        }

        $widths = array_fill(0, $cols, $size * 1.5);

        foreach ($cells as $row) {
            foreach ($row as $cell) {
                $w = 0.0;

                foreach ($cell['lines'] as $l) {
                    $w = max($w, $l['w']);
                }

                if ($cell['span'] === 1) {
                    $widths[$cell['col']] = max($widths[$cell['col']], $w + 2 * $padX);
                }
            }
        }

        foreach ($cells as $row) {
            foreach ($row as $cell) {
                if ($cell['span'] > 1) {
                    $have = array_sum(array_slice($widths, $cell['col'], $cell['span']));
                    $need = max(array_map(static fn (array $l): float => $l['w'], $cell['lines'] ?: [['w' => 0.0]])) + 2 * $padX;

                    if ($need > $have) {
                        $widths[$cell['col'] + $cell['span'] - 1] += $need - $have;
                    }
                }
            }
        }

        $ops = [];
        $y = 0.0;
        $th = max(1.0, $size / 15);
        $total = array_sum($widths);
        $lineH = $size * 1.0;

        foreach ($cells as $row) {
            $h = $size * 0.95 + 2 * $padY;

            foreach ($row as $cell) {
                $h = max($h, count($cell['lines']) * ($lineH + $size * 0.12) + 2 * $padY);
            }

            foreach ($row as $cell) {
                $x0 = array_sum(array_slice($widths, 0, $cell['col']));
                $cw = array_sum(array_slice($widths, $cell['col'], $cell['span']));
                $n = count($cell['lines']);
                $block = $n * ($lineH + $size * 0.12);
                $ly = $y + ($h - $block) / 2;

                foreach ($cell['lines'] as $l) {
                    $base = $ly + $lineH * 0.78;
                    $ops = [...$ops, ...$this->placed($l, $x0 + ($cw - $l['w']) / 2, $base)];
                    $ly += $lineH + $size * 0.12;
                }

                $ops[] = ['k' => 'line', 'x1' => $x0, 'y1' => $y, 'x2' => $x0 + $cw, 'y2' => $y, 'th' => $th];
                $ops[] = ['k' => 'line', 'x1' => $x0, 'y1' => $y, 'x2' => $x0, 'y2' => $y + $h, 'th' => $th];
                $ops[] = ['k' => 'line', 'x1' => $x0 + $cw, 'y1' => $y, 'x2' => $x0 + $cw, 'y2' => $y + $h, 'th' => $th];
                $ops[] = ['k' => 'line', 'x1' => $x0, 'y1' => $y + $h, 'x2' => $x0 + $cw, 'y2' => $y + $h, 'th' => $th];
            }

            $y += $h;
        }

        return $this->png(['w' => $total, 'a' => 0.0, 'd' => $y, 'ops' => $ops], 3);
    }

    /**
     * @return list<array{w: float, a: float, d: float, ops: list<array<string, mixed>>}>
     */
    private function wrap(string $text, float $size, bool $bold, float $max): array
    {
        $box = $this->line($text, $size, $bold);

        if ($box['w'] <= $max || str_contains($text, '\\(')) {
            return [$box];
        }

        $lines = [];
        $current = '';

        foreach (preg_split('/\s+/u', $text) ?: [] as $word) {
            $try = $current === '' ? $word : $current.' '.$word;

            if ($current !== '' && $this->line($try, $size, $bold)['w'] > $max) {
                $lines[] = $this->line($current, $size, $bold);
                $current = $word;
            } else {
                $current = $try;
            }
        }

        $lines[] = $this->line($current, $size, $bold);

        return $lines;
    }

    /**
     * Menggambar kotak menjadi PNG (latar putih).
     *
     * @param  array{w: float, a: float, d: float, ops: list<array<string, mixed>>}  $box
     * @return array{png: string, w: int, h: int, descent: int}
     */
    public function png(array $box, int $pad = 2): array
    {
        $pad *= self::SCALE;
        $w = max(1, (int) ceil($box['w']) + 2 * $pad);
        $h = max(1, (int) ceil($box['a'] + $box['d']) + 2 * $pad);
        $img = $this->renderFull($box, $pad, $w, $h);

        ob_start();
        imagepng($img);
        $bytes = (string) ob_get_clean();

        return [
            'png' => $bytes,
            'w' => (int) round($w / self::SCALE),
            'h' => (int) round($h / self::SCALE),
            'descent' => (int) round(($box['d'] + $pad) / self::SCALE),
        ];
    }

    /**
     * @param  array{w: float, a: float, d: float, ops: list<array<string, mixed>>}  $box
     */
    private function renderFull(array $box, int $pad, int $w, int $h): \GdImage
    {
        $img = imagecreatetruecolor(max(1, $w), max(1, $h));

        if ($img === false) {
            throw new InvalidArgumentException('Gagal membuat kanvas gambar.');
        }

        imagealphablending($img, true);
        imageantialias($img, true);
        $white = (int) imagecolorallocate($img, 255, 255, 255);
        $black = (int) imagecolorallocate($img, 0, 0, 0);
        imagefilledrectangle($img, 0, 0, $w, $h, $white);
        $this->paint($img, $box['ops'], $pad, $pad + $box['a'], $black);

        return $img;
    }

    /**
     * @param  list<array<string, mixed>>  $ops
     */
    private function paint(\GdImage $img, array $ops, float $ox, float $oy, int $color): void
    {
        foreach ($ops as $op) {
            if ($op['k'] === 'text') {
                imagettftext($img, $op['s'] * 0.75, 0, (int) round($ox + $op['x']), (int) round($oy + $op['y']), $color, $this->font($op['f']), $op['str']);

                continue;
            }

            imagesetthickness($img, max(1, (int) round($op['th'])));
            imageline($img, (int) round($ox + $op['x1']), (int) round($oy + $op['y1']), (int) round($ox + $op['x2']), (int) round($oy + $op['y2']), $color);
        }

        imagesetthickness($img, 1);
    }

    private function font(string $key): string
    {
        $file = match ($key) {
            'i' => 'STIXGeneralItalic.ttf',
            'b' => 'STIXGeneralBol.ttf',
            'bi' => 'STIXGeneralBolIta.ttf',
            default => 'STIXGeneral.ttf',
        };

        return dirname(__DIR__, 3).'/resources/fonts/'.$file;
    }

    // ───────────────────────── tata letak ─────────────────────────

    /**
     * @return array{w: float, a: float, d: float, ops: list<array<string, mixed>>}
     */
    private function math(string $latex, float $size): array
    {
        $this->t = $this->tokenize($latex);
        $this->i = 0;
        $saved = $this->size;
        $this->size = $size;
        $box = $this->sequence(null);
        $this->size = $saved;

        return $box;
    }

    /**
     * @return array{w: float, a: float, d: float, ops: list<array<string, mixed>>}
     */
    private function plain(string $text, float $size, bool $bold): array
    {
        $boxes = [];

        foreach (mb_str_split($text) as $ch) {
            $boxes[] = $this->glyph($ch, $size, $bold ? 'b' : 'r');
        }

        return $this->row($boxes);
    }

    /**
     * @return array{w: float, a: float, d: float, ops: list<array<string, mixed>>}
     */
    private function glyph(string $ch, float $size, string $font): array
    {
        if ($ch === ' ' || $ch === "\t") {
            return ['w' => $size * 0.28, 'a' => $size * 0.72, 'd' => $size * 0.22, 'ops' => []];
        }

        $bbox = imagettfbbox($size * 0.75, 0, $this->font($font), $ch);
        $w = $bbox === false ? $size * 0.5 : max($bbox[2], $bbox[4]) - min($bbox[0], $bbox[6]);
        $top = $bbox === false ? -$size * 0.7 : min($bbox[5], $bbox[7]);
        $bottom = $bbox === false ? 0 : max($bbox[1], $bbox[3]);
        $w += $size * 0.04;

        return [
            'w' => (float) $w,
            'a' => max($size * 0.68, -$top),
            'd' => max($size * 0.2, $bottom),
            'ops' => [['k' => 'text', 'x' => 0.0, 'y' => 0.0, 's' => $size, 'f' => $font, 'str' => $ch]],
        ];
    }

    /**
     * @param  list<array{w: float, a: float, d: float, ops: list<array<string, mixed>>}>  $boxes
     * @return array{w: float, a: float, d: float, ops: list<array<string, mixed>>}
     */
    private function row(array $boxes): array
    {
        $x = 0.0;
        $a = $this->size * 0.72;
        $d = $this->size * 0.22;
        $ops = [];

        foreach ($boxes as $b) {
            foreach ($b['ops'] as $op) {
                $ops[] = $this->move($op, $x, 0.0);
            }

            $x += $b['w'];
            $a = max($a, $b['a']);
            $d = max($d, $b['d']);
        }

        return ['w' => $x, 'a' => $a, 'd' => $d, 'ops' => $ops];
    }

    /**
     * @param  array<string, mixed>  $op
     * @return array<string, mixed>
     */
    private function move(array $op, float $dx, float $dy): array
    {
        if ($op['k'] === 'text') {
            $op['x'] += $dx;
            $op['y'] += $dy;

            return $op;
        }

        $op['x1'] += $dx;
        $op['x2'] += $dx;
        $op['y1'] += $dy;
        $op['y2'] += $dy;

        return $op;
    }

    /**
     * @param  array{w: float, a: float, d: float, ops: list<array<string, mixed>>}  $box
     * @return list<array<string, mixed>>
     */
    private function placed(array $box, float $dx, float $dy): array
    {
        return array_map(fn (array $op): array => $this->move($op, $dx, $dy), $box['ops']);
    }

    /**
     * @return array{w: float, a: float, d: float, ops: list<array<string, mixed>>}
     */
    private function empty(float $w = 0.0): array
    {
        return ['w' => $w, 'a' => $this->size * 0.72, 'd' => $this->size * 0.22, 'ops' => []];
    }

    // ───────────────────────── parser ─────────────────────────

    /**
     * @return list<string>
     */
    private function tokenize(string $s): array
    {
        preg_match_all('/\\\\[A-Za-z]+|\\\\.|\s+|./us', $s, $m);

        return array_map(static fn (string $x): string => ctype_space($x) ? ' ' : $x, $m[0]);
    }

    private function peek(): ?string
    {
        while (($this->t[$this->i] ?? null) === ' ') {
            $this->i++;
        }

        return $this->t[$this->i] ?? null;
    }

    /**
     * @return array{w: float, a: float, d: float, ops: list<array<string, mixed>>}
     */
    private function sequence(?string $stop): array
    {
        $boxes = [];

        while (($tok = $this->peek()) !== null) {
            if ($stop !== null && ($tok === $stop || ($stop === 'ROW' && in_array($tok, ['&', '\\\\', '\\end'], true)))) {
                break;
            }

            if ($stop === '}' && $tok === '}') {
                break;
            }

            $base = in_array($tok, ['^', '_'], true) ? $this->empty(0.0) : $this->atom();
            $boxes[] = $this->scripted($base);
        }

        return $this->row($boxes);
    }

    /**
     * @param  array{w: float, a: float, d: float, ops: list<array<string, mixed>>}  $base
     * @return array{w: float, a: float, d: float, ops: list<array<string, mixed>>}
     */
    private function scripted(array $base): array
    {
        $sub = null;
        $sup = null;

        while (in_array($this->peek(), ['^', '_'], true)) {
            $op = $this->t[$this->i++];
            $arg = $this->scriptArg();

            if ($op === '^') {
                $sup = $arg;
            } else {
                $sub = $arg;
            }
        }

        if ($sub === null && $sup === null) {
            return $base;
        }

        $ops = $base['ops'];
        $w = $base['w'];
        $a = $base['a'];
        $d = $base['d'];
        $sw = 0.0;

        if ($sup !== null) {
            $up = max($this->size * 0.4, $base['a'] - $this->size * 0.3);
            $ops = [...$ops, ...$this->placed($sup, $w, -$up)];
            $a = max($a, $up + $sup['a']);
            $sw = max($sw, $sup['w']);
        }

        if ($sub !== null) {
            $down = $this->size * 0.2;
            $ops = [...$ops, ...$this->placed($sub, $w, $down)];
            $d = max($d, $down + $sub['d']);
            $sw = max($sw, $sub['w']);
        }

        return ['w' => $w + $sw + $this->size * 0.03, 'a' => $a, 'd' => $d, 'ops' => $ops];
    }

    /**
     * @return array{w: float, a: float, d: float, ops: list<array<string, mixed>>}
     */
    private function scriptArg(): array
    {
        $saved = $this->size;
        $this->size = $saved * 0.7;
        $box = $this->argument();
        $this->size = $saved;

        return $box;
    }

    /**
     * @return array{w: float, a: float, d: float, ops: list<array<string, mixed>>}
     */
    private function argument(): array
    {
        if ($this->peek() === '{') {
            $this->i++;
            $inner = $this->sequence('}');
            $this->i++;

            return $inner;
        }

        return $this->atom();
    }

    /**
     * @return array{w: float, a: float, d: float, ops: list<array<string, mixed>>}
     */
    private function atom(): array
    {
        $this->peek();
        $tok = $this->t[$this->i++] ?? '';

        if ($tok === '{') {
            $this->i--;

            return $this->argument();
        }

        if ($tok === '' || $tok === '\\\\' || $tok === '&' || $tok === '}') {
            return $this->empty();
        }

        if ($tok[0] === '\\' && strlen($tok) > 1) {
            return $this->command(substr($tok, 1));
        }

        return $this->char($tok);
    }

    /**
     * @return array{w: float, a: float, d: float, ops: list<array<string, mixed>>}
     */
    private function char(string $ch): array
    {
        if ($ch === '-') {
            $ch = '−';
        }

        if (in_array($ch, self::SPACED, true)) {
            $g = $this->glyph($ch, $this->size, 'r');
            $sp = $this->size * 0.2;

            return $this->row([$this->empty($sp), $g, $this->empty($sp)]);
        }

        if (preg_match('/^\p{L}$/u', $ch) === 1) {
            return $this->glyph($ch, $this->size, 'i');
        }

        return $this->glyph($ch, $this->size, 'r');
    }

    private function symbol(string $ch, bool $greekLower): array
    {
        if (in_array($ch, self::SPACED, true)) {
            return $this->char($ch);
        }

        return $this->glyph($ch, $this->size, $greekLower ? 'i' : 'r');
    }

    private function rawGroup(): string
    {
        if ($this->peek() !== '{') {
            return $this->t[$this->i++] ?? '';
        }

        $this->i++;
        $depth = 1;
        $buf = '';

        while (($x = $this->t[$this->i++] ?? null) !== null) {
            if ($x === '{') {
                $depth++;
            } elseif ($x === '}' && --$depth === 0) {
                break;
            }

            $buf .= $x[0] === '\\' && strlen($x) > 1 ? (self::SYMBOLS[substr($x, 1)] ?? (strlen($x) === 2 ? substr($x, 1) : '')) : $x;
        }

        return $buf;
    }

    /**
     * @return array{w: float, a: float, d: float, ops: list<array<string, mixed>>}
     */
    private function command(string $name): array
    {
        $s = $this->size;

        if (in_array($name, ['frac', 'dfrac', 'tfrac'], true)) {
            return $this->fraction($this->argument(), $this->argument());
        }

        if ($name === 'binom') {
            $inner = $this->fraction($this->argument(), $this->argument(), false);

            return $this->fenced('(', ')', $inner);
        }

        if ($name === 'sqrt') {
            $deg = null;

            if ($this->peek() === '[') {
                $this->i++;
                $boxes = [];

                while (($x = $this->peek()) !== null && $x !== ']') {
                    $boxes[] = $this->atom();
                }

                $this->i++;
                $saved = $this->size;
                $this->size = $saved * 0.6;
                $deg = $this->row($boxes);
                $this->size = $saved;
            }

            return $this->radical($this->argument(), $deg);
        }

        if ($name === 'left') {
            $begin = $this->delimiter();
            $inner = $this->sequence('\\right');

            if ($this->peek() === '\\right') {
                $this->i++;
            }

            return $this->fenced($begin, $this->delimiter(), $inner);
        }

        if (in_array($name, ['text', 'mathrm', 'textrm', 'mathbf', 'operatorname', 'mbox'], true)) {
            $font = $name === 'mathbf' ? 'b' : 'r';

            return $this->row(array_map(fn (string $c): array => $this->glyph($c, $this->size, $font), mb_str_split($this->rawGroup())));
        }

        if (in_array($name, ['bar', 'overline', 'vec', 'hat', 'dot', 'tilde'], true)) {
            return $this->accent($name, $this->argument());
        }

        if ($name === 'underline') {
            $b = $this->argument();
            $b['ops'][] = ['k' => 'line', 'x1' => 0.0, 'y1' => $b['d'] + 2, 'x2' => $b['w'], 'y2' => $b['d'] + 2, 'th' => max(1, $s / 20)];
            $b['d'] += 4;

            return $b;
        }

        if (isset(self::NARY[$name])) {
            return $this->nary($name, self::NARY[$name]);
        }

        if ($name === 'lim') {
            $this->skipLimits();
            $base = $this->plain('lim', $s, false);

            if ($this->peek() === '_') {
                $this->i++;
                $sub = $this->scriptArg();
                $w = max($base['w'], $sub['w']);
                $ops = [...$this->placed($base, ($w - $base['w']) / 2, 0), ...$this->placed($sub, ($w - $sub['w']) / 2, $base['d'] + $sub['a'] * 0.9)];

                return ['w' => $w, 'a' => $base['a'], 'd' => $base['d'] + $sub['a'] * 0.9 + $sub['d'], 'ops' => $ops];
            }

            return $base;
        }

        if (in_array($name, self::FUNCS, true)) {
            $fn = $this->scripted($this->plain($name, $s, false));

            return $this->row([$fn, $this->empty($s * 0.12)]);
        }

        if ($name === 'begin') {
            return $this->environment();
        }

        if (in_array($name, ['limits', 'nolimits', 'displaystyle', 'textstyle', 'right', '!'], true)) {
            return $this->empty(0.0);
        }

        if (in_array($name, ['quad', 'qquad'], true)) {
            return $this->empty($s * ($name === 'quad' ? 1.0 : 2.0));
        }

        if (in_array($name, [',', ';', ':', ' '], true)) {
            return $this->empty($s * 0.25);
        }

        if (isset(self::SYMBOLS[$name])) {
            $sym = self::SYMBOLS[$name];

            return $this->symbol($sym, preg_match('/^\p{Ll}$/u', $sym) === 1);
        }

        if (strlen($name) === 1 && ! ctype_alpha($name)) {
            return $this->glyph($name, $s, 'r');
        }

        return $this->plain($name, $s, false);
    }

    private function skipLimits(): void
    {
        if (in_array($this->peek(), ['\\limits', '\\nolimits'], true)) {
            $this->i++;
        }
    }

    /**
     * @param  array{w: float, a: float, d: float, ops: list<array<string, mixed>>}  $num
     * @param  array{w: float, a: float, d: float, ops: list<array<string, mixed>>}  $den
     * @return array{w: float, a: float, d: float, ops: list<array<string, mixed>>}
     */
    private function fraction(array $num, array $den, bool $bar = true): array
    {
        $s = $this->size;
        $axis = $s * 0.25;
        $gap = $s * 0.12;
        $w = max($num['w'], $den['w']) + $s * 0.2;
        $ops = [
            ...$this->placed($num, ($w - $num['w']) / 2, -($axis + $gap + $num['d'])),
            ...$this->placed($den, ($w - $den['w']) / 2, -$axis + $gap + $den['a']),
        ];

        if ($bar) {
            $ops[] = ['k' => 'line', 'x1' => $s * 0.05, 'y1' => -$axis, 'x2' => $w - $s * 0.05, 'y2' => -$axis, 'th' => max(1, $s / 18)];
        }

        return [
            'w' => $w,
            'a' => $axis + $gap + $num['d'] + $num['a'],
            'd' => max($s * 0.22, $den['a'] + $den['d'] + $gap - $axis),
            'ops' => $ops,
        ];
    }

    /**
     * @param  array{w: float, a: float, d: float, ops: list<array<string, mixed>>}  $inner
     * @param  array{w: float, a: float, d: float, ops: list<array<string, mixed>>}|null  $deg
     * @return array{w: float, a: float, d: float, ops: list<array<string, mixed>>}
     */
    private function radical(array $inner, ?array $deg): array
    {
        $s = $this->size;
        $top = -($inner['a'] + $s * 0.14);
        $bottom = $inner['d'];
        $h = $bottom - $top;
        $rw = $s * 0.55;
        $th = max(1, $s / 18);
        $ox = $deg !== null ? max(0.0, $deg['w'] - $rw * 0.35) : 0.0;
        $ops = [
            ['k' => 'line', 'x1' => $ox, 'y1' => $top + $h * 0.58, 'x2' => $ox + $rw * 0.2, 'y2' => $top + $h * 0.5, 'th' => $th],
            ['k' => 'line', 'x1' => $ox + $rw * 0.2, 'y1' => $top + $h * 0.5, 'x2' => $ox + $rw * 0.5, 'y2' => $bottom, 'th' => $th * 1.6],
            ['k' => 'line', 'x1' => $ox + $rw * 0.5, 'y1' => $bottom, 'x2' => $ox + $rw, 'y2' => $top, 'th' => $th],
            ['k' => 'line', 'x1' => $ox + $rw, 'y1' => $top, 'x2' => $ox + $rw + $inner['w'] + $s * 0.1, 'y2' => $top, 'th' => $th],
            ...$this->placed($inner, $ox + $rw + $s * 0.05, 0),
        ];

        if ($deg !== null) {
            $ops = [...$ops, ...$this->placed($deg, 0, $top + $h * 0.35)];
        }

        return ['w' => $ox + $rw + $inner['w'] + $s * 0.12, 'a' => -$top + $th, 'd' => $bottom, 'ops' => $ops];
    }

    private function delimiter(): string
    {
        $this->peek();
        $tok = $this->t[$this->i++] ?? '.';

        return match ($tok) {
            '.' => '',
            '\\{' => '{',
            '\\}' => '}',
            '\\|' => '‖',
            '\\lfloor' => '⌊',
            '\\rfloor' => '⌋',
            '\\lceil' => '⌈',
            '\\rceil' => '⌉',
            '\\langle' => '⟨',
            '\\rangle' => '⟩',
            default => $tok[0] === '\\' ? '' : $tok,
        };
    }

    /**
     * @param  array{w: float, a: float, d: float, ops: list<array<string, mixed>>}  $inner
     * @return array{w: float, a: float, d: float, ops: list<array<string, mixed>>}
     */
    private function fenced(string $open, string $close, array $inner): array
    {
        $s = $this->size;
        $h = $inner['a'] + $inner['d'];
        $tall = $h > $s * 1.15;
        $left = $this->bracket($open, $inner, $tall);
        $right = $this->bracket($close, $inner, $tall);
        $ops = [...$left['ops'], ...$this->placed($inner, $left['w'], 0), ...$this->placed($right, $left['w'] + $inner['w'], 0)];

        return [
            'w' => $left['w'] + $inner['w'] + $right['w'],
            'a' => max($inner['a'], $left['a']),
            'd' => max($inner['d'], $left['d']),
            'ops' => $ops,
        ];
    }

    /**
     * @param  array{w: float, a: float, d: float, ops: list<array<string, mixed>>}  $inner
     * @return array{w: float, a: float, d: float, ops: list<array<string, mixed>>}
     */
    private function bracket(string $ch, array $inner, bool $tall): array
    {
        $s = $this->size;

        if ($ch === '') {
            return $this->empty(0.0);
        }

        if (! $tall) {
            return $this->glyph($ch, $s, 'r');
        }

        $top = -$inner['a'] - $s * 0.05;
        $bottom = $inner['d'] + $s * 0.05;
        $th = max(1, $s / 18);
        $w = $s * 0.3;

        if (in_array($ch, ['|', '∣'], true)) {
            return ['w' => $w, 'a' => -$top, 'd' => $bottom, 'ops' => [['k' => 'line', 'x1' => $w / 2, 'y1' => $top, 'x2' => $w / 2, 'y2' => $bottom, 'th' => $th]]];
        }

        $isOpen = in_array($ch, ['(', '[', '{', '⌊', '⌈', '⟨'], true);
        $xa = $isOpen ? $w * 0.85 : $w * 0.15;
        $xb = $isOpen ? $w * 0.35 : $w * 0.65;
        $mid = ($top + $bottom) / 2;
        $ops = [];

        if (in_array($ch, ['(', ')'], true)) {
            $steps = 8;
            $prev = null;

            for ($k = 0; $k <= $steps; $k++) {
                $f = $k / $steps;
                $x = $xa + ($xb - $xa) * (1 - (2 * $f - 1) ** 2);
                $p = [$x, $top + ($bottom - $top) * $f];

                if ($prev !== null) {
                    $ops[] = ['k' => 'line', 'x1' => $prev[0], 'y1' => $prev[1], 'x2' => $p[0], 'y2' => $p[1], 'th' => $th];
                }

                $prev = $p;
            }
        } elseif ($ch === '{' || $ch === '}') {
            $hh = $bottom - $top;
            $far = $isOpen ? $w * 0.9 : $w * 0.1;
            $body = $isOpen ? $w * 0.5 : $w * 0.5;
            $tip = $isOpen ? $w * 0.1 : $w * 0.9;
            $pts = [[$far, $top], [$body, $top + $hh * 0.1], [$body, $mid - $hh * 0.08], [$tip, $mid], [$body, $mid + $hh * 0.08], [$body, $bottom - $hh * 0.1], [$far, $bottom]];

            for ($k = 0; $k < count($pts) - 1; $k++) {
                $ops[] = ['k' => 'line', 'x1' => $pts[$k][0], 'y1' => $pts[$k][1], 'x2' => $pts[$k + 1][0], 'y2' => $pts[$k + 1][1], 'th' => $th];
            }
        } else {
            $ops[] = ['k' => 'line', 'x1' => $xb, 'y1' => $top, 'x2' => $xb, 'y2' => $bottom, 'th' => $th];

            if (in_array($ch, ['[', ']', '⌊', '⌋', '⌈', '⌉'], true)) {
                $dir = $isOpen ? 1 : -1;
                $arm = $w * 0.4 * $dir;

                if (! in_array($ch, ['⌊', '⌋'], true)) {
                    $ops[] = ['k' => 'line', 'x1' => $xb, 'y1' => $top, 'x2' => $xb + $arm, 'y2' => $top, 'th' => $th];
                }

                if (! in_array($ch, ['⌈', '⌉'], true)) {
                    $ops[] = ['k' => 'line', 'x1' => $xb, 'y1' => $bottom, 'x2' => $xb + $arm, 'y2' => $bottom, 'th' => $th];
                }
            }
        }

        return ['w' => $w, 'a' => -$top, 'd' => $bottom, 'ops' => $ops];
    }

    /**
     * @param  array{w: float, a: float, d: float, ops: list<array<string, mixed>>}  $b
     * @return array{w: float, a: float, d: float, ops: list<array<string, mixed>>}
     */
    private function accent(string $name, array $b): array
    {
        $s = $this->size;
        $y = -($b['a'] + $s * 0.1);
        $th = max(1, $s / 20);

        if (in_array($name, ['bar', 'overline'], true)) {
            $b['ops'][] = ['k' => 'line', 'x1' => 0.0, 'y1' => $y, 'x2' => $b['w'], 'y2' => $y, 'th' => $th];
        } elseif ($name === 'vec') {
            $b['ops'][] = ['k' => 'line', 'x1' => 0.0, 'y1' => $y, 'x2' => $b['w'], 'y2' => $y, 'th' => $th];
            $b['ops'][] = ['k' => 'line', 'x1' => $b['w'], 'y1' => $y, 'x2' => $b['w'] - $s * 0.18, 'y2' => $y - $s * 0.1, 'th' => $th];
            $b['ops'][] = ['k' => 'line', 'x1' => $b['w'], 'y1' => $y, 'x2' => $b['w'] - $s * 0.18, 'y2' => $y + $s * 0.1, 'th' => $th];
        } else {
            $mark = match ($name) {
                'hat' => '^',
                'tilde' => '~',
                default => '˙',
            };
            $g = $this->glyph($mark, $s, 'r');
            $b['ops'] = [...$b['ops'], ...$this->placed($g, max(0.0, ($b['w'] - $g['w']) / 2), -($b['a'] - $s * 0.2))];
        }

        $b['a'] += $s * 0.2;

        return $b;
    }

    /**
     * @return array{w: float, a: float, d: float, ops: list<array<string, mixed>>}
     */
    private function nary(string $name, string $chr): array
    {
        $this->skipLimits();
        $s = $this->size;
        $isInt = in_array($name, ['int', 'iint', 'iiint', 'oint'], true);
        $sub = null;
        $sup = null;

        while (in_array($this->peek(), ['^', '_'], true)) {
            $op = $this->t[$this->i++];
            $arg = $this->scriptArg();

            if ($op === '^') {
                $sup = $arg;
            } else {
                $sub = $arg;
            }
        }

        $big = $this->glyph($chr, $s * 1.5, 'r');
        $shift = $s * 0.18;
        $big['ops'] = array_map(fn (array $op): array => $this->move($op, 0, $shift), $big['ops']);
        $big['a'] = max($big['a'] - $shift, $s * 0.9);
        $big['d'] = $big['d'] + $shift;
        $ops = [];
        $w = $big['w'];

        if ($isInt) {
            $ops = $big['ops'];
            $a = $big['a'];
            $d = $big['d'];
            $sw = 0.0;

            if ($sup !== null) {
                $ops = [...$ops, ...$this->placed($sup, $w + $s * 0.28, -($big['a'] - $sup['a'] * 0.5))];
                $a = max($a, $big['a'] + $sup['a'] * 0.4);
                $sw = max($sw, $sup['w']);
            }

            if ($sub !== null) {
                $ops = [...$ops, ...$this->placed($sub, $w - $s * 0.1, $big['d'] - $s * 0.05)];
                $d = max($d, $big['d'] + $sub['d']);
                $sw = max($sw, $sub['w']);
            }

            $head = ['w' => $w + $s * 0.28 + $sw, 'a' => $a, 'd' => $d, 'ops' => $ops];
        } else {
            $cw = max($w, $sub['w'] ?? 0.0, $sup['w'] ?? 0.0);
            $ops = $this->placed($big, ($cw - $w) / 2, 0);
            $a = $big['a'];
            $d = $big['d'];

            if ($sup !== null) {
                $ops = [...$ops, ...$this->placed($sup, ($cw - $sup['w']) / 2, -($big['a'] + $sup['d'] + $s * 0.04))];
                $a = $big['a'] + $sup['a'] + $sup['d'] + $s * 0.04;
            }

            if ($sub !== null) {
                $ops = [...$ops, ...$this->placed($sub, ($cw - $sub['w']) / 2, $big['d'] + $sub['a'] + $s * 0.04)];
                $d = $big['d'] + $sub['a'] + $sub['d'] + $s * 0.04;
            }

            $head = ['w' => $cw + $s * 0.08, 'a' => $a, 'd' => $d, 'ops' => $ops];
        }

        $body = $this->scripted($this->atom());

        return $this->row([$head, $body]);
    }

    /**
     * @return array{w: float, a: float, d: float, ops: list<array<string, mixed>>}
     */
    private function environment(): array
    {
        $env = $this->rawGroup();
        $rows = [[]];

        while ($this->peek() !== null && $this->peek() !== '\\end') {
            $rows[array_key_last($rows)][] = $this->sequence('ROW');
            $sep = $this->peek();

            if ($sep === '&') {
                $this->i++;
            } elseif ($sep === '\\\\') {
                $this->i++;
                $rows[] = [];
            }
        }

        if ($this->peek() === '\\end') {
            $this->i++;
            $this->rawGroup();
        }

        $rows = array_values(array_filter($rows, static fn (array $r): bool => $r !== []));

        if ($rows === []) {
            return $this->empty();
        }

        $s = $this->size;
        $cols = max(array_map('count', $rows));
        $widths = array_fill(0, $cols, 0.0);

        foreach ($rows as $r) {
            foreach ($r as $c => $cell) {
                $widths[$c] = max($widths[$c], $cell['w']);
            }
        }

        $gapX = $s * 0.6;
        $gapY = $s * 0.25;
        $y = 0.0;
        $ops = [];
        $totalW = array_sum($widths) + $gapX * ($cols - 1);
        $rowBoxes = [];

        foreach ($rows as $r) {
            $a = $s * 0.72;
            $d = $s * 0.22;

            foreach ($r as $cell) {
                $a = max($a, $cell['a']);
                $d = max($d, $cell['d']);
            }

            $rowBoxes[] = [$y + $a, $a, $d, $r];
            $y += $a + $d + $gapY;
        }

        $height = $y - $gapY;
        $mid = $height / 2;

        foreach ($rowBoxes as [$base, , , $r]) {
            $x = 0.0;
            $alignLeft = in_array($env, ['aligned', 'align', 'cases'], true);

            foreach ($r as $c => $cell) {
                $dx = $alignLeft ? $x : $x + ($widths[$c] - $cell['w']) / 2;
                $ops = [...$ops, ...$this->placed($cell, $dx, $base - $mid)];
                $x += $widths[$c] + $gapX;
            }
        }

        $grid = ['w' => $totalW, 'a' => $mid + $s * 0.25, 'd' => $height - $mid - $s * 0.25, 'ops' => $ops];
        $grid['d'] = max($grid['d'], $s * 0.2);

        return match ($env) {
            'pmatrix' => $this->fenced('(', ')', $grid),
            'bmatrix' => $this->fenced('[', ']', $grid),
            'vmatrix' => $this->fenced('|', '|', $grid),
            'cases' => $this->fenced('{', '', $grid),
            default => $grid,
        };
    }
}
