<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Documents\DocxBuilder;
use App\Services\Documents\DocxStructureReader;
use App\Services\Documents\LatexToOmml;
use PHPUnit\Framework\TestCase;

/**
 * Rumus Word → LaTeX (tampil via KaTeX) dan LaTeX → Equation Word pada naskah ekspor; gambar inline.
 */
class MathAndImageTest extends TestCase
{
    public function test_latex_is_converted_to_wellformed_omml(): void
    {
        $samples = ['\frac{15}{56}', 'x^{2}+\sqrt{y}', '\sqrt[3]{x+1}', '\sum_{i=1}^{n} i^2', '\left(\frac{a}{b}\right)', '30^{\circ}', '\log_{2} 8', '\text{luas } = \pi r^2'];

        foreach ($samples as $latex) {
            $xml = (new LatexToOmml)->convert($latex);
            $doc = new \DOMDocument;

            $this->assertTrue(@$doc->loadXML('<root xmlns:m="http://schemas.openxmlformats.org/officeDocument/2006/math">'.$xml.'</root>'), $latex);
        }

        $this->assertStringContainsString('<m:f>', (new LatexToOmml)->convert('\frac{1}{2}'));
        $this->assertStringContainsString('<m:rad>', (new LatexToOmml)->convert('\sqrt{2}'));
        $this->assertStringContainsString('<m:sSup>', (new LatexToOmml)->convert('x^2'));
    }

    public function test_exported_docx_keeps_equations_that_read_back_as_latex(): void
    {
        $path = sys_get_temp_dir().'/bestie-math-'.uniqid().'.docx';

        $builder = new DocxBuilder;
        $builder->runs([['text' => '1. Hitung '], ...$builder->richRuns('\(\frac{15}{56}\) dan \(\sqrt{x}\)')]);
        $builder->save($path);

        $texts = array_column((new DocxStructureReader)->read($path), 'text');
        @unlink($path);

        $this->assertStringContainsString('\(\frac{15}{56}\)', $texts[0]);
        $this->assertStringContainsString('\(\sqrt{x}\)', $texts[0]);
    }

    public function test_image_marker_becomes_inline_drawing(): void
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true);
        $path = sys_get_temp_dir().'/bestie-img-'.uniqid().'.docx';

        $builder = new DocxBuilder;
        $runs = $builder->richRuns('Lihat ⟦img:7⟧ lalu jawab', [], static fn (int $id): ?array => $id === 7 ? ['bytes' => (string) $png, 'ext' => 'png'] : null);
        $builder->runs($runs);
        $builder->save($path);

        $zip = new \ZipArchive;
        $zip->open($path);
        $this->assertNotFalse($zip->locateName('word/media/image1.png'));
        $this->assertStringContainsString('<wp:inline', (string) $zip->getFromName('word/document.xml'));
        $zip->close();
        @unlink($path);
    }

    public function test_bold_italic_underline_roundtrip_through_docx(): void
    {
        $path = sys_get_temp_dir().'/bestie-fmt-'.uniqid().'.docx';

        $builder = new DocxBuilder;
        $builder->runs($builder->richRuns('Biasa <b>tebal</b> <i><u>miring garis</u></i> akhir'));
        $builder->save($path);

        $zip = new \ZipArchive;
        $zip->open($path);
        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();
        $this->assertStringContainsString('<w:u w:val="single"/>', $xml);

        $block = (new DocxStructureReader)->read($path)[0];
        @unlink($path);

        $this->assertSame('Biasa tebal miring garis akhir', $block['text']);
        $this->assertStringContainsString('<b>tebal</b>', $block['rich']);
        $this->assertStringContainsString('<u>', $block['rich']);
        $this->assertStringContainsString('<i>', $block['rich']);
    }

    public function test_matching_key_supports_image_tokens_for_statements_and_choices(): void
    {
        $key = "A=⟦img:11⟧; B=Surabaya\nPengecoh: ⟦img:12⟧; Malang";
        $parsed = \App\Support\MatchingKey::parse($key);

        $this->assertSame('⟦img:11⟧', $parsed['pairs']['A']);
        $this->assertContains('⟦img:12⟧', $parsed['choices']);
        $this->assertCount(4, $parsed['choices']);
    }
}
