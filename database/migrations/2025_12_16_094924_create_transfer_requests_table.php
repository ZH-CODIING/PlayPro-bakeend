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
        Schema::create('transfer_requests', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            
            $table->foreignId('current_booking_id')->constrained('field_bookings')->onDelete('cascade');
            
            $table->foreignId('target_field_id')->constrained('fields')->onDelete('cascade');
            
            $table->foreignId('target_period_id')->constrained('field_periods')->onDelete('cascade');

            $table->enum('status', ['Pending', 'Approved', 'Rejected'])->default('Pending');
            
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transfer_requests');
    }
};