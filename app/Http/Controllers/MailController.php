<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\UserNotificationMail;
use Illuminate\Support\Facades\Validator;

class MailController extends Controller
{
    public function sendMail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'emails'  => 'required',
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
        ], [
            'emails.required'  => 'يجب إدخال بريد إلكتروني واحد على الأقل.',
            'subject.required' => 'العنوان مطلوب.',
            'message.required' => 'الرسالة مطلوبة.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $emails = is_array($request->emails) ? $request->emails : [$request->emails];

        try {
            foreach ($emails as $email) {
                Mail::to($email)->queue(new UserNotificationMail(
                    $request->subject,
                    $request->message
                ));
            }

            return response()->json([
                'status'  => true,
                'message' => 'تم إرسال البريد الإلكتروني بنجاح في الخلفية.',
                'data'    => [
                    'emails'  => $emails,
                    'subject' => $request->subject,
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => false,
                'message' => 'فشل في إرسال البريد الإلكتروني.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
