<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coaches', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                  ->constrained()
                  ->cascadeOnDelete();

            $table->foreignId('field_id')
                  ->constrained()
                  ->cascadeOnDelete();

            $table->string('name')->nullable();
            $table->unsignedInteger('age')->nullable();

            $table->text('description')->nullable(); 
            $table->unsignedInteger('experience_years')->nullable(); 

            $table->json('images')->nullable(); 
            $table->string('cv_file')->nullable(); 

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coaches');
    }
};
