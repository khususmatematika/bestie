# M4 — Notifikasi, dashboard, aksesibilitas, dan UAT (BESTIE)

## Yang baru
1. **Notifikasi dalam aplikasi** (ikon lonceng di bilah atas, semua peran; tanpa email):
   - Validator: paket baru dikirim guru; nomor diunggah ulang.
   - Guru: nomor diminta diganti / dibuka kembali; paket dinyatakan Fix.
   - Validator & guru: pengingat tenggat (H-2 s.d. hari H).
   Klik notifikasi membuka halaman terkait dan menandainya dibaca. Ada "Tandai semua dibaca". Hitungan diperbarui tiap pindah halaman dan setiap 90 detik.
2. **Dashboard diperkaya**
   - Guru: daftar paket periode ini dengan progres per nomor, tenggat.
   - Validator: antrean telaah (progres, nomor menunggu telaah, perlu diganti) + tenggat validasi.
   - Admin: kemajuan telaah per mapel dan daftar penugasan yang belum membuat paket.
3. **Aksesibilitas:** tautan "Lewati ke konten" (tekan Tab pertama kali), landmark konten utama, lonceng dengan label jumlah belum dibaca dan tutup dengan Esc, bilah kemajuan berperan `progressbar`, hormat pada pengaturan "kurangi gerakan".

## Cara memasang
**[VS CODE / file]** ekstrak zip ke `C:\laragon\www\banksoal` (timpa).
**[TERMINAL]**
```
php artisan migrate
npm run build
php artisan test
```
Ada 1 migrasi baru: tabel `notifications`.

**Pengingat tenggat** dikirim oleh perintah `php artisan notifications:deadlines`. Terjadwal harian pukul 07:00; agar berjalan otomatis jalankan penjadwal: saat pengembangan `php artisan schedule:work` (terminal terpisah), saat produksi cron `* * * * * php artisan schedule:run`. Uji manual: isi tenggat periode besok lalu jalankan perintah itu.

## UAT — daftar uji per peran
Tandai tiap baris setelah dicoba. Gunakan data contoh (min. 1 guru, 1 validator, 1 admin).

**Admin**
- [ ] Impor akun guru; kredensial awal muncul sekali.
- [ ] Tunjuk validator dan batasi mapel.
- [ ] Buat penugasan mengajar; salin dari semester lain.
- [ ] Atur format unduhan akhir di Pengaturan.
- [ ] Impor naskah lama ke Arsip Soal.
- [ ] Dashboard: semua kartu dapat diklik; daftar "Belum membuat paket" benar.
- [ ] Kunci paket Fix; ganti validator.

**Guru**
- [ ] Unggah kisi-kisi (.xlsx/.docx/.pdf) dan naskah (.docx/.pdf) contoh asli; nomor, bentuk soal, kunci terbaca.
- [ ] Rumus tampil seperti di Word; gambar tampil di posisinya.
- [ ] Tempel gambar (Ctrl+V) pada soal dan pilihan.
- [ ] Kirim paket dan pilih validator (atau validasi mandiri bila diizinkan).
- [ ] Menerima notifikasi "diminta diganti"; unggah ulang nomor tanpa menggeser layar.
- [ ] Unduh rekap dan naskah final saat Fix.

**Validator**
- [ ] Notifikasi paket baru; antrean di dashboard.
- [ ] Telaah per nomor: daftar periksa tercentang bawaan, hilangkan centang yang tidak terpenuhi.
- [ ] Panel kemiripan menampilkan soal arsip yang mirip.
- [ ] Tombol Terjemahkan membuka tab terjemahan.
- [ ] Setujui / minta ganti (catatan wajib); cek ulang nomor yang diunggah ulang.
- [ ] Paket otomatis Fix saat 100%.

**Lintas peran**
- [ ] Tema terang bawaan; sidebar dapat diciutkan; tampil baik di layar ponsel.
- [ ] Navigasi keyboard: Tab, "Lewati ke konten", Esc menutup lonceng/menu.
- [ ] Periode ditutup / semester arsip bersifat hanya-baca.
