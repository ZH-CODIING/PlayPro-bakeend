<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Http\Request;
class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'total_price',
        'status',
          'shipping_name',
    'shipping_phone',
    'shipping_address',
    'shipping_city',
    
    'total_before_discount',
    'coupon_name',
    'coupon_percentage',
    'coupon_discount',

    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }
    public function payments()
{
    return $this->hasMany(Payment::class);
}

   

    public function scopeFilter($query, array $filters)
    {
        return $query
            ->when($filters['status'] ?? null, function ($q, $status) {
                $q->where('status', $status);
            })
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($query) use ($search) {
                    $query->where('total_price', 'LIKE', "%{$search}%")
                          ->orWhere('id', $search)
                          ->orWhereHas('user', function ($q) use ($search) {
                              $q->where('name', 'LIKE', "%{$search}%");
                          });
                });
            });
    }

}
