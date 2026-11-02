# M3 — Arsip, kemiripan, dan terjemahan (BESTIE)

## Isi
1. **Arsip soal (admin):** menu *Soal → Arsip Soal*. Impor naskah tahun lalu (.docx/.pdf) per mapel, tingkat (opsional), dan tahun/semester asal. Dibaca per nomor, disimpan sebagai teks saja. Paket yang berstatus Fix/terkunci pada semester lain **otomatis** ikut jadi pembanding.
2. **Kemiripan (validator):** di halaman telaah per nomor muncul panel *Kemiripan dengan arsip & paket lain* berisi sampai 5 soal paling mirip (>= 40%) dari arsip impor, paket Fix semester lama, dan paket lain pada periode yang sama (mapel sama). Ketuk baris untuk melihat soal pembandingnya. Warna: >= 70% merah (sangat mirip), >= 50% kuning. Ini hanya penanda bantu; keputusan tetap pada validator.
3. **Terjemahan (validator):** tombol *Terjemahkan* di panel Soal membuka Google Terjemahan pada tab baru dengan teks soal dan pilihan (pilih bahasa tujuan: Indonesia, Inggris, Arab, Mandarin, Jepang). Teks baru dikirim ke layanan luar saat tombol diklik, tidak otomatis. Glosarium tidak dibuat sesuai keputusan.

## Cara memasang
**[VS CODE / file]** ekstrak zip ke `C:\laragon\www\banksoal` (timpa).
**[TERMINAL]**
```
php artisan migrate
npm run build
php artisan test
```
Ada 1 migrasi baru: tabel `archive_imports` dan `archive_items`.

## Cara mencoba
1. Masuk sebagai Admin -> Arsip Soal -> pilih mapel, isi tahun asal, unggah naskah .docx lama -> "Impor ke arsip".
2. Sebagai Validator, buka paket mapel yang sama -> nomor yang teksnya mirip akan menampilkan persentase.
3. Klik *Terjemahkan* untuk membuka terjemahan.

## Batas
- Kemiripan dihitung dari teks (n-gram kata); soal dengan angka diganti tapi kalimat sama tetap terdeteksi mirip, sedangkan parafrase panjang bisa lolos.
- Gambar dan rumus tidak dibandingkan sebagai gambar; rumus dibandingkan sebagai teks LaTeX.
- Hingga 1.500 soal pembanding per pengecekan.
