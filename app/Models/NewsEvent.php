<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NewsEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'images',
    ];

    protected $casts = [
        'images' => 'array',
    ];


  protected $appends = ['images_urls'];

    public function getImagesUrlsAttribute()
    {
        if (!is_array($this->images)) {
            return [];
        }

        return collect($this->images)->map(function ($image) {

   
            if (is_string($image)) {
                return [
                    'url' => asset('storage/' . $image),
                    'description' => null
                ];
            }

    
            return [
                'url' => isset($image['url'])
                    ? asset('storage/' . $image['url'])
                    : null,
                'description' => $image['description'] ?? null
            ];
        })->values();
    }
    
     public function scopeFilter($query, array $filters)
    {
        return $query
          
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where(function ($query) use ($search) {
                    $query->where('title', 'LIKE', "%{$search}%")
                          ;
                         
                });
            });
    }
}
