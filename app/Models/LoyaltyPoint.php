<?php

namespace App\Models;
use Illuminate\Http\Request;

use Illuminate\Database\Eloquent\Model;

class LoyaltyPoint extends Model
{
    protected $table = 'loyalty_points';

    protected $fillable = [
        'points',
        'discount_percent',
    ];

    protected $casts = [
        'points' => 'integer',
        'discount_percent' => 'float',
    ];
    
    
      public function scopeFilter($query, Request $request)
{
    return $query
  
        ->when($request->filled('search'), function ($q) use ($request) {
            $q->where(function ($query) use ($request) {
                $query->where('points', 'LIKE', '%' . $request->search . '%')
                ->orWhere('discount_percent', 'LIKE', '%' . $request->search . '%')

                ;
                  
            });
        });
}
}
