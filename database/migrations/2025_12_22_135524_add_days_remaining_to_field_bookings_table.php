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
        $table->integer('days_remaining')->nullable()->after('renewal_date');
    });
}

public function down()
{
    Schema::table('field_bookings', function (Blueprint $table) {
        $table->dropColumn('days_remaining');
    });
}

};
