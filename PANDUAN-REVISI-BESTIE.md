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

---

## Mapel awal kosong
`php artisan db:seed` kini hanya membuat peran dan tingkat X/XI/XII (`GradeLevelSeeder`). Mapel diisi admin lewat menu **Mapel** atau **Impor Mapel**. `ReferenceSeeder` (14 mapel contoh) tetap ada untuk tes, tidak dipanggil `db:seed`.

---

## Kecepatan (impor & proses lama)

**Penyebab utama di kode (sudah diperbaiki):**
1. Impor guru, ekstraksi naskah, dan PDF dikirim ke **antrian**; tanpa `php artisan queue:work` yang hidup, semuanya menunggu selamanya. Kini berjalan otomatis **segera setelah respons dikirim** (tanpa worker). Untuk kembali memakai worker: `BANKSOAL_INLINE_JOBS=false`.
2. Font Google (Plus Jakarta Sans, Source Serif 4, JetBrains Mono) dimuat dari internet — bila server/jaringan sekolah lambat atau offline, **setiap halaman tertahan**. Kini font dipasang lokal lewat `@fontsource-variable/*` (CSP ikut diperketat).

**Setelan server yang wajib (Proxmox) — `.env` & perintah:**
```bash
composer install --no-dev -o
php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan event:cache
```
```dotenv
APP_DEBUG=false
BCRYPT_ROUNDS=10          # default 12; impor 100 guru jadi ±4x lebih cepat, tetap aman
CACHE_STORE=file          # cache tidak perlu ke database
DB_HOST=127.0.0.1         # pakai IP, bukan nama host (hindari DNS lambat)
```
- Aktifkan **OPcache** di `php.ini`: `opcache.enable=1`, `opcache.memory_consumption=256`, `opcache.validate_timestamps=0` (jalankan `systemctl reload php8.x-fpm` setelah deploy).
- MySQL: tambahkan `skip-name-resolve` di `my.cnf`; beri VM/LXC minimal 2 vCPU & 2 GB RAM.
- Nginx: aktifkan `gzip on;` untuk css/js/json.
- Setelah menimpa berkas: `npm install && npm run build`.

---

## Soal menjodohkan dengan pengecoh
Pilihan jawaban kini boleh **lebih banyak** daripada pernyataan (mis. 5 pernyataan → 6+ pilihan, 5 benar + pengecoh).

**Format di naskah Word** (template baru sudah memuat contohnya):
```
6. Jodohkanlah setiap ibu kota provinsi berikut dengan provinsinya!
   A. Surabaya (Jawa Timur)        ← jawaban benar di dalam tanda kurung, font MERAH
   B. Bandung (Jawa Barat)
   ...
   Pengecoh: Sulawesi Selatan; Kalimantan Timur     ← satu baris, dipisah titik koma
```
- Kunci tersimpan: `A=Jawa Timur; B=Jawa Barat; …` + baris `Pengecoh: …`. Tanpa baris pengecoh, soal lama tetap terbaca seperti sebelumnya.
- Halaman soal menampilkan **Pilihan jawaban** (benar = hijau, pengecoh = kuning). Formulir edit punya kolom kunci 3 baris dan penghitung "n pernyataan · m pilihan jawaban" (peringatan bila m ≤ n).
- Naskah final mencetak baris **"Pilihan jawaban: …"** (urut abjad, tidak membocorkan kunci); lembar kunci memuat pasangan + pengecoh.
- Template naskah & kisi-kisi serta Panduan sudah diperbarui. **[TERMINAL]** `npm run build && php artisan test`.

---

## Kolom wajib kisi-kisi
Yang **wajib**: **Indikator Soal**, **No. Soal**, **Bentuk Soal**. Capaian Pembelajaran, Materi, Submateri, dan Level Kognitif **opsional**.
- Template kisi-kisi: tiga judul kolom wajib berwarna oranye; lembar Petunjuk menandai WAJIB/OPSIONAL.
- Saat unggah, indikator atau bentuk soal yang kosong menghasilkan peringatan per nomor (nomor kosong tetap dilewati).
- Halaman Panduan diperbarui. **[TERMINAL]** `npm run build && php artisan test`.

---

## "40 nomor belum lengkap" pada naskah Bahasa Indonesia

**Penyebab:** naskah Word memakai penomoran otomatis, tetapi nomor soalnya berada pada *tingkat daftar* 6 (PG) dan 2 (PGK/Menjodohkan/Benar-Salah), bukan tingkat 0 seperti template. Pembaca naskah hanya mengenali tingkat 0 → **0 soal terbaca**, sehingga 40 nomor dibuat hanya dari kisi-kisi (tanpa teks soal & kunci).

**Perbaikan:**
- Nomor soal kini dikenali pada tingkat daftar berapa pun (daftar angka bersarang di dalam stimulus tetap dianggap teks).
- **Benar/Salah beberapa pernyataan** (A, B, C masing-masing "(B/S)" dengan huruf merah) kini didukung. Kunci: `A=B; B=S; C=B`. Halaman soal menampilkan label Benar/Salah per pernyataan; naskah final mencetak "(Benar / Salah)" di tiap pernyataan; formulir edit menampilkan kolom pernyataan.
- Pesan "belum lengkap" kini menyebut kolom yang benar-benar dicek (teks soal, indikator soal, kunci jawaban, pilihan). Bobot tidak diwajibkan.
- Hasil uji dengan berkas Anda: 40 soal terbaca (20 PG, 10 PGK, 5 Menjodohkan, 5 Benar/Salah) dan semua cocok dengan kisi-kisi tanpa peringatan.

**Yang masih perlu Anda isi: nomor 17** — pada naskah tidak ada pilihan yang diberi **warna merah**, jadi kunci kosong. Beri warna merah pada pilihan yang benar (judul penelitian: *"menambahkan lokasi dan subjek penelitian"* bila itu kuncinya), lalu unggah ulang / isi kunci di halaman soal.

**[TERMINAL]** `npm run build && php artisan test`, lalu unggah ulang naskah pada paket draft (ekstraksi ulang).

---

## Template kisi-kisi Word (.docx)
- Template kisi-kisi kini tersedia juga dalam **Word (.docx)**: tabel lanskap berjudul kolom standar, kolom wajib (Indikator Soal, No. Soal, Bentuk Soal) berjudul oranye, contoh nomor 1–8. Unduh di halaman **Buat paket** (dua tombol: .docx dan .xlsx) dan di **Panduan**.
- Keterangan unggah kini: **Kisi-kisi (.docx atau .xlsx)** (sebelumnya tertulis .xlsx/.csv).
- Hasil uji: template Word terbaca kembali 8 nomor tanpa peringatan.
- **[TERMINAL]** `npm run build && php artisan test`.

## Format teks (tebal/miring/garis bawah) & menjodohkan bergambar

- **Impor Word**: tebal, miring, dan garis bawah pada naskah ikut terbaca (disimpan sebagai tag `<b>`, `<i>`, `<u>` di teks soal, pilihan, dan kunci). Warna merah tetap penanda kunci.
- **Edit soal**: tombol **B / I / U** di atas kolom soal, atau blok teks lalu Ctrl+B / Ctrl+I / Ctrl+U (berlaku di teks soal, pilihan/pernyataan, kunci menjodohkan, pedoman penskoran).
- **Tampil & ekspor DOCX**: tag dirender sebagai tebal/miring/garis bawah (tanpa v-html) dan dikonversi menjadi format Word asli.
- **Menjodohkan teks↔gambar / gambar↔gambar**: pernyataan (A–E), jawaban pada kunci (`A=⟦img:ID⟧`), dan pengecoh (`Pengecoh: ⟦img:ID⟧; teks`) boleh gambar. Di editor, tempel gambar (Ctrl+V) langsung pada kolom pernyataan atau kunci. Di Word, sisipkan gambar pada paragraf pernyataan, pada jawaban `( … )`, dan pada paragraf `Pengecoh:`.
- **Naskah ekspor**: bila ada pilihan bergambar, "Pilihan jawaban" dicetak bernomor (1., 2., …) dan lembar kunci menulis `A = pilihan 2`.
- Setelah menimpa berkas: `npm run build` lalu `php artisan test`.

## Tampilan validator: soal menjodohkan dengan "tali"

Di halaman telaah validator, pernyataan (kiri) dihubungkan dengan jawaban benarnya (kanan) memakai garis lengkung berwarna; pilihan pengecoh (garis putus-putus) tidak bertali. Mendukung teks, rumus, dan gambar. Komponen: `MatchingRopes.vue` (dipakai `ItemSoal` dengan prop `ropes`).

## Perbaikan tampilan & naskah (batch November)

- Soal menjodohkan di sisi guru kini juga memakai "tali"; kunci jawaban menjodohkan ditulis satu baris per pasangan (di layar dan pada lembar kunci naskah), pengecoh di baris terakhir.
- Template naskah: baris "Pengecoh:" tidak lagi miring.
- Naskah final: halaman kisi-kisi landscape (lalu rekap), naskah soal dan lembar kunci tetap portrait.
- Identitas "Kelas / Fase" tidak lagi menulis "Fase Fase E".
- Karakter animasi login memakai seragam khaki.
- Admin › Monitoring Paket: ikon tempat sampah per paket menghapus **paket** (bukan butir soal). Paket berstatus Fix atau Terkunci tidak dapat dihapus. Tercatat di log aktivitas.

## Revisi admin: penugasan mengajar

- Form akun guru tidak lagi memuat bagian "Penugasan mengajar".
- Menu **Penugasan Mengajar** langsung menampilkan semua mapel aktif per kelas beserta fasenya, urut abjad nama mapel. Admin tinggal memilih guru pada tiap baris (bisa lebih dari satu guru; ikon X melepas guru). Tersedia pencarian, filter "Belum ada guru", pilihan semester, dan "Salin dari semester lain" (rollover). Semester arsip hanya-baca.

Pembaruan: setiap mapel-kelas hanya punya **satu guru** (dropdown "Pilih guru…"; memilih guru lain menggantikan yang lama, memilih kosong melepas). Di atas tabel tampil ringkasan: total mapel-kelas, penugasan terisi, dan yang belum ada guru.

## Naskah untuk CBT sekolah

Setelah paket berstatus **Fix/Terkunci**, panel Unduhan (guru dan validator) menampilkan **Unduh naskah CBT (DOCX)**, berformat "Template Ujian CBT" sekolah: `@META-NOMOR`, `@META-JENIS`, `@META-MATERI`, `@META-NILAI` (bobot), `@KONTEN-SOAL`, `@KONTEN-OPSI` (daftar huruf), `@META-KUNCI`, `@KONTEN-KUNCI`, `@KONTEN-PERNYATAAN-n`, `@PERNYATAAN-KUNCI-n`.

Pemetaan jenis: Pilihan ganda → PG; PG kompleks → PGK (kunci dipisah koma); Benar/Salah → BENAR SALAH (satu pernyataan atau beberapa); Isian dan Uraian → ESSAY (kunci/pedoman penskoran pada `@KONTEN-KUNCI`); **Menjodohkan → ESSAY** (template CBT tidak punya jenis menjodohkan; pasangan ditulis pada soal dan kunci). Gambar diletakkan pada paragraf sendiri; rumus berupa Equation Word (template CBT menyarankan gambar untuk rumus, jadi periksa hasilnya saat unggah pertama).

## Subskrip, superskrip, rumus, dan gambar pada naskah CBT

- **Subskrip/superskrip** dari Word (mis. H₂O, x²) ikut terbaca (tag `<sub>`/`<sup>`), tampil pada telaah, dan tertulis kembali sebagai subskrip/superskrip Word pada naskah final dan naskah CBT. Di editor soal: tombol **x₂** dan **x²** (atau Ctrl+= dan Ctrl+Shift+=).
- **Rumus**: Equation Word otomatis terbaca dan tampil (KaTeX). Di editor ada tombol **∑ Rumus** berisi palet (pecahan, akar, pangkat, indeks, sigma, integral, limit, logaritma, trigonometri, simbol ≤ ≥ ≠ π θ dst.) yang menyisipkan rumus di posisi kursor; pratinjau tampil di bawah kolom.
- **Naskah CBT**: bila soal bergambar, `@KONTEN-SOAL:` berdiri sendiri, gambar tepat di bawahnya, lalu teks soal di bawah gambar.

## Pengecoh gambar, tabel Word, dan rumus pada naskah CBT

1. **Pengecoh menjodohkan berupa gambar** kini terbaca. Baris `Pengecoh:` boleh kosong dan gambarnya diletakkan di baris/paragraf berikutnya, atau gambar langsung di baris yang sama. Setiap gambar dihitung sebagai satu pengecoh.
2. **Tabel Word** kini dibaca saat unggah naskah: tabel digambar ulang menjadi gambar PNG (font STIX di `resources/fonts`) dan menjadi gambar milik soal tersebut, sehingga tampil di web, kisi-kisi, dan CBT. Tabel yang berisi daftar bernomor (naskah yang ditata dengan tabel) tidak diubah.
3. **Naskah template CBT** (paket Fix): rumus `\( … \)` digambar menjadi gambar PNG sebaris (sejajar baris teks), bukan Equation Word, sehingga terbaca CBT sekolah. Subskrip/superskrip tetap teks biasa.
4. Lisensi font: `resources/fonts/LICENSE_STIX` ikut disertakan.

Catatan: soal lama yang tabelnya sudah terlanjur hilang perlu diunggah ulang agar tabelnya terbaca.

## Soal bernomor/berbutir di dalam soal, dan kunci Benar/Salah

1. **Daftar di dalam soal** (bullet, angka 1. 2., huruf a. b., romawi) kini terbaca sebagai bagian soal dengan label ditulis ulang (nomor otomatis Word tidak ada di teks mentah). Daftar angka tingkat 0 yang berbeda dari nomor soal tidak lagi dianggap soal baru pada PG/PG kompleks/menjodohkan. Daftar huruf kecil/bersarang sebelum pilihan A–E menjadi bagian soal, bukan pilihan.
2. **Kunci Benar/Salah**: "(B/S)" dibaca per karakter beserta warnanya, sehingga tetap terbaca walau Word memecahnya menjadi beberapa potongan (ejaan, subskrip, revisi).
3. **Template naskah**: bagian Benar/Salah kini berupa soal "Tentukan kebenaran pernyataan berikut." dengan pernyataan berhuruf A, B, C, masing-masing diakhiri "(B/S)" (contoh dengan H₂O dan Na⁺). Tes template diperbarui (kunci `A=B; B=S`).

## Batas gambar per soal

Soal menjodohkan bergambar (5 pasang = 10 gambar + pengecoh) terpotong karena batas 8 gambar per soal pada unggah naskah, sehingga pasangan E dan pengecoh tidak terbaca. Batas dinaikkan menjadi 30 gambar per soal (sama dengan batas tempel gambar) dan 300 per paket; gambar yang melebihi batas dihitung sebagai dilewati.

## Menjodohkan: naskah final seperti BESTIE (tanpa tali) dan tanpa gambar ganda

1. **Naskah final Word**: soal menjodohkan ditata dua kolom tanpa garis, sama seperti tampilan BESTIE tetapi tanpa tali: pernyataan A, B, C di kiri, pilihan jawaban (pasangan benar + pengecoh, urutan tidak membocorkan kunci) di kanan. Pilihan bergambar diberi nomor (1., 2., …) yang dipakai kunci ("A = pilihan 2").
2. **Gambar ganda**: gambar yang hanya dirujuk oleh kunci/pilihan (menjodohkan gambar-gambar atau gambar-teks) tidak lagi ditampilkan sekali lagi sebagai galeri di bawah soal, baik di web maupun di naskah Word. Yang tampil hanya di bagian bertali (web) atau kolom pilihan (Word).

## Penanda Benar/Salah lebih toleran

"(B/S)" kini dikenali walau ada spasi ganda, NBSP, tab, spasi di dalam tanda kurung ("( B / S )"), huruf kecil ("(b/s)"), titik setelahnya, atau tanpa tanda kurung di akhir kalimat. Kunci tetap diambil dari huruf berwarna merah; bila seluruh "(B/S)" berwarna merah (ambigu) atau tidak ada yang merah, muncul peringatan agar guru menandai ulang.

## Pemilih berkas baru (FilePicker)

Tombol "Choose File" bawaan browser yang tipis diganti komponen `FilePicker.vue`: kotak putus-putus dengan ikon, tombol "Pilih berkas" yang jelas, dukungan tarik-lepas, nama dan ukuran berkas terpilih (kotak berubah hijau), serta tautan "Hapus pilihan". Dipakai pada unggah kisi-kisi/naskah di halaman paket, unggah ulang naskah revisi, dan impor arsip admin.

## Letak "Unggah / ganti berkas"

Pada halaman paket guru, kartu "Unggah / ganti berkas" dipindah ke atas daftar nomor soal, sejajar (dua kolom) dengan kartu "Kirim ke validator". Daftar berkas yang sudah diunggah tetap di kolom kanan bawah.

Pembaruan: bagian atas halaman paket guru kini tiga kolom sejajar — **Kisi-kisi**, **Naskah soal**, dan **Kirim ke validator** (layar sempit menumpuk ke bawah).

## Arsip soal, pemilih berkas seragam, persiapan awal, dan simpan kata sandi

1. **Arsip soal** didesain ulang: kartu impor bertahap (Identitas arsip → Berkas naskah), daftar arsip berupa kartu dengan ikon, lencana tahun/kelas/jumlah butir, dan jumlah berkas.
2. **Semua pemilih berkas** memakai `FilePicker` (kotak tarik-lepas dengan tombol "Pilih berkas"): buat paket, unggah di halaman paket, unggah ulang revisi, lampiran revisi butir, impor arsip, impor guru, impor mapel.
3. **Persiapan awal (dashboard admin)**: langkah yang sudah terlaksana dicoret dengan garis merah tebal.
4. **Login**: kolom username/kata sandi tidak lagi dikunci selama animasi; atribut `name`/`autocomplete` (`username`, `current-password`) dipasang dan, pada peramban Chromium, kredensial ditawarkan untuk disimpan setelah login berhasil. Penyimpanan dilakukan oleh peramban/pengelola kata sandi pengguna — bukan oleh aplikasi dan tidak ada kredensial di `.env`.

Pembaruan arsip soal: tata letak memakai *container query* (mengikuti lebar area konten, jadi tetap rapi saat sidebar dibuka/dilipat, bukan hanya lebar layar), dan kartu "Impor naskah lama" bisa dilipat sehingga daftar arsip memakai seluruh lebar.

## Redesain dashboard admin, validator, dan guru

Ketiga dashboard memakai kerangka yang sama (komponen baru di `Components/dashboard`):
- **DashboardHero**: banner sapaan dengan aksi utama dan satu angka kunci (guru: tenggat terdekat; validator: jumlah yang menunggu; admin: persentase persiapan awal).
- **SectionCard**: kartu berjudul dengan tautan "lihat semua" dan jumlah.
- **DeadlineList**: tenggat berupa blok tanggal berwarna (biru = aman, kuning = ≤3 hari, merah = lewat).
- **StatCard** mendapat keterangan kecil (`hint`); tampil 2 kolom di ponsel.
- Guru: peringatan merah bila ada nomor perlu diganti, tombol "Buat paket soal", kartu penugasan sebagai pintu masuk membuat paket.
- Validator: antrean, tenggat validasi, dan panduan 4 langkah bergambar.
- Admin: persiapan awal (dicoret merah bila selesai), semester, tenggat, status paket, kemajuan per mapel, penugasan yang belum membuat paket.
Tata letak memakai *container query*, sehingga ikut menyesuaikan saat sidebar dibuka/dilipat. Tidak ada perubahan backend.

## Revisi dashboard admin, impor mapel, paket guru, judul otomatis, jenis asesmen

1. **Dashboard admin**: "Belum membuat paket" kini hanya kartu jumlah; klik "Lihat guru" membuka jendela berisi nama guru beserta mapel/kelasnya (dikelompokkan per guru, ada pencarian). Persiapan awal menjadi deretan langkah mendatar (tidak ada ruang kosong), dan kolom kanan memuat ringkasan, semester, dan tenggat.
2. **Impor mapel**: tombol "Impor dari Excel" dan halaman impor dipercantik (tombol besar dengan status, tombol template beraksen).
3. **Paket soal saya (guru)**: tampilan kartu, filter status berupa pil, penugasan yang belum punya paket tampil sebagai kartu "Buat paket".
4. **Judul paket otomatis**: kolom judul dihapus dari "Buat paket soal"; judul dibuat otomatis (mapel, kelas, periode).
5. **Jenis asesmen** pada periode: Sumatif Akhir Semester (SAS), Sumatif Akhir Tahun (SAT), Sumatif Akhir Jenjang (SAJ). Periode lama berjenis STS tetap terbaca dan dapat disunting, tetapi tidak lagi ditawarkan untuk periode baru. Tidak perlu migrasi basis data.

## Naskah Mandarin berpinyin (ruby) dan CBT

1. Pinyin di atas hanzi (Phonetic Guide Word) kini terbaca sebagai penanda `⟦rb:hanzi|pinyin⟧`; tampil di BESTIE dengan pinyin di atas hanzi dan tetap berpinyin pada naskah final Word.
2. Ekspor CBT menggambar teks berpinyin menjadi gambar PNG (font Noto Sans SC di `resources/fonts`, subset GB2312, lisensi OFL). Satu baris = satu gambar, dilipat pada lebar 360 px agar terbaca di ponsel. Bila font/GD tidak tersedia, pinyin ditulis sebagai teks berkurung.
3. Server membutuhkan ekstensi PHP GD dengan FreeType (sudah dipakai untuk rumus).

Lebar gambar pada naskah CBT: semua gambar hasil konversi (equation dan teks Mandarin berpinyin) dibatasi maksimum 360 px layar (`CbtDocument::IMAGE_MAX_WIDTH`). Equation panjang dilipat ke beberapa baris pada operator/koma tingkat teratas; bagian yang masih terlalu lebar diperkecil proporsional (piksel tetap tajam). Gambar unggahan dibatasi 7–9 cm.

Kisi-kisi pada naskah final: kolom disamakan dengan template kisi-kisi (No., Capaian Pembelajaran, Materi, Submateri, Indikator Soal, No. Soal, Level Kognitif, Bentuk Soal). Baris dikelompokkan menurut CP > Materi > Submateri, dan sel yang sama digabung menjadi satu baris.

## Ganti akun (admin)

Admin dapat masuk sebagai guru/validator lewat menu profil (kanan atas) > "Ganti akun": cari nama/NIP/email lalu pilih "Sebagai Guru" atau "Sebagai Validator". Selama itu muncul bilah kuning di bawah layar dengan tombol "Kembali ke <nama admin>". Aturan: hanya akun aktif non-admin; ubah profil/kata sandi diblokir; awal dan akhir sesi tercatat di log aktivitas (`auth.impersonate.start/stop`), dan aktivitas lain selama itu diberi `impersonated_by` (ID admin).

### Ganti akun: pengetatan keamanan

- Sesi menyamar dibatasi waktu (bawaan 30 menit, ubah lewat `BANKSOAL_IMPERSONATE_MINUTES` di `.env`). Lewat batas, admin otomatis dikembalikan ke akunnya (log `auth.impersonate.stop` dengan `reason: expired`). Bilah bawah menampilkan jam berakhir.
- Selama menyamar, aksi keputusan validator (setujui, minta perbaikan, buka kembali) dan kirim paket oleh guru diblokir (403); admin tetap dapat membantu mengedit soal.

## Formulir soal guru: kotak teks menyesuaikan isi

Kotak teks soal, pilihan jawaban (A–E), kunci jawaban, indikator, dan pedoman skor kini tumbuh mengikuti panjang isinya (tanpa scroll ke samping/dalam kotak). Pilihan jawaban berupa kotak teks satu baris yang membungkus ke bawah; Enter pada pilihan/kunci tidak membuat baris baru. Direktif `v-autogrow` ada di `resources/js/Composables/autogrow.ts`.

## Pilihan jawaban multibaris (mis. alamat surat)

Baris baru di dalam satu pilihan jawaban kini dipertahankan: (1) paragraf lanjutan setelah pilihan di Word (`ExamDocxParser`) disimpan sebagai baris baru, bukan digabung spasi; (2) kotak edit pilihan/kunci menerima Enter; (3) tampilan memakai `whitespace-pre-line`; (4) ekspor Word/CBT mengubah baris baru menjadi `<w:br/>` (`DocxBuilder::run`). Naskah yang sudah terunggah perlu diunggah ulang agar baris barunya terbaca.

## Hapus nomor soal & pilihan jawaban berbentuk tabel

- Hapus nomor pada paket draft: nomor sesudahnya otomatis maju satu (hapus 33 → 34 menjadi 33, dst.), diproses berurutan agar tidak bentrok dengan indeks unik (`SaveDraftItem::delete`).
- Tabel pilihan: bila naskah Word memuat tabel yang kolom pertamanya huruf A, B, C, … berurutan (minimal 2 baris), tiap baris menjadi satu pilihan berupa gambar tabel (kolom huruf dibuang, judul kolom di atas huruf A ikut diulang pada tiap pilihan). Teks merah pada baris dibaca sebagai kunci. Tabel yang diletakkan tepat di bawah satu pilihan tetap menempel pada pilihan itu (cara lama).

### Pilihan jawaban: tabel di bawah huruf pilihan

Bentuk umum (huruf pilihan dari Bullets and Numbering, lalu tabel di bawahnya) kini dibaca; huruf "A." yang hanya diketik biasa tetap bukan pilihan: tabel menjadi isi pilihan itu (gambar tabel), teks merah di dalam tabel/huruf dibaca sebagai kunci. Butir daftar huruf yang kosong tidak lagi dibuang bila di bawahnya ada tabel/gambar.

### Pilihan jawaban harus memakai Bullet and Numbering

Huruf "A." yang hanya diketik biasa TIDAK dibaca sebagai pilihan; pilihan hanya dikenali bila berupa daftar huruf Word (Bullet and Numbering). Tabel di bawah butir daftar huruf yang kosong menjadi isi pilihan itu.

## Tenggat unggah & revisi: tidak menutup, hanya menandai terlambat

Tenggat unggah/revisi pada periode tidak memblokir guru; yang memblokir hanya periode ditutup, paket terkunci, atau semester diarsipkan. Paket kini diberi penanda (lencana kuning) pada daftar guru, detail guru, daftar admin, inbox dan telaah validator:
- "Lewat tenggat unggah": draft belum dikirim padahal tenggat unggah lewat; "Terlambat unggah": dikirim setelah tenggat unggah.
- "Revisi terlambat (n nomor)": nomor diunggah ulang setelah tenggat revisi; "Lewat tenggat revisi": masih ada nomor "perlu ganti" setelah tenggat revisi.
Tenggat berlaku sampai 23:59 WIB pada tanggalnya. Dihitung dari `submitted_at` dan `resubmitted_at` (tanpa kolom baru), lihat `PackagePresenter::lateness`. Tombol "Unggah ulang" pada guru adalah jalur revisi (versi baru), terpisah dari unggah awal.

### Rapikan nomor soal

Hapus nomor pada draft kini merapatkan seluruh nomor menjadi 1, 2, 3, … (celah lama ikut tertutup). Untuk paket yang sudah punya celah (mis. 30, 31, 32, 36, 37), guru menekan tombol "Rapikan nomor" di halaman paket (muncul hanya bila ada celah, hanya pada draft). Aksi tercatat `item.renumbered_draft`.
