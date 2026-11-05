# Kopi & Kita//

Website pemesanan kopi dengan sistem Buy Online, Pick-up in Store (BOPS) untuk tugas besar mata kuliah Pemrograman Web, Kelompok 2 kelas S1IT-KJ-24-004. Pelanggan memesan dari browser, membayar online melalui Midtrans Snap sandbox atau dengan QRIS statis di kasir, lalu mengambil pesanan di kedai dengan kode pesanan.

Spesifikasi lengkap ada di `../PRD_Kopi_dan_Kita.md` dan `../MEGAPROMPT_Kopi_dan_Kita.md`.

## Fitur per role

| Role | Fitur |
| --- | --- |
| Pelanggan | Registrasi dengan persetujuan privasi, katalog dengan pencarian dan filter, kustomisasi menu, keranjang AJAX, checkout dengan voucher, Bayar Online (Midtrans Snap) atau Bayar di Kasir (QRIS statis), status pesanan yang diperbarui otomatis, riwayat dan pesan ulang, struk 58 mm, pembatalan pesanan yang belum dibayar. |
| Karyawan | Antrean barista, konfirmasi pembayaran kasir dengan dialog validasi, ubah status, pengambilan dengan kode, cetak struk, pembatalan pesanan dengan alasan, ketersediaan menu dan opsi, riwayat pesanan. |
| Super Admin | Dashboard harian, akun pengguna, kategori, produk, grup opsi dan opsi, voucher, pengaturan kedai dan QRIS, monitoring pesanan dan transaksi, refund online dan manual, laporan penjualan, log audit, serta seluruh fitur Karyawan. |

Checklist uji penerimaan per role ada di `UAT.md`.

## Teknologi

- PHP 8.3 atau lebih baru (Laravel 13 membutuhkan PHP 8.3 minimum)
- Laravel 13, Blade, PHPUnit
- Bootstrap 5.3.8 dan jQuery 3.7.1 dari CDN dengan SRI, beserta salinan di `public/vendor/`
- MySQL 8 atau MariaDB 10.4 ke atas
- Midtrans Snap sandbox

Proyek ini tidak memakai Node, npm, maupun Vite. CSS dan JavaScript aplikasi ada di `public/css` dan `public/js`.

XAMPP terbaru membawa PHP 8.2 sehingga tidak dapat menjalankan Laravel 13. Pengguna Windows dapat memakai Laragon atau Laravel Herd, atau memakai XAMPP hanya untuk MySQL dengan PHP 8.3 ke atas yang dipasang terpisah.

## Cara Menjalankan Cepat (Environment `/workspace`)

Jika menggunakan environment lokal `/workspace` (PHP di `/workspace/php/bin` dan MySQL portabel di `127.0.0.1:3307`):

### Menggunakan Fish Shell

```fish
cd /workspace/Projects/kopi-dan-kita/web
fish_add_path /workspace/php/bin

# 1. Nyalakan MySQL portabel (127.0.0.1:3307)
./scripts/db.sh start

# 2. (Opsional) Reset ulang database & isi data seeder
php artisan migrate:fresh --seed

# 3. Jalankan web server Laravel
php artisan serve
```

Untuk menjalankan penjadwal otomatis (pembatalan pesanan kedaluwarsa), buka terminal kedua:

```fish
cd /workspace/Projects/kopi-dan-kita/web
fish_add_path /workspace/php/bin
php artisan schedule:work
```

> **Catatan Fish Shell:** Jangan gunakan `export PATH=/workspace/php/bin:$PATH` di Fish karena akan merusak daftar `PATH` sistem. Gunakan `fish_add_path /workspace/php/bin`.

### Menggunakan Bash / Zsh

```bash
cd /workspace/Projects/kopi-dan-kita/web
export PATH="/workspace/php/bin:$PATH"

# 1. Nyalakan MySQL portabel (127.0.0.1:3307)
./scripts/db.sh start

# 2. (Opsional) Reset ulang database & isi data seeder
php artisan migrate:fresh --seed

# 3. Jalankan web server Laravel
php artisan serve
```

Buka **http://localhost:8000** (atau **http://127.0.0.1:8000**) di browser. Untuk menghentikan MySQL portabel setelah selesai bekerja, jalankan `./scripts/db.sh stop`.

## Instalasi

1. Pastikan `php -v` menampilkan versi 8.3 atau lebih baru dan `composer -V` tersedia.
2. Buat database dan user MySQL:

   ```sql
   CREATE DATABASE kopi_kita;
   CREATE DATABASE kopi_kita_test;
   CREATE USER 'kopi_kita'@'localhost' IDENTIFIED BY 'isi_password';
   GRANT ALL PRIVILEGES ON kopi_kita.* TO 'kopi_kita'@'localhost';
   GRANT ALL PRIVILEGES ON kopi_kita_test.* TO 'kopi_kita'@'localhost';
   FLUSH PRIVILEGES;
   ```

3. Pasang dependensi dan siapkan konfigurasi:

   ```sh
   composer install
   cp .env.example .env
   php artisan key:generate
   ```

4. Isi `DB_PASSWORD` di `.env`. Isi `MIDTRANS_SERVER_KEY` dan `MIDTRANS_CLIENT_KEY` dengan kunci sandbox dari dashboard Midtrans (mode Sandbox).
5. Siapkan basis data dan file publik:

   ```sh
   php artisan migrate:fresh --seed
   php artisan storage:link
   ```

6. Jalankan aplikasi dan penjadwal di dua terminal:

   ```sh
   php artisan serve
   php artisan schedule:work
   ```

   Aplikasi tersedia di http://localhost:8000.

## Server MySQL lokal tanpa sudo (opsional)

Bila MySQL sistem tidak dapat dipakai, `scripts/db.sh` menjalankan MySQL 8.4 portabel milik proyek di `127.0.0.1:3307` tanpa sudo. Unduh tarball "Linux - Generic (glibc 2.28) Minimal" MySQL 8.4 dari dev.mysql.com, ekstrak ke `/workspace/apps/mysql-8.4.13` (atau setel `KK_MYSQL_BASE`), lalu:

```sh
scripts/db.sh init     # sekali saja, membuat data di /workspace/.cache/mysql-kopi
scripts/db.sh start    # setiap kali mulai bekerja
scripts/db.sh sql -e "CREATE DATABASE kopi_kita; CREATE DATABASE kopi_kita_test;"
scripts/db.sh stop
```

Server ini tidak berjalan otomatis setelah komputer dinyalakan ulang. Bila tes atau aplikasi menampilkan `Connection refused` pada port 3307, jalankan lagi `scripts/db.sh start`.

Buat user `kopi_kita` seperti pada langkah 2 lewat `scripts/db.sh sql`, lalu isi `DB_PORT=3307` di `.env`.

## Notifikasi Midtrans di komputer lokal

Midtrans tidak dapat mengirim notifikasi ke `localhost`. Saat menguji pembayaran online, buka tunnel ke port 8000 (misalnya `ngrok http 8000`), lalu isi `MIDTRANS_NOTIFICATION_URL` dengan `https://domain-tunnel/midtrans/notification` dan `APP_URL` dengan domain tunnel. Tanpa tunnel, status pembayaran tetap dapat diperbarui melalui Midtrans Status API saat halaman pesanan melakukan polling.

QRIS sandbox hanya boleh dibayar melalui simulator Midtrans (`https://simulator.sandbox.midtrans.com/v2/qris/index`), jangan dengan aplikasi bank atau e-wallet sungguhan.

## Demo lewat tunnel

Untuk demo dari perangkat lain dan agar notifikasi Midtrans sampai, jalankan aplikasi di balik tunnel HTTPS. Contoh dengan ngrok dan domain statis gratis dari dashboard ngrok (menu Domains):

1. Salin `.env.demo.example` menjadi `.env` (simpan `.env` lama bila perlu), isi `APP_KEY` lama atau jalankan `php artisan key:generate`, `DB_PASSWORD`, kunci Midtrans sandbox, dan ganti `nama-domain` dengan domain ngrok. Konfigurasi ini memakai `APP_DEBUG=false` dan `SESSION_SECURE_COOKIE=true`, sehingga login hanya berjalan lewat alamat HTTPS tunnel, bukan `localhost`.
2. Jalankan di tiga terminal:

   ```sh
   php artisan serve
   ngrok http --url=nama-domain.ngrok-free.app 8000
   php artisan schedule:work
   ```

3. Buka aplikasi melalui `https://nama-domain.ngrok-free.app`.

Aplikasi sudah mempercayai header proxy (`trustProxies`) sehingga URL dan cookie memakai HTTPS di balik tunnel. URL notifikasi dikirim ke Midtrans melalui header `X-Override-Notification` pada setiap transaksi, jadi Notification URL di dashboard Midtrans tidak wajib diubah.

## Akun contoh dari seeder

Dibuat oleh `php artisan migrate:fresh --seed`. Semua akun memakai password `password`.

| Role | Email |
| --- | --- |
| Super Admin | admin@kopikita.test |
| Karyawan | karyawan1@kopikita.test, karyawan2@kopikita.test |
| Pelanggan | pelanggan1@kopikita.test sampai pelanggan3@kopikita.test |

Seeder juga membuat pengaturan kedai (jam 07.00 sampai 22.00, batas bayar online 15 menit), 5 kategori, 30 produk (minuman memiliki grup opsi Ukuran, Suhu, Es, Gula, dan Tambahan), serta voucher `HEMAT10`, `POTONG5K`, dan `KADALUWARSA` (sengaja sudah lewat masa berlaku). Gambar produk dan QRIS memakai placeholder sampai diunggah lewat panel admin.

## Menjalankan tes

Tes memakai database MySQL `kopi_kita_test` (lihat `phpunit.xml`), bukan SQLite, karena SQLite mengabaikan `lockForUpdate()`.

```sh
php artisan test
vendor/bin/pint
```

Bila PHP tidak memiliki ekstensi `intl`, `php artisan db:show` gagal saat memformat ukuran tabel; gunakan `php artisan db:show --json`. Aplikasi sendiri tidak membutuhkan `intl` karena Rupiah diformat dengan `number_format()`.

## Aset offline

Untuk demo tanpa internet, setel `ASSET_CDN=false` di `.env` agar Bootstrap dan jQuery dimuat dari `public/vendor/`.
