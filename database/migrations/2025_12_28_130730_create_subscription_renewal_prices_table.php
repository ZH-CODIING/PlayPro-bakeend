<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_renewal_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academy_id')->constrained()->onDelete('cascade');
            $table->unsignedInteger('months'); // عدد الشهور
            $table->decimal('price', 10, 2);   // السعر
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_renewal_prices');
    }
};
