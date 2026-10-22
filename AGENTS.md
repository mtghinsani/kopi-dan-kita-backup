# Kopi & Kita: aturan kerja agen

Spesifikasi lengkap: `../MEGAPROMPT_Kopi_dan_Kita.md` (sumber utama) dan `../PRD_Kopi_dan_Kita.md`. Status pengerjaan: `../PROGRESS.md` (di luar repository).

## Perintah

- PHP dan Composer ada di `/workspace/php/bin` (`export PATH=/workspace/php/bin:$PATH`).
- Database: MySQL 8.4.13 portabel milik proyek di `127.0.0.1:3307`. Nyalakan dulu dengan `scripts/db.sh start` (status, stop, sql tersedia).
- `php artisan serve`, `php artisan schedule:work`
- PHP lokal tanpa ekstensi `intl`: pakai `php artisan db:show --json`, jangan `Number::format()`.
- `php artisan migrate:fresh --seed`
- `php artisan test` (MySQL `kopi_kita_test`), `vendor/bin/pint`

## Aturan inti

- Laravel 13 dengan struktur `bootstrap/app.php`; tidak ada `app/Http/Kernel.php` atau `app/Console/Kernel.php`. Jadwal di `routes/console.php`.
- Blade, Bootstrap 5.3.8, jQuery 3.7.1 dari CDN dengan SRI plus `public/vendor/`. Tanpa Tailwind, Vite, npm, Node, React, Vue, Livewire, starter kit, Breeze, Fortify, Sanctum.
- Nama tabel dan kolom mengikuti ERD (bahasa Indonesia); identifier kode dan pesan commit dalam bahasa Inggris; teks antarmuka dalam Bahasa Indonesia baku.
- Kolom bertanda cadangan di ERD tidak dibaca atau ditulis kode.
- Midtrans hanya melalui `App\Services\MidtransService` dengan HTTP Client Laravel. Rahasia hanya di `.env`; kode membaca `config()`, bukan `env()`.
- Setiap operasi yang menulis lebih dari satu tabel berada di `DB::transaction`; setiap perubahan status pesanan melalui `OrderService::transition()`.
- Tulis feature test lebih dulu; jangan melemahkan tes agar lulus.
- Commit kecil tanpa trailer `Co-Authored-By` atau atribusi alat AI. Jangan `git push`.
- Kerjakan per fase F0 sampai F7, berhenti di akhir fase dan laporkan output tes asli.
