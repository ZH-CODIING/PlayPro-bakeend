<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('fields', function (Blueprint $table) {
            $table->id();

            // بيانات أساسية
            $table->string('name'); // اسم الملعب
            $table->string('size'); // حجم الملعب (5x5 - 7x7 - 11x11)
            $table->string('capacity'); // سعة الملعب (عدد اللاعبين)


            // الموقع
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('city');
            $table->string('address');

            // شرح
            $table->text('description')->nullable();

            // صاحب الملعب
            $table->foreignId('owner_id')
                  ->constrained('users')
                  ->onDelete('cascade');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fields');
    }
};
