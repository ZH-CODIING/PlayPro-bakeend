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
        $this->authorizeRoles($request);

        $data = $request->validate([
            'title'        => 'required|string|max:255',
            'description'  => 'nullable|string',
            'images.*'     => 'nullable|image|mimes:jpg,jpeg,png,webp',
            'images_description.*' => 'nullable|string',
            'type'         => 'nullable|string',
        ]);

        $images = [];

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $index => $image) {
                $images[] = [
                    'url' => $image->store('blogs', 'public'),
                    'description' => $request->images_description[$index] ?? null,
                ];
            }
        }

        $data['images'] = $images;

        Log::info('Blog Store Data:', $data);

        $blog = Blog::create($data);
Cache::flush();

        return response()->json($blog, 201);
    }

    /**
     * تحديث مقال
     */
    public function update(Request $request, Blog $blog)
    {
        $this->authorizeRoles($request);

        $data = $request->validate([
            'title'                   => 'sometimes|required|string|max:255',
            'description'             => 'nullable|string',
            'images.*'                => 'nullable|image|mimes:jpg,jpeg,png,webp',
            'images_description.*'    => 'nullable|string',
            'type'                    => 'nullable|string',
        ]);

        // ---------- تحديث الصور ----------
        if ($request->hasFile('images')) {

            // حذف الصور القديمة
            if (is_array($blog->images)) {
                foreach ($blog->images as $img) {
                    if (isset($img['url']) && Storage::disk('public')->exists($img['url'])) {
                        Storage::disk('public')->delete($img['url']);
                    }
                }
            }

            $images = [];
            foreach ($request->file('images') as $index => $image) {
                $images[] = [
                    'url' => $image->store('blogs', 'public'),
                    'description' => $request->images_description[$index] ?? null,
                ];
            }

            $data['images'] = $images;
        }

        $blog->update($data);
        Cache::flush();


        return response()->json($blog);
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
