<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->foreignId('package_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('price'); // rupiah
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // optional: cegah 2 produk aktif untuk package yang sama (MVP)
            $table->unique('package_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
