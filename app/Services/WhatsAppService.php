<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    protected $baseUrl;
    protected $session;

    public function __construct()
    {
        $this->baseUrl = env('WHATSAPP_BASE_URL', 'https://w.alahrm.com/api/sessions');
        $this->session = env('WHATSAPP_SESSION_NAME', 'playpro1');
    }

    public function sendText($toPhone, $message)
    {
        $url = "{$this->baseUrl}/{$this->session}/send-text";

        $payload = [
            'to'      => $toPhone,
            'message' => $message,
        ];

        try {
            $response = Http::withHeaders([
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ])->timeout(30)->post($url, $payload);

            if ($response->successful()) {
                Log::info("✅ تم إرسال الرسالة بنجاح إلى {$toPhone}");
                // نعيد الرد مع إضافة علامة نجاح لتسهيل التحقق في الـ Job
                return array_merge($response->json(), ['success' => true]);
            } else {
                Log::error("❌ فشل إرسال الرسالة إلى {$toPhone}", [
                    'status'   => $response->status(),
                    'response' => $response->json(),
                ]);
                return array_merge($response->json() ?? [], ['success' => false]);
            }
        } catch (\Exception $e) {
            Log::error("❌ خطأ تقني أثناء الاتصال بـ API الواتساب: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}