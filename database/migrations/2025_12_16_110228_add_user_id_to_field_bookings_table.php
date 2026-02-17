<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB; // استيراد DB لتنفيذ تحديث البيانات

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // يجب أن نستخدم Schema::table مرتين للالتزام بالخطوات المطلوبة
        
        // الخطوة 1: أضف العمود مؤقتاً كـ nullable (يقبل القيمة الفارغة)
        Schema::table('field_bookings', function (Blueprint $table) {
            // foreignId() يضيف العمود، لكننا نجعله يقبل NULL مؤقتاً
            $table->unsignedBigInteger('user_id')->nullable()->after('id');
        });

        // ----------------------------------------------------------------------
        // الخطوة 2: تحديث البيانات الحالية (لإضافة قيمة user_id صالحة للصفوف القديمة)
        // **مهم:** تأكد أن ID رقم 1 موجود في جدول users
        DB::table('field_bookings')->update(['user_id' => 5]);
        // ----------------------------------------------------------------------

        // الخطوة 3: تعديل العمود ليكون NOT NULL ثم إضافة القيد الخارجي
        Schema::table('field_bookings', function (Blueprint $table) {
            
            // جعل العمود لا يقبل NULL
            $table->unsignedBigInteger('user_id')->nullable(false)->change();

            // إضافة القيد الخارجي الآن بعد أن أصبحت البيانات صالحة
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('field_bookings', function (Blueprint $table) {
            // حذف القيد الخارجي أولاً
            $table->dropForeign(['user_id']);
            
            // حذف العمود
            $table->dropColumn('user_id');
        });
    }
};