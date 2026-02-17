<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Academy extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'address',
        'latitude',
        'longitude',
        'stars',
        'image',
        'logo',
        'days,',
       'price_per_player',
       'user_id'
    ];

    protected $casts = [
    'days' => 'array',
];
public function renewalPrices()
    {
        return $this->hasMany(SubscriptionRenewalPrice::class);
    }

    public function fields()
    {
        return $this->hasMany(Field::class);
    }
    
    public function user()
{
    return $this->belongsTo(User::class);
}

    
           public function scopeFilter($query, array $filters)
    {
        return $query
          
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($query) use ($search) {
                    $query->where('name', 'LIKE', "%{$search}%")
                          ;
                         
                });
            });
    }
}
