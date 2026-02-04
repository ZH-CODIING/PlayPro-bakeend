<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Rating;

class RatingController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'type' => 'required|in:product,coach',
            'id' => 'required|integer',
            'rate' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string',
        ]);

        $user = auth()->user();

        // حددي نوع الموديل
        $modelClass = match ($request->type) {
            'product' => \App\Models\Product::class,
            'coach' => \App\Models\Coach::class,
        
        };

        $model = $modelClass::findOrFail($request->id);

        // يمنع المستخدم من تقييم نفس العنصر أكثر من مرة
        Rating::updateOrCreate(
            [
                'user_id' => $user->id,
                'rateable_id' => $model->id,
                'rateable_type' => $modelClass,
            ],
            [
                'rate' => $request->rate,
                'comment' => $request->comment,
            ]
        );

        return response()->json([
            'message' => 'تم حفظ التقييم بنجاح',
            'average_rating' => $model->averageRating()
        ]);
    }
}
