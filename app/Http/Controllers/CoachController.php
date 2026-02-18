<?php

namespace App\Http\Controllers;


use App\Http\Controllers\Controller;
use App\Models\Field;
use App\Models\User;
use App\Models\Coach;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class CoachController extends Controller
{
    /**
     * عرض كل الكوتشز
     */
    public function index(Request $request)
    {
        $coaches = Coach::with(['user', 'field'])
            ->filter($request->all())
        ->paginate(10);

        return response()->json($coaches, 200);
    }

    /**
     * إنشاء كوتش جديد
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'field_id'         => 'required|exists:fields,id',
            'name'             => 'nullable|string|max:255',
            'age'              => 'nullable|integer|min:10',
            'description'      => 'nullable|string',
            'experience_years' => 'nullable|integer|min:0',
            'images.*'         => 'nullable|image|mimes:jpg,jpeg,png',
            'cv_file'          => 'nullable|mimes:pdf,doc,docx',
            'phone'    => 'nullable|string',
        ]);

        $data['user_id'] = Auth::id();

        // رفع الصور
        if ($request->hasFile('images')) {
            $images = [];
            foreach ($request->file('images') as $image) {
                $images[] = $image->store('coaches/images', 'public');
            }
            $data['images'] = $images;
        }

        // رفع الـ CV
        if ($request->hasFile('cv_file')) {
            $data['cv_file'] = $request->file('cv_file')
                ->store('coaches/cv', 'public');
        }

        $coach = Coach::create($data);

        return response()->json([
            'message' => 'تم إنشاء الكوتش بنجاح',
            'data'    => $coach
        ], 201);
    }

    /**
     * عرض كوتش واحد
     */
    public function show($id)
    {
        $coach = Coach::with(['user', 'field','ratings'])->findOrFail($id);

        return response()->json($coach, 200);
    }

    /**
     * تحديث كوتش
     */
    public function update(Request $request, $id)
    {
        $coach = Coach::where('user_id', Auth::id())->findOrFail($id);

        $data = $request->validate([
            'field_id'         => 'sometimes|exists:fields,id',
            'name'             => 'nullable|string|max:255',
            'age'              => 'nullable|integer|min:10',
            'description'      => 'nullable|string',
            'experience_years' => 'nullable|integer|min:0',
            'images.*'         => 'nullable|image|mimes:jpg,jpeg,png',
            'cv_file'          => 'nullable|mimes:pdf,doc,docx',
          'phone'    => 'nullable|string',
        ]);

        if ($request->hasFile('images')) {
            $images = [];
            foreach ($request->file('images') as $image) {
                $images[] = $image->store('coaches/images', 'public');
            }
            $data['images'] = $images;
        }

        if ($request->hasFile('cv_file')) {
            $data['cv_file'] = $request->file('cv_file')
                ->store('coaches/cv', 'public');
        }

        $coach->update($data);

        return response()->json([
            'message' => 'تم تحديث بيانات الكوتش',
            'data'    => $coach
        ], 200);
    }

/**
     * 📌 جلب كل المدربين الذين يعملون في ملاعب المستخدم الحالي (المالك)
     */
public function myFieldsCoaches(Request $request)
{
    $user = Auth::user();

    if (!$user) {
        return response()->json([
            'status'  => false,
            'message' => 'يجب تسجيل الدخول أولاً'
        ], 401);
    }

    $allowedRoles = [
        User::ROLE_ADMIN,
        User::ROLE_OWNER,
        User::ROLE_OWNER_ACADEMY
    ];

    if (!in_array($user->role, $allowedRoles)) {
        return response()->json([
            'status'  => false,
            'message' => 'غير مصرح لك'
        ], 403);
    }

    // 📦 Query الأساسي
    $coaches = Coach::query()->with(['user', 'field.academy']);

    // 🔑 فلتر حسب الدور، ماعدا لو طلبنا all
    $showAll = $request->filled('booking_type') && $request->booking_type === 'all';

    if (!$showAll) {
        // 🏟 Owner → مدربي ملاعبه فقط
        if ($user->role === User::ROLE_OWNER) {
            $coaches->whereHas('field', function ($q) use ($user) {
                $q->where('owner_id', $user->id);
            });
        }

        // 🏫 Owner Academy → مدربي ملاعب الأكاديمية
        if ($user->role === User::ROLE_OWNER_ACADEMY) {
            $coaches->whereHas('field.academy', function ($q) use ($user) {
                $q->where('user_id', $user->id); // غيّرها لو اسم العمود مختلف
            });
        }
    }

    $coaches = $coaches->latest()->get();

    return response()->json([
        'status' => true,
        'total'  => $coaches->count(),
        'data'   => $coaches
    ]);
}


    /**
     * حذف كوتش
     */
    public function destroy($id)
    {
        $coach = Coach::where('user_id', Auth::id())->findOrFail($id);
        $coach->delete();

        return response()->json([
            'message' => 'تم حذف الكوتش بنجاح'
        ], 200);
    }
}
