<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionRenewalPrice;
use App\Models\User; // للتأكد من استخدام الـ Roles
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Academy;

class SubscriptionRenewalPriceController extends Controller
{
    /**
     * 📌 عرض الأسعار (مع فلترة اختيارية حسب الأكاديمية)
     */
public function index(Request $request)
{
    // 1. نبدأ الاستعلام مع جلب علاقة الأكاديمية (الاسم والـ ID)
    $query = SubscriptionRenewalPrice::with(['academy:id,name'])
        ->orderBy('months');

    // 2. التحقق إذا كان هناك academy_id مرسل في الـ URL
    // مثال: /api/subscription-renewal-prices?academy_id=5
    if ($request->has('academy_id') && $request->academy_id != null) {
        $query->where('academy_id', $request->academy_id);
    }

    // 3. (اختياري) لو عندك Scope للفلترة في الموديل
    if (method_exists(SubscriptionRenewalPrice::class, 'scopeFilter')) {
        $query->filter($request->all());
    }

    // 4. جلب البيانات
    $prices = $query->get();

    return response()->json([
        'status' => true,
        'message' => 'تم جلب البيانات بنجاح',
        'data' => $prices
    ]);
}

public function indexByRole(Request $request)
{
$user = Auth::user();
    $query = SubscriptionRenewalPrice::with(['academy']);

    if ($user->role === User::ROLE_OWNER_ACADEMY) {
        // تأكد أن $user->id هنا هو فعلاً 28 كما في الصورة
        $query->whereHas('academy', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        });
    }

    $prices = $query->get();

    return response()->json([
        'debug_user_id' => $user->id,
        'debug_role' => $user->role,
        'count' => $prices->count(),
        'data' => $prices
    ]);
}

    /**
     * 📌 إضافة سعر جديد
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        // 🔒 التحقق من الصلاحيات باستخدام الـ Roles الجديدة
        $allowedRoles = [
            User::ROLE_OWNER,
            User::ROLE_OWNER_ACADEMY,
            User::ROLE_ADMIN
        ];

        if (!$user || !in_array($user->role, $allowedRoles)) {
            return response()->json([
                'status' => false,
                'message' => 'غير مصرح لك بتنفيذ هذا الإجراء'
            ], 403);
        }

        $data = $request->validate([
            'academy_id' => 'required|exists:academies,id',
            'months'     => 'required|integer|min:1',
            'price'      => 'required|numeric|min:0',
        ]);

        // نصيحة أمان: إذا كان المستخدم OWNER_ACADEMY، يفضل التأكد أنه يملك هذه الأكاديمية فعلاً
        // if ($user->role === User::ROLE_OWNER_ACADEMY && $user->academy_id != $data['academy_id']) {
        //     return response()->json(['message' => 'لا يمكنك إضافة أسعار لأكاديمية أخرى'], 403);
        // }

        $price = SubscriptionRenewalPrice::create($data);

        return response()->json([
            'status' => true,
            'message' => 'تمت إضافة السعر بنجاح',
            'data' => $price
        ], 201);
    }

    /**
     * 📌 تحديث السعر
     */
    public function update(Request $request, $id)
    {
        $user = Auth::user();
        $allowedRoles = [User::ROLE_OWNER, User::ROLE_OWNER_ACADEMY, User::ROLE_ADMIN];

        if (!$user || !in_array($user->role, $allowedRoles)) {
            return response()->json(['status' => false, 'message' => 'صلاحيات غير كافية'], 403);
        }

        $price = SubscriptionRenewalPrice::findOrFail($id);

        $data = $request->validate([
            'months' => 'sometimes|required|integer|min:1',
            'price'  => 'sometimes|required|numeric|min:0',
                        'academy_id'  => 'sometimes|required|numeric|min:0',

        ]);

        $price->update($data);

        return response()->json([
            'status' => true,
            'message' => 'تم التحديث بنجاح',
            'data' => $price
        ]);
    }

    /**
     * 📌 حذف السعر
     */
    public function destroy($id)
    {
        $user = Auth::user();
        $allowedRoles = [User::ROLE_OWNER, User::ROLE_OWNER_ACADEMY, User::ROLE_ADMIN];

        if (!$user || !in_array($user->role, $allowedRoles)) {
            return response()->json(['status' => false, 'message' => 'صلاحيات غير كافية'], 403);
        }

        $price = SubscriptionRenewalPrice::findOrFail($id);
        $price->delete();

        return response()->json([
            'status' => true,
            'message' => 'تم الحذف بنجاح'
        ]);
    }
}