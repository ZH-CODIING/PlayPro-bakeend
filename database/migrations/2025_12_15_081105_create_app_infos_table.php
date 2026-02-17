<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('app_infos', function (Blueprint $table) {
            $table->id();

            // 🔹 Basic App Info
            $table->string('platform_name');
            $table->string('logo')->nullable();

            // 🔹 Social Media Links
            $table->string('facebook')->nullable();
            $table->string('instagram')->nullable();
            $table->string('tiktok')->nullable();
            $table->string('x')->nullable(); 
            $table->string('snapchat')->nullable();

            // 🔹 Contact Info
            $table->string('phone')->nullable();
            $table->string('whatsapp')->nullable();

            // 🔹 Management Info
            $table->string('management_name')->nullable();
            $table->string('management_image')->nullable();
            $table->text('address')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_infos');
    }
};
