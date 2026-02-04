<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Field;
use App\Models\FieldBooking; 
use App\Models\FieldPeriod; 

class TransferRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'current_booking_id',
        'target_field_id',
        'target_period_id',
        'status',
        'notes',
    ];
    
    // تعريف العلاقات
    
    // يشير إلى المستخدم الذي قام بتقديم الطلب
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    // يشير إلى الحجز الحالي الذي يطلب نقله
    public function currentBooking()
    {
        return $this->belongsTo(FieldBooking::class, 'current_booking_id'); 
    }
    
    // يشير إلى الملعب المستهدف الجديد
    public function targetField()
    {
        return $this->belongsTo(Field::class, 'target_field_id');
    }
    
    // يشير إلى الفترة/التوقيت المستهدف الجديد
    public function targetPeriod()
    {
        return $this->belongsTo(FieldPeriod::class, 'target_period_id');
    }
    
public function scopeFilter($query, array $filters)
{
    return $query
            ->when($filters['status'] ?? null, function ($q, $status) {
            $q->where('status', $status);
        })

    
        ->when($filters['search'] ?? null, function ($q, $search) {
            $q->where(function ($query) use ($search) {

                // 🔹 IDs (أرقام فقط)
                if (is_numeric($search)) {
                    $query->orWhere('id', $search)
                          ->orWhereHas('currentBooking', function ($q) use ($search) {
                              $q->where('id', $search);
                          });
                }

                // 🔹 تاريخ الإنشاء
                $query->orWhereDate('created_at', $search);

                // 🔹 اسم المستخدم
                $query->orWhereHas('user', function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%");
                });

                // 🔹 بيانات الحجز الحالي
                $query->orWhereHas('currentBooking', function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                      ->orWhere('phone', 'LIKE', "%{$search}%");
                });

                // 🔹 اسم الملعب المستهدف
                $query->orWhereHas('targetField', function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%");
                });

                // 🔹 الفترة الزمنية
                $query->orWhereHas('targetPeriod', function ($q) use ($search) {
                    $q->where('start_time', 'LIKE', "%{$search}%")
                      ->orWhere('end_time', 'LIKE', "%{$search}%");
                });
            });
        });
}


}