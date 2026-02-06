<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('attempts', function (Blueprint $table) {
            $table->index(
                ['package_id', 'status', 'total_score', 'submitted_at'],
                'attempts_ranking_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('attempts', function (Blueprint $table) {
            $table->dropIndex('attempts_ranking_index');
        });
    }
};
