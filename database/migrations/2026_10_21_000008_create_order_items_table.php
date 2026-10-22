<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('nama_snapshot', 100);
            $table->decimal('harga_snapshot', 12, 2);
            $table->unsignedTinyInteger('qty');
            $table->decimal('subtotal', 12, 2);
            $table->string('catatan', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
