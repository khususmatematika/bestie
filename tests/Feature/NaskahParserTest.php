<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Documents\NaskahParser;
use PHPUnit\Framework\TestCase;

class NaskahParserTest extends TestCase
{
    public function test_parses_numbers_options_and_key(): void
    {
        $result = (new NaskahParser())->parse([
            'Abaikan teks pembuka',
            'No. 1', 'Ibu kota Indonesia adalah …', 'A. Bandung', 'B. Jakarta', 'C. Medan', 'D. Surabaya', 'Kunci: B',
            'No. 2', 'Jelaskan fotosintesis.', 'Bentuk: uraian', 'Pedoman: Menyebut cahaya, klorofil, CO2.', 'Bobot: 5',
        ]);

        $this->assertCount(2, $result['items']);
        $this->assertSame('B', $result['items'][1]['answer_key']);
        $this->assertSame('uraian', $result['items'][2]['type']);
    }
}
