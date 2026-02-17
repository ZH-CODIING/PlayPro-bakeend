<?php

namespace App\Http\Controllers;

use App\Models\Partner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PartnerController extends Controller
{
    /**
     * عرض كل الشركاء
     */
//     public function index(Request $request)
// {
//     return response()->json(
//         Partner::query()
//             ->filter($request->all())
//             ->latest()
//             ->get()
//     );
// }

public function index(Request $request)
{
    $search = $request->get('search');

    $cacheKey = "partners:index:search={$search}";

    $partners = Cache::remember(
        $cacheKey,
        now()->addMinutes(10),
        function () use ($request) {
            return Partner::query()
                ->filter($request->all())
                ->latest()
                ->get();
        }
    );

    return response()->json($partners);
}


    /**
     * إضافة شريك جديد
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'image'       => 'nullable|image|mimes:jpg,jpeg,png,webp',
            'description' => 'nullable|string',
            'link' => 'nullable|url',
            'badge'        => 'nullable|string|max:255',
        ]);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('partners', 'public');
        }

        $partner = Partner::create($data);
        Cache::flush();


        return response()->json($partner, 201);
    }

    /**
     * عرض شريك واحد
     */
    public function show(Partner $partner)
    {
        return response()->json($partner);
    }

    /**
     * تحديث شريك
     */
    public function update(Request $request, Partner $partner)
    {
        $data = $request->validate([
            'name'        => 'sometimes|required|string|max:255',
            'image'       => 'nullable|image|mimes:jpg,jpeg,png,webp',
            'description' => 'nullable|string',
            'link' => 'nullable|url',
             'badge'        => 'sometimes|required|string|max:255',

        ]);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('partners', 'public');
        }

        $partner->update($data);
        Cache::flush();


        return response()->json($partner);
    }

    /**
     * حذف شريك
     */
    public function destroy(Partner $partner)
    {
        $partner->delete();
        Cache::flush();

        return response()->json(['message' => 'تم حذف الشريك بنجاح']);
    }
}
