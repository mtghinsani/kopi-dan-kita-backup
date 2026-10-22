<?php

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained();
            $table->decimal('nominal', 12, 2);
            $table->enum('status', array_column(PaymentStatus::cases(), 'value'))->default(PaymentStatus::Menunggu->value);
            $table->enum('metode', array_column(PaymentMethod::cases(), 'value'));
            $table->string('gateway_order_id', 50)->nullable()->unique();
            $table->string('transaction_id', 64)->nullable();
            $table->string('payment_type', 30)->nullable();
            $table->string('bukti_path', 255)->nullable(); // cadangan
            $table->dateTime('uploaded_at')->nullable(); // cadangan
            $table->foreignId('diverifikasi_oleh')->nullable()->constrained('users');
            $table->dateTime('verified_at')->nullable();
            $table->string('alasan_tolak', 255)->nullable(); // cadangan
            $table->dateTime('expired_at');
            $table->dateTime('created_at')->nullable();

            $table->index(['status', 'expired_at']);
            $table->index(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
