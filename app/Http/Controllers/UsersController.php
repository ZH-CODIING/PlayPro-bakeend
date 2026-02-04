<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use App\Mail\UserNotificationMail;
use App\Jobs\SendWhatsAppMessageJob;

class UsersController extends Controller
{
    /**
     * تسجيل مستخدم جديد
     */
public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'phone'    => 'required|string|unique:users,phone',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'phone'    => $request->phone,
            'role'     => 'User',
        ]);

        // إرسال الإشعارات للمستخدم ولجميع المديرين
        $this->sendRegistrationNotifications($user);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message'      => 'User registered successfully',
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'user'         => $user,
        ], 201);
    }
 /**
     * تسجيل مستخدم جديد
     */

public function registerOwner(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'              => 'required|string|max:255',
            'email'             => 'required|email|unique:users,email',
            'password'          => 'required|string|min:6',
            'phone'             => 'required|string|unique:users,phone',
            'registration_role' => 'nullable|string|in:User,Coach,Owner,OwnerAcademy',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $user = User::create([
            'name'              => $request->name,
            'email'             => $request->email,
            'password'          => Hash::make($request->password),
            'phone'             => $request->phone,
            'role'              => 'User',
            'registration_role' => $request->registration_role ?? 'User',
        ]);

        // إرسال الإشعارات للمستخدم ولجميع المديرين
        $this->sendRegistrationNotifications($user);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message'      => 'Owner registered successfully',
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'user'         => $user,
        ], 201);
    }

public function getPendingRegistrations(Request $request)
{
    $users = User::whereNotNull('registration_role')

        ->where('status', 'pending')
        ->filter($request)

        ->get();

    return response()->json([
        'status' => true,
        'data' => $users
    ]);
}

public function updateRole(Request $request, $userId)
{
    // 1. التأكد من صلاحية المنفذ (فقط Admin أو Owner)
    if (!auth()->user()->isAdmin() && !auth()->user()->isOwner()) {
        return response()->json(['message' => 'غير مصرح لك بتنفيذ هذا الإجراء.'], 403);
    }

    // 2. التحقق من البيانات المدخلة لتشمل كافة الأدوار الجديدة
    $data = $request->validate([
        'role' => 'required|string|in:' . implode(',', [
            User::ROLE_ADMIN,
            User::ROLE_OWNER,
            User::ROLE_COACH,
            User::ROLE_OWNER_ACADEMY,
            User::ROLE_MANAGEMENT,
            User::ROLE_USER
        ]),
    ]);

    $user = User::findOrFail($userId);

    // 3. تحديث الحالة تلقائياً إذا كانت معلقة
    if ($user->status === 'pending') {
        $user->status = 'active';
    }

    // 4. تحديث الدور
    $user->role = $data['role'];
    
    // ملاحظة: قد ترغب أيضاً في تحديث registration_role ليتطابق مع الدور الجديد
    $user->registration_role = $data['role'];

    $user->save();

    return response()->json([
        'status' => true,
        'message' => 'تم تحديث الدور وتفعيل الحساب بنجاح',
        'user' => [
            'id' => $user->id,
            'name' => $user->name,
            'role' => $user->role,
            'status' => $user->status
        ]
    ]);
}  


    /**
     * تسجيل دخول
     */
public function login(Request $request)
{
    $validator = Validator::make($request->all(), [
        'email' => 'required|email',
        'password' => 'required|string',
    ]);

    if ($validator->fails()) {
        return response()->json($validator->errors(), 422);
    }

    $user = User::where('email', $request->email)->first();

    // 1. التحقق من صحة البيانات
    if (!$user || !Hash::check($request->password, $user->password)) {
        return response()->json(['message' => 'Invalid credentials'], 401);
    }

    // 2. التحقق من الحظر
    if ($user->blocked) {
        return response()->json([
            'message' => 'لقد تم حظرك من قبل الادارة، لفك الحظر ومعرفة الاسباب الرجاء التواصل مع الادارة.'
        ], 403);
    }

    // 3. حساب إجمالي عدد مرات التجديد من حجوزات هذا المستخدم
    $totalRenewals = $user->bookings()->sum('renewal_count');

    // 4. جلب جميع مستويات نقاط الولاء من الجدول
    $loyaltyPoints = \App\Models\LoyaltyPoint::orderBy('points', 'asc')->get();

    // 5. إنشاء التوكن
    $token = $user->createToken('auth_token')->plainTextToken;

    return response()->json([
        'message' => 'Login successful',
        'access_token' => $token,
        'token_type' => 'Bearer',
        'user' => $user,
        'total_renewals' => (int) $totalRenewals, // إجمالي التجديدات
        'loyalty_settings' => $loyaltyPoints,      // مستويات الولاء المتاحة
    ]);
}
    /**
     * تسجيل خروج
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out successfully']);
    }

    /**
     * عرض بيانات البروفايل
     */
    public function profile(Request $request)
    {
        return response()->json($request->user());
    }

    /**
     * تحديث بيانات المستخدم
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'email' => ['sometimes', 'required', 'email', Rule::unique('users')->ignore($user->id)],
            'phone' => ['sometimes', 'required', 'string', Rule::unique('users')->ignore($user->id)],
            'avatar' => 'sometimes|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        if ($request->has('name'))
            $user->name = $request->name;
        if ($request->has('email'))
            $user->email = $request->email;
        if ($request->has('phone'))
            $user->phone = $request->phone;

        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->store('avatars', 'public');
            $user->avatar = url('storage/' . $path);
        }

        $user->save();

        return response()->json(['message' => 'Profile updated', 'user' => $user]);
    }

    /**
     * عرض كل المستخدمين (Admin فقط)
     */
    public function index(Request $request)
    {
        if ($request->user()->role !== 'Admin') {
            return response()->json(['message' => 'غير مصرح لك بتنفيذ هذا الإجراء.'], 403);
        }

        $users = User::query()
                ->filter($request)
                            ->paginate(10)

;
        return response()->json($users);
    }

    /**
     * حذف مستخدم (Admin فقط)
     */
    public function destroy($id, Request $request)
    {
        $user = User::findOrFail($id);

        if ($request->user()->role !== 'Admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $user->delete();
        return response()->json(['message' => 'User deleted successfully']);
    }

    /**
     * إعادة تعيين كلمة المرور
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'new_password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $user = User::where('email', $request->email)->first();
        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json(['message' => 'Password reset successfully']);
    }
    
    public function toggleBlock($id)
{
    if (!auth()->user()->isAdmin() && !auth()->user()->isOwner()) {
        return response()->json(['message' => 'غير مصرح لك بالقيام بهذا الإجراء.'], 403);
    }

    // 2. البحث عن المستخدم المراد حظره
    $user = User::findOrFail($id);

    // 3. حماية: منع الشخص من حظر نفسه
    if (auth()->id() == $user->id) {
        return response()->json(['message' => 'لا يمكنك حظر حسابك الشخصي.'], 400);
    }

    // 4. عكس حالة الحظر (إذا كان true يصبح false والعكس)
    $user->blocked = !$user->blocked;
    $user->save();

    $statusMessage = $user->blocked ? 'تم حظر المستخدم بنجاح.' : 'تم فك حظر المستخدم بنجاح.';

    return response()->json([
        'message' => $statusMessage,
        'user' => [
            'id' => $user->id,
            'name' => $user->name,
            'blocked' => $user->blocked
        ]
    ]);
}
/**
 * إنشاء حساب جديد بواسطة الآدمن (Admin Only)
 */
public function adminCreateUser(Request $request)
{
    // التأكد أن الصلاحية "Admin" باستخدام الهيلبر الموجود في الموديل
    if (!$request->user()->isAdmin()) {
        return response()->json(['message' => 'غير مصرح لك بتنفيذ هذا الإجراء.'], 403);
    }

    $validator = Validator::make($request->all(), [
        'name'     => 'required|string|max:255',
        'email'    => 'required|email|unique:users,email',
        'password' => 'required|string|min:6',
        'phone'    => 'required|string|unique:users,phone',
        // استخدام الثوابت من موديل User
        'role'     => 'required|string|in:' . implode(',', [
            User::ROLE_ADMIN,
            User::ROLE_OWNER,
            User::ROLE_COACH,
            User::ROLE_OWNER_ACADEMY,
            User::ROLE_MANAGEMENT,
            User::ROLE_USER
        ]),
        'status'   => 'nullable|string|in:active,pending',
    ]);

    if ($validator->fails()) {
        return response()->json($validator->errors(), 422);
    }

    $user = User::create([
        'name'              => $request->name,
        'email'             => $request->email,
        'password'          => Hash::make($request->password),
        'phone'             => $request->phone,
        'role'              => $request->role, 
        'status'            => $request->status ?? 'active',
        'registration_role' => $request->role,
    ]);

    return response()->json([
        'status'  => true,
        'message' => 'تم إنشاء الحساب بنجاح بنجاح بنجاح',
        'user'    => $user
    ], 201);
}

/**
 * جلب قائمة المستخدمين المحظورين فقط (Admin أو Owner فقط)
 */
public function getBlockedUsers(Request $request)
{
    // التأكد من الصلاحيات
    if (!auth()->user()->isAdmin() && !auth()->user()->isOwner()) {
        return response()->json(['message' => 'غير مصرح لك بالاطلاع على هذه البيانات.'], 403);
    }

    // جلب المستخدمين المحظورين
    $blockedUsers = User::where('blocked', true)
    ->filter($request)
    ->get();

    return response()->json([
        'status' => true,
        'count'  => $blockedUsers->count(),
        'data'   => $blockedUsers
    ]);
}

/**
 * 1. إرسال رمز التحقق للبريد الإلكتروني
 */
public function forgotPassword(Request $request)
{
    $validator = Validator::make($request->all(), [
        'email' => 'required|email|exists:users,email',
    ]);

    if ($validator->fails()) {
        return response()->json(['message' => 'البريد الإلكتروني غير موجود لدينا.'], 422);
    }

    // توليد رمز عشوائي مكون من 6 أرقام
    $otp = rand(100000, 999999);

    // تخزين الرمز في قاعدة البيانات (يفضل استخدام جدول password_resets أو عمود في جدول users)
    $user = User::where('email', $request->email)->first();
    $user->remember_token = $otp; // سنستخدم هذا الحقل مؤقتاً لتخزين الرمز
    $user->save();

    // إرسال البريد
    Mail::to($user->email)->send(new UserNotificationMail(
        'رمز استعادة كلمة المرور',
        "عزيزي {$user->name}، رمز التحقق الخاص بك هو: {$otp}"
    ));

    return response()->json(['message' => 'تم إرسال رمز التحقق إلى بريدك الإلكتروني.']);
}

/**
 * 2. التحقق من الرمز وتغيير كلمة المرور
 */
public function verifyOtpAndResetPassword(Request $request)
{
    $validator = Validator::make($request->all(), [
        'email'        => 'required|email|exists:users,email',
        'otp'          => 'required|string',
        'new_password' => 'required|string|min:6|confirmed',
    ]);

    if ($validator->fails()) {
        return response()->json($validator->errors(), 422);
    }

    $user = User::where('email', $request->email)
                ->where('remember_token', $request->otp)
                ->first();

    if (!$user) {
        return response()->json(['message' => 'رمز التحقق غير صحيح.'], 400);
    }

    // تحديث كلمة المرور وتصفير الرمز
    $user->password = Hash::make($request->new_password);
    $user->remember_token = null; 
    $user->save();

    return response()->json(['message' => 'تم تغيير كلمة المرور بنجاح.']);
}
/**
 * دالة موحدة لإرسال الإشعارات (User & Admins)
 */
private function sendRegistrationNotifications($user)
    {
        // 1. إرسال إيميل للمستخدم
        Mail::to($user->email)->send(new UserNotificationMail(
            'تم استلام طلب التسجيل بنجاح',
            "عزيزي {$user->name}، تم استلام طلبك بنجاح وسيتم مراجعته قريباً."
        ));

        // 2. جلب جميع المديرين من قاعدة البيانات
        $admins = User::where('role', 'Admin')->get();

        foreach ($admins as $admin) {
            // أ. إرسال إيميل للآدمن
            Mail::to($admin->email)->send(new UserNotificationMail(
                'إشعار تسجيل جديد',
                "قام مستخدم جديد بالتسجيل:\nالاسم: {$user->name}\nالبريد: {$user->email}\nالدور: {$user->registration_role}"
            ));

            // ب. إرسال واتساب للآدمن (عبر الـ Job)
            if ($admin->phone) {
                $cleanAdminPhone = preg_replace('/[^0-9]/', '', $admin->phone);
                $adminMessage = "🔔 *إشعار إداري جديد*\n\n" .
                                "مستخدم جديد سجل الآن:\n" .
                                "الاسم: {$user->name}\n" .
                                "الهاتف: {$user->phone}\n" .
                                "الدور: " . ($user->registration_role ?? 'User');

                dispatch(new SendWhatsAppMessageJob($cleanAdminPhone, $adminMessage));
            }
        }
}

}
