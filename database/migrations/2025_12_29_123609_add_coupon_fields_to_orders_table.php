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
        Schema::table('orders', function (Blueprint $table) {
    $table->decimal('total_before_discount', 10, 2)->default(0)->after('shipping_city');
    $table->decimal('coupon_discount', 10, 2)->nullable()->after('total_before_discount');
    $table->decimal('coupon_percentage', 5, 2)->nullable()->after('coupon_discount');
    $table->string('coupon_name')->nullable()->after('coupon_percentage');
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            //
        });
    }
};
