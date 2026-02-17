<?php

namespace App\Http\Controllers;

use App\Models\FieldBooking;
use App\Models\Field;
use App\Models\FieldPeriod;
use App\Models\User;
use App\Models\Academy;
use App\Models\SubscriptionRenewalPrice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Carbon\Carbon;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class AdminBookingController extends Controller
{
    /**
     * إضافة حجز أو اشتراك يدوياً بواسطة الأدمن/صاحب العمل
     */
    public function manualStore(Request $request)
    {
        // 1. التحقق من الصلاحيات (يسمح فقط للأدمن وأصحاب الملاعب/الأكاديميات)
        $this->checkAdminAccess();

        // 2. التحقق من البيانات
        $data = $request->validate([
            // بيانات المستخدم
            'user_name'     => 'required|string|max:255',
            'user_email'    => 'required|email',
            'user_phone'    => 'required|string|max:20',
            'user_password' => 'nullable|string|min:6', // اختياري، سيتم إنشاء واحد تلقائي لو فارغ
            
            // بيانات الحجز
            'field_id'      => 'required|exists:fields,id',
            'period_id'     => 'required|exists:field_periods,id',
            'academy_id'    => 'nullable|exists:academies,id',
            'date'          => 'required|date',
            'days'          => 'nullable|array', // للأيام المحددة (السبت، الأحد...)
            
            // بيانات مالية
            'total_price'   => 'required|numeric|min:0',
            'paid_amount'   => 'required|numeric|min:0',
            'payment_method'=> 'required|string',
            
            // في حال كان تجديد اشتراك أكاديمية
            'renewal_price_id' => 'nullable|exists:subscription_renewal_prices,id',
        ]);

        try {
            DB::beginTransaction();

            // 3. التعامل مع المستخدم (بحث أو إنشاء)
            $user = User::where('email', $data['user_email'])->first();
            
            if (!$user) {
                $user = User::create([
                    'name'     => $data['user_name'],
                    'email'    => $data['user_email'],
                    'phone'    => $data['user_phone'],
                    'password' => Hash::make($data['user_password'] ?? '12345678'),
                    'role'     => 'user',
                ]);
                $userCreated = true;
            } else {
                $userCreated = false;
            }

            // 4. جلب بيانات الملعب والفترة
            $field = Field::findOrFail($data['field_id']);
            $period = FieldPeriod::findOrFail($data['period_id']);

            // 5. حساب المبالغ المتبقية
            $totalPrice = (float) $data['total_price'];
            $paid = (float) $data['paid_amount'];
            $remaining = max($totalPrice - $paid, 0);

            // 6. تحديد تاريخ انتهاء الاشتراك (renewal_date)
            // إذا كان هناك خطة تجديد، نأخذ عدد الشهور منها، وإلا نفترض شهر واحد
            $months = 1;
            if ($data['renewal_price_id']) {
                $plan = SubscriptionRenewalPrice::find($data['renewal_price_id']);
                $months = $plan->months ?? 1;
            }
            $renewalDate = Carbon::parse($data['date'])->addMonths($months);

            // 7. إنشاء الحجز
            $booking = FieldBooking::create([
                'user_id'               => $user->id,
                'field_id'              => $field->id,
                'period_id'             => $period->id,
                'academy_id'            => $data['academy_id'] ?? null,
                'name'                  => $data['user_name'],
                'phone'                 => $data['user_phone'],
                'email'                 => $data['user_email'],
                'date'                  => $data['date'],
                'days'                  => isset($data['days']) ? implode(',', $data['days']) : null,
                'price'                 => $totalPrice,
                'total_before_discount' => $totalPrice,
                'paid'                  => $paid,
                'remaining'             => $remaining,
                'payment_method'        => $data['payment_method'],
                'status'                => 'active',
                'renewal_date'          => $renewalDate,
                'days_remaining'        => Carbon::now()->diffInDays($renewalDate, false) > 0 ? Carbon::now()->diffInDays($renewalDate) : 0,
            ]);

            // 8. توليد الـ QR Code
            $this->generateQr($booking, $field, $period);

            // 9. الإشعارات (اختياري)
            if (method_exists($this, 'notifyParties')) {
                $this->notifyParties($booking, 'new_booking');
            }

            DB::commit();

            return response()->json([
                'status'  => true,
                'message' => $userCreated ? 'تم إنشاء حساب جديد وحجز الاشتراك بنجاح' : 'تم إضافة الاشتراك للمستخدم الحالي بنجاح',
                'data'    => $booking->load(['user', 'field', 'period'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status'  => false,
                'message' => 'حدث خطأ: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * دالة توليد الـ QR
     */
    private function generateQr($booking, $field, $period)
    {
        $qrData = [
            'booking_id' => $booking->id,
            'name'       => $booking->name,
            'field'      => $field->name,
            'period'     => substr($period->start_time, 0, 5) . ' - ' . substr($period->end_time, 0, 5),
            'type'       => $booking->academy_id ? 'Academy' : 'Field',
        ];

        $qrName = 'booking_' . $booking->id . '_' . Str::random(6) . '.png';
        $qrPath = public_path('qrcodes');

        if (!file_exists($qrPath)) {
            mkdir($qrPath, 0755, true);
        }

        QrCode::format('png')->size(300)->generate(json_encode($qrData), $qrPath . '/' . $qrName);

        $booking->update(['qr_code' => url('qrcodes/' . $qrName)]);
    }

    /**
     * التحقق من الصلاحيات
     */
    private function checkAdminAccess()
    {
        $user = Auth::user();
        $allowed = [User::ROLE_ADMIN, User::ROLE_OWNER, User::ROLE_OWNER_ACADEMY];

        if (!$user || !in_array($user->role, $allowed)) {
            abort(response()->json(['status' => false, 'message' => 'غير مصرح لك'], 403));
        }
    }
}