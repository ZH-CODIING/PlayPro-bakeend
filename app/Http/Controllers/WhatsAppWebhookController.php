<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\WhatsAppMessage;
use App\Jobs\SendWhatsAppMessageJob;

class WhatsAppWebhookController extends Controller
{
    /**
     * استقبال الرسائل (Webhook)
     * ملاحظة: تأكد أن النظام الجديد يرسل البيانات بنفس هيكلة 'messages'
     */
    public function receive(Request $request)
    {
        Log::info('📥 Webhook جديد وصل', $request->all());

        $messages = $request->input('messages');

        if (!$messages || !is_array($messages)) {
            return response()->json(['status' => 'no messages'], 422);
        }

        foreach ($messages as $data) {
            $remoteJid = $data['key']['remoteJid'] ?? null;
            
            // استخراج النص (حسب الهيكلية القديمة)
            $text = $data['message']['extendedTextMessage']['text']
                ?? $data['message']['conversation']
                ?? $data['message']['imageMessage']['caption']
                ?? '[وسائط أو نص غير معروف]';

            if (!$remoteJid) continue;

            $phone = explode('@', $remoteJid)[0];

            try {
                WhatsAppMessage::create([
                    'from' => $phone,
                    'text' => $text,
                    'received_at' => now(),
                    'id_message' => $data['key']['id'] ?? null,
                ]);
            } catch (\Exception $e) {
                Log::error('❌ خطأ في حفظ الرسالة الواردة: ' . $e->getMessage());
            }
        }

        return response()->json(['status' => 'received'], 200);
    }

    /**
     * إرسال رسالة (عبر الـ API الجديد) من خلال الـ Job
     */
    public function sendMessageFromRequest(Request $request)
    {
        $request->validate([
            'phones' => 'required|array', // مصفوفة أرقام
            'message' => 'required|string',
        ]);

        $phones = $request->input('phones');
        $message = $request->input('message');

        foreach ($phones as $phone) {
            // استدعاء الـ Job الذي قمنا بتعديله ليستخدم w.alahrm.com
            SendWhatsAppMessageJob::dispatch($phone, $message);
        }

        return response()->json([
            'status' => true,
            'message' => 'تم إضافة الرسائل لطابور الإرسال بالنظام الجديد',
            'count' => count($phones)
        ]);
    }

    /**
     * عرض الرسائل الخاصة برقم معين
     */
    public function getMessagesByPhone($phone)
    {
        $messages = WhatsAppMessage::where('from', $phone)
            ->orderBy('received_at', 'asc')
            ->get();

        $user = \App\Models\User::where('phone_number', (string) $phone)->first();

        return response()->json([
            'status' => true,
            'phone' => $phone,
            'user' => $user,
            'messages' => $messages
        ]);
    }

    /**
     * جلب كل الرسائل مجمعة حسب الرقم
     */
    public function getAllMessages()
    {
        $result = WhatsAppMessage::orderBy('received_at', 'desc')
            ->get()
            ->groupBy('from')
            ->map(function ($messages, $phone) {
                return [
                    'phone' => $phone,
                    'user' => \App\Models\User::where('phone_number', (string) $phone)->first(),
                    'messages' => $messages
                ];
            })->values();

        return response()->json([
            'status' => true,
            'messages' => $result
        ]);
    }
}