<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Documents\BlueprintRowsParser;
use App\Services\Documents\DocumentRows;
use App\Services\Documents\DocxStructureReader;
use App\Services\Documents\DocxTextReader;
use App\Services\Documents\ExamDocxParser;
use App\Services\Documents\ItemAssembler;
use PHPUnit\Framework\TestCase;

/**
 * Format asli yang dipakai guru (naskah Word dengan penomoran otomatis, kunci teks merah, rumus, gambar;
 * kisi-kisi Word dengan sel gabungan dan kolom "No. Soal").
 */
class RealFormatTest extends TestCase
{
    private const NASKAH = __DIR__.'/../fixtures/naskah-asat-matematika-xi.docx';

    private const KISI = __DIR__.'/../fixtures/kisi-kisi-asat-matematika-xi.docx';

    public function test_naskah_is_parsed_per_number_with_type_key_and_images(): void
    {
        $blocks = (new DocxStructureReader)->read(self::NASKAH);
        $parser = new ExamDocxParser;

        $this->assertTrue($parser->supports($blocks));

        $result = $parser->parse($blocks);
        $items = $result['items'];

        $this->assertCount(21, $items);
        $this->assertSame([], $result['warnings']);

        foreach (range(1, 10) as $n) {
            $this->assertSame('pg', $items[$n]['type'], "No. {$n}");
            $this->assertCount(5, $items[$n]['options']);
        }

        foreach (range(11, 15) as $n) {
            $this->assertSame('pg_kompleks', $items[$n]['type']);
        }

        foreach (range(16, 20) as $n) {
            $this->assertSame('benar_salah', $items[$n]['type']);
            $this->assertSame([], $items[$n]['options']);
        }

        $this->assertSame('menjodohkan', $items[21]['type']);

        // Kunci dari teks merah.
        $this->assertSame('C', $items[1]['answer_key']);
        $this->assertSame('B,D,E', $items[11]['answer_key']);
        $this->assertSame('S', $items[16]['answer_key']);
        $this->assertSame('B', $items[17]['answer_key']);
        $this->assertSame('A=150; B=120; C=75; D=105; E=40', $items[21]['answer_key']);

        // Penanda "(B/S)" tidak ikut di teks soal; rumus pecahan terbaca.
        $this->assertStringNotContainsString('(B/S)', $items[17]['stem']);
        $this->assertSame('\\(\\frac{15}{56}\\)', $items[8]['options']['B']);

        // Stimulus bullet digabung ke teks soal.
        $this->assertStringContainsString('• Nomor BIB', $items[10]['stem']);

        $withImages = array_keys(array_filter($items, static fn (array $i): bool => $i['images'] !== []));
        $this->assertSame([4, 5, 10, 11, 12, 18], $withImages);

        // Gambar ditandai pada posisinya di teks (soal atau pilihan), bukan hanya dilampirkan.
        $all = $items[4]['stem'].' '.implode(' ', $items[4]['options']);
        $this->assertStringContainsString('⟦IMG:word/media/', $all);
    }

    public function test_kisi_kisi_table_reads_no_soal_and_inherits_merged_cells(): void
    {
        $rows = DocumentRows::fromTables((new DocxTextReader)->read(self::KISI)['tables']);
        $parsed = (new BlueprintRowsParser)->parse($rows);

        $this->assertCount(21, $parsed['rows']);
        $this->assertSame([], $parsed['warnings']);

        $one = $parsed['rows'][1];
        $this->assertSame('pg', $one['type']);
        $this->assertSame('C3 Aplikasi', $one['blueprint']['level']);
        $this->assertSame('Lingkaran', $one['blueprint']['materi']);
        $this->assertSame('Keliling lingkaran', $one['blueprint']['submateri']);

        // Nomor 5 mewarisi CP/materi/submateri dari sel gabungan di atasnya.
        $five = $parsed['rows'][5];
        $this->assertSame('Keliling lingkaran', $five['blueprint']['submateri']);
        $this->assertStringStartsWith('Di akhir fase F', $five['blueprint']['cp']);

        $this->assertSame('benar_salah', $parsed['rows'][16]['type']);
        $this->assertSame('menjodohkan', $parsed['rows'][21]['type']);
        $this->assertSame('Kombinatorika', $parsed['rows'][6]['blueprint']['materi']);
    }

    public function test_naskah_and_kisi_kisi_are_assembled_without_gaps(): void
    {
        $naskah = (new ExamDocxParser)->parse((new DocxStructureReader)->read(self::NASKAH))['items'];
        $kisi = (new BlueprintRowsParser)->parse(DocumentRows::fromTables((new DocxTextReader)->read(self::KISI)['tables']))['rows'];

        $assembled = (new ItemAssembler)->assemble($naskah, $kisi);

        $this->assertSame([], $assembled['warnings']);
        $this->assertCount(21, $assembled['items']);

        foreach ($assembled['items'] as $row) {
            $content = $row['content'];
            $this->assertNotSame('', $content['blueprint']['indikator'], 'indikator No. '.$row['number']);
            $this->assertNotSame('', $content['answer_key'], 'kunci No. '.$row['number']);
            $this->assertNotSame('', $content['stem'], 'soal No. '.$row['number']);
        }
    }
}
