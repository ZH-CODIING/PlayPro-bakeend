<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\FieldBooking;
use App\Jobs\SendWhatsAppMessageJob;
use Carbon\Carbon;

class SendBookingWhatsAppReminders extends Command
{
    protected $signature = 'bookings:send-reminders';
    protected $description = 'Send WhatsApp reminders for expiring bookings';

  public function handle()
{
    $today = Carbon::today();

    $bookings = FieldBooking::whereNotNull('renewal_date')->get();

    foreach ($bookings as $booking) {

        // 🔔 فاضل يومين
        if ($booking->renewal_date->equalTo($today->copy()->addDays(2))) {

            SendWhatsAppMessageJob::dispatch(
                $booking->phone,
                "⚽ تنبيه مهم\n
اشتراكك فاضل عليه يومين ويخلص.\n
لو حابب تكمل وتجدده بسهولة، ادخل من هنا 👇\n
https://playpro-site.netlify.app/profile"
            );
        }

        // ❌ الاشتراك انتهى
        if ($booking->renewal_date->lessThanOrEqualTo($today)) {

            SendWhatsAppMessageJob::dispatch(
                $booking->phone,
                "❌ تنبيه انتهاء الاشتراك\n
اشتراكك انتهى.\n
تقدر تجدده في أي وقت من خلال الرابط ده 👇\n
https://playpro-site.netlify.app/profile"
            );
        }
    }
}

}
