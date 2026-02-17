<?php

namespace App\Http\Controllers;

use App\Models\FieldBooking;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StatisticsController extends Controller
{
    /**
     * جلب الإحصائيات التفصيلية (لوحة التحكم)
     */
    public function getDetailedStatistics()
    {
        $user = Auth::user();

        // 1. التحقق من الصلاحيات
        $allowedRoles = [User::ROLE_ADMIN, User::ROLE_OWNER, User::ROLE_OWNER_ACADEMY];
        if (!in_array($user->role, $allowedRoles)) {
            return response()->json(['status' => false, 'message' => 'غير مصرح لك'], 403);
        }

        // 2. بناء الاستعلام الأساسي (Base Query)
        $query = FieldBooking::query();

        // تصفية البيانات لو كان المستخدم صاحب ملعب وليس آدمن
        if ($user->role !== User::ROLE_ADMIN) {
            $query->whereHas('field', function ($q) use ($user) {
                $q->where('owner_id', $user->id);
            });
        }

        // 3. تنفيذ العمليات الحسابية في استعلام واحد لسرعة الأداء
        $stats = (clone $query)->selectRaw("
            -- أرقام عامة
            COUNT(*) as total_bookings,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as total_cancelled,
            
            -- إحصائيات الأكاديمية (التي لها academy_id)
            SUM(CASE WHEN academy_id IS NOT NULL THEN 1 ELSE 0 END) as academy_bookings_count,
            COALESCE(SUM(CASE WHEN academy_id IS NOT NULL THEN price ELSE 0 END), 0) as academy_revenue,
            
            -- إحصائيات الملاعب العادية (التي ليس لها academy_id)
            SUM(CASE WHEN academy_id IS NULL THEN 1 ELSE 0 END) as field_bookings_count,
            COALESCE(SUM(CASE WHEN academy_id IS NULL THEN price ELSE 0 END), 0) as field_bookings_revenue,
            
            -- أعداد المشتركين (الأكاديميات)
            SUM(CASE WHEN academy_id IS NOT NULL AND status = 'active' THEN 1 ELSE 0 END) as active_subscribers,
            SUM(CASE WHEN academy_id IS NOT NULL AND (status = 'expired' OR status = 'inactive') THEN 1 ELSE 0 END) as expired_subscribers
        ")->first();

        // 4. تنسيق الإخراج
        return response()->json([
            'status' => true,
            'message' => 'تم جلب الإحصائيات بنجاح',
            'data' => [
                'total_bookings_count' => (int) $stats->total_bookings,            // إجمالي كل الحجوزات
                'cancellations_count'  => (int) $stats->total_cancelled,          // إجمالي الإلغاءات
                
                'fields_bookings_count' => (int) $stats->field_bookings_count,     // عدد حجوزات الملاعب فقط
                'fields_total_amount'   => (float) $stats->field_bookings_revenue, // مبالغ حجوزات الملاعب فقط
                
                'academy_bookings_count' => (int) $stats->academy_bookings_count,  // عدد حجوزات الأكاديمية
                'academy_total_amount'   => (float) $stats->academy_revenue,       // مبالغ حجوزات الأكاديمية
                
                'active_subscribers_count'  => (int) $stats->active_subscribers,  // عدد المشتركين النشطين
                'expired_subscribers_count' => (int) $stats->expired_subscribers  // عدد المنتهية اشتراكاتهم
            ]
        ]);
    }
}