<?php

namespace App\Http\Controllers;

use App\Models\AboutSection;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Models\AboutImage;
use Illuminate\Support\Facades\Storage;

class AboutSectionController extends Controller
{
    private function authorizeAdmin(Request $request)
    {
        $user = $request->user();
        if (!$user || !$user->hasRole([User::ROLE_ADMIN])) {
            abort(response()->json(['status' => false, 'message' => 'غير مصرح لك'], 403));
        }
    }

public function index()
{
    $about = Cache::remember('about_sections_with_images', now()->addDays(1), function () {
        return AboutSection::with('image')->orderBy('order', 'asc')->get();
    });

    return response()->json(['status' => true, 'data' => $about]);
}
    public function store(Request $request)
    {
        $this->authorizeAdmin($request);
        $data = $request->validate([
            'key' => 'required|string|unique:about_sections',
            'title' => 'required|string',
            'description' => 'nullable|string',
            'items' => 'nullable|array',
            'order' => 'nullable|integer'
        ]);

        $section = AboutSection::create($data);
        Cache::flush();
        return response()->json(['status' => true, 'data' => $section], 201);
    }

   /**
 * إضافة أو تحديث صورة لقسم معين
 */
public function updateImage(Request $request, $sectionId)
{
    $this->authorizeAdmin($request);

    $request->validate([
        'image' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
    ]);

    $section = AboutSection::findOrFail($sectionId);

    // رفع الصورة الجديدة
    $path = $request->file('image')->store('about', 'public');

    // التحقق إذا كان القسم لديه صورة قديمة لحذفها
    if ($section->image) {
        Storage::disk('public')->delete($section->image->image_path);
        
        $section->image->update([
            'image_path' => $path
        ]);
    } else {
        // إنشاء سجل جديد للصورة
        AboutImage::create([
            'about_section_id' => $section->id,
            'image_path' => $path
        ]);
    }

    Cache::flush();

    return response()->json([
        'status' => true,
        'message' => 'تم تحديث الصورة بنجاح',
        'image_url' => asset('storage/' . $path)
    ]);
}

    public function update(Request $request, AboutSection $aboutSection)
    {
        $this->authorizeAdmin($request);
        $data = $request->validate([
            'title' => 'sometimes|string',
            'description' => 'nullable|string',
            'items' => 'nullable|array',
            'order' => 'sometimes|integer'
        ]);

        $aboutSection->update($data);
        Cache::flush();
        return response()->json(['status' => true, 'data' => $aboutSection]);
    }

    public function destroy(Request $request, AboutSection $aboutSection)
    {
        $this->authorizeAdmin($request);
        $aboutSection->delete();
        Cache::flush();
        return response()->json(['status' => true, 'message' => 'تم الحذف']);
    }
}