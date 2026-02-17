<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TermAndCondition extends Model
{
    protected $table = 'terms_and_conditions';
    protected $fillable = ['title', 'content', 'order'];

    protected $casts = [
        'content' => 'array', // يحول الـ JSON إلى مصفوفة PHP فوراً
    ];
}