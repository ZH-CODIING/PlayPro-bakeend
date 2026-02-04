<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Field;
use App\Models\FieldPeriod;
use App\Models\Coach;
use Illuminate\Support\Facades\Auth;

class FieldPeriodController extends Controller
{
    /**
     * عرض كل فترات ملعب معين
     */
    public function index(Request $request)
    {
        $query = FieldPeriod::query()
                ->select('start_time', 'end_time')
                ->filter($request)
                ->distinct()
                ->orderBy('start_time')
                ->get();
        return response()->json($query);
    }
    
    
      public function indexPeriod(Request $request)
    {
        $query = FieldPeriod::query()
                ->filter($request)->latest()->get();
        return response()->json($query);
    }

    /**
     * إنشاء فترة جديدة (Owner أو Admin)
     */
    public function store(Request $request, $fieldId)
    {
        $user = Auth::user();
        $field = Field::findOrFail($fieldId);

        if (! $user->isAdmin() && $user->id !== $field->owner_id) {
            return response()->json([
                'status' => false,
                'message' => 'غير مصرح لك'
            ], 403);
        }

        $data = $request->validate([
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'price_per_player' => 'required|numeric|min:0',
            'type' => 'nullable|in:enable,disable',
            'age_group' => 'nullable|string',
            'days' => 'nullable|array',
            'days.*' => 'in:Saturday,Sunday,Monday,Tuesday,Wednesday,Thursday,Friday',
            'coach_ids' => 'nullable|array',
            'coach_ids.*' => 'exists:coaches,id',
            'capacity' => 'nullable|string',

        ]);

        $data['type'] = $data['type'] ?? FieldPeriod::TYPE_ENABLE;

        $period = $field->periods()->create($data);

        // ربط الـ coaches بالجدول الوسيط
        if (!empty($data['coach_ids'])) {
            $period->coaches()->sync($data['coach_ids']);
        }

        return response()->json([
            'status' => true,
            'message' => 'تم إنشاء الفترة بنجاح',
            'data' => $period->load('coaches')
        ], 201);
    }

    /**
     * تغيير حالة الفترة (enable / disable)
     */
    public function changeStatus(Request $request, $fieldId, $periodId)
    {
        $user = Auth::user();
        $field = Field::findOrFail($fieldId);
        $period = FieldPeriod::where('field_id', $fieldId)->findOrFail($periodId);

        if (! $user->isAdmin() && $user->id !== $field->owner_id) {
            return response()->json([
                'status' => false,
                'message' => 'غير مصرح لك'
            ], 403);
        }

        $data = $request->validate([
            'type' => 'required|in:enable,disable'
        ]);

        $period->update([
            'type' => $data['type']
        ]);

        return response()->json([
            'status' => true,
            'message' => 'تم تغيير حالة الفترة',
            'data' => $period
        ]);
    }

    /**
     * عرض فترة محددة
     */
    public function show($fieldId, $periodId)
    {
        $period = FieldPeriod::with('coaches')->where('field_id', $fieldId)->findOrFail($periodId);

        return response()->json([
            'status' => true,
            'data' => $period
        ]);
    }

    /**
     * تحديث فترة (Owner أو Admin)
     */
public function update(Request $request, $fieldId, $periodId)
{
    $user = Auth::user();
    $field = Field::findOrFail($fieldId);
    $period = FieldPeriod::where('field_id', $fieldId)->findOrFail($periodId);

    if (! $user->isAdmin() && $user->id !== $field->owner_id) {
        return response()->json([
            'status' => false,
            'message' => 'غير مصرح لك'
        ], 403);
    }

    $data = $request->validate([
        'start_time' => 'required|date_format:H:i',
        'end_time'   => 'required|date_format:H:i|after:start_time',
        'price_per_player' => 'nullable|numeric|min:0',
        'type'       => 'nullable|in:enable,disable',
        'age_group'  => 'nullable|string',
        'capacity'  => 'nullable|string',
        'days'       => 'nullable|array',
        'days.*'     => 'in:Saturday,Sunday,Monday,Tuesday,Wednesday,Thursday,Friday',
        'coach_ids'  => 'nullable|array',
        'coach_ids.*'=> 'exists:coaches,id',
    ]);

    // إضافة الثواني تلقائيًا قبل التحديث
    $data['start_time'] .= ':00';
    $data['end_time']   .= ':00';

    $period->update($data);

    // تحديث الكوتشز في الجدول الوسيط
    if (isset($data['coach_ids'])) {
        $period->coaches()->sync($data['coach_ids']);
    }

    // إزالة الثواني عند الإرجاع
    $period->start_time = substr($period->start_time, 0, 5);
    $period->end_time   = substr($period->end_time, 0, 5);

    return response()->json([
        'status' => true,
        'message' => 'تم تحديث الفترة بنجاح',
        'data' => $period->load('coaches')
    ]);
}


    /**
     * حذف فترة (Owner أو Admin)
     */
    public function destroy($fieldId, $periodId)
    {
        $user = Auth::user();
        $field = Field::findOrFail($fieldId);
        $period = FieldPeriod::where('field_id', $fieldId)->findOrFail($periodId);

        if (! $user->isAdmin() && $user->id !== $field->owner_id) {
            return response()->json([
                'status' => false,
                'message' => 'غير مصرح لك'
            ], 403);
        }

        $period->delete();

        return response()->json([
            'status' => true,
            'message' => 'تم حذف الفترة بنجاح'
        ]);
    }
}
