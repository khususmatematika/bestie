<?php

declare(strict_types=1);

namespace App\Services\Documents;

/**
 * Mengubah subset LaTeX (yang dihasilkan pembaca DOCX dan yang lazim diketik guru) menjadi OMML (Office Math),
 * sehingga rumus pada naskah ekspor tampil sebagai Equation Word asli. Perintah yang tidak dikenal
 * diteruskan sebagai teks agar tidak ada isi yang hilang.
 */
final class LatexToOmml
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

    private const ACCENTS = ['bar' => '̅', 'vec' => '⃗', 'hat' => '̂', 'dot' => '̇', 'tilde' => '̃'];

    /** @var list<string> */
    private array $t = [];

    private int $i = 0;

    /** Mengembalikan isi <m:oMath> (tanpa pembungkus). */
    public function convert(string $latex): string
    {
        $this->t = $this->tokenize($latex);
        $this->i = 0;

        return $this->sequence(null);
    }

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

    private function sequence(?string $stop): string
    {
        $out = '';

        while (($tok = $this->peek()) !== null) {
            if ($stop !== null && ($tok === $stop || ($stop === '\\right' && $tok === '\\right') || ($stop === 'END' && $tok === '\\end'))) {
                break;
            }

            if ($stop === '}' && $tok === '}') {
                break;
            }

            if (in_array($tok, ['&', '\\\\'], true) && $stop === 'ROW') {
                break;
            }

            if ($stop === 'ROW' && $tok === '\\end') {
                break;
            }

            $out .= $this->scripted($this->atom());
        }

        return $out;
    }

    /** Menambahkan ^ dan _ yang mengikuti sebuah atom. */
    private function scripted(string $base): string
    {
        $sub = null;
        $sup = null;

        while (in_array($this->peek(), ['^', '_'], true)) {
            $op = $this->t[$this->i++];
            $arg = $this->argument();

            if ($op === '^') {
                $sup = $arg;
            } else {
                $sub = $arg;
            }
        }

        if ($sub === null && $sup === null) {
            return $base;
        }

        $e = '<m:e>'.$base.'</m:e>';

        return match (true) {
            $sub !== null && $sup !== null => '<m:sSubSup>'.$e.'<m:sub>'.$sub.'</m:sub><m:sup>'.$sup.'</m:sup></m:sSubSup>',
            $sup !== null => '<m:sSup>'.$e.'<m:sup>'.$sup.'</m:sup></m:sSup>',
            default => '<m:sSub>'.$e.'<m:sub>'.$sub.'</m:sub></m:sSub>',
        };
    }

    /** Argumen: grup {…} atau satu atom. */
    private function argument(): string
    {
        if ($this->peek() === '{') {
            $this->i++;
            $inner = $this->sequence('}');
            $this->i++;

            return $inner;
        }

        return $this->atom();
    }

    private function atom(): string
    {
        $this->peek();
        $tok = $this->t[$this->i++] ?? '';

        if ($tok === '{') {
            $this->i--;

            return $this->argument();
        }

        if ($tok === '\\\\' || $tok === '&' || $tok === '}') {
            return '';
        }

        if ($tok[0] === '\\' && strlen($tok) > 1) {
            return $this->command(substr($tok, 1));
        }

        return $this->run($tok);
    }

    private function run(string $text, string $style = ''): string
    {
        if ($text === '') {
            return '';
        }

        $props = match ($style) {
            'text' => '<m:rPr><m:nor/></m:rPr>',
            'rm' => '<m:rPr><m:sty m:val="p"/></m:rPr>',
            default => '',
        };

        return '<m:r>'.$props.'<m:t xml:space="preserve">'.htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</m:t></m:r>';
    }

    private function command(string $name): string
    {
        if (in_array($name, ['frac', 'dfrac', 'tfrac'], true)) {
            return '<m:f><m:num>'.$this->argument().'</m:num><m:den>'.$this->argument().'</m:den></m:f>';
        }

        if ($name === 'binom') {
            return '<m:d><m:dPr><m:begChr m:val="("/><m:endChr m:val=")"/></m:dPr><m:e><m:f><m:fPr><m:type m:val="noBar"/></m:fPr><m:num>'.$this->argument().'</m:num><m:den>'.$this->argument().'</m:den></m:f></m:e></m:d>';
        }

        if ($name === 'sqrt') {
            $deg = '';

            if ($this->peek() === '[') {
                $this->i++;
                $buf = '';

                while (($x = $this->peek()) !== null && $x !== ']') {
                    $buf .= $this->scripted($this->atom());
                }

                $this->i++;
                $deg = $buf;
            }

            return '<m:rad><m:radPr>'.($deg === '' ? '<m:degHide m:val="1"/>' : '').'</m:radPr><m:deg>'.$deg.'</m:deg><m:e>'.$this->argument().'</m:e></m:rad>';
        }

        if ($name === 'left') {
            return $this->delimited();
        }

        if (in_array($name, ['text', 'mathrm', 'textrm', 'mathbf', 'operatorname', 'mbox'], true)) {
            return $this->run($this->rawGroup(), $name === 'text' || $name === 'mbox' ? 'text' : 'rm');
        }

        if (isset(self::ACCENTS[$name])) {
            return '<m:acc><m:accPr><m:chr m:val="'.self::ACCENTS[$name].'"/></m:accPr><m:e>'.$this->argument().'</m:e></m:acc>';
        }

        if ($name === 'overline') {
            return '<m:bar><m:barPr><m:pos m:val="top"/></m:barPr><m:e>'.$this->argument().'</m:e></m:bar>';
        }

        if ($name === 'underline') {
            return '<m:bar><m:barPr><m:pos m:val="bot"/></m:barPr><m:e>'.$this->argument().'</m:e></m:bar>';
        }

        if (isset(self::NARY[$name])) {
            return $this->nary(self::NARY[$name]);
        }

        if ($name === 'lim') {
            $this->skipLimits();
            $sub = null;

            if ($this->peek() === '_') {
                $this->i++;
                $sub = $this->argument();
            }

            $base = $this->run('lim', 'rm');

            return $sub === null ? $base : '<m:limLow><m:e>'.$base.'</m:e><m:lim>'.$sub.'</m:lim></m:limLow>';
        }

        if (in_array($name, self::FUNCS, true)) {
            return '<m:func><m:fName>'.$this->scripted($this->run($name, 'rm')).'</m:fName><m:e>'.$this->scripted($this->atom()).'</m:e></m:func>';
        }

        if ($name === 'begin') {
            return $this->environment();
        }

        if (in_array($name, ['limits', 'nolimits', 'displaystyle', 'textstyle', 'right', 'quad', 'qquad', ',', ';', '!', ':'], true)) {
            return in_array($name, ['quad', 'qquad', ',', ';', ':'], true) ? $this->run(' ') : '';
        }

        if ($name === ' ') {
            return $this->run(' ');
        }

        if (isset(self::SYMBOLS[$name])) {
            return $this->run(self::SYMBOLS[$name]);
        }

        if (strlen($name) === 1 && ! ctype_alpha($name)) {
            return $this->run($name);
        }

        return $this->run($name, 'rm');
    }

    private function skipLimits(): void
    {
        if (in_array($this->peek(), ['\\limits', '\\nolimits'], true)) {
            $this->i++;
        }
    }

    /** Teks mentah di dalam {…} (untuk \text, \mathrm). */
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

    private function nary(string $chr): string
    {
        $this->skipLimits();
        $sub = '';
        $sup = '';

        while (in_array($this->peek(), ['^', '_'], true)) {
            $op = $this->t[$this->i++];
            $arg = $this->argument();

            if ($op === '^') {
                $sup = $arg;
            } else {
                $sub = $arg;
            }
        }

        $props = '<m:naryPr><m:chr m:val="'.$chr.'"/>'.($sub === '' ? '<m:subHide m:val="1"/>' : '').($sup === '' ? '<m:supHide m:val="1"/>' : '').'</m:naryPr>';

        return '<m:nary>'.$props.'<m:sub>'.$sub.'</m:sub><m:sup>'.$sup.'</m:sup><m:e>'.$this->scripted($this->atom()).'</m:e></m:nary>';
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

    private function delimited(): string
    {
        $begin = $this->delimiter();
        $inner = $this->sequence('\\right');

        if ($this->peek() === '\\right') {
            $this->i++;
        }

        $end = $this->delimiter();

        return '<m:d><m:dPr><m:begChr m:val="'.htmlspecialchars($begin, ENT_XML1).'"/><m:endChr m:val="'.htmlspecialchars($end, ENT_XML1).'"/></m:dPr><m:e>'.$inner.'</m:e></m:d>';
    }

    private function environment(): string
    {
        $env = $this->rawGroup();
        $rows = [[]];

        while ($this->peek() !== null && $this->peek() !== '\\end') {
            $cell = $this->sequence('ROW');
            $rows[array_key_last($rows)][] = $cell;
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

        if ($env === 'aligned' || $env === 'align' || $env === 'cases') {
            $eq = '<m:eqArr>'.implode('', array_map(static fn (array $r): string => '<m:e>'.implode('', $r).'</m:e>', $rows)).'</m:eqArr>';

            return $env === 'cases' ? '<m:d><m:dPr><m:begChr m:val="{"/><m:endChr m:val=""/></m:dPr><m:e>'.$eq.'</m:e></m:d>' : $eq;
        }

        $matrix = '<m:m>'.implode('', array_map(static fn (array $r): string => '<m:mr>'.implode('', array_map(static fn (string $c): string => '<m:e>'.$c.'</m:e>', $r)).'</m:mr>', $rows)).'</m:m>';
        $wrap = match ($env) {
            'pmatrix' => ['(', ')'],
            'bmatrix' => ['[', ']'],
            'vmatrix' => ['|', '|'],
            default => null,
        };

        return $wrap === null ? $matrix : '<m:d><m:dPr><m:begChr m:val="'.$wrap[0].'"/><m:endChr m:val="'.$wrap[1].'"/></m:dPr><m:e>'.$matrix.'</m:e></m:d>';
    }
}
