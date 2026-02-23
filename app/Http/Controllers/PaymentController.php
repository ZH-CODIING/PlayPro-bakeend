<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Order;
use App\Models\FieldBooking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use App\Models\User;
use Endroid\QrCode\Builder\Builder;
use Illuminate\Support\Facades\Storage;

class PaymentController extends Controller
{
    /**
 * تحديث الملاحظات الخاصة بعملية الدفع
 */
public function updateNotes(Request $request, $id)
{
    // 1. التحقق من المدخلات
    $request->validate([
        'notes' => 'nullable|string|max:1000',
    ]);

    try {
        // 2. البحث عن الدفع
        $payment = Payment::findOrFail($id);

        // 3. التحقق من الصلاحيات (اختياري: إذا أردت أن يعدلها المالك أو الأدمن فقط)
        $user = Auth::user();
        if ($user->role !== User::ROLE_ADMIN && $user->id !== $payment->user_id) {
             return response()->json(['status' => false, 'message' => 'غير مصرح لك بتعديل هذه الملاحظات'], 403);
        }

        // 4. تحديث الحقل (تأكد أن حقل notes موجود في fillable في موديل Payment)
        $payment->update([
            'notes' => $request->notes
        ]);

        return response()->json([
            'status' => true,
            'message' => 'تم تحديث الملاحظات بنجاح',
            'data' => [
                'notes' => $payment->notes
            ]
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'حدث خطأ أثناء التحديث',
            'error' => $e->getMessage()
        ], 500);
    }
}
    /**
     * معالجة الويب هوك من بايموب
     * يتم استدعاؤها تلقائياً عند أي تحديث على حالة الدفع
     */
    public function webhook(Request $request)
    {
        try {
            // 1️⃣ التحقق من صحة الطلب باستخدام HMAC
            // يمكنك تعطيل التحقق مؤقتاً في بيئة الاختبار بإضافة PAYMOB_SKIP_HMAC=true في .env
            $skipHmacVerification = config('services.paymob.skip_hmac', false);
            
            if (!$skipHmacVerification && !$this->verifyHmac($request)) {
                Log::warning('Paymob Webhook: Invalid HMAC signature', [
                    'ip' => $request->ip(),
                    'data' => $request->all()
                ]);
                
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid signature'
                ], 403);
            }

            // 2️⃣ استخراج البيانات من الويب هوك
            $data = $request->input('obj');
            
            $transactionId = $data['id'] ?? null;
            $success = $data['success'] ?? false;
            $pending = $data['pending'] ?? false;
            $amountCents = $data['amount_cents'] ?? 0;
            $merchantOrderId = $data['order']['merchant_order_id'] ?? null;
            $gatewayOrderId = $data['order']['id'] ?? null;
            
            // معلومات إضافية مفيدة
            $refundedAmountCents = $data['refunded_amount_cents'] ?? 0;
            $isRefund = $data['is_refund'] ?? false;
            $isVoid = $data['is_void'] ?? false;
            $errorOccurred = $data['error_occured'] ?? false;

            Log::info('Paymob Webhook Received', [
                'transaction_id' => $transactionId,
                'merchant_order_id' => $merchantOrderId,
                'success' => $success,
                'pending' => $pending,
                'is_refund' => $isRefund,
                'is_void' => $isVoid,
                'error' => $errorOccurred
            ]);

            // 3️⃣ البحث عن سجل الدفع في قاعدة البيانات
            $payment = null;
            
            if ($merchantOrderId) {
                $paymentId = (int) str_replace('PAYMENT_', '', $merchantOrderId);
                $payment = Payment::find($paymentId);
            }
            
            if (!$payment && $gatewayOrderId) {
                $payment = Payment::where('gateway_reference', $gatewayOrderId)->first();
            }

            if (!$payment) {
                Log::error('Paymob Webhook: Payment not found', [
                    'merchant_order_id' => $merchantOrderId,
                    'gateway_order_id' => $gatewayOrderId
                ]);
                
                return response()->json([
                    'status' => false,
                    'message' => 'Payment not found'
                ], 404);
            }

            // 4️⃣ تحديد الحالة الجديدة للدفع
            $newStatus = $this->determinePaymentStatus(
                $success, 
                $pending, 
                $isRefund, 
                $isVoid, 
                $errorOccurred
            );

            // 5️⃣ تحديث سجل الدفع والكيانات المرتبطة داخل Transaction
            DB::transaction(function () use ($payment, $newStatus, $transactionId, $data, $merchantOrderId) {
                
                // تحديث سجل الدفع
                $payment->update([
                    'status' => $newStatus,
                    'payment_id' => $transactionId,
                    'meta' => array_merge($payment->meta ?? [], [
                        'webhook_data' => $data,
                        'updated_at' => now()->toDateTimeString()
                    ])
                ]);

                Log::info('Payment status updated', [
                    'payment_id' => $payment->id,
                    'old_status' => $payment->getOriginal('status'),
                    'new_status' => $newStatus
                ]);

                // 6️⃣ تحديث حالة الطلب أو الحجز المرتبط
                $this->updateRelatedEntity($payment, $newStatus, $transactionId, $merchantOrderId);
            });

            // 7️⃣ الرد على بايموب بنجاح
            return response()->json([
                'status' => true,
                'message' => 'Webhook processed successfully'
            ], 200);

        } catch (\Exception $e) {
            Log::error('Paymob Webhook Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * التحقق من صحة توقيع HMAC
     */
/**
 * التحقق من صحة توقيع HMAC
 */
private function verifyHmac(Request $request): bool
{
    $data = $request->input('obj');
    $receivedHmac = $request->query('hmac');
    $hmacSecret = config('services.paymob.hmac');

    if (!$data || !$receivedHmac || !$hmacSecret) {
        return false;
    }

    // الترتيب الأبجدي الصارم للحقول المطلوبة من Paymob
    // يجب تحويل القيم المنطقية (true/false) لنصوص دقيقة
    $string = 
        (isset($data['amount_cents']) ? $data['amount_cents'] : '') .
        (isset($data['created_at']) ? $data['created_at'] : '') .
        (isset($data['currency']) ? $data['currency'] : '') .
        ($data['error_occured'] ? 'true' : 'false') .
        ($data['has_parent_transaction'] ? 'true' : 'false') .
        (isset($data['id']) ? $data['id'] : '') .
        (isset($data['integration_id']) ? $data['integration_id'] : '') .
        ($data['is_3d_secure'] ? 'true' : 'false') .
        ($data['is_auth'] ? 'true' : 'false') .
        ($data['is_capture'] ? 'true' : 'false') .
        ($data['is_refunded'] ? 'true' : 'false') .
        ($data['is_standalone_payment'] ? 'true' : 'false') .
        ($data['is_voided'] ? 'true' : 'false') .
        (isset($data['order']['id']) ? $data['order']['id'] : '') .
        (isset($data['owner']) ? $data['owner'] : '') .
        ($data['pending'] ? 'true' : 'false') .
        (isset($data['source_data']['pan']) ? $data['source_data']['pan'] : '') .
        (isset($data['source_data']['sub_type']) ? $data['source_data']['sub_type'] : '') .
        (isset($data['source_data']['type']) ? $data['source_data']['type'] : '') .
        ($data['success'] ? 'true' : 'false');

    $calculatedHmac = hash_hmac('sha512', $string, $hmacSecret);

    // تسجيل للمقارنة في حالة الفشل (اختياري للتشخيص)
    if (!hash_equals($calculatedHmac, $receivedHmac)) {
        Log::error('Paymob HMAC Mismatch', [
            'built_string' => $string,
            'calculated' => $calculatedHmac,
            'received' => $receivedHmac
        ]);
    }

    return hash_equals($calculatedHmac, $receivedHmac);
}

    /**
     * تحديد حالة الدفع بناءً على استجابة بايموب
     */
    private function determinePaymentStatus(
        bool $success, 
        bool $pending, 
        bool $isRefund, 
        bool $isVoid, 
        bool $errorOccurred
    ): string {
        // حالة الاسترداد (Refund)
        if ($isRefund || $isVoid) {
            return 'refunded';
        }

        // حالة الخطأ أو الفشل
        if ($errorOccurred || (!$success && !$pending)) {
            return 'failed';
        }

        // حالة قيد الانتظار
        if ($pending) {
            return 'pending';
        }

        // حالة النجاح
        if ($success) {
            return 'paid';
        }

        // الحالة الافتراضية
        return 'pending';
    }

    /**
     * تحديث حالة الطلب أو الحجز المرتبط بالدفع
     */
private function updateRelatedEntity(
    Payment $payment, 
    string $paymentStatus, 
    ?string $transactionId,
    ?string $merchantOrderId
): void {
    try {
        // ✅ 1. تحديث طلبات المتجر (Order) إن وجدت
        if ($payment->order_id) {
            $order = Order::find($payment->order_id);
            if ($order) {
                // تحويل حالة الدفع لحالة طلب (مثلاً: paid -> processing أو completed)
                $orderStatus = $this->mapPaymentStatusToOrderStatus($paymentStatus);
                
                $order->update([
                    'status' => $orderStatus
                ]);

                Log::info('Order status updated', [
                    'order_id' => $order->id,
                    'new_status' => $orderStatus
                ]);
            }
        }

        // ✅ 2. تحديث حجز الملعب أو الأكاديمية (Field Booking)
        if ($payment->field_booking_id) {
            $booking = FieldBooking::find($payment->field_booking_id);
            
            if ($booking) {
                // نجهز الحقول الثلاثة الأساسية التي طلبت تحديثها
                $updateData = [
                    'payment_status'    => $paymentStatus,    // حالة الدفع (مدفوع/فشل)
                    'transaction_id'    => $transactionId,    // رقم العملية
                    'merchant_order_id' => $merchantOrderId,   // رقم الطلب من بايموب
                ];

                // // ✅ إذا نجحت عملية الدفع - تحديث المبالغ المالية
                // if ($paymentStatus === 'paid') {
                //     // المبالغ: المدفوع الجديد = المدفوع القديم + مبلغ العملية الحالية
                //     $updateData['paid'] = $booking->paid + $payment->amount;
                //     $updateData['remaining'] = max(0, $booking->price - $updateData['paid']);
                    
                 $this->createZatcaQrForPayment($payment);
                    
                //     // تطبيق منطق العربون النقدي (Cash Deposit) إن وجد
                //     if ($booking->cash_deposit > 0) {
                //         $booking->applyCashDeposit();
                //     }
                // }

                // ✅ إذا كانت العملية استرداد (Refund)
                if ($paymentStatus === 'refunded') {
                    $updateData['paid'] = max(0, $booking->paid - $payment->amount);
                    $updateData['remaining'] = $booking->price - $updateData['paid'];
                }

                // تنفيذ التحديث للحقول المحددة أعلاه فقط
                // لاحظ أننا لم نضع 'status' هنا لتجنب خطأ التوافق
                $booking->update($updateData);

                // ✅ الآن نقوم بتحديث "حالة الحجز" (status) بناءً على منطق النظام الداخلي والتوقيت
                $booking->refresh();           // إعادة تحميل البيانات من القاعدة
                $booking->refreshStatus();      // تحديث (active/expired) بناءً على تاريخ الحجز
                $booking->refreshDaysRemaining(); // تحديث الأيام المتبقية

                Log::info('Field booking updated successfully', [
                    'booking_id'     => $booking->id,
                    'payment_status' => $paymentStatus,
                    'system_status'  => $booking->status // الحالة الداخلية للنظام
                ]);
            }
        }

    } catch (\Exception $e) {
        Log::error('Error updating related entity: ' . $e->getMessage(), [
            'payment_id'       => $payment->id,
            'field_booking_id' => $payment->field_booking_id,
            'trace'            => $e->getTraceAsString()
        ]);
        
        throw $e; // إلغاء العملية Transaction في حالة حدوث خطأ
    }
}

    /**
     * تحويل حالة الدفع إلى حالة الطلب
     */
    private function mapPaymentStatusToOrderStatus(string $paymentStatus): string
    {
        return match($paymentStatus) {
            'paid' => 'paid',           // الطلب مؤكد
            'pending' => 'pending',          // قيد الانتظار
            'failed' => 'cancelled',         // ملغي
            'refunded' => 'refunded',        // مسترد
            default => 'pending'
        };
    }

    /**
     * تحويل حالة الدفع إلى حالة الحجز
     */
    private function mapPaymentStatusToBookingStatus(string $paymentStatus): string
    {
        return match($paymentStatus) {
            'paid' => 'confirmed',           // الحجز مؤكد
            'pending' => 'pending',          // قيد الانتظار
            'failed' => 'cancelled',         // ملغي
            'refunded' => 'cancelled',       // ملغي (بعد الاسترداد)
            default => 'pending'
        };
    }

    // ========== باقي الدوال الأصلية ==========

    public function store(Request $request)
    {
        // نفس الكود الموجود...
        $data = $request->validate([
            'amount' => 'required|numeric|min:1',
            'order_id' => 'nullable|exists:orders,id',
            'field_booking_id' => 'nullable|exists:field_bookings,id',
        ]);

        if (! $data['order_id'] && ! $data['field_booking_id']) {
            return response()->json([
                'message' => 'order_id or field_booking_id is required'
            ], 422);
        }

        try {
            $payment = Payment::create([
                'user_id' => Auth::id(),
                'order_id' => $data['order_id'] ?? null,
                'field_booking_id' => $data['field_booking_id'] ?? null,
                'gateway' => 'paymob',
                'amount' => $data['amount'],
                'currency' => 'SAR',
                'status' => 'pending',
            ]);

            $authResponse = Http::post('https://ksa.paymob.com/api/auth/tokens', [
                'api_key' => config('services.paymob.api_key'),
            ]);

            if (! $authResponse->successful()) {
                throw new \Exception('Paymob Auth Failed: ' . $authResponse->body());
            }

            $token = $authResponse->json('token');

            $orderResponse = Http::withToken($token)->post(
                'https://ksa.paymob.com/api/ecommerce/orders',
                [
                    'amount_cents' => (int) ($payment->amount * 100),
                    'currency' => 'SAR',
                    'merchant_order_id' => "PAYMENT_" . $payment->id,
                    'items' => [],
                ]
            );

            if (! $orderResponse->successful()) {
                throw new \Exception('Paymob Order Failed: ' . $orderResponse->body());
            }

            $gatewayOrderId = $orderResponse->json('id');

            $keyResponse = Http::withToken($token)->post(
                'https://ksa.paymob.com/api/acceptance/payment_keys',
                [
                    'amount_cents' => (int) ($payment->amount * 100),
                    'expiration' => 3600,
                    'order_id' => $gatewayOrderId,
                    'currency' => 'SAR',
                    'integration_id' => (int) config('services.paymob.integration_id'),
                    'billing_data' => [
                        'first_name' => Auth::user()->name ?? 'Guest',
                        'last_name'  => 'User',
                        'email'      => Auth::user()->email ?? 'customer@example.com',
                        'phone_number' => Auth::user()->phone ?? '966500000000',
                        'apartment' => 'NA',
                        'floor' => 'NA',
                        'street' => 'NA',
                        'building' => 'NA',
                        'shipping_method' => 'NA',
                        'postal_code' => 'NA',
                        'city' => 'NA',
                        'country' => 'SA',
                        'state' => 'NA',
                    ],
                ]
            );

            if (! $keyResponse->successful()) {
                throw new \Exception('Payment Key Failed: ' . $keyResponse->body());
            }

            $payment->update([
                'gateway_reference' => $gatewayOrderId,
            ]);

            $paymentKey = $keyResponse->json('token');
            $iframeId = config('services.paymob.iframe_id');

            return response()->json([
                'status' => true,
                'payment_id' => $payment->id,
                'checkout_url' => "https://ksa.paymob.com/api/acceptance/iframes/{$iframeId}?payment_token={$paymentKey}",
            ], 201);

        } catch (\Exception $e) {
            if (isset($payment)) {
                $payment->update(['status' => 'failed']);
            }

            Log::error('Payment Store Error: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Payment initialization failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

public function paymentCallback(Request $request)
{
    $merchantOrderId = $request->query('merchant_order_id');
    $isSuccess = $request->query('success') === 'true';
    
    $payment = null;
    if ($merchantOrderId) {
        $paymentId = (int) str_replace('PAYMENT_', '', $merchantOrderId);
        $payment = Payment::find($paymentId);
    } 

    if (!$payment) {
        return redirect()->to("https://playpro-site.netlify.app/payment-status?status=error");
    }

    // التحقق مما إذا كانت الفاتورة لم تُدفع بعد
    if ($payment->status !== 'paid') {
        $payment->update([
            'status' => $isSuccess ? 'paid' : 'rejected'
        ]);

        // ✅ إضافة توليد QR الخاص بـ ZATCA عند نجاح الدفع فقط
        if ($isSuccess) {
            $this->createZatcaQrForPayment($payment);
        }
    }

    return redirect()->to("https://playpro-site.netlify.app/payment-status?" . http_build_query([
        'id' => $payment->id,
        'status' => $payment->status 
    ]));
}
    public function refund(Payment $payment)
    {
        if ($payment->status !== 'paid') {
            return response()->json([
                'status' => false,
                'message' => 'لا يمكن استرداد مبلغ عملية غير ناجحة'
            ], 422);
        }

        $response = Http::withHeaders([
            'Authorization' => 'Token ' . config('services.paymob.secret_key'),
            'Content-Type'  => 'application/json'
        ])->post('https://ksa.paymob.com/api/acceptance/void_refund/refund', [
            'transaction_id' => (int) $payment->payment_id,
            'amount_cents'   => (int) ($payment->amount * 100),
        ]);

        if ($response->successful()) {
            $result = $response->json();
            
            $payment->update([
                'status' => 'refunded',
                'meta' => array_merge($payment->meta ?? [], [
                    'refund_details' => $result
                ]),
            ]);

            return response()->json([
                'status' => true,
                'message' => 'تم استرداد المبلغ بنجاح',
                'data' => $result
            ]);
        }

        Log::error('Paymob Refund Failed: ' . $response->body());
        
        return response()->json([
            'status' => false,
            'message' => 'فشل استرداد المبلغ من بايموب',
            'error' => $response->json()
        ], $response->status());
    }

public function index(Request $request)
{
    $user = Auth::user();

    $allowedRoles = [
        User::ROLE_ADMIN,
        User::ROLE_OWNER,
        User::ROLE_OWNER_ACADEMY
    ];

    // 🔐 التحقق من الصلاحيات
    if (!in_array($user->role, $allowedRoles)) {
        return response()->json([
            'status'  => false,
            'message' => 'غير مصرح لك'
        ], 403);
    }

    // 📦 Query الأساسي
    $payments = Payment::query()
        ->with([
            'fieldBooking.field',
            'fieldBooking.academy'
        ]);

    // 👑 فلترة حسب المالك (Owner / Owner Academy)
    if (
        $user->role === User::ROLE_OWNER ||
        $user->role === User::ROLE_OWNER_ACADEMY
    ) {
        $payments->whereHas('fieldBooking.field', function ($q) use ($user) {
            $q->where('owner_id', $user->id);
        });
    }

    // 🧩 فلتر حسب نوع الحجز (ملعب / أكاديمية)
    if ($request->filled('booking_type')) {
        if ($request->booking_type === 'field') {
            $payments->whereHas('fieldBooking', function ($q) {
                $q->whereNull('academy_id');
            });
        }

        if ($request->booking_type === 'academy') {
            $payments->whereHas('fieldBooking', function ($q) {
                $q->whereNotNull('academy_id');
            });
        }
    }

    // 📅 فلتر حسب التاريخ
    if ($request->filled('from_date')) {
        $payments->whereDate('created_at', '>=', $request->from_date);
    }

    if ($request->filled('to_date')) {
        $payments->whereDate('created_at', '<=', $request->to_date);
    }

    // 🔄 فلتر حسب الحالة
    if ($request->filled('status')) {
        $payments->where('status', $request->status);
    }

    // 🔍 بحث ديناميكي
if ($request->filled('search')) {
    $search = $request->search;

    $payments->where(function ($q) use ($search) {
        $q->where('payment_id', 'like', "%{$search}%")
          ->orWhere('gateway_reference', 'like', "%{$search}%")
          ->orWhereHas('user', function ($q2) use ($search) {
              $q2->where('name', 'like', "%{$search}%");
          });
    });
}


    // ⏳ الترتيب + Pagination
    $payments = $payments->latest()->paginate(10);

    return response()->json([
        'status' => true,
        'data'   => $payments
    ]);
}




/**
 * توليد صورة QR المتوافقة مع ZATCA وحفظها
 */
private function createZatcaQrForPayment($payment)
{
    try {
        // البيانات الرسمية من الشهادة التي أرفقتها
        $sellerName = "شركة بلاي برو (ديل روز لتقديم المشروبات)"; 
        $vatNumber = "311527964100003"; 
        
        // حساب الضريبة: إذا كان المبلغ الإجمالي (amount) شامل الضريبة (15%)
        // المعادلة: المبلغ الإجمالي - (المبلغ الإجمالي / 1.15)
        $totalAmount = (float) $payment->amount;
        $vatAmount = $totalAmount - ($totalAmount / 1.15);

        // التوقيت بتنسيق ISO 8601 المطلوب من الزكاة
        $issueDateTime = $payment->created_at->format('Y-m-d\TH:i:s\Z');

        // ترميز البيانات بنظام TLV ثم Base64
        $qrData = $this->generateZatcaTlv(
            $sellerName,
            $vatNumber,
            $issueDateTime,
            $totalAmount,
            $vatAmount
        );

        // إنشاء الصورة
        $fileName = 'qr-codes/zatca-' . $payment->id . '.png';
        $result = Builder::create()
            ->data($qrData)
            ->size(300)
            ->margin(10)
            ->build();

        // تخزين الصورة في القرص العام
        Storage::disk('public')->put($fileName, $result->getString());
        
        // تحديث سجل الدفع برابط الصورة في حقل الميتا
        $currentMeta = $payment->meta ?? [];
        $payment->update([
            'meta' => array_merge($currentMeta, [
                'zatca_qr_url' => url('storage/' . $fileName),
                'vat_amount' => round($vatAmount, 2),
                'seller_name' => $sellerName
            ])
        ]);

        return url('storage/' . $fileName);

    } catch (\Exception $e) {
        Log::error("ZATCA QR Generation Failed: " . $e->getMessage());
        return null;
    }
}

/**
 * دالة الترميز الخاصة بـ ZATCA (TLV Encode)
 */
private function generateZatcaTlv($seller, $vatNo, $time, $total, $vat)
{
    $innerTlv = 
        $this->tlvSlot(1, $seller) .
        $this->tlvSlot(2, $vatNo) .
        $this->tlvSlot(3, $time) .
        $this->tlvSlot(4, number_format($total, 2, '.', '')) .
        $this->tlvSlot(5, number_format($vat, 2, '.', ''));

    return base64_encode($innerTlv);
}

private function tlvSlot($tag, $value)
{
    // تحويل النص إلى UTF-8 لضمان قراءة الحروف العربية بشكل صحيح
    $value = (string) $value;
    $length = strlen($value); // للأمان مع UTF-8 يفضل استخدام mb_strlen في بعض الحالات لكن strlen كافية إذا كان الملف UTF-8
    return chr($tag) . chr($length) . $value;
}


}
