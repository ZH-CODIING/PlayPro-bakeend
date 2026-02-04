<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('field_images', function (Blueprint $table) {
            $table->id();

            $table->foreignId('field_id')
                  ->constrained('fields')
                  ->onDelete('cascade');

            $table->string('image');
            // مسار الصورة

            $table->enum('type', ['icon', 'gallery'])
                  ->default('gallery');
            // icon = أيقونة الملعب
            // gallery = صور الملعب

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_images');
    }
};
