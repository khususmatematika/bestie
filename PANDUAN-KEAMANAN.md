# Daftar periksa keamanan BESTIE sebelum dipublikasikan

## 1. `.env` produksi (di server, jangan di-commit)
```dotenv
APP_ENV=production
APP_DEBUG=false          # WAJIB false: debug membocorkan isi .env & jalur berkas
APP_URL=https://domain-sekolah
APP_KEY=...              # php artisan key:generate (sekali, simpan aman)
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true   # bila memakai HTTPS
SESSION_ENCRYPT=true
SESSION_SAME_SITE=lax
LOG_LEVEL=warning
DB_USERNAME=user_khusus_bestie   # BUKAN root
DB_PASSWORD=sandi_kuat
```
- Tidak ada lagi `ADMIN_*` di `.env`; admin dibuat dengan `php artisan bestie:create-admin`.
- `.env` harus ada di `.gitignore` (bawaan Laravel). Jangan kirim `.env` lewat chat/email.
- Dokumen root web server = folder `public/` saja. Jangan arahkan ke folder proyek.
- Pastikan `https://domain/.env` dan `https://domain/storage/...` menghasilkan 404.
- Setelah deploy: `php artisan config:cache route:cache view:cache`, `composer install --no-dev -o`.
- Hak akses: `storage/` & `bootstrap/cache/` dapat ditulis web server; sisanya read-only.
- Aktifkan HTTPS (Let's Encrypt) dan backup basis data berkala.
- Jalankan `composer audit` dan `npm audit` berkala.

## 2. Yang sudah dijaga di kode
| Risiko | Penanganan |
|---|---|
| XSS | Tidak ada `v-html`/`{!! !!}`; semua teks soal tampil via interpolasi Vue (di-escape); rumus lewat KaTeX `trust:false`; CSP ber-nonce + `frame-ancestors 'none'`. |
| SQL injection | Eloquent/parameter binding; `*Raw` hanya berisi literal tetap. |
| CSRF | Token CSRF Laravel pada semua POST/PUT/DELETE; cookie SameSite=lax. |
| Akses tidak sah (IDOR) | Policy/Gate di setiap paket, nomor, berkas, unduhan; admin-only pada rute `/admin`. |
| Brute force | Login 5/menit per akun+IP, 30 gagal/5 menit per IP, 30/menit rute login; ganti sandi 6/menit; unduhan & ZIP massal dibatasi. |
| Sesi | `session()->regenerate()` saat login & ganti sandi; sesi lain berakhir saat sandi diganti/di-reset/akun dinonaktifkan/dihapus; akun nonaktif langsung keluar. |
| Unggahan | Daftar putih ekstensi + cek tanda tangan isi (`SafeUpload`), batas ukuran, gambar diverifikasi magic-byte, disimpan di disk privat dengan nama UUID, disajikan lewat controller terotorisasi + `nosniff`. |
| DOCX/XLSX berbahaya | XML dibaca dengan `LIBXML_NONET` (anti-XXE) dan batas ukuran; sel Excel ditulis sebagai teks (anti formula-injection). |
| Kebocoran data | Kata sandi tidak masuk log aktivitas; kartu akun sekali unduh; sandi awal hanya tampil sekali. |
| Header | X-Frame-Options, nosniff, Referrer-Policy, Permissions-Policy, CSP, HSTS (HTTPS). |

## 3. Catatan sisa risiko
- `katex` (npm audit: 1 *low*, "prototype pollution bypass trust"). BESTIE memakai `trust:false` sehingga tidak terdampak; pembaruan ke 0.19 adalah perubahan besar — uji dulu sebelum menaikkan.
- Kata sandi awal guru dibagikan lewat kartu akun: serahkan langsung ke orangnya; sistem memaksa ganti saat login pertama.
- Pengujian PHPUnit dan penetration test eksternal belum dijalankan di lingkungan pengembangan ini.
