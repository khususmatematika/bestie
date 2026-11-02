<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Documents\DocxStructureReader;
use App\Services\Documents\ExamDocxParser;
use Tests\TestCase;

/**
 * Naskah dengan nomor soal pada tingkat daftar 2/6 (bukan tingkat 0) dan Benar/Salah beberapa pernyataan.
 */
class NestedNumberingTest extends TestCase
{
    private const NASKAH = __DIR__.'/../fixtures/naskah-bahasa-indonesia-40.docx';

    public function test_questions_numbered_at_any_list_level_are_read(): void
    {
        $parsed = (new ExamDocxParser)->parse((new DocxStructureReader)->read(self::NASKAH));
        $items = $parsed['items'];

        $this->assertCount(40, $items);
        $this->assertSame('pg', $items[1]['type']);
        $this->assertSame('B', $items[1]['answer_key']);
        $this->assertSame('pg_kompleks', $items[21]['type']);
        $this->assertSame('A,C,E', $items[21]['answer_key']);
        $this->assertSame('menjodohkan', $items[31]['type']);
        $this->assertSame('benar_salah', $items[36]['type']);
        $this->assertSame('A=B; B=B; C=S', $items[36]['answer_key']);
        $this->assertCount(3, $items[36]['options']);
        $this->assertStringNotContainsString('(B/S)', $items[36]['options']['A']);
    }
}
