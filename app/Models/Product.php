<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'price',
        'type',
        'quantity',
        'image',
    ];

    protected $appends = ['image_url'];

    public function getImageUrlAttribute()
    {
        return $this->image
            ? asset('storage/' . $this->image)
            : null;
    }


   public function orderItems()
{
    return $this->hasMany(OrderItem::class);
}


    public function orders()
{
    return $this->belongsToMany(Order::class, 'order_items')
                ->withPivot(['quantity', 'price', 'total'])
                ->withTimestamps();
}

public function ratings()
{
    return $this->morphMany(Rating::class, 'rateable');
}

public function averageRating()
{
    return round($this->ratings()->avg('rate'), 1);
}


  public function scopeFilter($query, Request $request)
{
    return $query
  
        ->when($request->filled('search'), function ($q) use ($request) {
            $q->where(function ($query) use ($request) {
                $query->where('type', 'LIKE', '%' . $request->search . '%')
                ->orWhere('name', 'LIKE', '%' . $request->search . '%')
                 ->orWhere('price', 'LIKE', '%' . $request->search . '%')

                ;
                  
            });
        });
}
   
}
