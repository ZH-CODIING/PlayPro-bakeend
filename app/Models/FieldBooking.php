<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Carbon\Carbon;


class FieldBooking extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'field_id',
        'academy_id',
        'period_id',
        'name',
        'phone',
        'email',
        'date',
        'days',
        'players_count',
        'age',
        'price',
        'discount',
        'paid',
        'remaining',
        'days_remaining',
        'payment_method',
        'renewal_date',
        'renewal_price',
        'renewal_count',
        'qr_code',
        'status',
        'total_before_discount',
        'coupon_discount',
        'coupon_percentage',
        'coupon_name',
         'cash_deposit',
         'payment_status',
        'transaction_id',
        'merchant_order_id',
    ];

    protected $casts = [
        'date' => 'date',
        'renewal_date' => 'date',
    'days' => 'array',

    ];

public function applyCashDeposit(): void
{
    if ($this->cash_deposit <= 0) {
        return;
    }

    $this->remaining = max(
        $this->remaining - $this->cash_deposit,
        0
    );



    $this->save();
}






    /* ================= Relations ================= */

    public function field()
    {
        return $this->belongsTo(Field::class);
    }

    public function period()
    {
        return $this->belongsTo(FieldPeriod::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function academy()
    {
        return $this->belongsTo(Academy::class);
    }
    
    public function payments()
{
    return $this->hasMany(Payment::class);
}

    /* ================= Helpers ================= */

 
/**
 * تحديث حالة الحجز بناءً على النوع
 * حجز الملعب: ينتهي فقط بعد مرور اليوم بالكامل.
 * الأكاديمية: ينتهي بانتهاء تاريخ التجديد.
 */
public function refreshStatus(): void
{
    if ($this->status === 'cancelled') {
        return;
    }

    $today = now()->startOfDay();

    if ($this->academy_id) {
        // تنتهي إذا كان تاريخ التجديد أصغر من تاريخ اليوم (يعني اليوم اللي بعده بدأ)
        if ($this->renewal_date && $this->renewal_date->lt($today)) {
            $this->update(['status' => 'expired']);
        } else {
            $this->update(['status' => 'active']);
        }
    } 
    // حالة حجز الملعب العادي
    else {
        // فقط إذا كان تاريخ الحجز "قبل اليوم" (يعني اليوم خلص فعلياً ودخلنا في يوم جديد)
        if ($this->date && $this->date->lt($today)) {
            $this->update(['status' => 'expired']); 
        } else {
            $this->update(['status' => 'active']);
        }
    }
    }
    /**
     * حساب الأيام المتبقية
     * للأكاديمية: الفرق بين اليوم وتاريخ التجديد.
     * للملعب: 0 (لأنها يوم واحد).
     */
    public function calculateDaysRemaining()
    {
        if (!$this->academy_id || !$this->renewal_date) {
            return 0;
        }

        $today = now()->startOfDay();
        $endDate = $this->renewal_date->startOfDay();

        if ($today->greaterThanOrEqualTo($endDate)) {
            return 0;
        }

        return $today->diffInDays($endDate);
    }

public function refreshDaysRemaining(): void
    {
        $this->days_remaining = $this->calculateDaysRemaining();
        $this->save();
    }



 protected $appends = ['attendance_count'];

    /* ================= Relations ================= */

    public function attendances()
    {
        return $this->hasMany(BookingAttendance::class);
    }

    /* ================= Accessors ================= */

    public function getAttendanceCountAttribute()
    {
        return $this->attendances()->count();
    }


  /* ================= Scopes ================= */

    public function scopeWithBasicRelations($query)
    {
        return $query->with([
            'field',
            'period',
            'user',
            'academy',
        ]);
    }
    
 
    public function scopeFilter($query, Request $request)
    {
        return $query
        ->when($request->filled('day'), function ($q) use ($request) {
    $q->whereHas('period', function ($periodQuery) use ($request) {
        $periodQuery->whereJsonContains('days', $request->day);
    });
})

            ->when($request->field_id, function ($q) use ($request) {
                $q->where('field_id', $request->field_id);
            })
            ->when($request->period_id, function ($q) use ($request) {
                $q->where('period_id', $request->period_id);
            })
          
           ->when(
    $request->filled('period_start')
    || $request->filled('period_end'),
    function ($q) use ($request) {
                    $q->whereHas('period', function ($periodQuery) use ($request) {
                        if ($request->filled('period_start')) {
                            $periodQuery->where('start_time', '>=', $request->period_start);
                        }
                        if ($request->filled('period_end')) {
                            $periodQuery->where('end_time', '<=', $request->period_end);
                        }
                    });
                    
                }
            )
            ->when($request->filled('date'), function ($q) use ($request) {
                $q->whereDate('date', $request->date);
            })
            ->when($request->filled('date_from'), function ($q) use ($request) {
                $q->whereDate('date', '>=', $request->date_from);
            })
            ->when($request->filled('date_to'), function ($q) use ($request) {
                $q->whereDate('date', '<=', $request->date_to);
            })
            ->when($request->filled('min_price'), function ($q) use ($request) {
                $q->where('total_amount', '>=', $request->min_price);
            })
            ->when($request->filled('max_price'), function ($q) use ($request) {
                $q->where('total_amount', '<=', $request->max_price);
            })
         ->when($request->search, function ($q) use ($request) {
    $q->where(function ($query) use ($request) {

        $query->where('name', 'LIKE', '%' . $request->search . '%')
              ->orWhere('phone', 'LIKE', '%' . $request->search . '%')
              ->orWhere('id', 'LIKE', '%' . $request->search . '%')

              ->orWhereHas('user', function ($q) use ($request) {
                    $q->where('name', $request->search);
              });

    });
})

            ->when($request->status, function ($q) use ($request) {
                $q->where('status', $request->status);
               
            });
    }
    
    
    
    
    //statistics احصائيات
    
    
public static function statistics($query): array
{

    return [
        'total_bookings' => $query->count(),

        'total_bookings_price' => (clone $query)->sum('price'),

        'total_bookings_canceled' =>
            (clone $query)->where('status', 'cancelled')->count(),

        'total_bookings_canceled_price' =>
            (clone $query)->where('status', 'cancelled')->sum('paid'),

        'total_deposit' =>
            (clone $query)->sum('paid'),

        'total_cash_deposit' =>
            (clone $query)->sum('cash_deposit'),

        'total_coupon_discount' =>
            (clone $query)->sum('coupon_discount'),

        'total_discount_percentage' =>
            (clone $query)->sum('discount'),

        // ✅ إجمالي الخصم الفعلي (قبل - بعد)
        'total_discount_price' => (clone $query)
            ->selectRaw('SUM(total_before_discount - price) as total')
            ->value('total'),
    ];
}

    
    public static function academyStatistics($query): array
{

    return [
        'total_bookings' => $query->count(),

        'total_bookings_price' => (clone $query)->sum('price'),

        'total_bookings_canceled' =>
            (clone $query)->where('status', 'cancelled')->count(),

        'total_bookings_canceled_price' =>
            (clone $query)->where('status', 'cancelled')->sum('paid'),

        'total_deposit' =>
            (clone $query)->sum('paid'),

        'total_cash_deposit' =>
            (clone $query)->sum('cash_deposit'),

        'total_coupon_discount' =>
            (clone $query)->sum('coupon_discount'),

        'total_discount_percentage' =>
            (clone $query)->sum('discount'),

        // ✅ إجمالي الخصم الفعلي (قبل - بعد)
        'total_discount_price' => (clone $query)
            ->selectRaw('SUM(total_before_discount - price) as total')
            ->value('total'),
    ];
}

//     public static function statistics(): array
// {
//     return [
//         'total_bookings' => self::count(),
//         'total_bookings_price'    => self::sum('price'),
//         'total_bookings_canceled' => self::where('status','cancelled')->count(),
//         'total_bookings_canceled_price' => self::where('status','cancelled')->sum('paid'),
//         'total_deposit'  => self::sum('paid'),
//         'total_cash_deposit'  => self::sum('cash_deposit'),
//         'total_coupon_discount'  => self::sum('coupon_discount'),
//         'total_discount_percentage'  => self::sum('discount'),
//         'total_discount_price' => self::query()
//             ->selectRaw('SUM(total_before_discount-price ) as total')
//             ->value('total'),

//     ];
// }

}