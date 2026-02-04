<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('field_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('field_id')->constrained('fields')->onDelete('cascade');
            $table->foreignId('period_id')->constrained('field_periods')->onDelete('cascade');
            $table->string('name');
            $table->string('phone');
            $table->string('email');
            $table->date('date');
            $table->integer('players_count')->default(1); // عدد اللاعبين للحجز
            $table->decimal('price', 8, 2); // السعر النهائي
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_bookings');
    }
};
