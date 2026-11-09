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

    public function test_subscript_and_superscript_roundtrip_through_docx(): void
    {
        $path = sys_get_temp_dir().'/bestie-subsup-'.uniqid().'.docx';

        $builder = new DocxBuilder;
        $builder->runs($builder->richRuns('H<sub>2</sub>O dan x<sup>2</sup>'));
        $builder->save($path);

        $zip = new \ZipArchive;
        $zip->open($path);
        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();
        $this->assertStringContainsString('w:val="subscript"', $xml);
        $this->assertStringContainsString('w:val="superscript"', $xml);

        $block = (new DocxStructureReader)->read($path)[0];
        @unlink($path);

        $this->assertSame('H2O dan x2', $block['text']);
        $this->assertStringContainsString('H<sub>2</sub>O', $block['rich']);
        $this->assertStringContainsString('x<sup>2</sup>', $block['rich']);
    }

    public function test_equation_is_rasterized_to_png(): void
    {
        $r = (new \App\Services\Rendering\MathRaster)->equation('\frac{a+b}{2} + \sqrt{x^2}');

        $this->assertStringStartsWith("\x89PNG", $r['png']);
        $this->assertGreaterThan(20, $r['w']);
        $this->assertGreaterThan(20, $r['h']);
    }

    public function test_word_table_is_read_as_generated_image(): void
    {
        $path = sys_get_temp_dir().'/bestie-tbl-'.uniqid().'.docx';
        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::CREATE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>');
        $cell = static fn (string $t): string => '<w:tc><w:p><w:r><w:t>'.$t.'</w:t></w:r></w:p></w:tc>';
        $zip->addFromString('word/document.xml', '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'
            .'<w:p><w:r><w:t>Perhatikan tabel</w:t></w:r></w:p><w:tbl><w:tr>'.$cell('Nama').$cell('Massa').'</w:tr><w:tr>'.$cell('Air').$cell('2').'</w:tr></w:tbl></w:body></w:document>');
        $zip->close();

        $reader = new DocxStructureReader;
        $blocks = $reader->read($path);

        $this->assertCount(2, $blocks);
        $this->assertSame(['generated/table-1.png'], $blocks[1]['images']);
        $this->assertStringStartsWith("\x89PNG", (string) $reader->media($path, 'generated/table-1.png'));

        @unlink($path);
    }

    public function test_menjodohkan_image_distractors_are_read(): void
    {
        $block = static fn (string $t, ?array $list = null, array $images = []): array => ['text' => $t, 'rich' => $t, 'bold' => false, 'list' => $list, 'runs' => [], 'images' => $images];
        $dec = ['num_id' => 1, 'level' => 0, 'fmt' => 'decimal'];
        $up = ['num_id' => 2, 'level' => 0, 'fmt' => 'upperLetter'];

        $result = (new \App\Services\Documents\ExamDocxParser)->parse([
            $block('B. Menjodohkan'),
            $block('Jodohkan!', $dec),
            $block('⟦IMG:m/1.png⟧ (A)', $up, ['m/1.png']),
            $block('⟦IMG:m/2.png⟧ (B)', $up, ['m/2.png']),
            $block('Pengecoh:'),
            $block('⟦IMG:m/3.png⟧⟦IMG:m/4.png⟧', null, ['m/3.png', 'm/4.png']),
        ]);

        $key = $result['items'][1]['answer_key'];
        $this->assertStringContainsString('Pengecoh: ⟦IMG:m/3.png⟧; ⟦IMG:m/4.png⟧', $key);
        $this->assertContains('m/4.png', $result['items'][1]['images']);
    }

    public function test_menjodohkan_with_equations_in_pairs_and_distractors(): void
    {
        $block = static fn (string $t, ?array $list = null): array => ['text' => $t, 'rich' => $t, 'bold' => false, 'list' => $list, 'runs' => [], 'images' => []];
        $dec = ['num_id' => 1, 'level' => 0, 'fmt' => 'decimal'];
        $up = ['num_id' => 2, 'level' => 0, 'fmt' => 'upperLetter'];

        $result = (new \App\Services\Documents\ExamDocxParser)->parse([
            $block('B. Menjodohkan'),
            $block('Jodohkan turunan berikut!', $dec),
            $block('\\(f(x)=x^2\\) (\\(2x\\))', $up),
            $block('\\(g(x)=\\frac{1}{x}\\) (\\(-\\frac{1}{x^{2}}\\))', $up),
            $block('Pengecoh:'),
            $block('\\(3x\\); \\(a;b\\)'),
        ]);

        $item = $result['items'][1];
        $this->assertSame('\\(f(x)=x^2\\)', $item['options']['A']);
        $this->assertSame("A=\\(2x\\); B=\\(-\\frac{1}{x^{2}}\\)\nPengecoh: \\(3x\\); \\(a;b\\)", $item['answer_key']);
        $this->assertSame(['\\(3x\\)', '\\(a;b\\)'], \App\Support\MatchingKey::parse($item['answer_key'])['distractors']);
    }

    public function test_ruby_pinyin_is_kept_and_roundtrips_through_word(): void
    {
        $path = sys_get_temp_dir().'/bestie-ruby-'.uniqid().'.docx';
        $doc = new \App\Services\Documents\DocxBuilder('Times New Roman', 12, 1.5, null, 'uji', true);
        $doc->runs($doc->richRuns('⟦rb:老|lǎo⟧⟦rb:师|shī⟧：好！', [], null), ['after' => 20]);
        $doc->save($path);

        $blocks = (new \App\Services\Documents\DocxStructureReader)->read($path);
        $this->assertStringContainsString('⟦rb:老|lǎo⟧⟦rb:师|shī⟧：好！', $blocks[0]['text']);
        $this->assertSame('老师 好', \App\Services\Similarity\ItemSimilarity::normalize('⟦rb:老|lǎo⟧⟦rb:师|shī⟧ 好'));

        @unlink($path);
    }

    public function test_cjk_raster_draws_pinyin_text_as_wrapped_png(): void
    {
        $this->assertTrue(\App\Services\Rendering\CjkRaster::available());

        $text = str_repeat('⟦rb:你好|nǐhǎo⟧ ', 12).'<b>⟦rb:中国|zhōngguó⟧</b>。';
        $png = (new \App\Services\Rendering\CjkRaster)->line($text);

        $this->assertStringStartsWith("\x89PNG", $png['png']);
        $this->assertLessThanOrEqual(420, $png['w']);   // dilipat agar muat di ponsel
        $this->assertGreaterThan(70, $png['h']);        // lebih dari satu baris
    }

    public function test_cbt_equation_images_fit_phone_width(): void
    {
        $raster = new \App\Services\Rendering\MathRaster;
        $long = 'T\\left( t \\right)=20+80x+3t^{2}-15t+120+\\frac{3x+4}{x-1}+\\sqrt{x^{2}+9}-7x+11+4y-3z+100';

        $free = $raster->equation($long);
        $cbt = $raster->equation($long, 360);

        $this->assertGreaterThan(360, $free['w']);
        $this->assertLessThanOrEqual(360, $cbt['w']);
        $this->assertGreaterThan($free['h'], $cbt['h']); // dilipat menjadi beberapa baris
        $this->assertLessThanOrEqual(360, $raster->equation('\\frac{'.str_repeat('1234567890', 12).'}{2}', 360)['w']);
    }

    public function test_true_false_key_survives_split_runs_and_nested_stem_lists(): void
    {
        $block = static fn (string $t, ?array $list, array $runs): array => ['text' => $t, 'rich' => $t, 'bold' => false, 'list' => $list, 'runs' => $runs, 'images' => []];
        $dec = ['num_id' => 1, 'level' => 0, 'fmt' => 'decimal'];
        $up = ['num_id' => 2, 'level' => 0, 'fmt' => 'upperLetter'];
        $r = static fn (string $t, bool $red = false): array => ['t' => $t, 'red' => $red];

        $result = (new \App\Services\Documents\ExamDocxParser)->parse([
            $block('Benar/Salah', null, [$r('Benar/Salah')]),
            $block('Tentukan kebenaran pernyataan berikut.', $dec, [$r('Tentukan kebenaran pernyataan berikut.')]),
            $block('Air H2O. (B/S)', $up, [$r('Air H2O. ('), $r('B', true), $r('/'), $r('S'), $r(')')]),
            $block('Garam. (B/S)', $up, [$r('Garam. (B'), $r('/', false), $r('S', true), $r(')')]),
            $block('Pilihan Ganda', null, [$r('Pilihan Ganda')]),
            $block('Perhatikan!', $dec, [$r('Perhatikan!')]),
            $block('Sila 1', ['num_id' => 3, 'level' => 1, 'fmt' => 'bullet'], [$r('Sila 1')]),
            $block('Pertama', ['num_id' => 5, 'level' => 0, 'fmt' => 'decimal'], [$r('Pertama')]),
            $block('Ya', ['num_id' => 7, 'level' => 0, 'fmt' => 'upperLetter'], [$r('Ya', true)]),
            $block('Tidak', ['num_id' => 7, 'level' => 0, 'fmt' => 'upperLetter'], [$r('Tidak')]),
        ]);

        $this->assertSame('A=B; B=S', $result['items'][1]['answer_key']);
        $this->assertStringContainsString("• Sila 1\n1. Pertama", $result['items'][2]['stem']);
        $this->assertSame('A', $result['items'][2]['answer_key']);
        $this->assertCount(2, $result['items']);
    }

    public function test_two_column_matching_table_is_built(): void
    {
        $path = sys_get_temp_dir().'/bestie-cols-'.uniqid().'.docx';
        $builder = new DocxBuilder;
        $builder->columns(['Pernyataan', 'Pilihan jawaban'], [['A. Lingkaran', '1. Bulat'], ['B. Persegi', '2. Kotak']], [46, 54]);
        $builder->save($path);

        $zip = new \ZipArchive;
        $zip->open($path);
        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();
        @unlink($path);

        $this->assertStringContainsString('<w:tbl>', $xml);
        $this->assertStringContainsString('A. Lingkaran', $xml);
        $this->assertStringContainsString('2. Kotak', $xml);
    }

    public function test_true_false_marker_tolerates_spacing_case_and_missing_parentheses(): void
    {
        $r = static fn (string $t, bool $red = false): array => ['t' => $t, 'red' => $red];
        $dec = ['num_id' => 1, 'level' => 0, 'fmt' => 'decimal'];
        $variants = [
            [$r('Malang adalah salah satu kota di Jawa Timur.  ('), $r('B', true), $r('/S)')],
            [$r("Malang kota.\u{00A0}\u{00A0}("), $r('B', true), $r('/S)')],
            [$r("Malang kota.\t("), $r('B', true), $r('/S) ')],
            [$r('Malang kota. ( '), $r('B', true), $r(' / S )')],
            [$r('Malang kota. ('), $r('b', true), $r('/s)')],
            [$r('Malang kota. '), $r('B', true), $r('/S')],
        ];

        foreach ($variants as $n => $runs) {
            $text = implode('', array_map(static fn (array $x): string => $x['t'], $runs));
            $result = (new \App\Services\Documents\ExamDocxParser)->parse([
                ['text' => 'Benar/Salah', 'rich' => 'Benar/Salah', 'bold' => false, 'list' => null, 'runs' => [], 'images' => []],
                ['text' => trim($text), 'rich' => $text, 'bold' => false, 'list' => $dec, 'runs' => $runs, 'images' => []],
            ]);

            $this->assertSame('B', $result['items'][1]['answer_key'], 'varian '.$n);
            $this->assertStringStartsWith('Malang', $result['items'][1]['stem']);
            $this->assertStringNotContainsStringIgnoringCase('/S', $result['items'][1]['stem'], 'varian '.$n);
            $this->assertSame([], $result['warnings'], 'varian '.$n);
        }
    }
}
