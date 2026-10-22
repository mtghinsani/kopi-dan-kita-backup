<?php

use App\Enums\RefundStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained();
            $table->decimal('nominal', 12, 2);
            $table->enum('status', array_column(RefundStatus::cases(), 'value'));
            $table->string('refund_key', 50)->nullable()->unique();
            $table->string('no_referensi', 50)->nullable();
            $table->foreignId('diproses_oleh')->nullable()->constrained('users');
            $table->dateTime('processed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
