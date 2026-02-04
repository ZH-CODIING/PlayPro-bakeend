<?php

namespace App\Http\Controllers;

use App\Models\Academy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class AcademyController extends Controller
{
    /**
     * تحقق من صلاحيات الوصول
     */
    private function authorizeAcademyAccess($user)
    {
        if (! $user || ! $user->hasRole([
            User::ROLE_ADMIN,
            User::ROLE_OWNER,
            User::ROLE_OWNER_ACADEMY
        ])) {
            abort(response()->json([
                'status' => false,
                'message' => 'غير مصرح لك بالوصول'
            ], 403));
        }
    }

    /**
     * عرض كل الأكاديميات (Admin فقط)
     */
    public function index(Request $request)
    {

        $academies = Academy::withCount('fields')
            ->latest()
            ->filter($request->all())
            ->get();

        return response()->json([
            'status' => true,
            'data' => $academies
        ]);
    }

    /**
     * عرض الأكاديميات الخاصة بالمستخدم أو Admin يشوف الكل
     */
public function myAcademies(Request $request)
{
    $user = $request->user();

    // التأكد من الصلاحيات (Admin, Owner, OwnerAcademy)
    $this->authorizeAcademyAccess($user);

    $query = Academy::withCount('fields');

    // 1. إذا كان Admin: يرى كل الأكاديميات بدون استثناء
    if ($user->isAdmin()) {
        // لا يتم إضافة أي شروط where
    } 
    
    // 2. إذا كان Owner أو OwnerAcademy: يرى ما يخصه فقط
    elseif ($user->hasRole([User::ROLE_OWNER, User::ROLE_OWNER_ACADEMY])) {
        $query->where(function ($q) use ($user) {
            // الحالة الأولى: هو المنشئ للأكاديمية (الموجود في حقل user_id بالجدول)
            $q->where('user_id', $user->id) 
            
            // الحالة الثانية: هو صاحب ملاعب مسجلة تحت أكاديمية معينة
            ->orWhereHas('fields', function ($sub) use ($user) {
                $sub->where('owner_id', $user->id);
            });
        });
    }

    $academies = $query->latest()->get();

    return response()->json([
        'status' => true,
        'total'  => $academies->count(),
        'data'   => $academies
    ]);
}

    /**
     * عرض أكاديمية واحدة مع الملاعب
     */
    public function show(Request $request, $id)
    {
     

        $academy = Academy::with([
            'fields.icon',
            'fields.gallery',
            'fields.periods.coaches',
        ])->find($id);

        if (! $academy) {
            return response()->json([
                'status' => false,
                'message' => 'الأكاديمية غير موجودة'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $academy
        ]);
    }

    /**
     * إنشاء أكاديمية جديدة
     */
    public function store(Request $request)
    {
        if (! $request->user()->hasRole([
            User::ROLE_ADMIN,
            User::ROLE_OWNER_ACADEMY
        ])) {
            return response()->json([
                'status' => false,
                'message' => 'غير مصرح لك بإنشاء أكاديمية'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'address'     => 'nullable|string',
            'days'        => 'nullable|string',
            'price_per_player' => 'required|numeric|min:0',
            'latitude'    => 'nullable|numeric',
            'longitude'   => 'nullable|numeric',
            'stars'       => 'nullable|numeric|min:0|max:5',
            'image'       => 'nullable|image|mimes:jpg,jpeg,png',
            'logo'        => 'nullable|image|mimes:jpg,jpeg,png',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->except(['image', 'logo']);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('academies', 'public');
            $data['image'] = url('public/storage/' . $path);
        }

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('academies/logos', 'public');
            $data['logo'] = url('public/storage/' . $path);
        }

        $academy = Academy::create($data);

        return response()->json([
            'status' => true,
            'message' => 'تم إنشاء الأكاديمية بنجاح',
            'data' => $academy
        ], 201);
    }

    /**
     * تعديل أكاديمية
     */
    public function update(Request $request, $id)
    {
        $this->authorizeAcademyAccess($request->user());

        $academy = Academy::find($id);

        if (!$academy) {
            return response()->json([
                'status' => false,
                'message' => 'الأكاديمية غير موجودة'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name'        => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'address'     => 'nullable|string',
            'days'        => 'nullable|string',
            'price_per_player' => 'nullable|numeric|min:0',
            'latitude'    => 'nullable|numeric',
            'longitude'   => 'nullable|numeric',
            'stars'       => 'nullable|numeric|min:0|max:5',
            'image'       => 'nullable|image|mimes:jpg,jpeg,png',
            'logo'        => 'nullable|image|mimes:jpg,jpeg,png',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->except(['image', 'logo']);

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('academies', 'public');
            $data['image'] = url('public/storage/' . $path);
        }

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('academies/logos', 'public');
            $data['logo'] = url('public/storage/' . $path);
        }

        $academy->update($data);

        return response()->json([
            'status' => true,
            'message' => 'تم تعديل الأكاديمية بنجاح',
            'data' => $academy
        ]);
    }

    /**
     * حذف أكاديمية
     */
    public function destroy(Request $request, $id)
    {
        $this->authorizeAcademyAccess($request->user());

        $academy = Academy::find($id);

        if (!$academy) {
            return response()->json([
                'status' => false,
                'message' => 'الأكاديمية غير موجودة'
            ], 404);
        }

        $academy->delete();

        return response()->json([
            'status' => true,
            'message' => 'تم حذف الأكاديمية بنجاح'
        ]);
    }
}
