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
    Schema::table('field_periods', function (Blueprint $table) {
        $table->enum('type', ['enable', 'disable'])
              ->default('enable')
              ->after('price_per_player');
    });
}

public function down()
{
    Schema::table('field_periods', function (Blueprint $table) {
        $table->dropColumn('type');
    });
}

};
