<?php

namespace App\Http\Controllers;

use App\Models\NewsEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;


class NewsEventController extends Controller
{
    // public function index(Request $request)
    // {
    //     return response()->json(
    //         NewsEvent::query()
    //             ->filter($request->all())
    //             ->latest()
    //         ->paginate(10)
    //     );
    // }
    
    
    public function index(Request $request)
{
    $page   = $request->get('page', 1);
    $search = $request->get('search');

    // Cache Key مختلف حسب الفلترة والصفحة
    $cacheKey = "news_events:index:page={$page}:search={$search}";

    $newsEvents = Cache::remember(
        $cacheKey,
        now()->addMinutes(10),
        function () use ($request) {
            return NewsEvent::query()
                ->filter($request->all())
                ->latest()
                ->paginate(10);
        }
    );

    return response()->json($newsEvents);
}


    public function show(NewsEvent $newsEvent)
    {
        return response()->json($newsEvent);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'images_description.*' => 'nullable|string',
            'images.*'    => 'nullable|image|mimes:jpg,jpeg,png,webp',
        ]);

        $images = [];

         if ($request->hasFile('images')) {
            foreach ($request->file('images') as $index => $image) {
                $images[] = [
                    'url' => $image->store('newsEvent', 'public'),
                    'description' => $request->images_description[$index] ?? null,
                ];
            }
        }

        $newsEvent = NewsEvent::create([
            'title'       => $data['title'],
            'description' => $data['description'] ?? null,
            'images'      => $images,
        ]);
Cache::flush();

        return response()->json($newsEvent, 201);
    }

public function update(Request $request, NewsEvent $newsEvent)
{
    $data = $request->validate([
        'title'                 => 'sometimes|required|string|max:255',
        'description'           => 'nullable|string',
        'images.*'              => 'nullable|image|mimes:jpg,jpeg,png,webp',
        'images_description.*'  => 'nullable|string',
        'deleted_images'        => 'nullable|array',
        'deleted_images.*'      => 'integer',
    ]);

    // الصور الحالية
    $currentImages = is_array($newsEvent->images)
        ? $newsEvent->images
        : [];

    $imagesChanged = false;

    /**
     * 🔴 حذف صور معينة
     */
    if ($request->filled('deleted_images')) {

        foreach ($request->deleted_images as $index) {

            if (isset($currentImages[$index])) {

                // حذف من storage
                if (
                    isset($currentImages[$index]['url']) &&
                    Storage::disk('public')->exists($currentImages[$index]['url'])
                ) {
                    Storage::disk('public')->delete($currentImages[$index]['url']);
                }

                // حذف من array
                unset($currentImages[$index]);
                $imagesChanged = true;
            }
        }

        $currentImages = array_values($currentImages);
    }

    /**
     * 🟢 تعديل وصف الصور فقط
     */
    if ($request->filled('images_description')) {

        foreach ($request->images_description as $index => $desc) {

            if (isset($currentImages[$index])) {
                $currentImages[$index]['description'] = $desc;
                $imagesChanged = true;
            }
        }
    }

    /**
     * 🟡 تعديل / إضافة صور
     */
    if ($request->hasFile('images')) {

        foreach ($request->file('images') as $index => $image) {

            // لو فيه صورة قديمة في نفس المكان → امسحيها
            if (
                isset($currentImages[$index]['url']) &&
                Storage::disk('public')->exists($currentImages[$index]['url'])
            ) {
                Storage::disk('public')->delete($currentImages[$index]['url']);
            }

            // خزني الصورة الجديدة
            $currentImages[$index] = [
                'url' => $image->store('newsEvent', 'public'),
                'description' => $request->images_description[$index]
                    ?? $currentImages[$index]['description']
                    ?? null,
            ];

            $imagesChanged = true;
        }
    }

    // لو حصل أي تعديل على الصور
    if ($imagesChanged) {
        $data['images'] = array_values($currentImages);
    }

    $newsEvent->update($data);
    Cache::flush();

    return response()->json($newsEvent->fresh());
}




    public function destroy(NewsEvent $newsEvent)
    {
        $newsEvent->delete();
        Cache::flush();

        return response()->json(['message' => 'تم الحذف بنجاح']);
    }
}
