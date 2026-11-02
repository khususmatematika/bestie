<?php

declare(strict_types=1);

namespace App\Services\Documents;

use RuntimeException;
use ZipArchive;

/**
 * Penulis .docx minimal (paragraf teks, tebal opsional) untuk template unduhan.
 * Dokumen hasilnya dapat dibuka Word/LibreOffice dan dibaca ulang oleh DocxTextReader.
 */
final class MinimalDocxWriter
{
    /**
     * @param  list<string|array{text: string, bold?: bool}>  $paragraphs
     */
    public function write(string $path, array $paragraphs): void
    {
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Tidak dapat membuat berkas DOCX.');
        }

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            .'</Types>');

        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            .'</Relationships>');

        $body = '';

        foreach ($paragraphs as $paragraph) {
            $text = is_array($paragraph) ? $paragraph['text'] : $paragraph;
            $bold = is_array($paragraph) && ($paragraph['bold'] ?? false);

            $body .= '<w:p><w:r>'.($bold ? '<w:rPr><w:b/></w:rPr>' : '')
                .'<w:t xml:space="preserve">'.htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</w:t></w:r></w:p>';
        }

        $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'
            .$body
            .'<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1134" w:right="1134" w:bottom="1134" w:left="1134" w:header="708" w:footer="708" w:gutter="0"/></w:sectPr>'
            .'</w:body></w:document>');

        $zip->close();
    }
}
