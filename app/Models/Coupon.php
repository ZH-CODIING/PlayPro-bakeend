<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class Coupon extends Model
{
    protected $fillable = [
        'name',
        'discount',
        'status',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
    ];
    
    
    
    
  public function scopeFilter($query, Request $request)
{
    return $query
  
                ->when($request->filled('status'), function ($q) use ($request) {
                $q->where('status', $request->status);

            })
        ->when($request->filled('search'), function ($q) use ($request) {
            $q->where(function ($query) use ($request) {
                $query->where('start_date', 'LIKE', '%' . $request->search . '%')
                ->orWhere('name', 'LIKE', '%' . $request->search . '%')
                 ->orWhere('end_date', 'LIKE', '%' . $request->search . '%')

                ;
                  
            });
        });
}
    
    
}
