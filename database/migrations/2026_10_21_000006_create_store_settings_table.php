<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_settings', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nama_kedai', 100);
            $table->text('alamat');
            $table->string('kontak', 50);
            $table->string('qris_image', 255)->nullable();
            $table->time('jam_buka');
            $table->time('jam_tutup');
            // Kolom cadangan dari ERD kelompok; tidak dibaca atau ditulis kode aplikasi.
            $table->integer('panjang_slot_menit')->nullable();
            $table->integer('lead_time_menit')->nullable();
            $table->integer('kapasitas_slot')->nullable();
            $table->integer('batas_bayar_menit')->default(15);
            $table->integer('mulai_buat_menit')->nullable();
            $table->integer('batas_tahan_menit')->nullable();
            $table->boolean('pemesanan_dibuka')->default(true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_settings');
    }
};
