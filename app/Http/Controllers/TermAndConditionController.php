<?php

namespace App\Http\Controllers;

use App\Models\TermAndCondition;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class TermAndConditionController extends Controller
{
    /**
     * التحقق من أن المستخدم Admin فقط
     */
    private function authorizeAdmin(Request $request)
    {
        $user = $request->user();

        if (!$user || !$user->hasRole([User::ROLE_ADMIN])) {
            abort(response()->json([
                'status' => false,
                'message' => 'غير مصرح لك بالوصول، هذه الصلاحية للمسؤول فقط'
            ], 403));
        }
    }

    /**
     * عرض جميع الشروط والأحكام (متاح للجميع)
     */
    public function index()
    {
        // استخدام الكاش لتحسين الأداء
        $terms = Cache::remember('terms_and_conditions_all', now()->addDays(1), function () {
            return TermAndCondition::orderBy('order', 'asc')->get();
        });

        return response()->json([
            'status' => true,
            'data' => $terms
        ]);
    }

    /**
     * عرض قسم واحد
     */
    public function show(TermAndCondition $term)
    {
        return response()->json([
            'status' => true,
            'data' => $term
        ]);
    }

    /**
     * إنشاء قسم جديد (للأدمن فقط)
     */
    public function store(Request $request)
    {
        $this->authorizeAdmin($request);

        $data = $request->validate([
            'title'   => 'required|string|max:255',
            'content' => 'required|array', // مصفوفة النقاط
            'order'   => 'nullable|integer'
        ]);

        $term = TermAndCondition::create($data);
        
        Cache::flush(); // مسح الكاش لتحديث البيانات عند العميل

        return response()->json([
            'status' => true,
            'message' => 'تم إضافة الشروط بنجاح',
            'data' => $term
        ], 201);
    }

    /**
     * تحديث قسم (للأدمن فقط)
     */
    public function update(Request $request, TermAndCondition $term)
    {
        $this->authorizeAdmin($request);

        $data = $request->validate([
            'title'   => 'sometimes|required|string|max:255',
            'content' => 'sometimes|required|array',
            'order'   => 'sometimes|integer'
        ]);

        $term->update($data);
        
        Cache::flush();

        return response()->json([
            'status' => true,
            'message' => 'تم تحديث الشروط بنجاح',
            'data' => $term
        ]);
    }

    /**
     * حذف قسم (للأدمن فقط)
     */
    public function destroy(Request $request, TermAndCondition $term)
    {
        $this->authorizeAdmin($request);

        $term->delete();
        
        Cache::flush();

        return response()->json([
            'status' => true,
            'message' => 'تم حذف القسم بنجاح'
        ]);
    }
}