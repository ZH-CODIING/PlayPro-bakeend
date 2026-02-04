<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AboutImage extends Model
{
   protected $fillable = ['about_section_id', 'image_path'];

public function section()
{
    return $this->belongsTo(AboutSection::class, 'about_section_id');
}
}
