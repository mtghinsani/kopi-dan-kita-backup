# Checklist UAT Kopi & Kita

Dokumen ini dipakai penguji manusia untuk memastikan setiap fitur berjalan dari sisi pengguna. Skenario disusun dari user story dan kriteria penerimaan di `../PRD_Kopi_dan_Kita.md` (bagian 6 dan 14.10) serta skenario UAT di `../MEGAPROMPT_Kopi_dan_Kita.md`.

## Persiapan

1. Jalankan `php artisan migrate:fresh --seed` agar data kembali ke kondisi awal.
2. Jalankan `php artisan serve` dan `php artisan schedule:work` di dua terminal.
3. Untuk skenario pembayaran online lewat webhook, jalankan juga tunnel dan pakai konfigurasi di bagian Demo lewat tunnel pada README.
4. Uji di jam operasional kedai (bawaan 07.00 sampai 22.00). Di luar jam itu checkout memang ditolak.
5. Semua akun contoh memakai password `password`.

| Role | Akun |
| --- | --- |
| Pelanggan | pelanggan1@kopikita.test, pelanggan2@kopikita.test |
| Karyawan | karyawan1@kopikita.test |
| Super Admin | admin@kopikita.test |

QRIS sandbox hanya boleh dibayar melalui simulator Midtrans di https://simulator.sandbox.midtrans.com/v2/qris/index. Jangan membayar dengan aplikasi bank atau e-wallet sungguhan.

Isi kolom Hasil dengan Lulus atau Gagal, dan tulis catatan bila gagal.

## Pelanggan

| No | Skenario | Langkah | Hasil yang diharapkan | Hasil |
| --- | --- | --- | --- | --- |
| PL-01a | Registrasi | Buka `/register`, isi data, centang persetujuan privasi, kirim. | Masuk otomatis dan diarahkan ke `/menu`. | |
| PL-01b | Registrasi tanpa persetujuan | Ulangi tanpa mencentang persetujuan. | Ditolak dengan pesan di bawah kotak persetujuan. | |
| PL-01c | Login salah | Login dengan password salah. | Pesan umum tanpa menyebut field yang salah. | |
| PL-01d | Batas percobaan | Login salah enam kali dalam satu menit. | Percobaan keenam ditolak (terlalu banyak percobaan). | |
| PL-02 | Profil | Ubah nama dan nomor HP di `/profil`, lalu ganti password dengan password lama yang benar. | Pesan sukses; email tidak dapat diubah. | |
| PL-03 | Katalog | Buka `/menu`. | Produk dikelompokkan per kategori, harga tertulis seperti Rp22.000. | |
| PL-04 | Cari dan filter | Cari `latte`, lalu pilih kategori Kopi. Cari `zzz`. | Hasil tergabung sesuai kata kunci dan kategori; pencarian kosong menampilkan "Menu tidak ditemukan". | |
| PL-05a | Kustomisasi | Tekan Tambah pada Kopi Susu Gula Aren, pilih Large, Dingin, Gula Kurang, Extra Shot, jumlah 2. | Harga di tombol berubah menjadi Rp64.000 tanpa reload. | |
| PL-05b | Validasi opsi | Tekan Tambah ke keranjang tanpa memilih Ukuran. | Pesan "Pilih minimal 1 untuk Ukuran." di bawah grup; data tidak terkirim. | |
| PL-05c | Catatan aman | Isi catatan `<script>alert(1)</script>` lalu tambahkan. | Di keranjang dan antrean, catatan tampil sebagai teks tanpa memunculkan alert. | |
| PL-06 | Keranjang AJAX | Di `/keranjang`, ubah jumlah lalu hapus satu item. | Subtotal dan badge navbar berubah tanpa reload; isi keranjang tetap ada setelah logout dan login lagi. | |
| PL-07a | Voucher | Di checkout, pakai `HEMAT10`, lalu coba `KADALUWARSA`. | HEMAT10 memotong 10%; KADALUWARSA ditolak dengan pesan. | |
| PL-07b | Menu habis saat checkout | Karyawan menandai menu di keranjang Habis, lalu pelanggan menekan Buat Pesanan. | Ditolak dengan nama menu; tidak ada pesanan terbentuk. | |
| PL-08a | Bayar di Kasir | Checkout dengan Bayar di Kasir. | Halaman pesanan menampilkan kode 6 karakter besar, QRIS kedai, total, dan batas bayar sampai jam tutup. | |
| PL-08b | Bayar Online lunas | Checkout dengan Bayar Online, pilih QRIS di popup, salin URL gambar QR, bayar di simulator. | Dalam beberapa detik halaman berganti menjadi Pembayaran Berhasil tanpa reload. | |
| PL-08c | Tutup lalu lanjutkan | Tutup popup Snap, lalu tekan Lanjutkan Pembayaran. | Popup yang sama terbuka lagi; status tidak berubah saat popup ditutup. | |
| PL-08d | Ganti metode | Pada pesanan online yang belum dibayar, tekan Ganti ke Bayar di Kasir. | QRIS kedai tampil; transaksi online lama di dashboard Midtrans berstatus cancel. | |
| PL-08e | Kedaluwarsa | Buat pesanan online lalu biarkan lebih dari 15 menit (penjadwal berjalan). | Status menjadi Kedaluwarsa. | |
| PL-08f | Tunnel mati | Matikan tunnel, bayar pesanan online di simulator, biarkan halaman pesanan terbuka. | Status tetap berubah menjadi Dibayar dalam 30 detik melalui Status API. | |
| PL-09 | Status otomatis | Biarkan halaman pesanan terbuka saat karyawan memproses pesanan. | Badge dan langkah status berubah sendiri; label status selalu berupa teks. | |
| PL-10 | Riwayat dan pesan ulang | Buka `/pesanan`, tekan Pesan Ulang pada pesanan lama. | Item masuk keranjang dengan harga terbaru; item yang habis dilewati dan disebut namanya. | |
| PL-11 | Struk | Pada pesanan yang sudah dibayar, tekan Lihat Struk lalu Cetak Struk. | Struk selebar kertas 58 mm berisi nama kedai, kode, item, opsi, diskon, total, metode bayar. | |
| PL-12 | Pembatalan | Batalkan pesanan yang masih Menunggu Pembayaran. Coba juga pada pesanan yang sudah dibayar. | Pesanan menunggu dapat dibatalkan setelah konfirmasi; tombol Batalkan tidak tersedia untuk pesanan yang sudah dibayar. | |
| PL-13 | Akses pesanan orang lain | Login sebagai pelanggan2, buka URL pesanan milik pelanggan1. | Halaman tidak ditemukan (404). | |
| PL-14 | Tampilan responsif | Buka menu, keranjang, checkout, dan halaman pesanan di lebar 360, 768, dan 1280 px. | Tidak ada scroll horizontal; semua tombol dapat ditekan. | |
| PL-15 | Kinerja katalog | Jalankan Lighthouse mode mobile pada `/menu`. | Katalog 30 produk tampil kurang dari 3 detik. | |

## Karyawan

| No | Skenario | Langkah | Hasil yang diharapkan | Hasil |
| --- | --- | --- | --- | --- |
| KY-01 | Login dan akses | Login sebagai karyawan, lalu buka `/admin`. | Diarahkan ke `/karyawan`; `/admin` ditolak (403). | |
| KY-02 | Antrean | Buka `/karyawan` saat ada beberapa pesanan dibayar. | Kartu urut waktu bayar dengan item, opsi, catatan; pesanan belum dibayar tidak tampil; pesanan baru disorot dalam 10 detik. | |
| KY-03a | Konfirmasi kasir | Di `/karyawan/kasir`, masukkan kode pesanan Bayar di Kasir, tekan Konfirmasi Pembayaran. | Dialog menampilkan kode, nama, total, dan pertanyaan apakah pembayaran sudah diterima. | |
| KY-03b | Batal di dialog | Tekan Batal pada dialog. | Tidak ada perubahan data. | |
| KY-03c | Konfirmasi | Tekan Ya, Konfirmasi. | Pesanan menjadi Dibayar, masuk antrean, dan halaman pelanggan berganti menjadi Pembayaran Berhasil. | |
| KY-03d | Kode salah | Cari kode yang tidak ada. | Pesan "Kode pesanan tidak valid." | |
| KY-04 | Proses pesanan | Di antrean, tekan Proses lalu Siap Diambil. | Status berubah berurutan; kartu pindah ke kolom Siap diambil. | |
| KY-05a | Pengambilan terlalu cepat | Di `/karyawan/pengambilan`, cari pesanan yang masih Diproses. | Tombol konfirmasi tidak tersedia; tertulis pesanan belum siap diambil. | |
| KY-05b | Pengambilan | Cari pesanan Siap Diambil, tekan Konfirmasi Pengambilan, lalu Cetak Struk. | Status menjadi Selesai; struk 58 mm dapat dicetak. | |
| KY-06 | Batal oleh kedai | Buka pesanan di Riwayat, tekan Batalkan Pesanan tanpa alasan, lalu dengan alasan. | Tanpa alasan ditolak; dengan alasan berhasil dan alasan tampil di halaman pelanggan. | |
| KY-07 | Ketersediaan | Di `/karyawan/ketersediaan`, matikan sebuah menu dan sebuah opsi. | Katalog pelanggan menampilkan menu Habis dan opsi nonaktif pada muatan berikutnya. | |
| KY-08 | Riwayat | Cari pesanan dengan nama pelanggan dan filter tanggal, lalu buka detailnya. | Hasil sesuai filter; detail menampilkan riwayat status beserta pelakunya. | |

## Super Admin

| No | Skenario | Langkah | Hasil yang diharapkan | Hasil |
| --- | --- | --- | --- | --- |
| SA-01 | Dashboard | Login sebagai admin. | Diarahkan ke `/admin` dengan pendapatan hari ini, jumlah Perlu Refund, dan jumlah pesanan per status. | |
| SA-02a | Akun staf | Buat akun Karyawan baru, lalu login dengan akun itu. | Akun dapat login dan masuk ke `/karyawan`. | |
| SA-02b | Nonaktif | Nonaktifkan akun tersebut saat sedang login di browser lain. | Akun dikeluarkan pada permintaan berikutnya; admin tidak dapat menonaktifkan akunnya sendiri. | |
| SA-02c | Atur ulang password | Atur ulang password akun karyawan. | Karyawan dapat login dengan password baru. | |
| SA-03 | Kategori | Hapus kategori Kopi, lalu tambah dan hapus kategori kosong. | Kategori berisi produk ditolak dengan alasan; kategori kosong terhapus. | |
| SA-04a | Produk dan gambar | Tambah produk dengan gambar JPG, lalu coba unggah file `.svg`, `.php`, dan gambar lebih dari 2 MB. | Gambar JPG tampil di katalog; tiga file lain ditolak. | |
| SA-04b | Ubah harga | Ubah harga dasar produk. | Log audit UBAH_HARGA mencatat nilai lama dan baru; pesanan lama tetap memakai harga saat dipesan. | |
| SA-04c | Hapus produk terpesan | Hapus produk yang pernah dipesan. | Ditolak dengan saran menandainya Habis. | |
| SA-05 | Opsi | Tambah grup opsi dengan minimal lebih besar dari maksimal, lalu grup wajib baru tanpa opsi. | Grup tidak valid ditolak; grup tanpa opsi tidak tampil di katalog. | |
| SA-06 | Voucher | Buat voucher persen 150, lalu voucher valid. Kurangi kuota di bawah jumlah terpakai. | Nilai 150 dan kuota di bawah terpakai ditolak; voucher valid dapat dipakai pelanggan. | |
| SA-07 | Pengaturan | Tutup pemesanan, unggah QRIS kedai, ubah jam tutup. | Checkout pelanggan ditolak saat pemesanan ditutup; QRIS baru tampil di halaman Bayar di Kasir; log audit UBAH_PENGATURAN tersimpan. | |
| SA-08 | Monitoring | Filter `/admin/pesanan` per status, metode, dan tanggal; buka `/admin/transaksi`. | Daftar sesuai filter; transaksi menampilkan gateway order ID, transaction ID, tipe, dan status. | |
| SA-09a | Refund online | Batalkan pesanan online yang sudah lunas dari fitur karyawan, lalu di detail admin tekan Proses Refund lewat Midtrans. | Refund selesai bila Midtrans menerima; bila ditolak, pesan galat tampil dan refund dapat dicatat manual. | |
| SA-09b | Refund kasir | Batalkan pesanan Bayar di Kasir yang sudah lunas, catat refund manual tanpa dan dengan nomor referensi. | Tanpa nomor referensi ditolak; dengan nomor referensi selesai dan badge Perlu Refund hilang. | |
| SA-10 | Laporan | Buka `/admin/laporan` dengan rentang bawaan, lalu isi tanggal akhir sebelum tanggal awal. | Hanya pesanan Selesai dihitung, dengan 10 menu terlaris; rentang terbalik ditolak. | |
| SA-11 | Log audit | Filter log audit per aksi dan pelaku. | Daftar terurut terbaru dengan nilai lama dan baru yang terbaca. | |
| SA-12 | Fitur karyawan | Dari navbar admin, buka Dashboard operasional. | Semua halaman `/karyawan` dapat dibuka oleh admin. | |

## Webhook Midtrans

Skenario ini membutuhkan tunnel aktif.

| No | Skenario | Langkah | Hasil yang diharapkan | Hasil |
| --- | --- | --- | --- | --- |
| MT-01 | Notifikasi lunas | Bayar pesanan online di simulator saat tunnel aktif. | Log server menunjukkan `POST /midtrans/notification` 200 dan pesanan Dibayar tanpa menunggu polling 30 detik. | |
| MT-02 | Kirim ulang notifikasi | Dari dashboard Midtrans sandbox, kirim ulang notifikasi transaksi yang sama. | Dibalas 200; riwayat status tidak bertambah. | |
| MT-03 | Voucher di Snap | Bayar online dengan voucher HEMAT10. | Rincian transaksi di dashboard Midtrans memuat baris voucher bernilai negatif dan total sama dengan total pesanan. | |
