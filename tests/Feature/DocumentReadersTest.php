<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Documents\DocumentRows;
use App\Services\Documents\DocxBuilder;
use App\Services\Documents\DocxTextReader;
use App\Services\Documents\NaskahRowsParser;
use PHPUnit\Framework\TestCase;

class DocumentReadersTest extends TestCase
{
    public function test_naskah_rows_parser_reads_options_key_and_type(): void
    {
        $result = (new NaskahRowsParser)->parse([
            ['no' => '1', 'bentuk' => 'Pilihan ganda', 'soal' => 'Ibu kota?', 'opsi_a' => 'Bandung', 'opsi_b' => 'Jakarta', 'kunci' => 'B', 'bobot' => '1'],
            ['no' => '2', 'bentuk' => 'Uraian', 'soal' => 'Jelaskan.', 'pedoman' => 'Sebut dua hal', 'bobot' => '5'],
            ['no' => '', 'soal' => 'tanpa nomor'],
        ]);

        $this->assertSame(['A' => 'Bandung', 'B' => 'Jakarta'], $result['items'][1]['options']);
        $this->assertSame('pg', $result['items'][1]['type']);
        $this->assertSame('uraian', $result['items'][2]['type']);
        $this->assertSame(5.0, $result['items'][2]['bobot']);
        $this->assertNotEmpty($result['warnings']);
    }

    public function test_docx_table_becomes_blueprint_rows(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'kisi').'.docx';
        (new DocxBuilder)->table([
            ['No', 'Indikator Soal', 'Level Kognitif'],
            ['1', 'Menentukan ibu kota', 'C1'],
        ], [10, 60, 30])->save($path);

        $rows = DocumentRows::fromTables((new DocxTextReader)->read($path)['tables']);
        @unlink($path);

        $this->assertSame('Menentukan ibu kota', $rows[0]['indikator_soal']);
        $this->assertSame('C1', $rows[0]['level_kognitif']);
    }

    public function test_line_breaks_in_text_become_word_line_breaks(): void
    {
        $path = sys_get_temp_dir().'/br-'.uniqid().'.docx';
        $doc = new DocxBuilder;
        $doc->runs([['text' => "Kepada Yth. Bapak HRD\nPT Digital Kreatif\nJakarta Pusat"]]);
        $doc->save($path);

        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($path));
        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();
        @unlink($path);

        $this->assertSame(2, substr_count($xml, '<w:br/>'));
        $this->assertStringNotContainsString("HRD\nPT", $xml);
    }
}
