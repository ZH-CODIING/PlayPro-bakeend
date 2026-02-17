<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingAttendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'field_booking_id',
        'date',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    /* ================= Relations ================= */

    public function booking()
    {
        return $this->belongsTo(FieldBooking::class, 'field_booking_id');
    }
    


}
