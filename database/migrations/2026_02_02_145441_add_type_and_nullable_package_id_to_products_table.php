<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1️⃣ Drop FK + unique dulu
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['package_id']);
            $table->dropUnique(['package_id']);
        });

        // 2️⃣ Alter column + tambah type
        Schema::table('products', function (Blueprint $table) {
            $table->enum('type', ['single', 'bundle'])
                ->default('single')
                ->after('name');

            $table->foreignId('package_id')->nullable()->change();
        });

        // 3️⃣ Tambah FK + unique lagi
        Schema::table('products', function (Blueprint $table) {
            $table->foreign('package_id')
                ->references('id')
                ->on('packages')
                ->cascadeOnDelete();

            // nullable unique → hanya berlaku untuk single
            $table->unique('package_id');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['package_id']);
            $table->dropUnique(['package_id']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('package_id')->nullable(false)->change();
            $table->dropColumn('type');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreign('package_id')
                ->references('id')
                ->on('packages')
                ->cascadeOnDelete();

            $table->unique('package_id');
        });
    }
};
