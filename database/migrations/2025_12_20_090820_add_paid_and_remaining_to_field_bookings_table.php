<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('field_bookings', function (Blueprint $table) {
            $table->decimal('paid', 10, 2)->default(0)->after('price')->comment('المبلغ المدفوع');
            $table->decimal('remaining', 10, 2)->default(0)->after('paid')->comment('المبلغ المتبقي');
        });
    }

    public function down(): void
    {
        Schema::table('field_bookings', function (Blueprint $table) {
            $table->dropColumn(['paid', 'remaining']);
        });
    }
};
