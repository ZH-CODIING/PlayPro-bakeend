<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use SoftDeletes;

    /**
     * الحقول اللي مسموح تتعمل لها mass assignment
     */
    protected $fillable = [
        'user_id',
        'order_id',
        'field_booking_id',
        'gateway',
        'payment_id',
        'gateway_reference',
        'status',
        'amount',
        'currency',
        'meta',
        'notes',
    ];

    /**
     * تحويل أنواع البيانات تلقائي
     */
    protected $casts = [
        'meta' => 'array',
        'amount' => 'decimal:2',
    ];

    /**
     * العلاقات
     */

    // المستخدم اللي دفع
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // لو الدفع خاص بـ Order
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    // لو الدفع خاص بـ Field Booking
    public function fieldBooking()
    {
        return $this->belongsTo(FieldBooking::class);
    }

    /**
     * Scopes مفيدة
     */

    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
