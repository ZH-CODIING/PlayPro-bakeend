<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class AppInfo extends Model
{
    protected $fillable = [
        'platform_name',
        'logo',
        'facebook',
        'instagram',
        'tiktok',
        'x',
        'snapchat',
        'phone',
        'whatsapp',
        'management_name',
        'management_image',
        'address',
    ];

    protected $appends = [
        'logo_url',
        'management_image_url',
    ];

    // 🔹 Full URL للوجو
    public function getLogoUrlAttribute()
    {
        return $this->logo
            ? asset('storage/' . $this->logo)
            : null;
    }

    // 🔹 Full URL لصورة الإدارة
    public function getManagementImageUrlAttribute()
    {
        return $this->management_image
            ? asset('storage/' . $this->management_image)
            : null;
    }
}
