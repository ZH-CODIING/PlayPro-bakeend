<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Partner extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'image',
        'description',
        'link',
        'badge',
    ];

    protected $appends = ['image_url'];

    public function getImageUrlAttribute()
    {
        if ($this->image) {
            return asset('storage/' . $this->image);
        }

        return null;
    }
    
    
public function scopeFilter($query, array $filters)
    {
        return $query
            ->when($filters['badge'] ?? null, function ($q, $badge) {
                $q->where('badge', $badge);
            })
            ->when($filters['search'] ?? null, function ($q, $search) {
                $q->where('name', 'LIKE', "%{$search}%");
            });
    }
}
