<?php

namespace App\Http\Controllers;

use App\Models\LoyaltyPoint;
use Illuminate\Http\Request;

class LoyaltyPointController extends Controller
{
    /**
     * عرض كل قواعد نقاط الولاء
     */
    public function index(Request $request)
    {
                    $user = $request->user();

        $points = LoyaltyPoint::orderBy('points')
              ->filter($request)
               ->get();

        return response()->json([
            'status' => true,
            'data' => $points
        ]);
    }

    /**
     * إضافة Rule جديدة
     */
    public function store(Request $request)
    {
            $user = $request->user();

    if (! in_array($user->role, ['Owner', 'Admin'])) {
        return response()->json([
            'status' => false,
            'message' => 'غير مصرح لك بالوصول'
        ], 403);
    }
        $data = $request->validate([
            'points' => 'required|integer|min:1|unique:loyalty_points,points',
            'discount_percent' => 'required|numeric|min:0|max:100',
        ]);

        $loyaltyPoint = LoyaltyPoint::create($data);

        return response()->json([
            'status' => true,
            'message' => 'Loyalty point rule created successfully',
            'data' => $loyaltyPoint
        ], 201);
    }

    /**
     * عرض Rule واحدة
     */
public function show(Request $request, $id)
    {
                            $user = $request->user();
        $loyaltyPoint = LoyaltyPoint::query()
                      ->filter($request)

           -> findOrFail($id);

        return response()->json([
            'status' => true,
            'data' => $loyaltyPoint
        ]);
    }

    /**
     * تعديل Rule
     */
    public function update(Request $request, $id)
    {
            $user = $request->user();

    if (! in_array($user->role, ['Owner', 'Admin'])) {
        return response()->json([
            'status' => false,
            'message' => 'غير مصرح لك بالوصول'
        ], 403);
    }
        $loyaltyPoint = LoyaltyPoint::findOrFail($id);

        $data = $request->validate([
            'points' => 'required|integer|min:1|unique:loyalty_points,points,' . $loyaltyPoint->id,
            'discount_percent' => 'required|numeric|min:0|max:100',
        ]);

        $loyaltyPoint->update($data);

        return response()->json([
            'status' => true,
            'message' => 'Loyalty point rule updated successfully',
            'data' => $loyaltyPoint
        ]);
    }

    /**
     * حذف Rule
     */
    public function destroy(Request $request, $id)
    {
            $user = $request->user();

    if (! in_array($user->role, ['Owner', 'Admin'])) {
        return response()->json([
            'status' => false,
            'message' => 'غير مصرح لك بالوصول'
        ], 403);
    }
        $loyaltyPoint = LoyaltyPoint::findOrFail($id);
        $loyaltyPoint->delete();

        return response()->json([
            'status' => true,
            'message' => 'Loyalty point rule deleted successfully'
        ]);
    }
}
