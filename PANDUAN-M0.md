# PANDUAN M0 — BankSoal (Laravel 13 + Vue 3 + Inertia v3)

Ikuti urut. Tanda **[TERMINAL]** = ketik di terminal. Tanda **[VS CODE]** = lakukan di editor.

## 0. Prasyarat (cek sekali)

**[TERMINAL]**
```bash
php -v          # harus 8.3 atau lebih baru
composer -V
node -v         # 20 atau lebih baru
npm -v
```
Ekstensi PHP yang dibutuhkan: `pdo_sqlite` (atau `pdo_mysql`), `mbstring`, `zip`, `gd`, `xml`, `intl`, `fileinfo`.
(Impor Excel memakai `zip` dan `gd`; tanpa keduanya impor gagal.)

## 1. Buat proyek Laravel 13

**[TERMINAL]**
```bash
composer create-project laravel/laravel:^13.0 banksoal
cd banksoal
code .
```
> `code .` membuka folder di VS Code. Semua perintah berikutnya dijalankan di folder `banksoal`
> (di VS Code: menu **Terminal → New Terminal**).

## 2. Pasang paket PHP

**[TERMINAL]**
```bash
composer require inertiajs/inertia-laravel spatie/laravel-permission maatwebsite/excel
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
```
Alasan paket (rules.md §2): Inertia = jembatan Laravel↔Vue; Spatie Permission = peran (admin/validator/guru); Laravel Excel = impor akun & template.

## 3. Salin kode M0

**[VS CODE]**
1. Ekstrak `banksoal-m0.zip`.
2. Salin **seluruh isi** folder hasil ekstrak ke akar proyek `banksoal`, pilih **Replace/Timpa** bila ditanya.
   (Cara cepat: seret isi folder ke panel Explorer VS Code, atau di terminal: `cp -R /lokasi/banksoal-m0/. .`)
3. **Hapus** berkas bawaan yang tidak dipakai lagi:
   - `resources/js/app.js`
   - `resources/views/welcome.blade.php`
   - `tests/Feature/ExampleTest.php`

Berkas yang ditimpa/ditambah: `package.json`, `vite.config.ts`, `tsconfig.json`, `bootstrap/app.php`, `routes/web.php`,
`config/banksoal.php`, `lang/id/validation.php`, `app/**`, `database/migrations/2026_10_26_*`, `database/seeders/*`,
`resources/css/app.css`, `resources/js/**`, `resources/views/app.blade.php`, `tests/Feature/*`.

## 4. Atur `.env`

**[VS CODE]** buka `.env`, ubah/tambahkan baris berikut:

```dotenv
APP_NAME=BankSoal
APP_LOCALE=id
APP_FALLBACK_LOCALE=id
APP_FAKER_LOCALE=id_ID
APP_URL=http://localhost:8000

# Inertia: render sisi klien saja (tanpa server SSR)
INERTIA_SSR_ENABLED=false

# Catatan: akun admin TIDAK ditaruh di .env. Dibuat lewat `php artisan bestie:create-admin` (langkah 6).
```
Basis data bawaan = SQLite (file `database/database.sqlite`, dibuat otomatis). Untuk MySQL ubah `DB_CONNECTION=mysql`
dan isi `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`.

## 5. Pasang paket JavaScript

**[TERMINAL]**
```bash
npm install
```
(`package.json` hasil salinan sudah memuat Vue, Inertia, Tailwind 4, Vite 8, ikon Lucide.)

## 6. Buat tabel & isi data awal

**[TERMINAL]**
```bash
php artisan migrate
php artisan db:seed
php artisan bestie:create-admin
```
`db:seed` membuat peran dan tingkat X/XI/XII. **Mapel dibiarkan kosong** — isi lewat menu Mapel atau Impor Mapel. `bestie:create-admin` menanyakan
**email, nama, dan kata sandi** secara interaktif (kata sandi tidak tampil saat diketik dan tidak disimpan di `.env`).
Lupa sandi admin? Jalankan `php artisan bestie:create-admin --reset`.

(Opsional, data contoh untuk mencoba: `php artisan db:seed --class=DemoSeeder` → tahun ajaran 2026/2027,
semester Ganjil aktif, periode STS & SAS, 3 guru contoh, sandi `Password123`. Jangan dipakai di produksi.)

## 7. Jalankan aplikasi

**[TERMINAL]**
```bash
composer dev
```
Perintah ini menjalankan server web, **antrian (queue)**, dan Vite sekaligus. Buka **http://localhost:8000**.

> Antrian **wajib hidup** karena impor guru diproses di latar belakang. Bila `composer dev` tidak tersedia di
> sistem Anda, buka 3 terminal: `php artisan serve` · `npm run dev` · `php artisan queue:listen`.

Login memakai email + kata sandi yang Anda ketik pada `bestie:create-admin`.

## 8. Uji alur prosedur langkah 1–2

1. **Persiapan** — di menu **Tahun Ajaran** buat `2026/2027` → menu **Semester** buat Ganjil lalu klik **Aktifkan**
   → menu **Periode Asesmen** buat STS dan SAS. (Lewati bila sudah memakai DemoSeeder.)
2. **Langkah 1: akun guru**
   - Menu **Akun Guru → Impor Excel → Unduh template**, isi baris guru, unggah, klik **Mulai impor**.
   - Setelah status *Selesai*, klik **Unduh kartu akun** (berisi sandi awal; hanya bisa diunduh sekali).
   - Atau tambah satu per satu lewat **Tambah guru** (sandi awal tampil sekali di jendela).
3. **Langkah 2: validator** — menu **Validator → Jadikan validator** pada guru terpilih (opsional: **Cakupan** mapel).
4. **Uji multi-peran** — login sebagai guru yang jadi validator, buka menu pengguna (kanan atas) → **Masuk sebagai**.
5. **Uji multi-semester** — pemilih periode di bilah atas mengganti konteks; semester arsip bersifat hanya-baca.

Tips file Excel: kolom `nip` harus berformat **Teks** (template sudah begitu). NIP 18 digit yang tersimpan sebagai angka
akan terpotong oleh Excel itu sendiri.

## 9. Jalankan tes & pemeriksaan kualitas

**[TERMINAL]**
```bash
php artisan test            # tes backend (PHPUnit)
npm run typecheck           # tipe TypeScript/Vue
npm run build               # build produksi frontend
./vendor/bin/pint           # rapikan gaya kode PHP
```

## 10. Masalah umum

| Gejala | Penyebab & solusi |
|--------|-------------------|
| `Vite manifest not found` | Jalankan `npm run dev` (atau `npm run build`). |
| Impor tertahan di "Menunggu" | Antrian tidak jalan → `php artisan queue:listen`. |
| `Class "ZipArchive" not found` | Aktifkan ekstensi `zip` di php.ini lalu restart. |
| `Table 'roles' doesn't exist` | Lupa langkah 2 (publish Spatie) → ulangi lalu `php artisan migrate`. |
| Halaman putih setelah ganti kode | `php artisan optimize:clear` lalu muat ulang. |
| Pesan validasi berbahasa Inggris | Pastikan `APP_LOCALE=id` di `.env`, lalu `php artisan config:clear`. |
| Lupa sandi admin | `php artisan tinker` → `User::where('email','admin@...')->first()->update(['password'=>'SandiBaru12345','must_change_password'=>true]);` |

## 11. Yang BELUM ada di M0 (sengaja, dikerjakan tahap berikut)

- i18n antarmuka (vue-i18n) → M4. Teks M0 sudah Bahasa Indonesia tetapi ditulis langsung di komponen.
- Pinia, Redis/Horizon, Docker, CI, ESLint/Larastan, Pest/Vitest/Playwright → belum dipasang (tes memakai PHPUnit bawaan).
- Halaman guru/validator hanya dashboard; **paket soal, unggah, telaah** = M1.

Lanjut ke **M1**: migration paket/butir/revisi/review, Action transisi status (`SubmitPackage`, `ApproveItem`, `RequestItemChange`,
`UploadItemRevision`), unggah & ekstraksi, inbox validator, halaman telaah per nomor.
