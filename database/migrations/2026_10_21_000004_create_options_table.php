<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('option_groups')->cascadeOnDelete();
            $table->string('nama', 50);
            $table->decimal('selisih_harga', 12, 2)->default(0);
            $table->boolean('tersedia')->default(true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('options');
    }
};
