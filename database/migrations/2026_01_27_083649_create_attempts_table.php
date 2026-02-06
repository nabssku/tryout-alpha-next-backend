<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('attempts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('package_id')->constrained()->cascadeOnDelete();

            $table->enum('status', ['in_progress', 'submitted', 'expired'])->default('in_progress');

            $table->timestamp('started_at');
            $table->timestamp('ends_at');
            $table->timestamp('submitted_at')->nullable();

            $table->integer('total_score')->default(0);

            $table->timestamps();

            $table->index(['user_id', 'package_id', 'status']);
            $table->index(['status', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attempts');
    }
};
