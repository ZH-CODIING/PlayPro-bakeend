<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('field_periods', function (Blueprint $table) {
            $table->id();

            $table->foreignId('field_id')
                  ->constrained('fields')
                  ->onDelete('cascade');

            $table->time('start_time'); // من
            $table->time('end_time');   // إلى

            $table->decimal('price_per_player', 8, 2);
            // سعر اللاعب في الفترة دي

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_periods');
    }
};

