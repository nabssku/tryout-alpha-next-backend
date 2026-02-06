<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();

            $table->string('merchant_order_id', 50)->unique();
            $table->unsignedInteger('amount'); // final amount (setelah promo)
            $table->enum('status', ['pending', 'paid', 'failed', 'expired'])->default('pending');

            $table->string('duitku_reference', 100)->nullable();
            $table->text('payment_url')->nullable();
            $table->string('payment_method', 30)->nullable();

            $table->string('promo_code', 50)->nullable();
            $table->unsignedInteger('discount')->default(0);

            $table->timestamp('paid_at')->nullable();
            $table->json('raw_callback')->nullable();

            $table->timestamps();
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
