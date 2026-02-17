<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\Payment;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{
    /**
     * عرض كل الطلبات الخاصة بالمستخدم الحالي
     */
public function index(Request $request)
{
    $perPage = $request->input('per_page', 10);

    $query = Order::with('items.product')
            ->filter($request->all());

    if ($request->user()->role !== 'Admin') {
        $query->where('user_id', Auth::id());
    }

    return response()->json([
        'data' => $query->paginate($perPage)
    ], 200);
}


    /**
     * إنشاء طلب جديد (بدون دفع)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'shipping_name'      => 'required|string|max:255',
            'shipping_phone'     => 'required|string',
            'shipping_address'   => 'required|string',
            'shipping_city'      => 'required|string',
            'coupon_name'        => 'nullable|string',
            'items'              => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity'   => 'required|integer|min:1',
        ]);

        // 🔹 إنشاء الطلب الأساسي
        $order = Order::create([
            'user_id' => Auth::id(),
            'total_price' => 0,
            'shipping_name' => $validated['shipping_name'],
            'shipping_phone' => $validated['shipping_phone'],
            'shipping_address' => $validated['shipping_address'],
            'shipping_city' => $validated['shipping_city'],
             'total_price' => 0,
        'total_before_discount' => 0,
        ]);

        $total = 0;

        foreach ($validated['items'] as $item) {

            $product = Product::findOrFail($item['product_id']);

            // التحقق من الكمية
            if ($product->quantity < $item['quantity']) {
                return response()->json([
                    'message' => 'الكمية غير متاحة للمنتج: ' . $product->name
                ], 422);
            }

            $price = $product->price;
            $totalItem = $price * $item['quantity'];

            OrderItem::create([
                'order_id'   => $order->id,
                'product_id' => $product->id,
                'quantity'   => $item['quantity'],
                'price'      => $price,
                'total'      => $totalItem,
            ]);

            // ➖ تقليل كمية المنتج
            $product->decrement('quantity', $item['quantity']);

            $total += $totalItem;
        }

       
    // ===============================
    // 🧮 تطبيق الكوبون (لو موجود)
    // ===============================

    $discountValue = 0;
    $discountPercentage = null;
    $couponName = null;

    if (!empty($validated['coupon_name'])) {

        $coupon = \App\Models\Coupon::where('name', $validated['coupon_name'])
            ->where('status', 'active')
            ->whereDate('start_date', '<=', now())
            ->whereDate('end_date', '>=', now())
            ->first();

        if (!$coupon) {
            return response()->json([
                'message' => 'كوبون الخصم غير صالح'
            ], 422);
        }

        $discountPercentage = $coupon->discount;
        $discountValue = ($total * $discountPercentage) / 100;
        $couponName = $coupon->name;
    }

    $finalTotal = max($total - $discountValue, 0);

    // 🔹 تحديث الطلب بعد الخصم
    $order->update([
        'total_before_discount' => $total,
        'coupon_name'           => $couponName,
        'coupon_percentage'     => $discountPercentage,
        'coupon_discount'       => $discountValue,
        'total_price'           => $finalTotal,
    ]);
    
        $payment = Payment::create([
        'user_id' => auth()->id(),
        'order_id' => $order->id,
        'gateway' => 'paymob',
        'amount' => $order->total_price, // أو $data['amount']
        'currency' => 'EGP',
        'status' => 'pending',
    ]);

    return response()->json([
        'order' => $order->load('items.product'),
                'payment' => $payment

    ], 201);
}

    /**
     * عرض تفاصيل طلب معين
     */
    public function show(Order $order)
    {
        if ($order->user_id !== Auth::id()) {
            return response()->json([
                'message' => 'غير مصرح لك بعرض هذا الطلب'
            ], 403);
        }

        return response()->json([
            'data' => $order->load('items.product')
        ], 200);
    }

    /**
     * حذف طلب (وإرجاع الكميات)
     */
    public function destroy(Order $order)
    {
        if ($order->user_id !== Auth::id()) {
            return response()->json([
                'message' => 'غير مصرح لك بحذف هذا الطلب'
            ], 403);
        }

        // ➕ إرجاع الكميات للمنتجات
        foreach ($order->items as $item) {
            $item->product->increment('quantity', $item->quantity);
        }

        $order->delete();

        return response()->json([
            'message' => 'تم حذف الطلب بنجاح'
        ], 200);
    }
}
