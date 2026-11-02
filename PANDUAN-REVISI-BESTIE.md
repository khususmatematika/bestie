# Revisi BESTIE — rumus, gambar inline, daftar periksa

**BESTIE** = Berkas Evaluasi Sumatif : Telaah, Integrasi, Eksekusi.

## Yang berubah
1. Nama situs **BESTIE** (sidebar, halaman masuk, judul tab, template naskah).
2. **Rumus (Equation Word)** pada naskah .docx dibaca lalu ditampilkan dengan KaTeX (pecahan, akar, pangkat, indeks, sigma, integral, limit, matriks, sudut °, dll.). Pada unduhan DOCX, rumus kembali menjadi Equation Word asli.
3. **Gambar** ditampilkan tepat di posisinya pada teks soal atau pilihan jawaban (bukan hanya di bawah soal). Gambar yang tidak punya posisi tetap tampil di bawah soal.
4. Daftar periksa validator **tercentang semua secara bawaan**; validator tinggal menghilangkan centang pada butir yang tidak terpenuhi.
5. Tiga butir baru: *Stimulus ada dan dibutuhkan saat mengerjakan soal*; *Pilihan jawaban homogen*; *Soal dibutuhkan saat mencari jawaban*.

## Langkah
**[TERMINAL]** (folder `C:\laragon\www\banksoal`)
```
npm install
npm run build
php artisan test
```
Tidak ada migrasi baru.

**[VS CODE]** buka `.env`, ubah `APP_NAME=BESTIE`, simpan, lalu di terminal `php artisan config:clear`.

## Penting
- Naskah yang **sudah** diunggah sebelumnya harus diekstrak ulang (unggah ulang naskah) agar rumus dan penanda gambar terbentuk.
- Rumus di teks disimpan sebagai `\( ... \)` (LaTeX) dan gambar sebagai `⟦img:nomor⟧`. Pada form edit butir ada pratinjau; jangan menghapus penanda bila gambar ingin tetap tampil.
- Guru dapat mengetik rumus sendiri, mis. `\(\frac{15}{56}\)`.
- Tampilan Equation pada DOCX hasil ekspor sudah diuji sebagai OMML valid, tetapi belum dilihat di Microsoft Word; mohon dicek pada unduhan pertama.

## Tambahan: tempel gambar langsung (Ctrl+V)
Pada form isi/ubah nomor, gambar (screenshot atau salinan dari Word/browser) cukup **ditempel (Ctrl+V)** di kolom *Teks soal*, *Pilihan jawaban*, atau *Pedoman penskoran*. Gambar otomatis tersimpan dan penanda `⟦img:…⟧` muncul di posisi kursor; pratinjau tampil di bawah kolom. Tempelan teks biasa tetap berperilaku normal. Hapus penanda untuk membuang gambar dari teks. Batas: PNG/JPEG/GIF, 3 MB per gambar, 30 gambar per nomor. Hanya guru pemilik pada paket draft atau nomor yang diminta diganti.
Tidak ada migrasi; cukup `npm run build`.

## Tambahan: gambar lama, sidebar, responsif, dashboard
- **Gambar lama tidak hilang (diperbaiki):** gambar kini melekat pada nomor hanya selama penandanya `⟦img:…⟧` masih ada di teks. Saat nomor disimpan atau diunggah ulang, gambar yang penandanya dihapus/diganti dilepas (berkas disimpan hanya untuk riwayat versi). Unggah ulang dari naskah .docx juga menyimpan gambar baru dan menempatkannya di posisinya (sebelumnya penanda mentah ikut tertulis dan gambar lama tertinggal).
- **Sidebar:** tombol "Ciutkan menu" di dasar sidebar (desktop) membuat menu hanya ikon (nama muncul saat kursor diarahkan); pilihan diingat di peramban.
- **Responsif:** daftar nomor soal di halaman paket guru kini berupa baris yang membungkus (tanpa tabel dan tanpa geser kiri-kanan), termasuk tombol "Unggah ulang".
- **Dashboard:** semua kartu statistik, penugasan mengajar, tenggat, dan semester aktif kini dapat diklik menuju halaman terkait.

## Tambahan: rekap validasi dan kop
- **Rekap validasi** (unduhan XLSX dan lembar rekap pada naskah final) kini memuat kolom **Daftar periksa validator** (jumlah terpenuhi dari 10 dan butir yang tidak terpenuhi, dari telaah terakhir yang memuat daftar periksa) dan **Kemiripan soal** (skor tertinggi terhadap arsip/paket lain beserta sumbernya, dihitung saat rekap dibuat; "Tidak ada ≥ 40%" bila tidak ada).
- **Kop naskah** meniru contoh: logo di kiri; baris atas tebal (Pemerintah Provinsi…, Dinas Pendidikan), nama sekolah lebih besar, alamat dan laman di bawahnya, lalu garis ganda. Teks diatur di Admin > Pengaturan (Baris atas kop, Nama sekolah, Baris tambahan kop; kosong = bawaan SMA Negeri 1 Turen) dan logo dapat dimatikan. Berkas logo: `resources/images/kop-logo.png` (ganti berkasnya bila ingin logo lain). Ubah **Nama sekolah** di Pengaturan menjadi "SMA Negeri 1 Turen" bila masih berisi nama lama. Pilih font Arial pada Pengaturan bila ingin kop persis bergaya sans-serif seperti contoh.
- Tidak ada migrasi; jalankan `npm run build` dan `php artisan test`.

## Tambahan: logo kop di depan teks + naskah final memakai kop terbaru
- Logo kop kini **mengambang di depan teks** (tanpa pembungkus) di sisi kiri, sehingga semua baris kop rata tengah terhadap lebar halaman. Di Word, logo dapat digeser/diubah ukurannya seperti gambar biasa.
- **Penyebab naskah final belum berkop:** naskah final dibuat sekali saat paket Fix lalu dipakai ulang, sehingga naskah yang dibuat sebelum perubahan kop tetap berformat lama. Sekarang setiap naskah menyimpan "sidik format" (kop, font, nama sekolah, logo, versi tata letak). Bila format berubah, panel Unduhan menampilkan pemberitahuan kuning dengan tombol **Buat ulang naskah final**, dan permintaan baru otomatis membuat naskah baru. Naskah lama (sebelum fitur ini) juga dianggap usang sehingga tombolnya muncul.
- **Langkah:** `php artisan migrate` (1 migrasi baru: kolom `format_hash`), `npm run build`, `php artisan test`. Antrean harus berjalan (`php artisan queue:work`) agar naskah dibuat ulang. Lalu buka paket Fix dan klik **Buat ulang naskah final**.

---

## Revisi: Rekap validasi siap cetak (per kolom, berwarna)

**Isi rekap:** kop sekolah (logo + garis ganda) → judul → identitas paket (mapel, kelas, guru, semester, jumlah soal, status, dst.) → tabel per nomor soal dengan **10 kolom daftar periksa**: **biru ✓ = terpenuhi**, **merah ✗ = tidak terpenuhi**, abu – = belum ditelaah → kolom **Kemiripan tertinggi (%)** (merah muda ≥ 70%, kuning ≥ 50%) → catatan, waktu validasi, upload ulang, versi → keterangan 10 butir → tanda tangan validator.

**Unduhan** (halaman paket → Unduhan):
- *Rekap validasi siap cetak (DOCX)* — A4 lanskap.
- *Rekap validasi (XLSX)* — A4 lanskap, muat 1 halaman lebar, judul tabel berulang, nomor halaman.
- Halaman rekap di *Naskah final* juga memakai tata letak yang sama (lanskap).

**Langkah:**
1. [VS CODE] Ekstrak zip ke `C:\laragon\www\banksoal` (timpa). Berkas `app/Exports/PackageRecapExport.php` sudah tidak dipakai — hapus bila masih ada.
2. [TERMINAL] `npm run build` lalu `php artisan test`. Tidak ada migrasi baru.
3. Naskah final lama tidak perlu dihapus; tombol **Buat ulang** akan mengikuti tata letak baru otomatis.

---

## Revisi 1 Nov: naskah final cepat, akun guru, unduh massal, login, sidebar

1. **Naskah final lebih cepat** — DOCX kini dibuat langsung saat diminta (tidak lagi menunggu `queue:work`). PDF (bila `BANKSOAL_SOFFICE` diisi) menyusul di antrian dan tombol PDF muncul sendiri setelah jadi.
2. **Membagikan akun guru** — template impor punya kolom `password` (opsional, min. 8 karakter; kosong = acak). Opsi "Wajib ganti kata sandi saat login pertama" ada di halaman impor dan di form tambah guru. Kartu akun (XLSX, sekali unduh) kini memuat kolom *Masuk dengan*, kata sandi, dan alamat login. Pada modal kata sandi, tombol **Salin** menghasilkan pesan siap kirim (alamat, akun, sandi).
3. **Email ATAU NIP** — salah satu cukup (keduanya bisa dipakai login). Kolom email kini boleh kosong → **ada migrasi baru**.
4. **Penugasan mengajar di Akun Guru** — form Tambah/Ubah guru punya bagian *Penugasan mengajar* (mapel × tingkat, semester aktif), sama dengan kolom `kode_mapel`/`tingkat` pada impor. Menu Penugasan tetap ada dan sinkron.
5. **Unduh massal (admin)** — Monitoring paket → **Unduh massal (ZIP)**: naskah final, soal tervalidasi, rekap DOCX/XLSX. Centang paket tertentu, atau biarkan kosong untuk semua hasil penyaringan (maks. 80 paket, draf dilewati; paket yang tak bisa dibuat dicatat di `CATATAN.txt` dalam ZIP).
6. **Login** dibuat ulang (logo sekolah, ikon, peringatan Caps Lock, kartu form). **Sidebar ciut**: tooltip, pemisah antar grup, tanpa scrollbar, tombol ciut juga di bilah atas.

**Langkah:** [VS CODE] ekstrak zip ke `C:\laragon\www\banksoal` (timpa) → [TERMINAL] `php artisan migrate`, `npm run build`, `php artisan test`. Hapus `public\hot` bila ada. Template impor lama tetap bisa dipakai (kolom password opsional).

**Login (revisi desain):** kartu login di tengah layar dengan logo SMAN 1 Turen (`resources/images/login-logo.png`), judul BESTIE, nama sekolah, mode terang/gelap, animasi masuk halus (mati otomatis bila perangkat memilih "kurangi gerakan"). Cukup `npm run build`.

**Login beranimasi:** ilustrasi guru (gambar orisinal, SVG) berjalan menarik kartu login dengan tali; kartu meluncur masuk dari kanan, lalu guru berhenti, bernapas, dan berkedip. Animasi otomatis mati bila perangkat memilih "kurangi gerakan". Di HP (< 370 px) ilustrasi disembunyikan. Berkas: `resources/js/Components/auth/PullCharacter.vue`. Cukup `npm run build`.

**Penyesuaian login & sidebar:** animasi guru diperlambat dan dibuat santai (kartu meluncur ±3,4 detik, langkah ±1,1 detik, tanpa hentakan), kacamata dihapus. Scrollbar pada sidebar disembunyikan di semua mode (menu tetap bisa digulir). Cukup `npm run build`.

---

## Revisi 1 Nov (malam): Panduan di BESTIE, template baru, animasi guru Korpri

1. **Menu Panduan** (semua peran, ikon pelampung di sidebar; alamat `/panduan`): langkah kerja Guru/Validator/Admin (tab), status paket, aturan menulis naskah dengan contoh tampilan Word, aturan kisi-kisi, FAQ, dan tombol Cetak.
2. **Template naskah soal (.docx)** dibuat ulang sesuai format asli yang dibaca BESTIE: judul bagian, penomoran otomatis Word, pilihan huruf otomatis, kunci merah, rumus Equation; 8 contoh untuk enam bentuk soal. Sudah diuji: dibaca balik tanpa peringatan.
3. **Template kisi-kisi (.xlsx)** dibuat ulang: kolom standar (No., Capaian Pembelajaran, Materi, Submateri, Indikator Soal, No. Soal, Level Kognitif, Bentuk Soal), contoh nomor 1–8 selaras dengan naskah (dengan sel digabung), dropdown level & bentuk, lembar "Petunjuk".
4. Template kini dapat diunduh semua peran (`/templates/naskah`, `/templates/kisi-kisi`); halaman Buat Paket diperbarui.
5. **Animasi login:** guru berseragam Korpri (kemeja biru dongker, lencana, celana & sepatu hitam), jalan jauh lebih pelan (kartu meluncur ±6,5 detik, langkah ±1,8 detik).

**Langkah:** [VS CODE] ekstrak zip ke `C:\laragon\www\banksoal` (timpa; hapus `app\Services\Documents\NaskahTemplate.php` dan `app\Exports\BlueprintTemplate.php` bila masih ada) → [TERMINAL] `npm run build`, `php artisan test`. Tidak ada migrasi baru.

---

## Revisi: baju Korpri, satu tombol ciutkan, admin tanpa .env, keamanan

**Berkas berubah:** `PullCharacter.vue`, `AppLayout.vue`, `app/Console/Commands/CreateAdmin.php` (baru), `app/Http/Middleware/SecurityHeaders.php` (baru), `bootstrap/app.php`, `resources/views/app.blade.php`, `config/banksoal.php`, `database/seeders/DatabaseSeeder.php`, `AdminSeeder.php` (**dihapus**), `PANDUAN-M0.md`, `tests/Feature/SecurityTest.php` (baru).

1. **Baju guru** digambar ulang: kemeja PDH Korpri biru dongker, kerah putih, lencana Korpri, tanda pangkat di bahu, saku dada, papan nama, ikat pinggang; celana & sepatu hitam.
2. **Tombol ciutkan menu** tinggal satu: di header, di samping periode (mis. "SAS Ganjil 2026/2027").
3. **Admin tidak lagi di `.env`.** Buat akun dengan perintah interaktif (kata sandi tersembunyi, tidak masuk riwayat shell):
   - **[TERMINAL]** `php artisan bestie:create-admin`
   - Lupa sandi admin: `php artisan bestie:create-admin --reset`
   - **[VS CODE]** buka `.env`, **hapus** baris `ADMIN_NAME`, `ADMIN_EMAIL`, `ADMIN_PASSWORD`. (`DB_USERNAME`/`DB_PASSWORD` tetap di `.env` — itu standar Laravel dan `.env` tidak boleh di-commit/dibagikan.)
4. **Keamanan:** header `X-Frame-Options`, `nosniff`, `Referrer-Policy`, `Permissions-Policy`, CSP ber-nonce (aktif otomatis saat tidak memakai `npm run dev`), HSTS di HTTPS.

**Untuk server produksi** (`.env`): `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true` (bila HTTPS), `SESSION_ENCRYPT=true`.

**Terminal:**
```bash
npm run build
php artisan test
```

---

## Revisi: halaman login satu layar & saran sandi tersimpan

- Halaman login kini **selalu satu layar penuh tanpa gulir** (laptop, HP, mode lanskap): kolom tengah mengecil otomatis bila layar pendek.
- Selama animasi guru berjalan (~6,5 detik) kolom email/sandi **terkunci** dan `autocomplete` dimatikan, sehingga saran nama pengguna/sandi tersimpan peramban tidak muncul. Setelah animasi selesai, kolom aktif dan saran muncul seperti biasa. `autofocus` dihapus.
- **[TERMINAL]** `npm run build` lalu muat ulang paksa (Ctrl+F5).

---

## Revisi: naskah final "loading terus" & panduan per peran

- **Penyebab:** permintaan naskah final lama tersangkut berstatus *antri* (antrian/queue tidak berjalan) dan dipakai ulang terus, sehingga spinner tidak berhenti. Kini permintaan yang tersangkut otomatis dibuat ulang **langsung** (DOCX hanya beberapa detik) saat halaman paket dibuka atau tombol ditekan; bila gagal, pesan galat tampil dan tombol "Siapkan naskah final" muncul lagi.
- **Panduan** kini hanya menampilkan langkah untuk peran yang sedang dipakai login (tanpa tab peran lain). Template & aturan naskah hanya tampil untuk Guru.
- **[TERMINAL]** `npm run build`, lalu buka ulang halaman paket yang tadi loading.

---

## Revisi: sidebar berkategori lipat & panduan per peran

- Kategori menu sidebar (Akademik, Pengguna, Soal, Sistem) kini bisa **dilipat/dibuka** dengan klik judul kategori; kategori yang berisi halaman aktif selalu terbuka, pilihan diingat di peramban. Dengan melipat kategori yang tidak dipakai, seluruh menu terlihat tanpa gulir. Pada mode ikon semua ikon tetap tampil.
- Panduan hanya menampilkan langkah kerja peran yang sedang aktif. Bila akun punya beberapa peran, ganti peran lewat menu pengguna (kanan atas) lalu buka Panduan lagi.
- **[TERMINAL]** `npm run build`, lalu Ctrl+F5.

---

## Revisi: sidebar ciut muat tanpa gulir
Pada mode ikon, jarak antar ikon dan kategori dipadatkan (dan makin rapat di layar pendek) sehingga seluruh menu admin terlihat tanpa scroll. **[TERMINAL]** `npm run build`, Ctrl+F5.

---

## Revisi: jarak sidebar ciut & lipat kategori
- Mode ikon: jarak antar ikon kini menyesuaikan tinggi layar (lebih longgar di layar tinggi, rapat di layar pendek) sehingga rail terisi rapi tanpa scroll dan tanpa ruang kosong besar di bawah.
- Bug kategori: kategori yang berisi halaman aktif kini **bisa dilipat dan dibuka kembali** kapan saja. Berpindah halaman hanya membuka kategori halaman tujuan.
- **[TERMINAL]** `npm run build`, Ctrl+F5.

---

## Audit keamanan pra-publikasi
Lihat **PANDUAN-KEAMANAN.md** (daftar periksa `.env` produksi + ringkasan perlindungan). Perbaikan kode: akun nonaktif langsung keluar, sesi lain dihentikan saat ganti sandi/nonaktif/hapus, throttle ganti sandi & login per IP, `katex` diperbarui (`npm audit fix`).
**[TERMINAL]** `npm install && npm run build && php artisan test`

---

## Perbaikan tes (21 gagal → akun admin di tes ikut keluar)
Pengecekan akun nonaktif terlalu ketat: pengguna buatan tes yang atribut `is_active`-nya belum terisi dianggap nonaktif. Kini hanya `is_active === false` (nilai eksplisit) yang dikeluarkan. **[TERMINAL]** `php artisan test`.
