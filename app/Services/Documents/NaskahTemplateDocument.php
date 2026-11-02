<?php

declare(strict_types=1);

namespace App\Services\Documents;

/**
 * Template naskah soal (.docx) sesuai format yang dibaca BESTIE: judul bagian, penomoran otomatis Word,
 * pilihan berhuruf otomatis, kunci berwarna merah, rumus Equation. Berisi contoh untuk keenam bentuk soal
 * (nomor 1–8) yang selaras dengan contoh pada template kisi-kisi.
 */
final class NaskahTemplateDocument
{
    private const RED = 'FF0000';

    public function build(string $path): void
    {
        $d = new DocxBuilder('Times New Roman', 12, 2.0, null, 'Template naskah soal — BESTIE');

        $d->paragraph('TEMPLATE NASKAH SOAL', ['bold' => true, 'size' => 16, 'align' => 'center', 'after' => 40]);
        $d->paragraph('BESTIE — Berkas Evaluasi Sumatif : Telaah, Integrasi, Eksekusi', ['italic' => true, 'size' => 10, 'align' => 'center', 'after' => 160]);

        $box = ['fill' => 'EEF2FF', 'align' => 'left', 'bold' => false, 'size' => 10];
        $d->grid([100], [[$box + ['text' => implode("\n", [
            'CARA MEMAKAI (hapus kotak ini sebelum diunggah, atau biarkan: teks sebelum soal pertama tidak dibaca)',
            '• Beri JUDUL BAGIAN sebelum soal-soalnya, satu baris sendiri: Pilihan Ganda, Pilihan Ganda Kompleks, Benar/Salah, Menjodohkan, Isian, atau Uraian. Judul ini menentukan bentuk soal.',
            '• NOMOR SOAL memakai daftar bernomor otomatis Word (tombol Numbering). Nomor berlanjut di seluruh dokumen dan harus sama dengan kolom "No. Soal" pada kisi-kisi.',
            '• PILIHAN JAWABAN memakai daftar huruf otomatis (A, B, C, D, E). Mulai ulang dari A pada tiap soal (klik kanan → Restart at A).',
            '• KUNCI JAWABAN = beri WARNA FONT MERAH pada pilihan yang benar. Benar/Salah: warnai huruf B atau S pada "(B/S)". Menjodohkan: tulis nilai pasangan di dalam tanda kurung merah di akhir tiap pernyataan.',
            '• Isian dan Uraian: kunci/pedoman penskoran tidak dibaca dari naskah; isi di BESTIE pada halaman soal.',
            '• RUMUS: Insert → Equation. GAMBAR: tempel langsung di bawah teks soal. Teks tanpa nomor di antara soal dan pilihan dianggap bagian soal (stimulus).',
            '• Warna oranye dianggap penanda revisi dan diabaikan. Satu berkas maksimal 5 pilihan (A–E) per soal.',
        ])]]], 0, 10);

        $d->paragraph('', ['after' => 60]);

        $q = $d->newList('decimal'); // nomor berlanjut di seluruh dokumen

        // ---- Pilihan Ganda
        $d->paragraph('Pilihan Ganda', ['bold' => true, 'after' => 80]);

        $d->runs([['text' => 'Hasil dari '], ['math' => '\\frac{3}{4}+\\frac{1}{4}'], ['text' => ' adalah ....']], ['numId' => $q]);
        $this->options($d, ['1/2', '3/4', '1', '5/4', '3/2'], 'C');

        $d->runs([['text' => 'Bacalah kutipan berikut, lalu jawablah pertanyaannya!']], ['numId' => $q]);
        $d->paragraph('"Pendidikan adalah senjata paling ampuh yang dapat kamu gunakan untuk mengubah dunia."', ['italic' => true, 'indent' => 0.75]);
        $d->paragraph('Pesan utama kutipan tersebut adalah ....', ['indent' => 0.75]);
        $this->options($d, ['Pendidikan itu mahal', 'Pendidikan membawa perubahan besar', 'Dunia tidak dapat diubah', 'Senjata perlu dipelajari', 'Belajar itu melelahkan'], 'B');

        // ---- Pilihan Ganda Kompleks
        $d->paragraph('', ['after' => 0]);
        $d->paragraph('Pilihan Ganda Kompleks', ['bold' => true, 'after' => 80]);

        $d->runs([['text' => 'Pilihlah DUA pernyataan yang benar tentang bilangan prima!']], ['numId' => $q]);
        $this->options($d, ['Semua bilangan prima adalah bilangan ganjil', '2 adalah bilangan prima', '9 adalah bilangan prima', '7 adalah bilangan prima', '1 adalah bilangan prima'], 'B,D');

        // ---- Benar/Salah
        $d->paragraph('', ['after' => 0]);
        $d->paragraph('Benar/ Salah', ['bold' => true, 'after' => 80]);

        $d->runs([['text' => 'Bilangan prima terkecil adalah 2. ('], ['text' => 'B', 'color' => self::RED], ['text' => '/S)']], ['numId' => $q]);
        $d->runs([['text' => 'Semua bilangan genap habis dibagi 4. (B/'], ['text' => 'S', 'color' => self::RED], ['text' => ')']], ['numId' => $q]);

        // ---- Menjodohkan
        $d->paragraph('', ['after' => 0]);
        $d->paragraph('Menjodohkan', ['bold' => true, 'after' => 80]);

        $d->runs([['text' => 'Jodohkanlah setiap akar kuadrat berikut dengan hasilnya!']], ['numId' => $q]);
        $l = $d->newList('upperLetter');
        foreach ([['Akar dari 16', '4'], ['Akar dari 81', '9'], ['Akar dari 144', '12']] as [$left, $value]) {
            $d->runs([['text' => $left.' ('], ['text' => $value, 'color' => self::RED], ['text' => ')']], ['numId' => $l]);
        }

        // ---- Isian
        $d->paragraph('', ['after' => 0]);
        $d->paragraph('Isian', ['bold' => true, 'after' => 80]);
        $d->runs([['text' => 'Hasil dari 12 × 12 adalah ....']], ['numId' => $q]);

        // ---- Uraian
        $d->paragraph('', ['after' => 0]);
        $d->paragraph('Uraian', ['bold' => true, 'after' => 80]);
        $d->runs([['text' => 'Jelaskan dua manfaat membaca buku bagi pelajar, disertai alasannya!']], ['numId' => $q]);

        $d->save($path);
    }

    /**
     * @param  list<string>  $texts  teks pilihan A, B, C, …
     * @param  string  $key  huruf kunci dipisah koma (diberi warna merah)
     */
    private function options(DocxBuilder $d, array $texts, string $key): void
    {
        $keys = array_map('trim', explode(',', $key));
        $list = $d->newList('upperLetter');

        foreach ($texts as $i => $text) {
            $letter = chr(65 + $i);
            $run = ['text' => $text] + (in_array($letter, $keys, true) ? ['color' => self::RED] : []);
            $d->runs([$run], ['numId' => $list, 'after' => 20]);
        }
    }
}
