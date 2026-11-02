<?php

declare(strict_types=1);

namespace App\Services\Documents;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use InvalidArgumentException;
use ZipArchive;

/**
 * Membaca DOCX sebagai deretan blok paragraf lengkap dengan struktur yang dipakai guru di Word:
 * jenis daftar bernomor (angka, huruf, bullet), warna teks per potongan (kunci jawaban ditandai merah),
 * cetak tebal, rumus (OMML) yang dilinearkan menjadi teks, serta gambar yang tertanam.
 * Penomoran otomatis Word tidak ada di teks mentah, jadi jenis daftarnya dibaca dari numbering.xml.
 */
final class DocxStructureReader
{
    private const NS_W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    private const NS_M = 'http://schemas.openxmlformats.org/officeDocument/2006/math';

    private const NS_R = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    private const NS_A = 'http://schemas.openxmlformats.org/drawingml/2006/main';

    private const NS_V = 'urn:schemas-microsoft-com:vml';

    private const MAX_XML_BYTES = 30 * 1024 * 1024;

    /** @var array<string, string> */
    private array $relationships = [];

    /** @var array<int, array<int, string>> */
    private array $formats = [];

    /**
     * @return list<array{text: string, bold: bool, list: array{num_id: int, level: int, fmt: string}|null, runs: list<array{t: string, red: bool}>, images: list<string>}>
     */
    public function read(string $path): array
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new InvalidArgumentException('Berkas DOCX tidak dapat dibuka. Pastikan berkas tidak rusak.');
        }

        try {
            $document = $this->part($zip, 'word/document.xml', true);
            $numbering = $this->part($zip, 'word/numbering.xml', false);
            $rels = $this->part($zip, 'word/_rels/document.xml.rels', false);
        } finally {
            $zip->close();
        }

        $this->relationships = [];

        if ($rels !== null) {
            foreach ($rels->getElementsByTagName('Relationship') as $rel) {
                if ($rel instanceof DOMElement) {
                    $this->relationships[$rel->getAttribute('Id')] = 'word/'.ltrim($rel->getAttribute('Target'), '/');
                }
            }
        }

        $this->formats = $numbering !== null ? $this->numberingFormats($numbering) : [];

        $xpath = $this->xpath($document);
        $blocks = [];

        foreach ($xpath->query('//w:body/w:p') ?: [] as $paragraph) {
            if ($paragraph instanceof DOMElement) {
                $blocks[] = $this->block($paragraph, $xpath);
            }
        }

        return $blocks;
    }

    /**
     * Mengambil isi satu berkas media (gambar) dari DOCX; null bila tidak ada atau terlalu besar.
     */
    public function media(string $docxPath, string $zipPath, int $maxBytes = 5 * 1024 * 1024): ?string
    {
        $zip = new ZipArchive;

        if ($zip->open($docxPath) !== true) {
            return null;
        }

        try {
            $stat = $zip->statName($zipPath);

            if ($stat === false || $stat['size'] > $maxBytes) {
                return null;
            }

            $bytes = $zip->getFromName($zipPath);

            return is_string($bytes) ? $bytes : null;
        } finally {
            $zip->close();
        }
    }

    private function part(ZipArchive $zip, string $name, bool $required): ?DOMDocument
    {
        $stat = $zip->statName($name);

        if ($stat === false) {
            if ($required) {
                throw new InvalidArgumentException('Berkas ini bukan dokumen Word (.docx) yang valid.');
            }

            return null;
        }

        if ($stat['size'] > self::MAX_XML_BYTES) {
            throw new InvalidArgumentException('Isi dokumen terlalu besar untuk diproses.');
        }

        $xml = $zip->getFromName($name);
        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = is_string($xml) && $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            if ($required) {
                throw new InvalidArgumentException('Isi dokumen tidak dapat dibaca (XML tidak valid).');
            }

            return null;
        }

        return $dom;
    }

    private function xpath(DOMDocument $dom): DOMXPath
    {
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', self::NS_W);
        $xpath->registerNamespace('m', self::NS_M);
        $xpath->registerNamespace('r', self::NS_R);
        $xpath->registerNamespace('a', self::NS_A);
        $xpath->registerNamespace('v', self::NS_V);

        return $xpath;
    }

    /**
     * @return array<int, array<int, string>>  numId => [level => numFmt]
     */
    private function numberingFormats(DOMDocument $numbering): array
    {
        $xpath = $this->xpath($numbering);
        $abstract = [];

        foreach ($xpath->query('//w:abstractNum') ?: [] as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $levels = [];

            foreach ($xpath->query('./w:lvl', $node) ?: [] as $lvl) {
                if ($lvl instanceof DOMElement) {
                    $fmt = $xpath->query('./w:numFmt', $lvl)?->item(0);
                    $levels[(int) $lvl->getAttributeNS(self::NS_W, 'ilvl')] = $fmt instanceof DOMElement ? $fmt->getAttributeNS(self::NS_W, 'val') : 'decimal';
                }
            }

            $abstract[(int) $node->getAttributeNS(self::NS_W, 'abstractNumId')] = $levels;
        }

        $map = [];

        foreach ($xpath->query('//w:num') ?: [] as $num) {
            if ($num instanceof DOMElement) {
                $abs = $xpath->query('./w:abstractNumId', $num)?->item(0);

                if ($abs instanceof DOMElement) {
                    $map[(int) $num->getAttributeNS(self::NS_W, 'numId')] = $abstract[(int) $abs->getAttributeNS(self::NS_W, 'val')] ?? [];
                }
            }
        }

        return $map;
    }

    /**
     * @return array{text: string, bold: bool, list: array{num_id: int, level: int, fmt: string}|null, runs: list<array{t: string, red: bool}>, images: list<string>}
     */
    private function block(DOMElement $paragraph, DOMXPath $xpath): array
    {
        $runs = [];
        $images = [];
        $boldRuns = 0;
        $textRuns = 0;

        $this->collect($paragraph, $xpath, $runs, $images, $boldRuns, $textRuns);

        $text = '';

        foreach ($runs as $run) {
            $text .= $run['t'];
        }

        $list = null;
        $numId = $xpath->query('./w:pPr/w:numPr/w:numId', $paragraph)?->item(0);

        if ($numId instanceof DOMElement && (int) $numId->getAttributeNS(self::NS_W, 'val') > 0) {
            $level = $xpath->query('./w:pPr/w:numPr/w:ilvl', $paragraph)?->item(0);
            $id = (int) $numId->getAttributeNS(self::NS_W, 'val');
            $lvl = $level instanceof DOMElement ? (int) $level->getAttributeNS(self::NS_W, 'val') : 0;

            $list = ['num_id' => $id, 'level' => $lvl, 'fmt' => $this->formats[$id][$lvl] ?? 'decimal'];
        }

        return [
            'text' => trim($text),
            'bold' => $textRuns > 0 && $boldRuns === $textRuns,
            'list' => $list,
            'runs' => $runs,
            'images' => $images,
        ];
    }

    /**
     * @param  list<array{t: string, red: bool}>  $runs
     * @param  list<string>  $images
     */
    private function collect(DOMNode $node, DOMXPath $xpath, array &$runs, array &$images, int &$boldRuns, int &$textRuns): void
    {
        foreach ($node->childNodes as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            if ($child->namespaceURI === self::NS_M && in_array($child->localName, ['oMath', 'oMathPara'], true)) {
                $text = trim($this->math($child));

                if ($text !== '') {
                    $runs[] = ['t' => '\\('.$text.'\\)', 'red' => $this->isRed($xpath->query('.//w:rPr/w:color', $child)?->item(0))];
                    $textRuns++;
                    $boldRuns++;
                }

                continue;
            }

            if ($child->namespaceURI !== self::NS_W) {
                continue;
            }

            if ($child->localName === 'r') {
                $this->run($child, $xpath, $runs, $images, $boldRuns, $textRuns);
            } elseif (in_array($child->localName, ['hyperlink', 'ins', 'smartTag', 'sdt', 'sdtContent', 'fldSimple'], true)) {
                $this->collect($child, $xpath, $runs, $images, $boldRuns, $textRuns);
            }
        }
    }

    /**
     * @param  list<array{t: string, red: bool}>  $runs
     * @param  list<string>  $images
     */
    private function run(DOMElement $run, DOMXPath $xpath, array &$runs, array &$images, int &$boldRuns, int &$textRuns): void
    {
        $text = '';

        foreach ($run->childNodes as $part) {
            if (! $part instanceof DOMElement || $part->namespaceURI !== self::NS_W) {
                continue;
            }

            match ($part->localName) {
                't' => $text .= $part->textContent,
                'tab' => $text .= "\t",
                'br', 'cr' => $text .= "\n",
                default => null,
            };
        }

        foreach ($xpath->query('.//a:blip/@r:embed | .//v:imagedata/@r:id', $run) ?: [] as $attr) {
            $target = $this->relationships[$attr->nodeValue ?? ''] ?? null;

            if ($target !== null) {
                $path = $this->normalizePath($target);
                $images[] = $path;
                $text .= '⟦IMG:'.$path.'⟧';
            }
        }

        if ($text === '') {
            return;
        }

        $props = $xpath->query('./w:rPr', $run)?->item(0);
        $bold = false;

        if ($props instanceof DOMElement) {
            $b = $xpath->query('./w:b', $props)?->item(0);
            $bold = $b instanceof DOMElement && ! in_array($b->getAttributeNS(self::NS_W, 'val'), ['0', 'false'], true);
        }

        $runs[] = ['t' => $text, 'red' => $this->isRed($xpath->query('./w:rPr/w:color', $run)?->item(0))];

        if (trim($text) !== '') {
            $textRuns++;

            if ($bold) {
                $boldRuns++;
            }
        }
    }

    private function normalizePath(string $path): string
    {
        $parts = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '..') {
                array_pop($parts);
            } elseif ($segment !== '.' && $segment !== '') {
                $parts[] = $segment;
            }
        }

        return implode('/', $parts);
    }

    private function isRed(?DOMNode $color): bool
    {
        if (! $color instanceof DOMElement) {
            return false;
        }

        $hex = ltrim($color->getAttributeNS(self::NS_W, 'val'), '#');

        if (preg_match('/^[0-9A-Fa-f]{6}$/', $hex) !== 1) {
            return false;
        }

        [$r, $g, $b] = [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];

        return $r >= 0xC0 && $g <= 0x50 && $b <= 0x50;
    }

    // ── Rumus (OMML) → LaTeX ─────────────────────────────────────────────────────

    private const NARY = [
        '∑' => '\\sum', '∏' => '\\prod', '∫' => '\\int', '∬' => '\\iint', '∭' => '\\iiint', '∮' => '\\oint',
        '⋃' => '\\bigcup', '⋂' => '\\bigcap', '⋁' => '\\bigvee', '⋀' => '\\bigwedge',
    ];

    private const FUNCTIONS = ['sin', 'cos', 'tan', 'cot', 'sec', 'csc', 'log', 'ln', 'lim', 'max', 'min', 'exp', 'det', 'arcsin', 'arccos', 'arctan', 'sinh', 'cosh', 'tanh', 'gcd'];

    private function math(DOMNode $node): string
    {
        $out = '';

        foreach ($node->childNodes as $child) {
            if (! $child instanceof DOMElement || $child->namespaceURI !== self::NS_M) {
                continue;
            }

            $out .= match ($child->localName) {
                'r' => $this->mathRun($child),
                'f' => $this->fraction($child),
                'sSup' => '{'.$this->part2($child, 'e').'}^{'.$this->part2($child, 'sup').'}',
                'sSub' => '{'.$this->part2($child, 'e').'}_{'.$this->part2($child, 'sub').'}',
                'sSubSup' => '{'.$this->part2($child, 'e').'}_{'.$this->part2($child, 'sub').'}^{'.$this->part2($child, 'sup').'}',
                'sPre' => '{}_{'.$this->part2($child, 'sub').'}^{'.$this->part2($child, 'sup').'}{'.$this->part2($child, 'e').'}',
                'rad' => $this->radical($child),
                'd' => $this->delimiter($child),
                'nary' => $this->nary($child),
                'func' => $this->func($child),
                'limLow' => $this->limit($child, '_'),
                'limUpp' => '\\overset{'.$this->part2($child, 'lim').'}{'.$this->part2($child, 'e').'}',
                'acc' => $this->accent($child),
                'bar' => $this->barOver($child),
                'm' => $this->matrix($child),
                'eqArr' => '\\begin{aligned}'.implode(' \\\\ ', array_map(fn (DOMElement $e): string => $this->math($e), $this->children($child, 'e'))).'\\end{aligned}',
                'oMath', 'oMathPara', 'e', 'num', 'den', 'sup', 'sub', 'deg', 'lim', 'fName', 'box', 'borderBox', 'groupChr', 'phant' => $this->math($child),
                default => '',
            };
        }

        return $out;
    }

    private function mathRun(DOMElement $run): string
    {
        $text = '';
        $plain = false;
        $normal = false;

        foreach ($run->childNodes as $part) {
            if (! $part instanceof DOMElement || $part->namespaceURI !== self::NS_M) {
                continue;
            }

            if ($part->localName === 't') {
                $text .= $part->textContent;
            } elseif ($part->localName === 'rPr') {
                foreach ($part->childNodes as $prop) {
                    if ($prop instanceof DOMElement && $prop->localName === 'sty' && in_array($prop->getAttributeNS(self::NS_M, 'val'), ['p', 'b'], true)) {
                        $plain = true;
                    }

                    if ($prop instanceof DOMElement && $prop->localName === 'nor') {
                        $normal = true;
                    }
                }
            }
        }

        if ($text === '') {
            return '';
        }

        $latex = $this->escapeLatex($text);

        if ($normal) {
            return '\\text{'.str_replace(['\\ ', ' '], ' ', $latex).'}';
        }

        return $plain && preg_match('/^[A-Za-z]{2,}$/', $text) === 1 ? '\\mathrm{'.$latex.'}' : $latex;
    }

    /** Karakter khusus LaTeX dan simbol yang perlu padanan. */
    private function escapeLatex(string $text): string
    {
        $map = [
            '\\' => '\\backslash ', '{' => '\\{', '}' => '\\}', '#' => '\\#', '$' => '\\$', '%' => '\\%', '&' => '\\&',
            '_' => '\\_', '~' => '\\sim ', '^' => '\\wedge ', '°' => '^{\\circ}', '∘' => '\\circ ', '…' => '\\ldots ', '·' => '\\cdot ', '−' => '-',
            "\u{00A0}" => '\\ ', '′' => "'", '″' => "''",
        ];

        return strtr($text, $map);
    }

    private function part2(DOMElement $node, string $name): string
    {
        foreach ($this->children($node, $name) as $child) {
            return $this->math($child);
        }

        return '';
    }

    /**
     * @return list<DOMElement>
     */
    private function children(DOMElement $node, string $name): array
    {
        $found = [];

        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement && $child->namespaceURI === self::NS_M && $child->localName === $name) {
                $found[] = $child;
            }
        }

        return $found;
    }

    private function attr(DOMElement $node, string $props, string $name, ?string $default): ?string
    {
        foreach ($this->children($node, $props) as $p) {
            foreach ($this->children($p, $name) as $c) {
                return $c->hasAttributeNS(self::NS_M, 'val') ? $c->getAttributeNS(self::NS_M, 'val') : $default;
            }
        }

        return $default;
    }

    private function fraction(DOMElement $node): string
    {
        $num = $this->part2($node, 'num');
        $den = $this->part2($node, 'den');

        return match ($this->attr($node, 'fPr', 'type', 'bar')) {
            'noBar' => '\\binom{'.$num.'}{'.$den.'}',
            'lin' => '{'.$num.'}/{'.$den.'}',
            'skw' => '{}^{'.$num.'}\\!/{}_{'.$den.'}',
            default => '\\frac{'.$num.'}{'.$den.'}',
        };
    }

    private function radical(DOMElement $node): string
    {
        $deg = trim($this->part2($node, 'deg'));

        return '\\sqrt'.($deg !== '' && $deg !== '2' ? '['.$deg.']' : '').'{'.$this->part2($node, 'e').'}';
    }

    private function delimiter(DOMElement $node): string
    {
        $begin = $this->attr($node, 'dPr', 'begChr', '(') ?? '(';
        $end = $this->attr($node, 'dPr', 'endChr', ')') ?? ')';
        $sep = $this->attr($node, 'dPr', 'sepChr', '|') ?? '|';
        $parts = array_map(fn (DOMElement $e): string => $this->math($e), $this->children($node, 'e'));

        return '\\left'.$this->delim($begin, true).' '.implode(' '.$this->sepLatex($sep).' ', $parts).' \\right'.$this->delim($end, false);
    }

    private function delim(string $chr, bool $open): string
    {
        return match ($chr) {
            '' => '.',
            '{' => '\\{',
            '}' => '\\}',
            '⌊' => '\\lfloor',
            '⌋' => '\\rfloor',
            '⌈' => '\\lceil',
            '⌉' => '\\rceil',
            '⟨', '〈' => '\\langle',
            '⟩', '〉' => '\\rangle',
            '‖' => '\\|',
            default => in_array($chr, ['(', ')', '[', ']', '|', '/'], true) ? $chr : '.',
        };
    }

    private function sepLatex(string $sep): string
    {
        return match ($sep) {
            '|' => '\\mid',
            ',' => ',',
            ';' => ';',
            default => $sep,
        };
    }

    private function nary(DOMElement $node): string
    {
        $chr = $this->attr($node, 'naryPr', 'chr', '∫') ?? '∫';
        $op = self::NARY[$chr] ?? $this->escapeLatex($chr);
        $sub = trim($this->part2($node, 'sub'));
        $sup = trim($this->part2($node, 'sup'));

        return $op.($sub !== '' ? '_{'.$sub.'}' : '').($sup !== '' ? '^{'.$sup.'}' : '').'{'.$this->part2($node, 'e').'}';
    }

    private function func(DOMElement $node): string
    {
        $name = trim($this->part2($node, 'fName'));
        $arg = $this->part2($node, 'e');
        $suffix = '';

        if (preg_match('/^\{?\\\\(?:mathrm|text)\{([A-Za-z]+)\}\}?(.*)$/s', $name, $mm) === 1) {
            $name = $mm[1];
            $suffix = $mm[2];
        }

        $cmd = (in_array($name, self::FUNCTIONS, true) ? '\\'.$name : '\\operatorname{'.$name.'}').$suffix;

        return $cmd.'\\left('.$arg.'\\right)';
    }

    private function limit(DOMElement $node, string $mark): string
    {
        $base = trim($this->part2($node, 'e'));
        $lim = $this->part2($node, 'lim');
        $plain = preg_replace('/^\\\\(?:mathrm|text)\{(.*)\}$/', '$1', $base) ?? $base;

        if ($plain === 'lim') {
            return '\\lim\\limits_{'.$lim.'}';
        }

        return '\\underset{'.$lim.'}{'.$base.'}';
    }

    private function accent(DOMElement $node): string
    {
        $chr = $this->attr($node, 'accPr', 'chr', "\u{0302}") ?? "\u{0302}";
        $cmd = match ($chr) {
            "\u{0304}", '¯', '̅' => '\\bar',
            "\u{20D7}", '→' => '\\vec',
            "\u{0307}", '˙' => '\\dot',
            "\u{0303}", '~' => '\\tilde',
            default => '\\hat',
        };

        return $cmd.'{'.$this->part2($node, 'e').'}';
    }

    private function barOver(DOMElement $node): string
    {
        $pos = $this->attr($node, 'barPr', 'pos', 'top');

        return ($pos === 'bot' ? '\\underline' : '\\overline').'{'.$this->part2($node, 'e').'}';
    }

    private function matrix(DOMElement $node): string
    {
        $rows = [];

        foreach ($this->children($node, 'mr') as $row) {
            $rows[] = implode(' & ', array_map(fn (DOMElement $e): string => $this->math($e), $this->children($row, 'e')));
        }

        return '\\begin{matrix}'.implode(' \\\\ ', $rows).'\\end{matrix}';
    }
}
