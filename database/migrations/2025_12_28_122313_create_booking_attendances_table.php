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
        Schema::create('booking_attendances', function (Blueprint $table) {
    $table->id();
    $table->foreignId('field_booking_id')->constrained()->cascadeOnDelete();
    $table->date('date'); // يوم الحضور
    $table->timestamps();

    // يمنع التكرار في نفس اليوم
    $table->unique(['field_booking_id', 'date']);
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_attendances');
    }
};
