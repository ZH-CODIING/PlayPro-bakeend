<?php

namespace App\Http\Controllers;

use App\Models\FieldBooking;
use App\Models\Field;
use App\Models\FieldPeriod;
use Illuminate\Http\Request;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Models\BookingAttendance;
use App\Models\SubscriptionRenewalPrice;
use App\Models\Academy;
use App\Models\LoyaltyPoint;
use App\Models\User;
use Illuminate\Support\Facades\DB;  
use Illuminate\Support\Facades\Hash;

class FieldBookingController extends Controller
{
  public function createContractBooking(Request $request)
{
    // 1. التحقق من الصلاحيات
    $this->checkRole();

    // 2. التحقق من البيانات (أضفنا days)
    $data = $request->validate([
        'user_name'     => 'required|string|max:255',
        'user_email'    => 'required|email|unique:users,email',
        'user_password' => 'required|string|min:6',
        'user_phone'    => 'required|string|max:20',
        'field_id'      => 'required|exists:fields,id',
        'start_date'    => 'required|date|after_or_equal:today',
        'end_date'      => 'required|date|after:start_date',
        'start_time'    => 'required',
        'end_time'      => 'required',
        'days'          => 'required|array', // مصفوفة بالأيام مثلاً ["Saturday", "Tuesday"]
        'contract_price'=> 'required|numeric|min:0',
        'payment_method'=> 'required|string',
        'paid'          => 'required|numeric|min:0',
    ]);

    try {
        DB::beginTransaction();

        // 3. إنشاء المستخدم الجديد (تم تصحيح حقل phone)
        $user = User::create([
            'name'     => $data['user_name'],
            'email'    => $data['user_email'],
            'password' => Hash::make($data['user_password']),
            'phone'    => $data['user_phone'],
            'role'     => 'user',
        ]);

        // 4. إنشاء فترة (Period) خاصة لهذا العقد
        $period = FieldPeriod::create([
            'field_id'         => $data['field_id'],
            'start_time'       => $data['start_time'],
            'end_time'         => $data['end_time'],
            'price_per_player' => $data['contract_price'],
            'capacity'         => 1,
            'is_active'        => true,
        ]);

        // 5. حساب المبالغ
        $totalPrice = $data['contract_price'];
        $remaining  = max($totalPrice - $data['paid'], 0);
        
        
        $startDate = Carbon::parse($data['start_date']);
        $endDate   = Carbon::parse($data['end_date']);

        if (now()->lt($startDate)) {
    // العقد لسه ما بدأش
$daysRemaining = abs($endDate->diffInDays($startDate));
     } elseif (now()->gt($endDate)) {
    // العقد خلص
       $daysRemaining = 0;
} else {
    // العقد شغال حاليًا
    $daysRemaining = now()->diffInDays($endDate);
}

        // 6. إنشاء الحجز (Contract Booking) مع إضافة الأيام
        $booking = FieldBooking::create([
            'user_id'               => $user->id,
            'field_id'              => $data['field_id'],
            'period_id'             => $period->id,
            'name'                  => $data['user_name'],
            'phone'                 => $data['user_phone'],
            'email'                 => $data['user_email'],
            'date'                  => $data['start_date'],
            'days'                  => implode(',', $data['days']), // تحويل [Sat, Tue] إلى "Sat,Tue"
            'renewal_date'          => $data['end_date'],
            'price'                 => $totalPrice,
            'total_before_discount' => $totalPrice,
            'paid'                  => $data['paid'],
            'remaining'             => $remaining,
            'payment_method'        => $data['payment_method'],
            'status'                => 'active',
             'days_remaining' => $daysRemaining,
            'academy_id'            => Auth::user()->academy_id ?? null,
        ]);

        // 7. العمليات الجانبية
        $field = Field::find($data['field_id']);
        if (method_exists($this, 'generateQrForBooking')) {
            $this->generateQrForBooking($booking, $field, $period);
        }
        
        if (method_exists($this, 'notifyParties')) {
            $this->notifyParties($booking, 'new_booking');
        }

        DB::commit();

        return response()->json([
            'status' => true,
            'message' => 'تم إنشاء العقد وحساب المستخدم بنجاح مع تحديد الأيام',
            'data' => $booking->load(['field', 'period', 'user'])
        ], 201);

    } catch (\Exception $e) {
        DB::rollBack();
        
        return response()->json([
            'status' => false,
            'message' => 'حدث خطأ أثناء إنشاء العقد: ' . $e->getMessage()
        ], 500);
    }
}
    
   public function bookField(Request $request)
    {
        // 1. التحقق من البيانات المدخلة
        $data = $request->validate([
            'field_id'       => 'required|exists:fields,id',
            'period_id'      => 'required|exists:field_periods,id',
            'name'           => 'required|string|max:255',
            'phone'          => 'required|string|max:20',
            'date'           => 'required|date|after_or_equal:today',
            'payment_method' => 'required|string',
            'payment_type'   => 'required|in:deposit,full', 
        ]);

        // 2. جلب الملعب والفترة المتوفرة
        $field = Field::findOrFail($data['field_id']);
        $period = FieldPeriod::where('id', $data['period_id'])
            ->where('field_id', $field->id)
            ->firstOrFail();

        // 3. التأكد من أن الموعد غير محجوز مسبقاً
        $isBooked = FieldBooking::where('field_id', $field->id)
            ->where('period_id', $period->id)
            ->whereDate('date', $data['date'])
            ->where('status', 'active')
            ->exists();

        if ($isBooked) {
            return response()->json([
                'status' => false,
                'message' => 'عذراً، هذا الموعد محجوز بالفعل'
            ], 400);
        }

        // 4. معالجة السعر (تم تغيير price إلى price_per_player بناءً على الموديل الخاص بك)
        $totalPrice = (float) ($period->price_per_player ?? 0);

        if ($totalPrice <= 0) {
            return response()->json([
                'status' => false,
                'message' => 'عذراً، سعر هذه الفترة غير محدد في النظام'
            ], 422);
        }
        
        // 5. حساب المبالغ المدفوعة والمتبقية
        if ($data['payment_type'] === 'deposit') {
            $paid = 50; // مبلغ العربون الثابت
            $remaining = max($totalPrice - $paid, 0);
        } else {
            $paid = $totalPrice;
            $remaining = 0;
        }

        // 6. إنشاء الحجز في قاعدة البيانات
        $booking = FieldBooking::create([
            'user_id'               => Auth::id(),
            'field_id'              => $field->id,
            'period_id'             => $period->id,
            'academy_id'            => null, // حجز ملاعب عادي
            'name'                  => $data['name'],
            'phone'                 => $data['phone'],
            'date'                  => $data['date'],
            'price'                 => $totalPrice,
            'total_before_discount' => $totalPrice,
            'email'                 => Auth::user()->email ?? null,
            'paid'                  => $paid,
            'remaining'             => $remaining,
            'payment_method'        => $data['payment_method'],
            'status'                => 'active',
            'renewal_date'          => null, 
            'days_remaining'        => 0,
        ]);

        // 7. توليد كود QR للحجز
        $this->generateQrForBooking($booking, $field, $period);

            // الآن استدعاء الإشعارات
            $this->notifyParties($booking, 'new_booking');
            
        return response()->json([
            'status' => true,
            'message' => 'تم حجز الملعب بنجاح',
            'data' => $booking->load(['field', 'period'])
        ], 201);
    }

    /**
     * توليد وحفظ كود QR الخاص بالحجز
     */
private function generateQrForBooking($booking, $field, $period)
{
    $qrData = [
        'booking_id'     => $booking->id,
        'customer'       => $booking->name,
        'field_name'     => $field->name,
        'booking_date'   => $booking->date->format('Y-m-d'),
        'time_slot'      => substr($period->start_time, 0, 5) . ' - ' . substr($period->end_time, 0, 5),
        'total_price'    => $booking->price,
        'amount_paid'    => $booking->paid,
        'remaining'      => $booking->remaining,
        'payment_status' => $booking->remaining > 0 ? 'Partial' : 'Fully Paid',
    ];

    $jsonContent = json_encode($qrData, JSON_UNESCAPED_UNICODE);

    $qrName = 'booking_' . $booking->id . '_' . Str::random(6) . '.png';
    $qrPath = public_path('qrcodes');

    if (!file_exists($qrPath)) {
        mkdir($qrPath, 0755, true);
    }

    QrCode::format('png')
        ->encoding('UTF-8') 
        ->size(300)
        ->margin(1)
        ->errorCorrection('H') 
        ->generate($jsonContent, $qrPath . '/' . $qrName);

    $booking->update([
        'qr_code' => url('qrcodes/' . $qrName)
    ]);
}
    
    /**
     * دالة خاصة للتحقق من الأدوار المسموح لها بالدخول
     * تشمل الأدمن، صاحب الملعب، وصاحب الأكاديمية
     */
    private function checkRole()
    {
        $user = Auth::user();
        $allowedRoles = [
            User::ROLE_ADMIN,
            User::ROLE_OWNER,
            User::ROLE_OWNER_ACADEMY,
        ];

        if (!$user || !in_array($user->role, $allowedRoles)) {
            abort(response()->json([
                'status' => false,
                'message' => 'غير مصرح لك بالوصول إلى هذه الموارد'
            ], 403));
        }
    }

    /**
     * عرض الحجوزات العادية (Admin / Owner / OwnerAcademy)
     */
    public function index(Request $request)
    {
        $this->checkRole();
        $user = Auth::user();

        $query = FieldBooking::query()
            ->whereNull('academy_id')
            ->withBasicRelations()
            ->filter($request)
            ->latest();

        // تصفية النتائج بناءً على هوية صاحب الملعب أو الأكاديمية
        if ($user->role === User::ROLE_OWNER || $user->role === User::ROLE_OWNER_ACADEMY) {
            $query->whereHas('field', function ($q) use ($user) {
                $q->where('owner_id', $user->id);
            });
        }

        $bookings = $query->paginate(20);

        foreach ($bookings as $booking) {
            $booking->refreshStatus();
                $booking->refreshDaysRemaining();

        }

        return response()->json([
            'status' => true,
            'data' => $bookings
        ]);
    }
    

public function futureBookings(Request $request)
{


    $query = FieldBooking::query()
        ->whereNull('academy_id')
        ->whereDate('date', '>=', Carbon::today()) // ✅ التاريخ أكبر من دلوقتي
        ->select(['id', 'date', 'field_id', 'period_id']) // ✅ الأعمدة المطلوبة فقط
        ->with([
            'field:id,name',        // ✅ رجّع اسم الملعب بس
            'period:id,start_time,end_time' // ✅ الفترة
        ])
        ->latest();


    $bookings = $query->get();

    return response()->json([
        'status' => true,
        'data' => $bookings
    ]);
}


    /**
     * عرض حجوزات الأكاديميات
     */
    public function indexAcademy(Request $request)
    {
        $this->checkRole();
        $user = Auth::user();

        $query = FieldBooking::query()
            ->whereNotNull('academy_id')
            ->withBasicRelations()
            ->filter($request)
            ->latest();

        if ($user->role === User::ROLE_OWNER || $user->role === User::ROLE_OWNER_ACADEMY) {
            $query->whereHas('field', function ($q) use ($user) {
                $q->where('owner_id', $user->id);
            });
        }

        $bookings = $query->paginate(20);

        foreach ($bookings as $booking) {
            $booking->refreshStatus();
        }

        return response()->json([
            'status' => true,
            'data' => $bookings
        ]);
    }

    /**
     * حجوزات المستخدم الحالي (العميل)
     */
public function myBookings(Request $request)
{
    $query = FieldBooking::query()
        ->where('user_id', Auth::id())
        ->withBasicRelations()
        ->latest();

    // الفلترة بناءً على النوع
    // 'field' = ملاعب فقط (academy_id is null)
    // 'academy' = أكاديميات فقط (academy_id is not null)
    if ($request->has('type')) {
        if ($request->type === 'field') {
            $query->whereNull('academy_id');
        } elseif ($request->type === 'academy') {
            $query->whereNotNull('academy_id');
        }
    }

    // تطبيق الفلاتر الإضافية إذا كانت موجودة في الموديل (مثل التاريخ أو الحالة)
    $query->filter($request);

    $bookings = $query->paginate(20);

    // تحديث الحالات وحساب الأيام المتبقية قبل إرسال البيانات
    foreach ($bookings as $booking) {
        $booking->refreshStatus();
        $booking->days_remaining = $booking->calculateDaysRemaining();
        
        // حفظ التحديثات إذا لزم الأمر (اختياري حسب منطق عملك)
        $booking->save(); 
    }

    return response()->json([
        'status' => true,
        'message' => 'تم جلب الحجوزات بنجاح',
        'filters_applied' => $request->type ?? 'all',
        'data' => $bookings
    ]);
}

    /**
     * إنشاء حجز جديد
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'field_id'        => 'required|exists:fields,id',
            'period_id'       => 'required|exists:field_periods,id',
            'academy_id'      => 'nullable|exists:academies,id',
            'name'            => 'required|string|max:255',
            'phone'           => 'required|string|max:20',
            'email'           => 'nullable|email',
            'date'            => 'required|date',
            'days'            => 'nullable|array',
            'days.*'          => 'string',
            'coupon_name'     => 'nullable|string',
            'players_count'   => 'nullable|integer|min:1',
            'age'             => 'nullable|integer|min:1',
            'discount'        => 'nullable|numeric|min:0',
            'payment_method'  => 'required|string',
            'payment_type'    => 'required|in:deposit,full',
            'renewal_price_id'=> 'nullable|exists:subscription_renewal_prices,id',

        ]);

        $field  = Field::findOrFail($data['field_id']);
        $period = FieldPeriod::where('id', $data['period_id'])
            ->where('field_id', $field->id)
            ->firstOrFail();

        $playersCount = $data['players_count'] ?? 1;

        $currentPlayers = FieldBooking::where('field_id', $field->id)
            ->where('period_id', $period->id)
            ->where('date', $data['date'])
            ->where('status', 'active')
            ->sum('players_count');

        if ($currentPlayers + $playersCount > (int)$period->capacity) {
            return response()->json([
                'status' => false,
                'message' => 'الفترة مكتملة'
            ], 400);
        }

        $pricePerPlayer = $period->price_per_player;

        if (!empty($data['academy_id'])) {
            $academy = Academy::find($data['academy_id']);
            if($data['renewal_price_id']){
                $totalBeforeDiscount = 0;
$months = null;
 $renewalPlan = SubscriptionRenewalPrice::findOrFail($data['renewal_price_id']);
    $months = $renewalPlan->months;
    $renewalPrice = $renewalPlan->price;

    $pricePerPlayer = $renewalPrice;
            }
            else{
            if ($academy && $academy->price_per_player) {
                $pricePerPlayer = $academy->price_per_player;
            }
        }
        }

        $basePrice = $pricePerPlayer * $playersCount;
        $manualDiscount = $data['discount'] ?? 0;
        $priceAfterManualDiscount = max($basePrice - $manualDiscount, 0);

        $couponDiscountValue = 0;
        $couponPercentage = null;
        $couponName = null;

        if (!empty($data['coupon_name'])) {
            $coupon = \App\Models\Coupon::where('name', $data['coupon_name'])
                ->where('status', 'active')
                ->whereDate('start_date', '<=', now())
                ->whereDate('end_date', '>=', now())
                ->first();

            if (!$coupon) {
                return response()->json([
                    'status' => false,
                    'message' => 'كوبون الخصم غير صالح'
                ], 422);
            }

            $couponPercentage = $coupon->discount;
            $couponDiscountValue = ($priceAfterManualDiscount * $couponPercentage) / 100;
            $couponName = $coupon->name;
        }

        $totalPrice = max($priceAfterManualDiscount - $couponDiscountValue, 0);

        if ($data['payment_type'] === 'deposit') {
            $paid = 50;
            $remaining = $totalPrice - $paid;
        } else {
            $paid = $totalPrice;
            $remaining = 0;
        }

        $booking = FieldBooking::create([
            'user_id'               => Auth::id(),
            'field_id'              => $field->id,
            'academy_id'            => $data['academy_id'] ?? null,
            'period_id'             => $period->id,
            'name'                  => $data['name'],
            'phone'                 => $data['phone'],
            'email'                 => $data['email'] ?? null,
            'date'                  => $data['date'],
            'players_count'         => $playersCount,
            'age'                   => $data['age'] ?? null,
            'total_before_discount' => $basePrice,
            'discount'              => $manualDiscount,
            'coupon_name'           => $couponName,
            'coupon_percentage'     => $couponPercentage,
            'coupon_discount'       => $couponDiscountValue,
            'price'                 => $totalPrice,
            'renewal_date'          => \Carbon\Carbon::parse($data['date'])->addDays(30),
            'days'                  => $data['days'] ?? null,
            'paid'                  => $paid,
            'remaining'             => $remaining,
            'payment_method'        => $data['payment_method'],
            'renewal_count'         => 0,
            'status'                => 'active',
        ]);


        $qrData = [
            'booking_id' => $booking->id,
            'field'      => $field->name,
            'date'       => $booking->date->format('Y-m-d'),
            'time'       => substr($period->start_time, 0, 5) . ' - ' . substr($period->end_time, 0, 5),
            'price'      => $totalPrice,
        ];

        $qrName = 'booking_' . $booking->id . '_' . Str::random(6) . '.png';
        $qrPath = public_path('qrcodes');

        if (!file_exists($qrPath)) {
            mkdir($qrPath, 0755, true);
        }

        QrCode::format('png')->size(300)
            ->generate(json_encode($qrData), $qrPath . '/' . $qrName);

        $booking->update([
            'qr_code' => url('qrcodes/' . $qrName),
            'days_remaining' => $booking->calculateDaysRemaining()
        ]);

        $this->notifyParties($booking, 'new_booking');

        return response()->json([
            'status' => true,
            'message' => 'تم إنشاء الحجز بنجاح',
            'data' => $booking->load(['field', 'period', 'academy'])
        ], 201);
    }

    /**
     * التحقق من QR
     */
public function verifyQr(Request $request)
{
    // 1. التحقق من المدخلات
    $data = $request->validate([
        'booking_id' => 'required|exists:field_bookings,id'
    ]);

    // 2. البحث عن الحجز بواسطة رابط الـ QR
    $booking = FieldBooking::where('id', $data['booking_id'])
        ->with(['field', 'period', 'academy', 'user'])
        ->first();

    if (!$booking) {
        return response()->json([
            'status' => false,
            'message' => 'عذراً، كود QR هذا غير موجود في النظام أو غير صالح.'
        ], 404);
    }

    // 3. تحديث البيانات (الحالة والأيام المتبقية للاشتراكات)
    $booking->refreshStatus();
    $booking->refreshDaysRemaining();

    // 4. فحص حالة الحجز
    if ($booking->status === 'expired') {
        return response()->json([
            'status' => false,
            'message' => 'هذا الحجز أو الاشتراك منتهي الصلاحية.',
            'data' => $booking
        ], 403);
    }

    if ($booking->status === 'cancelled') {
        return response()->json([
            'status' => false,
            'message' => 'هذا الحجز ملغي.',
            'data' => $booking
        ], 403);
    }

    // 5. تسجيل الحضور (Attendance)
    $today = Carbon::today();
    $attendance = BookingAttendance::firstOrCreate([
        'field_booking_id' => $booking->id,
        'date' => $today
    ]);

    // 6. تجهيز رسالة إضافية بخصوص المبالغ المتبقية (تحسين تجربة الموظف)
    $paymentWarning = '';
    if ($booking->remaining > 0) {
        $paymentWarning = " - تنبيه: متبقي مبلغ " . $booking->remaining . " ج.م لم يتم دفعه بعد.";
    }

    // 7. صياغة رسالة النجاح النهائية
    $statusMessage = $attendance->wasRecentlyCreated 
        ? 'تم تسجيل الحضور بنجاح' 
        : 'تم تسجيل الحضور مسبقاً لهذا اليوم';

    return response()->json([
        'status' => true,
        'message' => $statusMessage . $paymentWarning,
        'attendance_count' => $booking->attendance_count,
        'is_academy' => !is_null($booking->academy_id), // لمعرفة هل هو لاعب أكاديمية أم حجز عادي
        'data' => $booking
    ]);
}
    /**
     * عرض حجز واحد
     */
    public function show($id)
    {
        $booking = FieldBooking::with(['field', 'period',  'academy', 'user'])
            ->findOrFail($id);

        return response()->json([
            'status' => true,
            'data' => $booking
        ]);
    }

    /**
     * حذف حجز (Admin / Owner / OwnerAcademy)
     */
    public function destroy($id)
    {
        $this->checkRole();
        $user = Auth::user();
        $booking = FieldBooking::findOrFail($id);

        $isAdmin = $user->role === User::ROLE_ADMIN;
        $isOwnerOrAcademy = ($user->role === User::ROLE_OWNER || $user->role === User::ROLE_OWNER_ACADEMY) 
                            && $booking->field->owner_id === $user->id;

        if ($isAdmin || $isOwnerOrAcademy) {
            $booking->delete();
            return response()->json([
                'status' => true,
                'message' => 'تم حذف الحجز'
            ]);
        }

        return response()->json([
            'status' => false,
            'message' => 'غير مصرح لك'
        ], 403);
    }

    /**
     * تجديد حجز
     */
    public function renew(Request $request, $id)
    {
        $booking = FieldBooking::with(['field', 'period'])->findOrFail($id);
        $user = Auth::user();

        // السماح للعميل صاحب الحجز أو الأدمن أو صاحب الملعب/الأكاديمية بالتجديد
        $isAuthorized = ($user->role === User::ROLE_ADMIN) || 
                        ($user->id === $booking->user_id) ||
                        (($user->role === User::ROLE_OWNER || $user->role === User::ROLE_OWNER_ACADEMY) && $booking->field->owner_id === $user->id);

        if (!$isAuthorized) {
            return response()->json([
                'status' => false,
                'message' => 'غير مصرح لك'
            ], 403);
        }

        $data = $request->validate([
            'date'                  => 'required|date',
            'renewal_price_id'      => 'nullable:subscription_renewal_prices,id',
            'players_count'         => 'nullable|integer|min:1',
            'discount'              => 'nullable|numeric|min:0',
            'coupon_name'           => 'nullable|string',
            'payment_type'          => 'required|in:deposit,full',
            'payment_method'        => 'required|string',
        ]);

        $playersCount = $data['players_count'] ?? $booking->players_count;

        $currentPlayers = FieldBooking::where('field_id', $booking->field_id)
            ->where('period_id', $booking->period_id)
            ->where('date', $data['date'])
            ->where('id', '!=', $booking->id)
            ->where('status', 'active')
            ->sum('players_count');

        if ($currentPlayers + $playersCount > $booking->period->capacity) {
            return response()->json([
                'status' => false,
                'message' => 'الفترة مكتملة'
            ], 400);
        }

        $renewalPlan = SubscriptionRenewalPrice::findOrFail($data['renewal_price_id']);
        $months = $renewalPlan->months;
        $renewalPrice = $renewalPlan->price;

        $totalBeforeDiscount = $renewalPrice;
        $manualDiscount = $data['discount'] ?? 0;
        
        $newRenewalCount = $booking->renewal_count + 1;
        $loyaltyDiscountValue = 0;
        $loyaltyRule = LoyaltyPoint::where('points', $newRenewalCount)->first();

        if ($loyaltyRule) {
            $loyaltyDiscountValue = ($totalBeforeDiscount * $loyaltyRule->discount_percent) / 100;
        }

        $priceAfterManualDiscount = max($totalBeforeDiscount - $loyaltyDiscountValue - $manualDiscount, 0);
        
        $couponDiscountValue = 0;
        $couponPercentage = null;
        $couponName = null;

        if (!empty($data['coupon_name'])) {
            $coupon = \App\Models\Coupon::where('name', $data['coupon_name'])
                ->where('status', 'active')
                ->whereDate('start_date', '<=', now())
                ->whereDate('end_date', '>=', now())
                ->first();

            if (!$coupon) {
                return response()->json([
                    'status' => false,
                    'message' => 'كوبون الخصم غير صالح'
                ], 422);
            }

            $couponPercentage = $coupon->discount;
            $couponDiscountValue = ($priceAfterManualDiscount * $couponPercentage) / 100;
            $couponName = $coupon->name;
        }

        $finalPrice = max($priceAfterManualDiscount - $couponDiscountValue, 0);

        $deposit = 50;
        $paid = $data['payment_type'] === 'deposit' ? $deposit : $finalPrice;
        $remaining = $finalPrice - $paid;

        $renewalDate = Carbon::parse($data['date'])->addMonths($months);

        $booking->update([
            'date'                  => $data['date'],
            'players_count'         => $playersCount,
            'total_before_discount' => $totalBeforeDiscount,
            'discount'              => $manualDiscount,
            'coupon_name'           => $couponName,
            'coupon_percentage'     => $couponPercentage,
            'coupon_discount'       => $couponDiscountValue,
            'price'                 => $finalPrice,
            'paid'                  => $paid,
            'remaining'             => $remaining,
            'payment_method'        => $data['payment_method'],
            'renewal_date'          => $renewalDate,
            'renewal_price'         => $finalPrice,
            'renewal_count'         => $booking->renewal_count + 1,
            'status'                => 'active',
        ]);

        $booking->refresh();
        $booking->update([
            'days_remaining' => $booking->calculateDaysRemaining()
        ]);

        $this->notifyParties($booking, 'renewal');

        return response()->json([
            'status' => true,
            'message' => 'تم تجديد الحجز بنجاح',
            'renewal' => [
                'months'      => $months,
                'price'       => $finalPrice,
                'expires_at'  => $renewalDate->toDateString(),
            ],
            'data' => $booking->fresh()->load(['field', 'period', 'academy'])
        ]);
    }

    /**
     * تحديث بيانات الحجز (Admin / Owner / OwnerAcademy)
     */
    public function update(Request $request, $id)
    {
        $this->checkRole();
        $user = Auth::user();
        $booking = FieldBooking::with(['field', 'period'])->findOrFail($id);

        $isAdmin = $user->role === User::ROLE_ADMIN;
        $isOwnerOrAcademy = ($user->role === User::ROLE_OWNER || $user->role === User::ROLE_OWNER_ACADEMY) 
                            && $booking->field->owner_id === $user->id;

        if (!$isAdmin && !$isOwnerOrAcademy) {
            return response()->json([
                'status' => false,
                'message' => 'غير مصرح لك بتعديل هذا الحجز'
            ], 403);
        }

        $data = $request->validate([
            'field_id'        => 'sometimes|exists:fields,id',
            'period_id'       => 'sometimes|exists:field_periods,id',
            'name'            => 'sometimes|string|max:255',
            'phone'           => 'sometimes|string|max:20',
            'email'           => 'sometimes|nullable|email',
            'date'            => 'sometimes|date',
            'days'            => 'nullable|array',
            'days.*'          => 'string',
            'players_count'   => 'sometimes|integer|min:1',
            'age'             => 'sometimes|integer|min:1',
            'discount'        => 'sometimes|numeric|min:0',
            'price'           => 'sometimes|numeric|min:0',
            'renewal_price'   => 'sometimes|numeric|min:0',
            'renewal_date'    => 'sometimes|date',
            'paid'            => 'sometimes|numeric|min:0',
            'remaining'       => 'sometimes|numeric|min:0',
            'cash_deposit'    => 'sometimes|numeric|min:0',
            'payment_method'  => 'sometimes|string',
            'status'          => 'sometimes|in:active,expired,cancelled',
        ]);

        $booking->update($data);
        $booking->refresh();
        $booking->applyCashDeposit();

        $booking->update([
            'days_remaining' => $booking->calculateDaysRemaining()
        ]);

        return response()->json([
            'status' => true,
            'message' => 'تم تحديث الحجز بنجاح',
            'data' => $booking->fresh()->load(['field', 'period', 'academy', 'user'])
        ]);
    }

    /**
     * إلغاء حجز
     */
    public function cancel($id)
    {
        $user = Auth::user();
        $booking = FieldBooking::with('field')->findOrFail($id);

        // الصلاحيات: أدمن أو صاحب الحجز أو صاحب الملعب/الأكاديمية
        $isAuthorized = ($user->role === User::ROLE_ADMIN) || 
                        ($booking->user_id === $user->id) ||
                        (($user->role === User::ROLE_OWNER || $user->role === User::ROLE_OWNER_ACADEMY) && $booking->field->owner_id === $user->id);

        if (!$isAuthorized) {
            return response()->json([
                'status' => false,
                'message' => 'غير مصرح لك بإلغاء هذا الحجز'
            ], 403);
        }

        if ($booking->status !== 'active') {
            return response()->json([
                'status' => false,
                'message' => 'لا يمكن إلغاء هذا الحجز'
            ], 400);
        }

        $bookingDateTime = Carbon::parse(
            $booking->date->format('Y-m-d') . ' ' . $booking->period->start_time
        );

        $lastCancelTime = $bookingDateTime->copy()->subHours(24);

        if (now() >= $lastCancelTime && $user->role !== User::ROLE_ADMIN) {
            return response()->json([
                'message' => 'لا يمكن إلغاء الحجز قبل الموعد بأقل من 24 ساعة'
            ], 403);
        }

        $booking->update(['status' => 'cancelled']);

        return response()->json([
            'status' => true,
            'message' => 'تم إلغاء الحجز بنجاح',
            'data' => $booking->fresh()
        ]);
    }

public function statistics()
{
    $this->checkRole();
    $user = Auth::user();

    $query = FieldBooking::query()
        ->whereNull('academy_id');
       if (
        $user->role === User::ROLE_OWNER ||
        $user->role === User::ROLE_OWNER_ACADEMY
    ) {
        $query->whereHas('field', function ($q) use ($user) {
            $q->where('owner_id', $user->id);
        });
    }

    $stats = FieldBooking::statistics($query);

    return response()->json([
        'status' => true,
        'data' => $stats
    ]);
}

public function academyStatistics()
{
    $this->checkRole();
    $user = Auth::user();

    $query = FieldBooking::query()
        ->whereNotNull('academy_id');

    if (
        $user->role === User::ROLE_OWNER ||
        $user->role === User::ROLE_OWNER_ACADEMY
    ) {
        $query->whereHas('field', function ($q) use ($user) {
            $q->where('owner_id', $user->id);
        });
    }

    $stats = FieldBooking::academyStatistics($query);

    return response()->json([
        'status' => true,
        'data' => $stats
    ]);
}


    /**
     * جلب العملاء المتعثرين (Admin / Owner / OwnerAcademy)
     */
public function getInactiveAndDebtorCustomers(Request $request)
    {
        $this->checkRole();
        $user = Auth::user();
        $now = Carbon::now();
        $oneWeekAgo = Carbon::now()->subDays(7);

        $query = FieldBooking::query()
            ->withBasicRelations()
            ->filter($request)
            ->where(function ($q) use ($now, $oneWeekAgo) {
                // شرط الانتهاء
                $q->where(function ($sub) use ($now) {
                    $sub->where('status', 'expired')
                        ->orWhereDate('renewal_date', '<', $now->toDateString());
                })
                // أو شرط التعثر المالي (مضى عليه أكثر من أسبوع)
                ->orWhere(function ($sub) use ($oneWeekAgo) {
                    $sub->where('remaining', '>', 0)
                        ->whereDate('created_at', '<=', $oneWeekAgo->toDateString());
                });
            });

        // 🟢 التعديل هنا لضمان الفصل بين الصلاحيات
        if ($user->role === User::ROLE_ADMIN) {
            // الآدمن لا يحتاج لإضافة أي شروط (سيشاهد كل الملاعب)
        } elseif (in_array($user->role, [User::ROLE_OWNER, User::ROLE_OWNER_ACADEMY])) {
            // الأونر يرى فقط حجوزات الملاعب التي يملكها
            $query->whereHas('field', function ($q) use ($user) {
                $q->where('owner_id', $user->id);
            });
        } else {
            // أي رتبة أخرى (مثل المستخدم العادي) يرى حجوزاته هو فقط لزيادة الأمان
            $query->where('user_id', $user->id);
        }

        $bookings = $query->latest()->paginate(20);

        // تحديث البيانات في الـ Collection
        $bookings->getCollection()->transform(function ($booking) {
            $booking->refreshStatus();
            $booking->days_remaining = $booking->calculateDaysRemaining();
            return $booking;
        });

        return response()->json([
            'status' => true,
            'message' => 'قائمة العملاء المتعثرين والمنتهية اشتراكاتهم',
            'data' => $bookings
        ]);
    }
    /**
     * إرسال رسائل الواتساب
     */
private function notifyParties($booking, $type = 'new_booking')
{
    // تحميل البيانات الناقصة
    $booking->loadMissing(['field.owner', 'period']);
    $field = $booking->field;
    $owner = $field->owner;
    $period = $booking->period;
    
    // تنظيف أرقام الهواتف
    $customerPhone = str_replace(['@c.us', ' '], '', $booking->phone);
    $ownerPhone = $owner ? str_replace(['@c.us', ' '], '', $owner->phone_number) : null;
    
    $bookingId = $booking->id; 
    $qrCodeUrl = $booking->qr_code;

    // 1. رسالة العميل (اللاعب)
    if ($type === 'new_booking') {
        $customerMessage = "*تم تأكيد حجزك بنجاح*" . "\n\n"
            . "*رقم الحجز:* " . $bookingId . "\n"
            . "*الملعب:* " . $field->name . "\n"
            . "*التاريخ:* " . $booking->date->format('Y-m-d') . "\n"
            . "*التوقيت:* من " . substr($period->start_time, 0, 5) . " إلى " . substr($period->end_time, 0, 5) . "\n"
            . "*المبلغ المدفوع:* " . $booking->paid . " ج.م\n"
            . ($booking->remaining > 0 ? "*المبلغ المتبقي:* " . $booking->remaining . " ج.م\n" : "*حالة السداد:* تم دفع المبلغ بالكامل\n") . "\n"
            . "*رابط الـ QR Code للدخول:*" . "\n" . $qrCodeUrl . "\n\n"
            . "يرجى الاحتفاظ بهذا الرابط لإبرازه عند الدخول.";
    } else {
        $customerMessage = "*تم تجديد اشتراكك بنجاح*" . "\n\n"
            . "*رقم الاشتراك:* " . $bookingId . "\n"
            . "*الملعب:* " . $field->name . "\n"
            . "*تاريخ انتهاء الاشتراك:* " . $booking->renewal_date->toDateString() . "\n"
            . "*قيمة التجديد:* " . $booking->paid . " ج.م\n\n"
            . "*رابط الـ QR Code الجديد:*" . "\n" . $qrCodeUrl . "\n\n"
            . "شكراً لتعاملك معنا.";
    }

    // 2. رسالة صاحب الملعب (Owner)
    $title = ($type === 'new_booking' ? "*إشعار حجز جديد*" : "*إشعار تجديد اشتراك*");
    $ownerMessage = $title . "\n\n"
        . "*العميل:* " . $booking->name . "\n"
        . "*رقم الهاتف:* " . $customerPhone . "\n"
        . "*الملعب:* " . $field->name . "\n"
        . "*الموعد:* " . $booking->date->format('Y-m-d') . " الساعة " . substr($period->start_time, 0, 5) . "\n"
        . "*المبلغ المحصل:* " . $booking->paid . " ج.م\n"
        . ($booking->remaining > 0 ? "*المبلغ المتبقي:* " . $booking->remaining . " ج.م" : "*حالة السداد:* مدفوع بالكامل") . "\n\n"
        . "رقم المرجع: #" . $bookingId;

    // 3. إرسال الرسائل
    \App\Jobs\SendWhatsAppMessageJob::dispatch($customerPhone, $customerMessage);

    if ($ownerPhone) {
        \App\Jobs\SendWhatsAppMessageJob::dispatch($ownerPhone, $ownerMessage);
    }
}

}