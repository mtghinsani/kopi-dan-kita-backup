<?php

use App\Enums\VoucherType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 20)->unique();
            $table->enum('jenis', array_column(VoucherType::cases(), 'value'));
            $table->decimal('nilai', 12, 2);
            $table->unsignedInteger('kuota');
            $table->unsignedInteger('terpakai')->default(0);
            $table->dateTime('berlaku_mulai');
            $table->dateTime('berlaku_sampai');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vouchers');
    }
};
