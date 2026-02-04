<?php

namespace App\Http\Controllers;

use App\Models\ChairmanMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use Illuminate\Support\Facades\Cache;


class ChairmanMessageController extends Controller
{
    /**
     * التحقق من الصلاحيات: Admin / Management
     */
    private function authorizeRoles(Request $request)
    {
        $user = $request->user();

        if (! $user || ! $user->hasRole([User::ROLE_ADMIN, User::ROLE_MANAGEMENT])) {
            abort(response()->json([
                'status' => false,
                'message' => 'غير مصرح لك بالوصول'
            ], 403));
        }
    }

    /**
     * 📌 عرض كل الرسائل
     */
    // public function index(Request $request)
    // {
    //     $data = ChairmanMessage::query()->filter($request->all())

    //         ->latest()->get();
    //     return response()->json($data);
    // }
    
    
    public function index(Request $request)
{
    $search = $request->get('search');

    $cacheKey = "chairman_messages:index:search={$search}";

    $data = Cache::remember(
        $cacheKey,
        now()->addMinutes(10),
        function () use ($request) {
            return ChairmanMessage::query()
                ->filter($request->all())
                ->latest()
                ->get();
        }
    );

    return response()->json($data);
}



    /**
     * 📌 عرض رسالة واحدة
     */
    public function show(ChairmanMessage $chairmanMessage)
    {
        return response()->json($chairmanMessage);
    }

    /**
     * 📌 إنشاء رسالة جديدة
     */
    public function store(Request $request)
    {
        $this->authorizeRoles($request);

        $data = $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'image'       => 'nullable|image|mimes:jpg,jpeg,png,webp',
        ]);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('chairman', 'public');
        }

        $chairmanMessage = ChairmanMessage::create($data);
        Cache::flush();


        return response()->json($chairmanMessage, 201);
    }

    /**
     * 📌 تحديث رسالة
     */
    public function update(Request $request, ChairmanMessage $chairmanMessage)
    {
        $this->authorizeRoles($request);

        $data = $request->validate([
            'title'       => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'image'       => 'nullable|image|mimes:jpg,jpeg,png,webp',
        ]);

        if ($request->hasFile('image')) {
            // حذف الصورة القديمة
            if ($chairmanMessage->image) {
                Storage::disk('public')->delete($chairmanMessage->image);
            }
            $data['image'] = $request->file('image')->store('chairman', 'public');
        }

        $chairmanMessage->update($data);
        Cache::flush();


        return response()->json($chairmanMessage);
    }

    /**
     * 📌 حذف رسالة
     */
    public function destroy(Request $request, ChairmanMessage $chairmanMessage)
    {
        $this->authorizeRoles($request);

        if ($chairmanMessage->image) {
            Storage::disk('public')->delete($chairmanMessage->image);
        }

        $chairmanMessage->delete();
Cache::flush();

        return response()->json([
            'message' => 'تم الحذف بنجاح'
        ]);
    }
}
