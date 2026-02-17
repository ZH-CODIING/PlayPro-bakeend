<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use App\Models\Field;
use App\Models\Rating;

class Coach extends Model
{
    protected $fillable = [
        'user_id',
        'field_id',
        'name',
        'age',
        'description',
        'experience_years',
        'images',
        'cv_file',
    ];

    protected $casts = [
        'images' => 'array',
    ];

    protected $appends = [
        'images_urls',
        'cv_url',
    ];


    public function getImagesUrlsAttribute()
    {
        if (!$this->images) {
            return [];
        }

        return collect($this->images)->map(function ($image) {
            return asset('storage/' . $image);
        });
    }

    public function getCvUrlAttribute()
    {
        if (!$this->cv_file) {
            return null;
        }

            return asset('storage/' . $this->cv_file);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function field()
    {
        return $this->belongsTo(Field::class);
    }
    
    public function ratings()
{
    return $this->morphMany(Rating::class, 'rateable');
}

public function averageRating()
{
    return round($this->ratings()->avg('rate'), 1);
}


  public function scopeFilter($query, array $filters)
    {
        return $query
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($query) use ($search) {
                    $query->where('age', 'LIKE', "%{$search}%")
                          ->orWhere('name', 'LIKE', "%{$search}%")
                          ->orWhere('experience_years', 'LIKE', "%{$search}%")
                          ->orWhereHas('field', function ($q) use ($search) {
                              $q->where('name', 'LIKE', "%{$search}%");
                          });
                });
            });
    
}
    
    
}
