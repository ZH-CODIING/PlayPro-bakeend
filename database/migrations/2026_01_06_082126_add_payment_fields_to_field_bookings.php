<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
  public function up()
{
    Schema::table('field_bookings', function (Blueprint $table) {
        $table->string('payment_status')->default('pending'); // pending, paid, failed
        $table->string('transaction_id')->nullable();        
        $table->string('merchant_order_id')->nullable();      
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('field_bookings', function (Blueprint $table) {
            //
        });
    }
};
