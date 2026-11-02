# Format Asli Naskah & Kisi-kisi (sesuai contoh ASAT Matematika XI)

## [TERMINAL] (di folder proyek)
```
php artisan migrate
composer require smalot/pdfparser:^2.12     # lewati bila sudah
npm run build
php artisan test
```
Antrian harus jalan (`composer dev` / `php artisan queue:listen`).

## Cara sistem membaca NASKAH (.docx)
- Judul bagian (tebal): **Pilihan Ganda**, **Pilihan Ganda Kompleks**, **Benar/ Salah**, **Menjodohkan**, **Isian**, **Uraian**. Judul ini menentukan bentuk soal.
- Nomor soal = paragraf berdaftar angka (1., 2., …) dan **nomor mengikuti urutan** di seluruh dokumen (sama dengan "No. Soal" di kisi-kisi).
- Pilihan = daftar huruf (A–E). Bullet atau teks tanpa nomor sebelum pilihan = bagian teks soal (stimulus).
- **Kunci jawaban = teks berwarna merah**:
  - Pilihan ganda / kompleks: pilihan yang berwarna merah.
  - Benar/Salah: huruf **B** atau **S** merah pada "(B/S)".
  - Menjodohkan: nilai dalam tanda kurung merah di akhir tiap pernyataan, mis. `(150)`.
- Rumus (Equation Word) dibaca menjadi teks (pecahan `a/b`, pangkat `x^2`, akar `√(…)`).
- **Gambar** pada soal disimpan dan tampil di halaman telaah serta ikut di naskah final.
- Warna oranye (penanda revisi) diabaikan. Bobot tidak wajib.

## Cara sistem membaca KISI-KISI (.docx / .xlsx / .pdf)
Kolom: No., Capaian Pembelajaran, Materi, Submateri, Indikator Soal, **No. Soal**, Level Kognitif, Bentuk Soal (PG, PGK, BS, MJD, IS, Uraian).
Sel yang digabung (Capaian Pembelajaran, Materi, Submateri) otomatis diwariskan ke baris di bawahnya.
Nomor yang dipakai = kolom **No. Soal**; naskah dan kisi-kisi dicocokkan per nomor dan bila ada yang tidak cocok (nomor/bentuk) muncul peringatan.

## Batasan
- PDF harus berisi teks (bukan hasil pindai). Kisi-kisi dari PDF dibaca dari posisi teks; periksa hasilnya (kolom Submateri yang terpecah baris bisa tertukar antar baris).
- Naskah PDF tidak membawa warna/penomoran otomatis, jadi kunci dan gambar tidak terbaca; gunakan DOCX untuk hasil lengkap.
- Gambar bertumpuk/grup gambar kompleks bisa tidak terbaca.
