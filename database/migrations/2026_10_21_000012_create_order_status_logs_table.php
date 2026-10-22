<?php

use App\Enums\OrderStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $statuses = array_column(OrderStatus::cases(), 'value');

        Schema::create('order_status_logs', function (Blueprint $table) use ($statuses) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->enum('status_lama', $statuses)->nullable();
            $table->enum('status_baru', $statuses);
            $table->foreignId('diubah_oleh')->nullable()->constrained('users');
            $table->dateTime('changed_at')->nullable();

            $table->index(['order_id', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_logs');
    }
};
