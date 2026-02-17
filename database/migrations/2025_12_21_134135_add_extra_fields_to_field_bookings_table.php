<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('field_bookings', function (Blueprint $table) {

            $table->foreignId('academy_id')
                ->nullable()
                ->constrained('academies')
                ->nullOnDelete()
                ->after('field_id');

            $table->integer('age')->nullable()->after('academy_id');

            $table->decimal('discount', 8, 2)->default(0)->after('price');

            $table->date('renewal_date')->nullable()->after('date');

            $table->decimal('renewal_price', 8, 2)->nullable()->after('renewal_date');

            $table->integer('renewal_count')->default(0)->after('renewal_price');

            $table->string('payment_method')->nullable()->after('remaining');
        });
    }

    public function down(): void
    {
        Schema::table('field_bookings', function (Blueprint $table) {
            $table->dropForeign(['academy_id']);
            $table->dropColumn([
                'academy_id',
                'age',
                'discount',
                'renewal_date',
                'renewal_price',
                'renewal_count',
                'payment_method',
            ]);
        });
    }
};
