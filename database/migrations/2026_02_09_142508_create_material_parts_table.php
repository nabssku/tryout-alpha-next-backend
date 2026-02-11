<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('material_parts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('material_id')
                ->constrained('materials')
                ->cascadeOnDelete();

            $table->string('title', 200);
            $table->string('video_url'); // dipakai kalau material.type=video
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->unsignedInteger('sort_order')->default(1)->index();
            $table->boolean('is_active')->default(true)->index();

            $table->timestamps();

            $table->index(['material_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_parts');
    }
};
