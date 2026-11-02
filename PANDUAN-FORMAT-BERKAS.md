# Kisi-kisi & Naskah: Excel, Word, atau PDF

## [TERMINAL] (di folder proyek)
```
composer require smalot/pdfparser:^2.12
php artisan test
npm run build
```
`smalot/pdfparser` dipakai untuk membaca teks PDF. Antrian (`composer dev`) tetap harus jalan.

## [VS CODE]
Tidak ada yang perlu diedit.

## Format yang didukung
| Berkas | Format | Susunan |
|---|---|---|
| Kisi-kisi | .xlsx, .docx, .pdf | Tabel kolom: No, Capaian Pembelajaran, Materi, Indikator Soal, Level Kognitif, Bentuk Soal, Kunci, Bobot |
| Naskah | .docx, .pdf | Penanda `No. 1`, opsi `A. …`, `Kunci:`, `Pedoman:`, `Bentuk:`, `Bobot:` |

Template kisi-kisi (.xlsx) dan template naskah (.docx) tersedia di halaman Buat paket.
Unggah ulang seluruh naskah (untuk nomor "Perlu ganti") menerima .docx atau .pdf.

## Batasan
- PDF harus berisi teks (bukan hasil pindai/foto). PDF pindaian tidak terbaca; gunakan Word/Excel atau isi manual.
- Hasil dari PDF dapat berbeda dari aslinya (urutan teks, tabel bergabung); hasil ekstraksi selalu bisa diperiksa dan diedit sebelum dikirim.
- Gambar dan rumus kompleks tidak diekstrak pada format apa pun (muncul peringatan).
