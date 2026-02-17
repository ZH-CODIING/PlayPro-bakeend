<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'video',
    ];

    protected $appends = ['video_url'];

    public function getVideoUrlAttribute()
    {
        return $this->video
            ? asset('storage/' . $this->video)
            : null;
    }
}
