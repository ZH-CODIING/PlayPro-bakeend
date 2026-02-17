<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use Illuminate\Http\Request;
use App\Jobs\SendWhatsAppMessageJob;

class ContactMessageController extends Controller
{
/**
     * إرسال رسالة Contact Us
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'      => 'required|string|max:255',
            'email'     => 'required|email|max:255',
            'country'   => 'required|string|max:255',
            'phone'     => 'required|string|max:50',
            'subject'   => 'required|string|max:255',
            'message'   => 'required|string',
        ]);

        $contact = ContactMessage::create($data);

        // إرسال الإشعار عبر واتساب
        $this->notifyAdmin($contact);

        return response()->json([
            'status' => true,
            'message' => 'Message sent successfully',
            'data' => $contact
        ], 201);
    }

    /**
     * وظيفة لإرسال إشعار للإدارة عند وصول رسالة جديدة
     */
    private function notifyAdmin($contact)
    {
        // رقم هاتف الأدمن الذي سيستقبل الرسائل (قم بتغييره للرقم المطلوب)
        $adminPhone = "201023402756"; 

        // تنسيق نص الرسالة
        $adminMessage = "*رسالة تواصل جديدة* 📩" . "\n\n"
            . "*الاسم:* " . $contact->name . "\n"
            . "*الهاتف:* " . $contact->phone . "\n"
            . "*البلد:* " . $contact->country . "\n"
            . "*الموضوع:* " . $contact->subject . "\n"
            . "*الرسالة:* \n" . $contact->message;

        // إرسال الرسالة للأدمن
     \App\Jobs\SendWhatsAppMessageJob::dispatch($adminPhone, $adminMessage);

    // 2. إرسال رسالة تأكيد للعميل
    $customerPhone = preg_replace('/[^0-9]/', '', $contact->phone);

    $customerMessage = "شكراً لتواصلك معنا يا *" . $contact->name . "*.\n"
        . "لقد استلمنا رسالتك بخصوص: (" . $contact->subject . ") وسنقوم بالرد عليك في أقرب وقت.";

    if (!empty($customerPhone)) {
        \App\Jobs\SendWhatsAppMessageJob::dispatch($customerPhone, $customerMessage);
    }
    }

    /**
     * ðŸ”¹ Ø¹Ø±Ø¶ ÙƒÙ„ Ø§Ù„Ø±Ø³Ø§Ø¦Ù„ (Ù„Ù„Ø£Ø¯Ù…Ù†)
     */
    public function index()
    {
        $messages = ContactMessage::latest()->paginate(20);

        return response()->json([
            'status' => true,
            'data' => $messages
        ]);
    }

    /**
     * ðŸ”¹ Ø¹Ø±Ø¶ Ø±Ø³Ø§Ù„Ø© ÙˆØ§Ø­Ø¯Ø©
     */
    public function show($id)
    {
        $message = ContactMessage::findOrFail($id);

        return response()->json([
            'status' => true,
            'data' => $message
        ]);
    }
}
