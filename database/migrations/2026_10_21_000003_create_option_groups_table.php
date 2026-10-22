<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('option_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('nama', 50);
            $table->boolean('wajib')->default(false);
            $table->unsignedTinyInteger('min_pilih')->default(0);
            $table->unsignedTinyInteger('max_pilih')->default(1);
            $table->integer('urutan')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('option_groups');
    }
};
