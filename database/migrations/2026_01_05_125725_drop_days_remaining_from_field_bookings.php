<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('field_bookings', function (Blueprint $table) {
            if (Schema::hasColumn('field_bookings', 'days_remaining')) {
                $table->dropColumn('days_remaining');
            }
        });
    }

    public function down(): void
    {
        Schema::table('field_bookings', function (Blueprint $table) {
            if (!Schema::hasColumn('field_bookings', 'days_remaining')) {
                $table->integer('days_remaining')->default(0);
            }
        });
    }
};
