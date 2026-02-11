<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('materials', function (Blueprint $table) {
            $table->id();

            $table->enum('type', ['ebook', 'video'])->index();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('cover_url')->nullable();

            // Ebook file url (dipakai kalau type=ebook)
            $table->string('ebook_url')->nullable();

            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(1)->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('materials');
    }
};
