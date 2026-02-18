<?php

namespace App\Http\Controllers;

use App\Models\Blog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class BlogController extends Controller
{
    /**
     * التحقق من الصلاحيات: Admin / Coach / Management
     */
    private function authorizeRoles(Request $request)
    {
        $user = $request->user();

        if (! $user || ! $user->hasRole([User::ROLE_ADMIN, User::ROLE_COACH, User::ROLE_MANAGEMENT])) {
            abort(response()->json([
                'status' => false,
                'message' => 'غير مصرح لك بالوصول'
            ], 403));
        }
    }

    /**
     * عرض كل المقالات
     */
//   public function index(Request $request)
// {
//     return response()->json(
//         Blog::query()
//             ->filter($request->all())
//             ->paginate(10)
//     );
// }



public function index(Request $request)
{
    $page   = $request->get('page', 1);
    $search = $request->get('search');

    // Cache Key مختلف حسب الفلترة والصفحة
    $cacheKey = "blogs:index:page={$page}:search={$search}";

    $blogs = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($request) {
        return Blog::query()
            ->filter($request->all())
            ->paginate(10);
    });

    return response()->json($blogs);
}


    /**
     * عرض مقال واحد
     */
    public function show(Blog $blog)
    {
        return response()->json($blog);
    }

    /**
     * إنشاء مقال جديد
     */
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

        $blog = Blog::create([
            'title'       => $data['title'],
            'description' => $data['description'] ?? null,
            'images'      => $images,
        ]);
Cache::flush();

        return response()->json($blog, 201);
    }
    
    
    
    public function update(Request $request, Blog $blog)
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
    $currentImages = is_array($blog->images)
        ? $blog->images
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
                'url' => $image->store('blog', 'public'),
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

    $blog->update($data);
    Cache::flush();

    return response()->json($blog->fresh());
}

/**
 * حذف صورة واحدة محددة من المقال
 */
public function deleteImage(Request $request, Blog $blog)
{
    $this->authorizeRoles($request);

    $request->validate([
        'image_index' => 'required|integer'
    ]);

    $index = $request->image_index;
    $images = $blog->images;

    // التأكد أن الـ index موجود في المصفوفة
    if (!isset($images[$index])) {
        return response()->json(['message' => 'الصورة غير موجودة'], 404);
    }

    // 1. حذف الملف الفيزيائي من الـ Storage
    $imagePath = $images[$index]['url'] ?? null;
    if ($imagePath && Storage::disk('public')->exists($imagePath)) {
        Storage::disk('public')->delete($imagePath);
    }

    // 2. إزالة الصورة من المصفوفة
    unset($images[$index]);

    // 3. إعادة ترتيب المصفوفة (لأن unset تترك فجوات في الـ keys)
    $blog->images = array_values($images);
    $blog->save();

    // تفريغ الكاش
    Cache::flush();

    return response()->json([
        'status' => true,
        'message' => 'تم حذف الصورة بنجاح',
        'data' => $blog
    ]);
}
    /**
     * حذف مقال
     */
    public function destroy(Request $request, Blog $blog)
    {
        $this->authorizeRoles($request);

        // حذف ملفات الصور من التخزين
        if ($blog->images) {
            foreach ($blog->images as $image) {
                if (isset($image['url']) && Storage::disk('public')->exists($image['url'])) {
                    Storage::disk('public')->delete($image['url']);
                }
            }
        }

        // حذف المقال
        $blog->delete();
        Cache::flush();


        return response()->json([
            'message' => 'تم حذف المقال بنجاح'
        ]);
    }
}
