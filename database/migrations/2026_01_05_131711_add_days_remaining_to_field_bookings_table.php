<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('field_bookings', function (Blueprint $table) {
            $table->integer('days_remaining')->default(0)->after('renewal_date');
        });
    }

    public function down(): void
    {
        Schema::table('field_bookings', function (Blueprint $table) {
            $table->dropColumn('days_remaining');
        });
    }
};
