<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        DB::statement("
            ALTER TABLE field_bookings 
            MODIFY status ENUM('active', 'expired', 'cancelled') 
            DEFAULT 'active'
        ");
    }

    public function down()
    {
        DB::statement("
            ALTER TABLE field_bookings 
            MODIFY status ENUM('active', 'expired') 
            DEFAULT 'active'
        ");
    }
};
