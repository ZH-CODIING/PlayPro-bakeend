<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\FieldImage;

class Field extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'size',
        'capacity',
        'latitude',
        'longitude',
        'city',
        'address',
        'description',
        'owner_id',
        'academy_id',
        'is_featured',
    ];
    
        public function academy()
    {
        return $this->belongsTo(Academy::class);
    }
    
    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function periods()
    {
        return $this->hasMany(FieldPeriod::class);
    }
    
    public function images()
    {
        return $this->hasMany(FieldImage::class);
    }

    public function icon()
    {
        return $this->hasOne(FieldImage::class)
                    ->where('type', 'icon');
    }

    public function gallery()
    {
        return $this->hasMany(FieldImage::class)
                    ->where('type', 'gallery');
    }
      public function bookings()
    {
        return $this->hasMany(FieldBooking::class);
    }

public function scopeFilter($query, array $filters)
{
    return $query
        ->when($filters['search'] ?? null, function ($q, $search) {
            $q->where(function ($query) use ($search) {
                $query->where('city', 'LIKE', "%{$search}%")
                      ->orWhere('name', 'LIKE', "%{$search}%")
                      ->orWhere('size', 'LIKE', "%{$search}%")
                      ->orWhere('capacity', 'LIKE', "%{$search}%")
                      ->orWhereHas('owner', function ($q) use ($search) {
                          $q->where('name', 'LIKE', "%{$search}%")
                            ->orWhere('phone', 'LIKE', "%{$search}%");
                      });
            });
        })
      ->when($filters['academyFilter'] ?? null, function ($q, $academyFilter) {
            $q->where('academy_id', $academyFilter);
        })
        // إضافة هذا الجزء للتعامل مع الملاعب فقط أو الأكاديميات فقط
        ->when(isset($filters['field_only']) && $filters['field_only'] == 'true', function ($q) {
            $q->whereNull('academy_id'); // الملعب فقط هو الذي لا يتبع أكاديمية
        })
        ->when(isset($filters['academy_only']) && $filters['academy_only'] == 'true', function ($q) {
            $q->whereNotNull('academy_id'); // الأكاديمية فقط هي التي لها academy_id
        });
}

   

   
}
