# PANDUAN M2 — Rekap, Unduhan Berstempel Waktu, Format Admin, Ekspor Final

Salin isi `banksoal-m2.zip` ke `C:\laragon\www\banksoal` (timpa file yang sama).

## [TERMINAL]
```
php artisan migrate
npm run build
composer dev          # antrian wajib jalan: naskah final dibuat di antrian
php artisan test
```

## [VS CODE]
Tidak ada yang perlu diedit. (Opsional, untuk PDF: tambahkan ke `.env`
`BANKSOAL_SOFFICE="C:\Program Files\LibreOffice\program\soffice.exe"` bila LibreOffice terpasang, lalu `php artisan config:clear`.)

## Yang baru
- **Panel Unduhan** di halaman paket guru dan halaman telaah validator:
  - *Rekap validasi (XLSX)*: nomor, bentuk, status, validator, catatan terakhir, waktu validasi, waktu upload ulang, versi.
  - *Soal tervalidasi (DOCX)*: hanya nomor yang disetujui, dengan keterangan waktu validasi/upload ulang; header "BELUM FINAL" sampai paket Fix. Dapat dibatasi nomor: `?numbers=1,3,5`.
  - *Naskah final*: dibuat otomatis di antrian begitu paket mencapai Fix; unduhan dimulai otomatis saat siap. Memuat nomor dokumen dan hash SHA-256.
- **Admin → Pengaturan → Format naskah final**: font, ukuran, margin, baris kop tambahan, awalan nomor dokumen, footer, lembar kunci, kisi-kisi, rekap.
- **Admin → Monitoring Paket**: ikon unduh rekap per paket.
- Semua unduhan dicatat di Log Aktivitas; akses hanya guru pemilik, validator terkait, dan admin.

## Skenario uji
1. Selesaikan telaah sebuah paket sampai 100% (Fix).
2. Buka paket sebagai guru: panel Unduhan menampilkan "sedang disiapkan", lalu DOCX terunduh otomatis.
3. Ubah format di Pengaturan, lalu buka kembali nomor dan setujui lagi: naskah final baru memakai format terbaru.
4. Unduh rekap sebelum Fix: lihat kolom waktu validasi dan upload ulang.

## Batasan M2
PDF hanya bila LibreOffice dikonfigurasi (DOCX selalu ada). Template format berupa pengaturan (bukan DOCX placeholder unggahan) dan berlaku global; template per mapel/periode dan dwibahasa menyusul. Notifikasi "siap" saat ini lewat tampilan halaman (notifikasi penuh di M4).
