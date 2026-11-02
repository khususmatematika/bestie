<?php

declare(strict_types=1);

namespace App\Services\Documents;

use RuntimeException;
use ZipArchive;

/**
 * Penyusun .docx sederhana tanpa dependensi: paragraf (tebal/miring/rata), tabel, pindah halaman,
 * header (mis. "BELUM FINAL"), footer bernomor halaman, font dan margin. Hasil dapat dibuka Word/LibreOffice
 * dan dibaca ulang oleh DocxTextReader.
 */
final class DocxBuilder
{
    private string $body = '';

    /** @var array<string, array{bytes: string, ext: string}> */
    private array $media = [];

    public function __construct(
        private readonly string $font = 'Times New Roman',
        private readonly int $sizePt = 12,
        private readonly float $marginCm = 2.0,
        private readonly ?string $headerText = null,
        private readonly ?string $footerText = null,
        private readonly bool $landscape = false,
    ) {
        $this->landscapeNow = $landscape;
    }

    private bool $landscapeNow = false;

    /** @var array<int, int> numId => abstractNumId (1 = angka, 2 = huruf kapital, 3 = bullet) */
    private array $lists = [];

    /**
     * Membuat daftar baru (penomoran otomatis Word) dan mengembalikan numId-nya. Pakai opsi 'numId' pada runs()/paragraph().
     * Daftar huruf dibuat baru untuk tiap soal agar mulai dari A lagi.
     */
    public function newList(string $format): int
    {
        $abstract = ['decimal' => 1, 'upperLetter' => 2, 'bullet' => 3][$format] ?? 1;
        $id = count($this->lists) + 1;
        $this->lists[$id] = $abstract;

        return $id;
    }

    private function numberingXml(string $ns): string
    {
        $abs = static fn (int $id, string $fmt, string $text, int $left, int $hanging, string $rpr = ''): string => '<w:abstractNum w:abstractNumId="'.$id.'"><w:multiLevelType w:val="singleLevel"/>'
            .'<w:lvl w:ilvl="0"><w:start w:val="1"/><w:numFmt w:val="'.$fmt.'"/><w:lvlText w:val="'.$text.'"/><w:lvlJc w:val="left"/>'
            .'<w:pPr><w:ind w:left="'.$left.'" w:hanging="'.$hanging.'"/></w:pPr>'.($rpr !== '' ? '<w:rPr>'.$rpr.'</w:rPr>' : '').'</w:lvl></w:abstractNum>';

        $xml = '<w:numbering '.$ns.'>'
            .$abs(1, 'decimal', '%1.', 425, 425)
            .$abs(2, 'upperLetter', '%1.', 851, 426)
            .$abs(3, 'bullet', '•', 567, 283);

        foreach ($this->lists as $id => $abstract) {
            $xml .= '<w:num w:numId="'.$id.'"><w:abstractNumId w:val="'.$abstract.'"/><w:lvlOverride w:ilvl="0"><w:startOverride w:val="1"/></w:lvlOverride></w:num>';
        }

        return $xml.'</w:numbering>';
    }

    /** Lebar area teks pada bagian halaman saat ini (twips). */
    public function contentWidth(): int
    {
        return ($this->landscapeNow ? 16838 : 11906) - 2 * $this->twips($this->marginCm);
    }

    /** Mulai bagian halaman baru berorientasi lanskap (bagian sebelumnya tetap potret). */
    public function startLandscape(): self
    {
        if ($this->landscapeNow) {
            return $this->pageBreak();
        }

        $this->body .= '<w:p><w:pPr>'.$this->sectPr(false).'</w:pPr></w:p>';
        $this->landscapeNow = true;

        return $this;
    }

    private function sectPr(bool $landscape): string
    {
        $w = $this->twips($this->marginCm);
        $size = $landscape ? '<w:pgSz w:w="16838" w:h="11906" w:orient="landscape"/>' : '<w:pgSz w:w="11906" w:h="16838"/>';

        return '<w:sectPr><w:headerReference w:type="default" r:id="rId2"/><w:footerReference w:type="default" r:id="rId3"/>'
            .$size.'<w:pgMar w:top="'.$w.'" w:right="'.$w.'" w:bottom="'.$w.'" w:left="'.$w.'" w:header="708" w:footer="708" w:gutter="0"/></w:sectPr>';
    }

    /**
     * Tabel berpetak dengan sel bergaya. Sel = teks, atau larik {text, fill?, color?, bold?, align?, vertical?, size?}.
     *
     * @param  list<float>  $widths  persentase lebar kolom (jumlah 100)
     * @param  list<list<string|array<string, mixed>>>  $rows
     */
    public function grid(array $widths, array $rows, int $headerRows = 1, int $sizePt = 9): self
    {
        $total = $this->contentWidth();
        $cols = array_map(static fn ($w): int => (int) round($total * $w / 100), $widths);

        $xml = '<w:tbl><w:tblPr><w:tblW w:w="'.array_sum($cols).'" w:type="dxa"/><w:tblBorders>'
            .implode('', array_map(static fn (string $s): string => "<w:{$s} w:val=\"single\" w:sz=\"4\" w:space=\"0\" w:color=\"6B7280\"/>", ['top', 'left', 'bottom', 'right', 'insideH', 'insideV']))
            .'</w:tblBorders><w:tblLayout w:type="fixed"/><w:tblCellMar><w:top w:w="30" w:type="dxa"/><w:left w:w="60" w:type="dxa"/><w:bottom w:w="30" w:type="dxa"/><w:right w:w="60" w:type="dxa"/></w:tblCellMar></w:tblPr><w:tblGrid>'
            .implode('', array_map(static fn (int $c): string => '<w:gridCol w:w="'.$c.'"/>', $cols)).'</w:tblGrid>';

        foreach ($rows as $r => $row) {
            $head = $r < $headerRows;
            $rotated = $head && array_filter($row, static fn ($cell): bool => is_array($cell) && ! empty($cell['vertical'])) !== [];
            $xml .= '<w:tr><w:trPr><w:cantSplit/>'.($head ? '<w:tblHeader/>' : '').($rotated ? '<w:trHeight w:val="1250" w:hRule="atLeast"/>' : '').'</w:trPr>';

            foreach ($row as $c => $cell) {
                $cell = is_array($cell) ? $cell : ['text' => $cell];
                $fill = isset($cell['fill']) ? preg_replace('/[^0-9A-Fa-f]/', '', (string) $cell['fill']) : ($head ? 'E5E7EB' : null);
                $align = (string) ($cell['align'] ?? ($head ? 'center' : 'left'));
                $size = (int) ($cell['size'] ?? $sizePt);

                $xml .= '<w:tc><w:tcPr><w:tcW w:w="'.($cols[$c] ?? 600).'" w:type="dxa"/>'
                    .($fill ? '<w:shd w:val="clear" w:color="auto" w:fill="'.$fill.'"/>' : '')
                    .(! empty($cell['vertical']) ? '<w:textDirection w:val="btLr"/>' : '')
                    .'<w:vAlign w:val="center"/></w:tcPr>';

                foreach (explode("\n", (string) $cell['text']) as $line) {
                    $xml .= '<w:p>'.$this->paragraphProps(['after' => 0, 'align' => $align]).$this->run([
                        'text' => $line,
                        'bold' => (bool) ($cell['bold'] ?? $head),
                        'size' => $size,
                        'color' => $cell['color'] ?? null,
                    ] + []).'</w:p>';
                }

                $xml .= '</w:tc>';
            }

            $xml .= '</w:tr>';
        }

        $this->body .= $xml.'</w:tbl><w:p><w:pPr><w:spacing w:after="60"/></w:pPr></w:p>';

        return $this;
    }

    /**
     * @param  array{bold?: bool, italic?: bool, size?: int, align?: string, indent?: float, after?: int, color?: string}  $options
     */
    public function paragraph(string $text, array $options = []): self
    {
        return $this->runs([['text' => $text] + array_intersect_key($options, ['bold' => 1, 'italic' => 1, 'size' => 1, 'color' => 1])], $options);
    }

    /**
     * @param  list<array<string, mixed>>  $runs
     * @param  array{align?: string, indent?: float, after?: int}  $options
     */
    public function runs(array $runs, array $options = []): self
    {
        $this->body .= '<w:p>'.$this->paragraphProps($options);

        foreach ($runs as $run) {
            $this->body .= $this->run($run);
        }

        $this->body .= '</w:p>';

        return $this;
    }

    /**
     * @param  list<list<string>>  $rows  baris pertama dianggap judul kolom bila $header true
     * @param  list<int>  $widths  persentase lebar kolom (jumlah 100)
     */
    public function table(array $rows, array $widths, bool $header = true): self
    {
        $total = 9000;
        $grid = '';

        foreach ($widths as $w) {
            $grid .= '<w:gridCol w:w="'.(int) round($total * $w / 100).'"/>';
        }

        $xml = '<w:tbl><w:tblPr><w:tblW w:w="5000" w:type="pct"/><w:tblBorders>'
            .implode('', array_map(static fn (string $s): string => "<w:{$s} w:val=\"single\" w:sz=\"4\" w:space=\"0\" w:color=\"808080\"/>", ['top', 'left', 'bottom', 'right', 'insideH', 'insideV']))
            .'</w:tblBorders><w:tblLayout w:type="fixed"/><w:tblCellMar><w:left w:w="80" w:type="dxa"/><w:right w:w="80" w:type="dxa"/></w:tblCellMar></w:tblPr><w:tblGrid>'.$grid.'</w:tblGrid>';

        foreach ($rows as $i => $row) {
            $isHead = $header && $i === 0;
            $xml .= '<w:tr>'.($isHead ? '<w:trPr><w:tblHeader/></w:trPr>' : '');

            foreach ($row as $c => $cell) {
                $w = (int) round($total * ($widths[$c] ?? 10) / 100);
                $xml .= '<w:tc><w:tcPr><w:tcW w:w="'.$w.'" w:type="dxa"/>'.($isHead ? '<w:shd w:val="clear" w:color="auto" w:fill="E5E7EB"/>' : '').'</w:tcPr>';

                foreach (explode("\n", $cell) as $line) {
                    $xml .= '<w:p>'.$this->paragraphProps(['after' => 40]).implode('', array_map(fn (array $r): string => $this->run($r), $this->richRuns($line, ['bold' => $isHead, 'size' => max(8, $this->sizePt - 2)]))).'</w:p>';
                }

                $xml .= '</w:tc>';
            }

            $xml .= '</w:tr>';
        }

        $this->body .= $xml.'</w:tbl><w:p/>';

        return $this;
    }

    /**
     * Menyisipkan gambar (PNG/JPEG/GIF) sebagai paragraf tersendiri, diskalakan agar tidak melebihi lebar maksimum.
     */
    public function image(string $bytes, string $extension, float $maxWidthCm = 9.0, float $maxHeightCm = 8.0): self
    {
        $drawing = $this->drawing($bytes, $extension, $maxWidthCm, $maxHeightCm);

        if ($drawing !== '') {
            $this->body .= '<w:p><w:pPr><w:spacing w:after="120"/></w:pPr>'.$drawing.'</w:p>';
        }

        return $this;
    }

    /** Run berisi gambar inline (kosong bila bukan gambar valid). */
    private function drawing(string $bytes, string $extension, float $maxWidthCm, float $maxHeightCm, bool $floating = false): string
    {
        $size = @getimagesizefromstring($bytes);

        if ($size === false || $size[0] < 1 || $size[1] < 1) {
            return '';
        }

        $scale = min($maxWidthCm / ($size[0] / 37.8), $maxHeightCm / ($size[1] / 37.8), 1.0);
        $cx = (int) round(($size[0] / 37.8) * $scale * 360000);
        $cy = (int) round(($size[1] / 37.8) * $scale * 360000);

        $n = count($this->media) + 1;
        $ext = $extension === 'jpeg' ? 'jpg' : $extension;
        $name = 'image'.$n.'.'.$ext;
        $this->media[$name] = ['bytes' => $bytes, 'ext' => $ext];

        $graphic = '<a:graphic xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">'
            .'<pic:pic xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture"><pic:nvPicPr><pic:cNvPr id="'.$n.'" name="'.$name.'"/><pic:cNvPicPr/></pic:nvPicPr>'
            .'<pic:blipFill><a:blip r:embed="rIdImg'.$n.'"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill>'
            .'<pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="'.$cx.'" cy="'.$cy.'"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr></pic:pic>'
            .'</a:graphicData></a:graphic>';

        if ($floating) {
            // Gambar mengambang di depan teks (tanpa pembungkus), menempel di tepi kiri margin.
            return '<w:r><w:drawing><wp:anchor distT="0" distB="0" distL="0" distR="0" simplePos="0" relativeHeight="251659264" behindDoc="0" locked="0" layoutInCell="1" allowOverlap="1">'
                .'<wp:simplePos x="0" y="0"/><wp:positionH relativeFrom="margin"><wp:posOffset>0</wp:posOffset></wp:positionH>'
                .'<wp:positionV relativeFrom="paragraph"><wp:posOffset>0</wp:posOffset></wp:positionV>'
                .'<wp:extent cx="'.$cx.'" cy="'.$cy.'"/><wp:effectExtent l="0" t="0" r="0" b="0"/><wp:wrapNone/>'
                .'<wp:docPr id="'.$n.'" name="Logo '.$n.'"/><wp:cNvGraphicFramePr/>'.$graphic.'</wp:anchor></w:drawing></w:r>';
        }

        return '<w:r><w:drawing><wp:inline distT="0" distB="0" distL="0" distR="0">'
            .'<wp:extent cx="'.$cx.'" cy="'.$cy.'"/><wp:docPr id="'.$n.'" name="Gambar '.$n.'"/>'.$graphic.'</wp:inline></w:drawing></w:r>';
    }

    /**
     * Memecah teks soal menjadi run: teks biasa, rumus \( LaTeX \) (→ Equation Word) dan penanda ⟦img:ID⟧ (→ gambar inline).
     *
     * @param  array{bold?: bool, italic?: bool, size?: int, color?: string}  $base
     * @param  (callable(int): (array{bytes: string, ext: string}|null))|null  $images
     * @return list<array<string, mixed>>
     */
    public function richRuns(string $text, array $base = [], ?callable $images = null): array
    {
        $runs = [];
        $last = 0;

        preg_match_all('/\\\\\((.+?)\\\\\)|⟦img:(\d+)⟧/us', $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        foreach ($matches as $m) {
            $offset = $m[0][1];

            if ($offset > $last) {
                $runs[] = ['text' => substr($text, $last, $offset - $last)] + $base;
            }

            if (($m[1][1] ?? -1) >= 0 && $m[1][0] !== '') {
                $runs[] = ['math' => $m[1][0]] + $base;
            } elseif (isset($m[2]) && $m[2][1] >= 0 && $images !== null) {
                $file = $images((int) $m[2][0]);

                if ($file !== null) {
                    $runs[] = ['image' => $file['bytes'], 'ext' => $file['ext']];
                }
            }

            $last = $offset + strlen($m[0][0]);
        }

        if ($last < strlen($text)) {
            $runs[] = ['text' => substr($text, $last)] + $base;
        }

        return $runs === [] ? [['text' => ''] + $base] : $runs;
    }

    /**
     * Kop surat: logo mengambang di depan teks pada sisi kiri, sehingga seluruh baris teks rata tengah terhadap
     * lebar halaman; diakhiri garis ganda.
     *
     * @param  list<array{text: string, size: int, bold?: bool}>  $lines
     */
    public function kop(?string $logoBytes, string $logoExt, array $lines): self
    {
        $logo = $logoBytes !== null ? $this->drawing($logoBytes, $logoExt, 2.4, 2.4, true) : '';

        foreach (array_values($lines) as $i => $line) {
            $this->body .= '<w:p><w:pPr><w:spacing w:after="0" w:line="240" w:lineRule="auto"/><w:jc w:val="center"/></w:pPr>'
                .($i === 0 ? $logo : '')
                .$this->run(['text' => $line['text'], 'bold' => $line['bold'] ?? false, 'size' => $line['size']]).'</w:p>';
        }

        $this->body .= '<w:p><w:pPr><w:pBdr><w:bottom w:val="double" w:sz="6" w:space="1" w:color="44546A"/></w:pBdr><w:spacing w:before="60" w:after="160"/></w:pPr></w:p>';

        return $this;
    }

    /** Garis pemisah (batas bawah paragraf kosong), mis. di bawah kop. */
    public function rule(): self
    {
        $this->body .= '<w:p><w:pPr><w:pBdr><w:bottom w:val="single" w:sz="12" w:space="1" w:color="000000"/></w:pBdr><w:spacing w:after="160"/></w:pPr></w:p>';

        return $this;
    }

    public function pageBreak(): self
    {
        $this->body .= '<w:p><w:r><w:br w:type="page"/></w:r></w:p>';

        return $this;
    }

    public function save(string $path): void
    {
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Tidak dapat membuat berkas DOCX.');
        }

        $ns = 'xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"';
        $head = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $w = $this->twips($this->marginCm);

        $zip->addFromString('[Content_Types].xml', $head
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Default Extension="png" ContentType="image/png"/><Default Extension="jpg" ContentType="image/jpeg"/><Default Extension="gif" ContentType="image/gif"/>'
            .'<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            .'<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
            .'<Override PartName="/word/header1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.header+xml"/>'
            .'<Override PartName="/word/footer1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footer+xml"/>'
            .($this->lists !== [] ? '<Override PartName="/word/numbering.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.numbering+xml"/>' : '')
            .'</Types>');

        $zip->addFromString('_rels/.rels', $head
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            .'</Relationships>');

        $zip->addFromString('word/_rels/document.xml.rels', $head
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/header" Target="header1.xml"/>'
            .'<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer" Target="footer1.xml"/>'
            .($this->lists !== [] ? '<Relationship Id="rId4" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/numbering" Target="numbering.xml"/>' : '')
            .$this->mediaRelationships()
            .'</Relationships>');

        $font = $this->esc($this->font);
        $zip->addFromString('word/styles.xml', $head
            .'<w:styles '.$ns.'><w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii="'.$font.'" w:hAnsi="'.$font.'" w:cs="'.$font.'"/>'
            .'<w:sz w:val="'.($this->sizePt * 2).'"/><w:szCs w:val="'.($this->sizePt * 2).'"/><w:lang w:val="id-ID"/></w:rPr></w:rPrDefault>'
            .'<w:pPrDefault><w:pPr><w:spacing w:after="120" w:line="276" w:lineRule="auto"/></w:pPr></w:pPrDefault></w:docDefaults></w:styles>');

        $zip->addFromString('word/header1.xml', $head.'<w:hdr '.$ns.'><w:p><w:pPr><w:jc w:val="center"/></w:pPr>'
            .($this->headerText !== null && $this->headerText !== ''
                ? $this->run(['text' => $this->headerText, 'bold' => true, 'size' => 14, 'color' => 'C62828'])
                : '')
            .'</w:p></w:hdr>');

        $zip->addFromString('word/footer1.xml', $head.'<w:ftr '.$ns.'><w:p><w:pPr><w:jc w:val="center"/></w:pPr>'
            .$this->run(['text' => ($this->footerText !== null && $this->footerText !== '' ? $this->footerText.' — ' : '').'Halaman ', 'size' => 9])
            .'<w:r><w:rPr><w:sz w:val="18"/></w:rPr><w:fldChar w:fldCharType="begin"/></w:r>'
            .'<w:r><w:rPr><w:sz w:val="18"/></w:rPr><w:instrText xml:space="preserve"> PAGE </w:instrText></w:r>'
            .'<w:r><w:rPr><w:sz w:val="18"/></w:rPr><w:fldChar w:fldCharType="separate"/></w:r>'
            .'<w:r><w:rPr><w:sz w:val="18"/></w:rPr><w:t>1</w:t></w:r>'
            .'<w:r><w:rPr><w:sz w:val="18"/></w:rPr><w:fldChar w:fldCharType="end"/></w:r>'
            .'</w:p></w:ftr>');

        if ($this->lists !== []) {
            $zip->addFromString('word/numbering.xml', $head.$this->numberingXml($ns));
        }

        foreach ($this->media as $name => $file) {
            $zip->addFromString('word/media/'.$name, $file['bytes']);
        }

        $zip->addFromString('word/document.xml', $head.'<w:document '.$ns.' xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" xmlns:m="http://schemas.openxmlformats.org/officeDocument/2006/math"><w:body>'.$this->body
            .$this->sectPr($this->landscapeNow)
            .'</w:body></w:document>');

        $zip->close();
    }

    private function mediaRelationships(): string
    {
        $xml = '';
        $n = 0;

        foreach (array_keys($this->media) as $name) {
            $n++;
            $xml .= '<Relationship Id="rIdImg'.$n.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/'.$name.'"/>';
        }

        return $xml;
    }

    /**
     * @param  array{align?: string, indent?: float, after?: int}  $o
     */
    private function paragraphProps(array $o): string
    {
        $jc = match ($o['align'] ?? 'left') {
            'center' => '<w:jc w:val="center"/>',
            'right' => '<w:jc w:val="right"/>',
            'both' => '<w:jc w:val="both"/>',
            default => '',
        };

        $indent = isset($o['indent']) && $o['indent'] > 0 ? '<w:ind w:left="'.$this->twips($o['indent']).'"/>' : '';
        $spacing = isset($o['after']) ? '<w:spacing w:after="'.(int) $o['after'].'"/>' : '';

        $num = isset($o['numId']) ? '<w:numPr><w:ilvl w:val="0"/><w:numId w:val="'.(int) $o['numId'].'"/></w:numPr>' : '';
        $props = $num.$spacing.$indent.$jc;

        return $props === '' ? '' : '<w:pPr>'.$props.'</w:pPr>';
    }

    /**
     * @param  array<string, mixed>  $r
     */
    private function run(array $r): string
    {
        if (isset($r['math'])) {
            return '<m:oMath>'.(new LatexToOmml)->convert((string) $r['math']).'</m:oMath>';
        }

        if (isset($r['image'])) {
            return $this->drawing((string) $r['image'], (string) ($r['ext'] ?? 'png'), 7.0, 5.5);
        }

        $props = ($r['bold'] ?? false ? '<w:b/>' : '')
            .($r['italic'] ?? false ? '<w:i/>' : '')
            .(isset($r['color']) ? '<w:color w:val="'.preg_replace('/[^0-9A-Fa-f]/', '', $r['color']).'"/>' : '')
            .(isset($r['size']) ? '<w:sz w:val="'.((int) $r['size'] * 2).'"/><w:szCs w:val="'.((int) $r['size'] * 2).'"/>' : '');

        return '<w:r>'.($props === '' ? '' : '<w:rPr>'.$props.'</w:rPr>').'<w:t xml:space="preserve">'.$this->esc($r['text']).'</w:t></w:r>';
    }

    private function esc(string $text): string
    {
        $text = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $text) ?? '';

        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function twips(float $cm): int
    {
        return (int) round($cm * 567);
    }
}
