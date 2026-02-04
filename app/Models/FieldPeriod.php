<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;


class FieldPeriod extends Model
{
    use HasFactory;

    protected $fillable = [
        'field_id',
        'start_time',
        'end_time',
        'price_per_player',
        'type',
        'age_group',
        'days',
        'coach_ids',
        'capacity',
    ];

    protected $casts = [
        'days' => 'array',
        'coach_ids' => 'array',
    ];

    /* =======================
        Relations
    ======================= */

    public function field()
    {
        return $this->belongsTo(Field::class);
    }

    public function bookings()
    {
        return $this->hasMany(FieldBooking::class, 'period_id');
    }

    public function coaches()
    {
        return $this->belongsToMany(
            Coach::class,
            'field_period_coach',
            'period_id',
            'coach_id'
        );
    }
    
    
    
    public function scopeFilter($query, Request $request)
{
      return $query
        ->when($request->start_time, function ($q) use ($request) {
            $q->where('start_time', $request->start_time);
        })
 ->when($request->end_time, function ($q) use ($request) {
            $q->where('end_time', $request->end_time);
        });

}
    
    
    
    
}
