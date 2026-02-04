<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDaysToFieldBookingsTable extends Migration
{
    public function up(): void
    {
        Schema::table('field_bookings', function (Blueprint $table) {
            $table->json('days')->nullable()->after('date');
        });
    }

    public function down(): void
    {
        Schema::table('field_bookings', function (Blueprint $table) {
            $table->dropColumn('days');
        });
    }
}
