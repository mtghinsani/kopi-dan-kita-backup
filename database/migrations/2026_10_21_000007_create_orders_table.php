<?php

use App\Enums\OrderStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('voucher_id')->nullable()->constrained()->restrictOnDelete();
            $table->char('kode_pickup', 6)->nullable()->unique();
            $table->dateTime('slot_mulai')->nullable(); // cadangan
            $table->enum('status', array_column(OrderStatus::cases(), 'value'))->default(OrderStatus::MenungguPembayaran->value);
            $table->decimal('subtotal', 12, 2);
            $table->decimal('diskon', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->string('alasan_batal', 255)->nullable();
            $table->foreignId('dibatalkan_oleh')->nullable()->constrained('users');
            $table->dateTime('created_at')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('ready_at')->nullable();
            $table->dateTime('completed_at')->nullable();

            $table->index(['status', 'paid_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
