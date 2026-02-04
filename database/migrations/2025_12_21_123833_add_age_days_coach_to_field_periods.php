<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // تعديل جدول field_periods لإضافة الحقول الجديدة
        Schema::table('field_periods', function (Blueprint $table) {
            $table->string('age_group')->nullable()->after('type');       // الفئة العمرية
            $table->json('days')->nullable()->after('age_group');        // الأيام
            $table->json('coach_ids')->nullable()->after('days');        // الكوتشز
        });

        // إنشاء جدول وسيط للفترة والكوتش
        Schema::create('field_period_coach', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')->constrained('field_periods')->onDelete('cascade');
            $table->foreignId('coach_id')->constrained('coaches')->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('field_periods', function (Blueprint $table) {
            $table->dropColumn(['age_group', 'days', 'coach_ids']);
        });

        Schema::dropIfExists('field_period_coach');
    }
};
