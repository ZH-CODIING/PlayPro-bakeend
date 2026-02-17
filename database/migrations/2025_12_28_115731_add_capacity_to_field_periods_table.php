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
    Schema::table('field_periods', function (Blueprint $table) {
        $table->string('capacity')->nullable()->after('price_per_player');
    });
}

public function down(): void
{
    Schema::table('field_periods', function (Blueprint $table) {
        $table->dropColumn('capacity');
    });
}

};
