<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Documents\DocxStructureReader;
use App\Services\Documents\ExamDocxParser;
use PHPUnit\Framework\TestCase;
use ZipArchive;

/**
 * Pilihan jawaban berbentuk tabel: tiap baris berawalan huruf A–E menjadi satu pilihan (gambar baris tabel).
 */
class OptionTableTest extends TestCase
{
    private const W = 'xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"';

    private function para(string $text, ?int $num = null, bool $red = false): string
    {
        $pr = $num === null ? '' : '<w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="'.$num.'"/></w:numPr></w:pPr>';
        $rp = $red ? '<w:rPr><w:color w:val="FF0000"/></w:rPr>' : '';

        return '<w:p>'.$pr.'<w:r>'.$rp.'<w:t>'.$text.'</w:t></w:r></w:p>';
    }

    private function row(string ...$cells): string
    {
        return '<w:tr>'.implode('', array_map(fn (string $c): string => '<w:tc><w:tcPr><w:tcW w:w="2000" w:type="dxa"/></w:tcPr>'.$c.'</w:tc>', $cells)).'</w:tr>';
    }

    private function docx(?string $bodyOverride = null): string
    {
        $table = '<w:tbl><w:tblPr/><w:tblGrid><w:gridCol w:w="2000"/><w:gridCol w:w="2000"/><w:gridCol w:w="2000"/></w:tblGrid>'
            .$this->row($this->para('Pilihan'), $this->para('Unsur'), $this->para('Fungsi'))
            .$this->row($this->para('A'), $this->para('Besi'), $this->para('Magnet'))
            .$this->row($this->para('B'), $this->para('Tembaga'), $this->para('Kabel', null, true))
            .$this->row($this->para('C'), $this->para('Emas'), $this->para('Perhiasan'))
            .'</w:tbl>';
        $body = $bodyOverride ?? $this->para('Pasangan unsur dan fungsi yang tepat adalah...', 1).$table.$this->para('Soal kedua?', 1).$this->para('Satu', 2).$this->para('Dua', 2, true);
        $w = self::W;
        $doc = '<?xml version="1.0" encoding="UTF-8"?><w:document '.$w.'><w:body>'.$body.'</w:body></w:document>';
        $num = '<?xml version="1.0" encoding="UTF-8"?><w:numbering '.$w.'><w:abstractNum w:abstractNumId="0"><w:lvl w:ilvl="0"><w:numFmt w:val="decimal"/></w:lvl></w:abstractNum><w:abstractNum w:abstractNumId="1"><w:lvl w:ilvl="0"><w:numFmt w:val="upperLetter"/></w:lvl></w:abstractNum><w:num w:numId="1"><w:abstractNumId w:val="0"/></w:num><w:num w:numId="2"><w:abstractNumId w:val="1"/></w:num></w:numbering>';
        $types = '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>';
        $rels = '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>';

        $path = sys_get_temp_dir().'/opt-table-'.uniqid().'.docx';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);
        $zip->addFromString('[Content_Types].xml', $types);
        $zip->addFromString('_rels/.rels', $rels);
        $zip->addFromString('word/document.xml', $doc);
        $zip->addFromString('word/numbering.xml', $num);
        $zip->close();

        return $path;
    }

    public function test_table_rows_become_options_with_red_key_and_images(): void
    {
        $path = $this->docx();
        $reader = new DocxStructureReader;
        $items = (new ExamDocxParser)->parse($reader->read($path))['items'];

        $this->assertSame(['A', 'B', 'C'], array_keys($items[1]['options']));
        $this->assertSame('B', $items[1]['answer_key']);
        $this->assertStringContainsString('⟦IMG:generated/table-1-r2.png⟧', $items[1]['options']['A']);

        // Soal berikutnya tidak terganggu.
        $this->assertSame(['A' => 'Satu', 'B' => 'Dua'], $items[2]['options']);

        if (function_exists('imagettftext')) {
            $this->assertNotNull($reader->media($path, 'generated/table-1-r2.png'));
        }

        @unlink($path);
    }

    private function smallTable(string $cell, bool $red = false): string
    {
        return '<w:tbl><w:tblPr/><w:tblGrid><w:gridCol w:w="2000"/><w:gridCol w:w="2000"/></w:tblGrid>'
            .$this->row($this->para('Tanggal'), $this->para('Keterangan'))
            .$this->row($this->para('2 Jan'), $this->para($cell, null, $red))
            .'</w:tbl>';
    }

    public function test_typed_letters_without_numbering_are_not_options(): void
    {
        $body = $this->para('Soal satu?', 1).$this->para('A.').$this->smallTable('Modal').$this->para('B.').$this->smallTable('Gaji');
        $path = $this->docx($body);
        $items = (new ExamDocxParser)->parse((new DocxStructureReader)->read($path))['items'];
        @unlink($path);

        $this->assertSame([], $items[1]['options']);
    }

    public function test_table_placed_under_each_option_letter_becomes_that_option(): void
    {
        // Huruf dari daftar otomatis (Bullets and Numbering) yang kosong; tabel di bawah tiap huruf.
        $body = $this->para('Tabel buku kas yang benar?', 1)
            .$this->para('', 2).$this->smallTable('Modal')
            .$this->para('', 2).$this->smallTable('Gaji', true)
            .$this->para('', 2).$this->smallTable('Lain')
            .$this->para('Soal dua?', 1)
            .$this->para('', 2).$this->smallTable('X')
            .$this->para('', 2).$this->smallTable('Y', true);

        $path = $this->docx($body);
        $items = (new ExamDocxParser)->parse((new DocxStructureReader)->read($path))['items'];
        @unlink($path);

        $this->assertSame(['A', 'B', 'C'], array_keys($items[1]['options']));
        $this->assertSame('B', $items[1]['answer_key']);
        $this->assertSame(['A', 'B'], array_keys($items[2]['options']));
        $this->assertSame('B', $items[2]['answer_key']);
        $this->assertStringContainsString('⟦IMG:generated/table-', $items[1]['options']['A']);
    }
}
