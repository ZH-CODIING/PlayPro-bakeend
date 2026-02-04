<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ratings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id'); // اللي عمل التقييم
            $table->unsignedBigInteger('rateable_id'); // الـ id للكورس أو الرحلة أو الإيجار
            $table->string('rateable_type'); // اسم الموديل (Lesson, Trip, Rental)
            $table->unsignedTinyInteger('rate')->comment('قيمة التقييم من 1 إلى 5');
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ratings');
    }
};
