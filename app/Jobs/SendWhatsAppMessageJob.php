<?php

namespace App\Jobs;

use App\Models\WhatsAppMessage;
use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendWhatsAppMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $phone;
    protected string $message;

    /**
     * عدد مرات إعادة المحاولة في حالة فشل المهمة.
     */
    public int $tries = 3;

    /**
     * عدد الثواني للانتظار قبل إعادة المحاولة.
     */
    public int $backoff = 60;

    public function __construct(string $phone, string $message)
    {
        $this->phone   = $phone;
        $this->message = $message;
    }

    public function handle(WhatsAppService $whatsAppService): void
    {
        // 1. محاولة إرسال الرسالة عبر الخدمة
        $response = $whatsAppService->sendText($this->phone, $this->message);

        /**
         * 2. التحقق من نجاح الإرسال قبل الحفظ.
         * ملاحظة: تأكد أن الـ API الخاص بك يعيد ['success' => true] أو قم بتعديل الشرط حسب هيكلة رد الـ API لديك.
         */
        if ($response && (isset($response['success']) && $response['success'] === true)) {
            try {
                WhatsAppMessage::create([
                    'from'        => $this->phone, 
                    'text'        => $this->message,
                    'received_at' => now(),
                ]);
            } catch (\Throwable $e) {
                Log::error('❌ فشل في حفظ الرسالة بالسجل: ' . $e->getMessage());
            }
        } else {
            // إذا لم ينجح الـ API، نلقي استثناء لإجبار الـ Job على إعادة المحاولة (Retry)
            throw new \Exception("فشل إرسال رسالة الواتساب إلى {$this->phone} - سيتم إعادة المحاولة.");
        }
    }
}