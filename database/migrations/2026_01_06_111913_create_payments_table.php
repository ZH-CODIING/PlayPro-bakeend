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
    { Schema::create('payments', function (Blueprint $table) {
    $table->id();

    // المستخدم اللي دفع
    $table->foreignId('user_id')
        ->constrained()
        ->cascadeOnDelete();

    // ربط بالحجز (اختياري)
    $table->foreignId('field_booking_id')
        ->nullable()
        ->constrained('field_bookings')
        ->nullOnDelete();

    // ربط بالأوردر (اختياري)
    $table->foreignId('order_id')
        ->nullable()
        ->constrained('orders')
        ->nullOnDelete();

    // بيانات الدفع
    $table->string('gateway')->index(); // paymob, stripe, paypal
    $table->string('payment_id')->nullable()->unique();
    $table->string('gateway_reference')->nullable()->index();

    $table->string('status')->default('pending')->index();
    $table->decimal('amount', 15, 2)->unsigned();
    $table->string('currency', 10)->default('SAR');

    $table->json('meta')->nullable();
    $table->text('notes')->nullable();

    $table->timestamps();
    $table->softDeletes();
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
