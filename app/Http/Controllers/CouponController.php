<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;


class CouponController extends Controller
{
    /**
     * عرض كل الكوبونات (Admin)
     */
    public function index(Request $request)
    {
        $coupons = Coupon::query()
      ->filter($request)
        ->latest()->paginate(20);

        return response()->json([
            'status' => true,
            'data' => $coupons
        ]);
    }

    /**
     * إنشاء كوبون جديد (Admin)
     */
    public function store(Request $request)
    {
         $user = Auth::user();

    // ✅ Admin فقط
    if ($user->role !== 'Admin') {
        return response()->json([
            'status' => false,
            'message' => 'غير مصرح لك'
        ], 403);
    }
        
        $data = $request->validate([
            'name'       => 'required|string|unique:coupons,name',
            'discount'   => 'required|numeric|min:0',
            'status'     => 'required|in:active,notactive',
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
        ]);

        $coupon = Coupon::create($data);

        return response()->json([
            'status' => true,
            'message' => 'تم إنشاء الكوبون بنجاح',
            'data' => $coupon
        ], 201);
    }

    /**
     * عرض كوبون واحد
     */
    public function show($id)
    {
        $coupon = Coupon::findOrFail($id);

        return response()->json([
            'status' => true,
            'data' => $coupon
        ]);
    }

    /**
     * تحديث كوبون (Admin)
     */
    public function update(Request $request, $id)
    {
          $user = Auth::user();

    // ✅ Admin فقط
    if ($user->role !== 'Admin') {
        return response()->json([
            'status' => false,
            'message' => 'غير مصرح لك'
        ], 403);
    }
        $coupon = Coupon::findOrFail($id);

        $data = $request->validate([
            'name'       => 'sometimes|string|unique:coupons,name,' . $coupon->id,
            'discount'   => 'sometimes|numeric|min:0',
            'status'     => 'sometimes|in:active,notactive',
            'start_date' => 'sometimes|date',
            'end_date'   => 'sometimes|date|after_or_equal:start_date',
        ]);

        $coupon->update($data);

        return response()->json([
            'status' => true,
            'message' => 'تم تحديث الكوبون بنجاح',
            'data' => $coupon
        ]);
    }

    /**
     * حذف كوبون (Admin)
     */
    public function destroy($id)
    {
          $user = Auth::user();

    // ✅ Admin فقط
    if ($user->role !== 'Admin') {
        return response()->json([
            'status' => false,
            'message' => 'غير مصرح لك'
        ], 403);
    }
        $coupon = Coupon::findOrFail($id);
        $coupon->delete();

        return response()->json([
            'status' => true,
            'message' => 'تم حذف الكوبون'
        ]);
    }

    /**
     * التحقق من الكوبون (User)
     */
   public function check(Request $request)
{
    $data = $request->validate([
        'name'   => 'required|string',
    ]);

    $coupon = Coupon::where('name', $data['name'])
        ->where('status', 'active')
        ->whereDate('start_date', '<=', now())
        ->whereDate('end_date', '>=', now())
        ->first();

    if (!$coupon) {
        return response()->json([
            'status' => false,
            'message' => 'كوبون غير صالح'
        ], 404);
    }


    return response()->json([
        'status' => true,
        "Message"=>"كوبون صالح",
        'discount_percentage' => $coupon->discount . '%',
        'coupon' => $coupon
    ]);
}

}
