<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Field;
use App\Models\FieldImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Models\User;


class FieldImageController extends Controller
{
    private function checkRole()
{
    $user = Auth::user();

    $allowedRoles = [
        User::ROLE_OWNER,
        User::ROLE_OWNER_ACADEMY,
        User::ROLE_ADMIN,
    ];

    if (!in_array($user->role, $allowedRoles)) {
        abort(response()->json([
            'status' => false,
            'message' => 'غير مصرح لك بالوصول إلى هذه الموارد'
        ], 403));
    }
}

    /**
     * عرض كل صور ملعب
     */
    public function index($fieldId)
    {
        $field = Field::with(['icon', 'gallery'])->findOrFail($fieldId);

        return response()->json([
            'status' => true,
            'data' => [
                'icon' => $field->icon,
                'gallery' => $field->gallery,
            ]
        ]);
    }

    /**
     * إضافة صور / أيقونة للملعب (Owner فقط)
     */
    public function store(Request $request, $fieldId)
    {
            $this->checkRole();

        $field = Field::findOrFail($fieldId);


        $data = $request->validate([
 'icon' => 'nullable|file|max:200480',      // أي ملف، الحد الأقصى 20 ميجا (20480 كيلوبايت)
'images' => 'nullable|array',
'images.*' => 'file|max:200480',          // أي ملف، الحد الأقصى 20 ميجا لكل صورة

        ]);

        // رفع الأيقونة (واحدة فقط)
        if ($request->hasFile('icon')) {

            // حذف الأيقونة القديمة لو موجودة
            if ($field->icon) {
                Storage::disk('public')->delete($field->icon->image);
                $field->icon->delete();
            }

            $path = $request->file('icon')->store('fields/icons', 'public');

            $field->images()->create([
                'image' => $path,
                'type' => 'icon',
            ]);
        }

        // رفع صور المعرض
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $path = $image->store('fields/gallery', 'public');

                $field->images()->create([
                    'image' => $path,
                    'type' => 'gallery',
                ]);
            }
        }

        return response()->json([
            'status' => true,
            'message' => 'تم رفع الصور بنجاح',
            'data' => $field->load(['icon', 'gallery'])
        ], 201);
    }

    /**
     * حذف صورة واحدة
     */
    public function destroy($imageId)
    {
            $this->checkRole();

        $image = FieldImage::findOrFail($imageId);
        $field = $image->field;

        Storage::disk('public')->delete($image->image);
        $image->delete();

        return response()->json([
            'status' => true,
            'message' => 'تم حذف الصورة بنجاح'
        ]);
    }

    /**
     * تغيير صورة إلى أيقونة
     */
    public function makeIcon($imageId)
    {
            $this->checkRole();

        $image = FieldImage::findOrFail($imageId);
        $field = $image->field;


        // إزالة الأيقونة الحالية
        FieldImage::where('field_id', $field->id)
            ->where('type', 'icon')
            ->update(['type' => 'gallery']);

        // جعل الصورة أيقونة
        $image->update(['type' => 'icon']);

        return response()->json([
            'status' => true,
            'message' => 'تم تعيين الأيقونة بنجاح',
            'data' => $field->load(['icon', 'gallery'])
        ]);
    }
}
