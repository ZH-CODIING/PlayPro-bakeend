<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Field;
use App\Models\User; 
use Illuminate\Http\Request;
use App\Models\Coach;
use Illuminate\Support\Facades\Auth;

class FieldController extends Controller
{
    
    /**
 * عرض الملاعب الأكثر حجزاً
 */
public function mostBooked(Request $request)
{
    // نفترض أن العلاقة في موديل Field تسمى bookings
    $fields = Field::with(['owner:id,name,phone', 'icon', 'gallery'])
        ->withCount('bookings') // يقوم بإنشاء عمود وهمي اسمه bookings_count
        ->orderBy('bookings_count', 'desc') // الترتيب من الأكثر حجزاً للأقل
        ->paginate(10);

    return response()->json([
        'status' => true,
        'message' => 'الملاعب الأكثر حجزاً',
        'data' => $fields
    ]);
}
/**
     * جلب الملاعب القريبة بناءً على الإحداثيات
     */
    public function nearby(Request $request)
    {
        $request->validate([
            'latitude'  => 'required|numeric',
            'longitude' => 'required|numeric',
            'distance'  => 'nullable|numeric', // المسافة بالكيلومتر (اختياري)
        ]);

        $lat = $request->latitude;
        $lon = $request->longitude;
        $distanceLimit = $request->get('distance', 10); // الافتراضي 10 كم

        /*
         * معادلة Haversine لحساب المسافة بالكيلومترات:
         * 6371 هو نصف قطر الأرض بالكيلومترات.
         */
        $fields = Field::with(['owner:id,name,phone', 'icon', 'gallery'])
            ->select('*')
            ->selectRaw(
                "(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance",
                [$lat, $lon, $lat]
            )
            ->having('distance', '<=', $distanceLimit)
            ->orderBy('distance', 'asc')
            ->paginate(10);

        return response()->json([
            'status' => true,
            'user_location' => [
                'lat' => $lat,
                'lng' => $lon
            ],
            'total_found' => $fields->total(),
            'data' => $fields
        ]);
    }
    /**
     * جلب كل المدن الموجودة في الملاعب بدون تكرار
     */
    public function cities()
    {
        $cities = Field::select('city')
            ->distinct()
            ->orderBy('city')
            ->get();

        return response()->json([
            'status' => true,
            'total_cities' => $cities->count(),
            'data' => $cities
        ]);
    }

    /**
     * عرض كل الملاعب (Public Index)
     */
    public function index(Request $request)
    {
        $query = Field::with([
            'owner:id,name,phone',
            'periods.coaches:id,name', 
            'icon',
            'gallery'
        ]);
if ($request->has('is_featured')) {
    $query->where('is_featured', $request->boolean('is_featured') ? 1 : 0);
}


     
        $fields = $query->filter($request->all())->paginate(10);

        return response()->json([
            'status'        => true,
            'total_fields'  => $fields->count(),
            'total_cities'  => Field::distinct('city')->count('city'),
            'data'          => $fields
        ]);
    } 

    /**
     * عرض ملاعب المستخدم المالك أو صاحب الأكاديمية أو الآدمن
     */
    public function myFields(Request $request)
    {
        $user = Auth::user();

        $allowedRoles = [
            User::ROLE_OWNER, 
            User::ROLE_OWNER_ACADEMY, 
            User::ROLE_ADMIN
        ];

        if (!in_array($user->role, $allowedRoles)) {
            return response()->json([
                'status' => false,
                'message' => 'غير مصرح لك بالوصول إلى هذه الموارد'
            ], 403);
        }

        // فلترة النتائج: الآدمن يرى الكل، البقية يرون ملاعبهم فقط
        $query = Field::query()
        ->filter($request->all());

        if (!$user->isAdmin()) {
            $query->where('owner_id', $user->id);
        }
        
        if ($request->boolean('academy_only')) {
        $query->whereNotNull('academy_id');
    }
        if ($request->boolean('field_only')) {
        $query->whereNull('academy_id');
    }

        $fields = $query->with(['owner:id,name,phone', 'periods.coaches', 'icon', 'gallery'])
            ->latest()
            ->get();

        return response()->json([
            'status' => true,
            'total'  => $fields->count(),
            'data'   => $fields
        ]);
    }
    /**
     * عرض ملعب واحد
     */
public function show($id)
{
    $field = Field::with([
        'owner:id,name,phone',
        'periods.coaches:id,name',
        'icon',
        'gallery'
    ])->findOrFail($id);
   return response()->json([
        'status' => true,
        'data' => $field
    ]);
}
    /**
     * إنشاء ملعب جديد
     */
public function store(Request $request)
    {
        $user = Auth::user();
        $allowedRoles = [User::ROLE_OWNER, User::ROLE_OWNER_ACADEMY, User::ROLE_ADMIN];

        if (!in_array($user->role, $allowedRoles)) {
            return response()->json([
                'status' => false,
                'message' => 'غير مصرح لك بإنشاء ملعب'
            ], 403);
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'size' => 'required|string|max:50',
            'capacity' => 'required|string|min:1',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'city' => 'required|string|max:100',
            'address' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_featured' => 'nullable|boolean',
            'academy_id' => 'nullable|exists:academies,id',
            'periods' => 'required|array|min:1',
            'periods.*.start_time' => 'required|date_format:H:i',
            'periods.*.end_time' => 'required|date_format:H:i|after:periods.*.start_time',
            'periods.*.price_per_player' => 'required|numeric|min:0',
            'periods.*.age_group' => 'nullable|string',
            'periods.*.capacity' => 'nullable|string',
            'periods.*.days' => 'nullable|array',
            'periods.*.days.*' => 'in:Saturday,Sunday,Monday,Tuesday,Wednesday,Thursday,Friday',
            'periods.*.coach_ids' => 'nullable|array',
            'periods.*.coach_ids.*' => 'exists:coaches,id',
        ]);

        $field = Field::create([
            'name' => $data['name'],
            'size' => $data['size'],
            'capacity' => $data['capacity'],
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'city' => $data['city'],
            'address' => $data['address'],
            'description' => $data['description'] ?? null,
            'is_featured' => $data['is_featured'] ?? false,
            'owner_id' => $user->id,
            'academy_id' => $data['academy_id'] ?? null,
        ]);

        foreach ($data['periods'] as $periodData) {
            $period = $field->periods()->create([
                'start_time' => $periodData['start_time'],
                'end_time' => $periodData['end_time'],
                'price_per_player' => $periodData['price_per_player'],
                'age_group' => $periodData['age_group'] ?? null,
                'capacity' => $periodData['capacity'] ?? null,
                'days' => $periodData['days'] ?? null,
            ]);

            if (!empty($periodData['coach_ids'])) {
                $period->coaches()->sync($periodData['coach_ids']);
            }
        }

        return response()->json([
            'status' => true,
            'message' => 'تم إنشاء الملعب بنجاح',
            'data' => $field->load('periods.coaches')
        ], 201);
    }

    /**
     * تعديل ملعب
     */
    public function update(Request $request, $id)
    {
        $field = Field::findOrFail($id);
        $user = Auth::user();

        // التحقق: (آدمن) أو (صاحب الملعب)
        if (!$user->isAdmin() && $user->id !== $field->owner_id) {
            return response()->json([
                'status' => false,
                'message' => 'غير مصرح لك بتعديل هذا الملعب'
            ], 403);
        }

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'size' => 'sometimes|string|max:50',
            'capacity' => 'sometimes|string|min:1',
            'latitude' => 'sometimes|numeric',
            'longitude' => 'sometimes|numeric',
            'city' => 'sometimes|string|max:100',
            'address' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'is_featured' => 'nullable|boolean',
            'academy_id' => 'nullable|exists:academies,id',  
            'periods' => 'nullable|array',
            'periods.*.capacity' => 'nullable|string',
            'periods.*.start_time' => 'required|date_format:H:i',
            'periods.*.end_time' => 'required|date_format:H:i|after:periods.*.start_time',
            'periods.*.price_per_player' => 'required|numeric|min:0',
            'periods.*.age_group' => 'nullable|string',
            'periods.*.days' => 'nullable|array',
            'periods.*.coach_ids' => 'nullable|array',
            'periods.*.coach_ids.*' => 'exists:coaches,id',
        ]);

        $field->update(collect($data)->except(['periods', 'academy_id'])->toArray());

        // تحديث الأكاديمية (آدمن أو صاحب أكاديمية فقط)
        if (($user->isAdmin() || $user->isOwnerAcademy()) && array_key_exists('academy_id', $data)) {
            $field->academy_id = $data['academy_id'];
            $field->save();
        }

        if ($request->has('periods')) {
            $field->periods()->delete();
            foreach ($data['periods'] as $periodData) {
                $period = $field->periods()->create([
                    'start_time' => $periodData['start_time'],
                    'end_time' => $periodData['end_time'],
                    'price_per_player' => $periodData['price_per_player'],
                    'age_group' => $periodData['age_group'] ?? null,
                    'days' => $periodData['days'] ?? null,
                    'capacity' => $periodData['capacity'] ?? null,
                    'coach_ids' => $periodData['coach_ids'] ?? null,

                ]);

                if (!empty($periodData['coach_ids'])) {
                    $period->coaches()->sync($periodData['coach_ids']);
                }
            }
        }

        return response()->json([
            'status' => true,
            'message' => 'تم تعديل الملعب بنجاح',
            'data' => $field->load('periods.coaches')
        ]);
    }

    /**
     * حذف ملعب
     */
    public function destroy($id)
    {
        $field = Field::findOrFail($id);
        $user = Auth::user();

        if (!$user->isAdmin() && $user->id !== $field->owner_id) {
            return response()->json([
                'status' => false,
                'message' => 'غير مصرح لك بحذف هذا الملعب'
            ], 403);
        }

        $field->delete();

        return response()->json([
            'status' => true,
            'message' => 'تم حذف الملعب بنجاح'
        ]);
    }
}