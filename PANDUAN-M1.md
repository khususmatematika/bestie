# PANDUAN M1 — Paket Soal, Unggah, Inbox Validator, Telaah per Nomor

Salin isi `banksoal-m1.zip` ke `C:\laragon\www\banksoal` (timpa file yang sama).

## [TERMINAL] (di folder proyek)
```
php artisan migrate
npm run build
composer dev        # menjalankan server + antrian (WAJIB untuk ekstraksi naskah)
php artisan test    # opsional; mohon laporkan bila ada yang gagal
```
Bila tidak memakai `composer dev`, jalankan antrian di terminal terpisah: `php artisan queue:listen`.
Pastikan `vite.config.js` sudah dihapus (hanya `vite.config.ts`).

## [VS CODE]
Tidak ada yang perlu diedit manual.

## Skenario uji
1. Guru: menu **Paket Soal → Buat paket**, pilih penugasan, unduh template kisi-kisi & naskah, unggah keduanya.
2. Tunggu beberapa detik (antrian), buka paket: nomor soal terisi. Lengkapi yang kurang, lalu **Kirim ke validator**.
3. Validator: **Inbox Telaah** → ambil paket → telaah per nomor (J/K pindah nomor, A setujui, R minta ganti + catatan ≥10 karakter).
4. Guru: unggah ulang nomor berstatus *Perlu ganti*; validator menyetujui → paket otomatis **Fix** pada 100%.
5. Coba: buka kembali nomor disetujui, validasi mandiri (guru memilih dirinya, bila diizinkan di Pengaturan), dan admin **Monitoring Paket → Kunci**.

## Format naskah (DOCX)
Baris penanda `No. 1`, pilihan `A. …` berurutan A→E, lalu `Kunci:`, `Pedoman:`, `Bentuk:`, `Bobot:`.

## Batasan M1
Gambar/rumus kompleks dan PDF tidak diekstrak (hanya disimpan). Notifikasi masuk M4. Rekap/unduhan berstempel waktu = M2; deteksi kemiripan & terjemahan = M3.
