<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class SubscriptionRenewalPrice extends Model
{
    protected $fillable = [
        'months',
        'academy_id',
        'price',
    ];
    
        public function academy()
    {
        return $this->belongsTo(Academy::class);
    }
    public function scopeFilter($query, array $filters)
    {
        return $query
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($query) use ($search) {
                    $query->where('months', 'LIKE', "%{$search}%")
                          ->orWhere('price', 'LIKE', "%{$search}%");
                });
            });
    }
}
