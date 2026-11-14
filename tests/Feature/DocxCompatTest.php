<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Documents\DocxBuilder;
use Tests\TestCase;
use ZipArchive;

final class DocxCompatTest extends TestCase
{
    public function test_docx_does_not_stretch_lines_ending_with_manual_breaks(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'dx').'.docx';
        $doc = new DocxBuilder;
        $doc->runs([['text' => "Sofia : Hallo, bist du Emil?\nEmil : Ja, hallo."]], ['align' => 'both']);
        $doc->save($path);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path));
        $this->assertStringContainsString('doNotExpandShiftReturn', (string) $zip->getFromName('word/settings.xml'));
        $this->assertStringContainsString('settings.xml', (string) $zip->getFromName('word/_rels/document.xml.rels'));
        $this->assertStringContainsString('settings+xml', (string) $zip->getFromName('[Content_Types].xml'));
        $zip->close();
        unlink($path);
    }
}
