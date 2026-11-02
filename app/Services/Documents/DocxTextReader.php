<?php

declare(strict_types=1);

namespace App\Services\Documents;

use DOMDocument;
use DOMElement;
use DOMXPath;
use InvalidArgumentException;
use ZipArchive;

/**
 * Membaca teks paragraf dari berkas .docx tanpa pustaka tambahan (ZipArchive + DOM).
 *
 * Keamanan: hanya membaca word/document.xml, membatasi ukuran hasil dekompresi,
 * dan tidak pernah memuat entitas eksternal (LIBXML_NONET, tanpa LIBXML_NOENT).
 */
final class DocxTextReader
{
    private const MAX_XML_BYTES = 30 * 1024 * 1024;

    private const NS_W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    private const NS_M = 'http://schemas.openxmlformats.org/officeDocument/2006/math';

    /**
     * @return array{paragraphs: list<string>, images: int, tables: list<list<list<string>>>}
     */
    public function read(string $path): array
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new InvalidArgumentException('Berkas DOCX tidak dapat dibuka. Pastikan berkas tidak rusak.');
        }

        try {
            $stat = $zip->statName('word/document.xml');

            if ($stat === false) {
                throw new InvalidArgumentException('Berkas ini bukan dokumen Word (.docx) yang valid.');
            }

            if ($stat['size'] > self::MAX_XML_BYTES) {
                throw new InvalidArgumentException('Isi dokumen terlalu besar untuk diproses.');
            }

            $xml = $zip->getFromName('word/document.xml');
        } finally {
            $zip->close();
        }

        if (! is_string($xml) || $xml === '') {
            throw new InvalidArgumentException('Isi dokumen kosong atau tidak dapat dibaca.');
        }

        $dom = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            throw new InvalidArgumentException('Isi dokumen tidak dapat dibaca (XML tidak valid).');
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', self::NS_W);
        $xpath->registerNamespace('m', self::NS_M);

        $paragraphs = [];

        foreach ($xpath->query('//w:body//w:p') ?: [] as $paragraph) {
            if ($paragraph instanceof DOMElement) {
                $paragraphs[] = $this->paragraphText($paragraph, $xpath);
            }
        }

        $images = (int) $xpath->evaluate('count(//w:drawing | //w:pict)');

        $tables = [];

        foreach ($xpath->query('//w:body/w:tbl') ?: [] as $table) {
            $rows = [];

            foreach ($xpath->query('./w:tr', $table) ?: [] as $tr) {
                $cells = [];

                foreach ($xpath->query('./w:tc', $tr) ?: [] as $tc) {
                    $texts = [];

                    foreach ($xpath->query('.//w:p', $tc) ?: [] as $p) {
                        if ($p instanceof DOMElement) {
                            $texts[] = $this->paragraphText($p, $xpath);
                        }
                    }

                    $cells[] = trim(implode(' ', array_filter($texts, static fn (string $t): bool => trim($t) !== '')));
                }

                $rows[] = $cells;
            }

            $tables[] = $rows;
        }

        return ['paragraphs' => $paragraphs, 'images' => $images, 'tables' => $tables];
    }

    private function paragraphText(DOMElement $paragraph, DOMXPath $xpath): string
    {
        $text = '';

        foreach ($xpath->query('.//w:t | .//m:t | .//w:tab | .//w:br | .//w:cr', $paragraph) ?: [] as $node) {
            $name = $node->localName;

            if ($name === 'tab') {
                $text .= "\t";
            } elseif ($name === 'br' || $name === 'cr') {
                $text .= "\n";
            } else {
                $text .= $node->textContent;
            }
        }

        return rtrim($text);
    }
}
