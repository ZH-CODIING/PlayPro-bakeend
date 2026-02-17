<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\TransferRequest;
use App\Models\Field;
use App\Models\FieldPeriod;
use App\Models\FieldBooking;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\UserNotificationMail;
use App\Jobs\SendWhatsAppMessageJob;

class TransferRequestController extends Controller
{
    // =================================================================
    // دالة الإشعارات الموحدة المتطورة (WhatsApp + Email)
    // =================================================================
    private function notifyTransferParties($transferRequest, $type)
    {
        // شحن كافة العلاقات الضرورية بما فيها الكوتش وصاحب الملعب لضمان عدم حدوث Error
        $transferRequest->loadMissing([
            'user', 
            'targetField.owner', 
            'targetPeriod.coaches',
            'currentBooking.field'
        ]);
        
        $user = $transferRequest->user;
        $field = $transferRequest->targetField;
        $owner = $field->owner; 
        $period = $transferRequest->targetPeriod;
        $coach = $period->coach; 
        $booking = $transferRequest->currentBooking;

        // 1. تنظيف أرقام الهواتف (إزالة المسافات ورموز واتساب الزائدة)
        $customerPhone = str_replace(['@c.us', ' '], '', $user->phone ?? $booking->phone);
        $ownerPhone = $owner ? str_replace(['@c.us', ' '], '', $owner->phone_number ?? $owner->phone) : null;
        $coachPhone = $coach ? str_replace(['@c.us', ' '], '', $coach->phone_number ?? $coach->phone) : null;

        // 2. جلب أرقام هواتف الأدمن والمديرين ديناميكياً من قاعدة البيانات
        $adminPhones = User::whereIn('role', [User::ROLE_ADMIN, 'Management'])
                        ->whereNotNull('phone')
                        ->pluck('phone')
                        ->toArray();

        $adminPhones = array_map(function($p) {
            return str_replace(['@c.us', ' '], '', $p);
        }, $adminPhones);

        // --- توزيع الرسائل حسب الحالة ---

        if ($type === 'request_created') {
            // رسالة للإدارة (الأدمن والمديرين) عند إنشاء طلب جديد
            $adminMsg = "*طلب نقل حجز جديد في النظام*" . "\n\n"
                . "*العميل:* " . ($user->name ?? $booking->name) . "\n"
                . "*من ملعب:* " . ($booking->field->name ?? 'غير محدد') . "\n"
                . "*إلى ملعب:* " . $field->name . "\n"
                . "*التوقيت المستهدف:* " . substr($period->start_time, 0, 5) . "\n"
                . "يرجى مراجعة الطلب من لوحة التحكم.";
            
            foreach ($adminPhones as $phone) {
                SendWhatsAppMessageJob::dispatch($phone, $adminMsg);
            }
        } 
        elseif ($type === 'request_approved') {
            // 1. إشعار العميل بالموافقة
            $customerMsg = "*تمت الموافقة على طلب النقل بنجاح* ✅" . "\n\n"
                . "*رقم الحجز:* " . $booking->id . "\n"
                . "*الملعب الجديد:* " . $field->name . "\n"
                . "*الكوتش:* " . ($coach->name ?? 'غير محدد') . "\n"
                . "*التوقيت:* " . substr($period->start_time, 0, 5) . "\n"
                . "*رابط الـ QR Code المحدث:* \n" . $booking->qr_code;
            SendWhatsAppMessageJob::dispatch($customerPhone, $customerMsg);

            // 2. إشعار صاحب الملعب/الأكاديمية المستهدف
            if ($ownerPhone) {
                $ownerMsg = "*إشعار: تم نقل حجز جديد إلى ملعبك* 🏟️" . "\n\n"
                    . "*العميل:* " . ($user->name ?? $booking->name) . "\n"
                    . "*التاريخ:* " . $booking->date->format('Y-m-d') . "\n"
                    . "*التوقيت:* " . substr($period->start_time, 0, 5);
                SendWhatsAppMessageJob::dispatch($ownerPhone, $ownerMsg);
            }

            // 3. إشعار الكوتش (المدرب المسؤول عن الفترة الجديدة)
            if ($coachPhone) {
                $coachMsg = "*إشعار للكوتش: لاعب جديد في فترتك* ⚽" . "\n\n"
                    . "*الاسم:* " . ($user->name ?? $booking->name) . "\n"
                    . "*التاريخ:* " . $booking->date->format('Y-m-d') . "\n"
                    . "*الساعة:* " . substr($period->start_time, 0, 5);
                SendWhatsAppMessageJob::dispatch($coachPhone, $coachMsg);
            }

            // 4. إرسال بريد إلكتروني للعميل
            if ($user && $user->email) {
                $subject = "✅ تم اعتماد طلب نقل حجزك";
                $body = "عزيزي {$user->name}، تم نقل حجزك بنجاح إلى ملعب {$field->name} في الساعة " . substr($period->start_time, 0, 5);
                Mail::to($user->email)->send(new UserNotificationMail($subject, $body));
            }
        } 
        elseif ($type === 'request_rejected') {
            // إشعار العميل بالرفض (واتساب + بريد)
            $customerMsg = "*تحديث بخصوص طلب النقل* ❌" . "\n\n"
                . "نعتذر منك، تم رفض طلب النقل لعدم توفر السعة أو لأسباب فنية.\n"
                . "*حجزك الأصلي لا يزال قائماً دون أي تغيير في موعده وملعبه.*";
            SendWhatsAppMessageJob::dispatch($customerPhone, $customerMsg);

            if ($user && $user->email) {
                $subject = "❌ تحديث بخصوص طلب نقل الحجز";
                $body = "نعتذر منك، تم رفض طلب النقل. حجزك الحالي لا يزال سارياً بنفس الموعد.";
                Mail::to($user->email)->send(new UserNotificationMail($subject, $body));
            }
        }
    }

    // =================================================================
    // 1. عرض الطلبات (Index)
    // =================================================================
    public function index(Request $request)
    {
        $user = Auth::user();
        $query = TransferRequest::query()
                ->filter($request->all());

        if (in_array($user->role, [User::ROLE_ADMIN, User::ROLE_MANAGEMENT])) {
            // يرى كل الطلبات
        } elseif (in_array($user->role, [User::ROLE_OWNER, User::ROLE_OWNER_ACADEMY])) {
            $ownedFieldIds = $user->fields()->pluck('id');
            $query->whereIn('target_field_id', $ownedFieldIds);
        } elseif ($user->role === User::ROLE_COACH) {
            $coachFieldIds = FieldPeriod::where('coach_id', $user->id)->pluck('field_id')->unique();
            $query->whereIn('target_field_id', $coachFieldIds);
        } else {
            $query->where('user_id', $user->id); 
        }

        $requests = $query->with([
            'user:id,name', 
            'currentBooking.field:id,name',
            'targetField:id,name',
            'targetPeriod:id,start_time,end_time'
        ])->latest()->get();

        return response()->json(['status' => true, 'data' => $requests]);
    }

    // =================================================================
    // 2. إنشاء طلب (Store)
    // =================================================================
    public function store(Request $request)
    {
        $user = Auth::user();
        $data = $request->validate([
            'current_booking_id' => 'required|exists:field_bookings,id',
            'target_field_id'    => 'required|exists:fields,id',
            'target_period_id'   => 'required|exists:field_periods,id',
            'notes'              => 'nullable|string',
        ]);

        $booking = FieldBooking::find($data['current_booking_id']);
        if (!$booking || $booking->user_id !== $user->id) {
            return response()->json(['status' => false, 'message' => 'الحجز غير مملوك لك.'], 403);
        }

        $transferRequest = TransferRequest::create([
            'user_id' => $user->id,
            'current_booking_id' => $data['current_booking_id'],
            'target_field_id' => $data['target_field_id'],
            'target_period_id' => $data['target_period_id'],
            'status' => 'Pending',
            'notes'              => $request->notes ?? null,
        ]);

        $this->notifyTransferParties($transferRequest, 'request_created');

        return response()->json(['status' => true, 'message' => 'تم إرسال طلب النقل بنجاح.']);
    }

    // =================================================================
    // 3. الموافقة (Approve)
    // =================================================================
    public function approve(Request $request, TransferRequest $transferRequest)
    {
        $user = Auth::user();
        
        $isAuthorized = in_array($user->role, [User::ROLE_ADMIN, User::ROLE_MANAGEMENT]) ||
                        (in_array($user->role, [User::ROLE_OWNER, User::ROLE_OWNER_ACADEMY]) && $user->id === $transferRequest->targetField->owner_id);

        if (!$isAuthorized) {
            return response()->json(['status' => false, 'message' => 'غير مصرح لك بالموافقة.'], 403);
        }

        if ($transferRequest->status !== 'Pending') {
            return response()->json(['status' => false, 'message' => 'الطلب ليس في حالة انتظار.'], 400);
        }

        $booking = FieldBooking::findOrFail($transferRequest->current_booking_id);

        // --- تنفيذ النقل (تعديل الحقول المطلوبة فقط) ---
        $booking->field_id = $transferRequest->target_field_id;
        $booking->period_id = $transferRequest->target_period_id;

        // إدارة الـ QR Code (حذف القديم وتوليد جديد بالبيانات المحدثة)
        if ($booking->qr_code) {
            $oldPath = public_path(str_replace(url('/'), '', $booking->qr_code));
            if (File::exists($oldPath)) File::delete($oldPath);
        }

        $qrFileName = 'booking_transfer_'.$booking->id.'_'.Str::random(6).'.png';
        $qrPath = public_path('qrcodes/'.$qrFileName);
        
        if(!File::exists(public_path('qrcodes'))) {
            File::makeDirectory(public_path('qrcodes'), 0755, true);
        }

        QrCode::format('png')->size(300)->generate(json_encode(['booking_id' => $booking->id]), $qrPath);
        $booking->qr_code = url('qrcodes/'.$qrFileName);
        
        $booking->save(); // حفظ التغييرات على الحجز الأصلي

        $transferRequest->update(['status' => 'Approved']);

        // إرسال الإشعارات الموحدة لجميع الأطراف (عميل، أدمن، كوتش، أونر)
        $this->notifyTransferParties($transferRequest, 'request_approved');

        return response()->json([
            'status' => true, 
            'message' => 'تمت الموافقة بنجاح، وتم نقل الحجز للملعب والفترة الجديدة فقط.'
        ]);
    }

    // =================================================================
    // 4. الرفض (Reject)
    // =================================================================
    public function reject(Request $request, TransferRequest $transferRequest)
    {
        $user = Auth::user();

        $isAuthorized = in_array($user->role, [User::ROLE_ADMIN, User::ROLE_MANAGEMENT, User::ROLE_OWNER, User::ROLE_OWNER_ACADEMY]);

        if (!$isAuthorized) {
            return response()->json(['status' => false, 'message' => 'غير مصرح لك بالرفض.'], 403);
        }

        $transferRequest->update(['status' => 'Rejected']);

        $this->notifyTransferParties($transferRequest, 'request_rejected');

        return response()->json(['status' => true, 'message' => 'تم رفض الطلب وإرسال الإشعارات للعميل.']);
    }

    // =================================================================
    // 5. الحذف (Destroy)
    // =================================================================
    public function destroy(TransferRequest $transferRequest)
    {
        $user = Auth::user();

        if ($user->role !== User::ROLE_ADMIN && $transferRequest->user_id !== $user->id) {
            return response()->json(['status' => false, 'message' => 'غير مصرح لك بالحذف.'], 403);
        }

        if ($transferRequest->status !== 'Pending') {
            return response()->json(['status' => false, 'message' => 'لا يمكن حذف طلب تمت معالجته بالفعل.'], 400);
        }

        $transferRequest->delete();
        return response()->json(['status' => true, 'message' => 'تم حذف طلب النقل بنجاح.']);
    }
}