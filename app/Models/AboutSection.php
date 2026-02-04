<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class AboutSection extends Model
{
    protected $fillable = ['key', 'title', 'description', 'items', 'order'];

    protected $casts = [
        'items' => 'array',  
    ];
    public function image()
{
    return $this->hasOne(AboutImage::class);
}
}