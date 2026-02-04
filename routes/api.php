<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UsersController;
use App\Http\Controllers\FieldController;
use App\Http\Controllers\FieldImageController;
use App\Http\Controllers\FieldBookingController;
use App\Http\Controllers\FieldPeriodController;
use App\Http\Controllers\AppInfoController;
use App\Http\Controllers\ContactMessageController;
use App\Http\Controllers\PartnerController;
use App\Http\Controllers\NewsEventController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\ChairmanMessageController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\BannerController;
use App\Http\Controllers\MailController;
use App\Http\Controllers\TransferRequestController;
use App\Http\Controllers\CoachController;
use App\Http\Controllers\RatingController;
use App\Http\Controllers\WhatsAppWebhookController;
use App\Http\Controllers\AcademyController;
use App\Http\Controllers\FieldDayController;
use App\Http\Controllers\SubscriptionRenewalPriceController;
use App\Http\Controllers\CouponController;
use App\Http\Controllers\LoyaltyPointController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\TermAndConditionController;
use App\Http\Controllers\AboutSectionController;

Route::get('about', [AboutSectionController::class, 'index']);
Route::middleware('auth:sanctum')->post('about/{id}/upload-image', [AboutSectionController::class, 'updateImage']);
Route::middleware('auth:sanctum')->group(function () {
    Route::post('about', [AboutSectionController::class, 'store']);
    Route::put('about/{aboutSection}', [AboutSectionController::class, 'update']);
    Route::delete('about/{aboutSection}', [AboutSectionController::class, 'destroy']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('terms', TermAndConditionController::class)->except(['index', 'show']);
});

// المسارات العامة للعرض
Route::get('terms', [TermAndConditionController::class, 'index']);
Route::get('terms/{term}', [TermAndConditionController::class, 'show']);


Route::get('/fields/most-booked', [FieldController::class, 'mostBooked']);
Route::get('/fields/nearby', [FieldController::class, 'nearby']);

Route::middleware('auth:sanctum')->group(function () {
    // ضع المسار هنا لضمان وجود مستخدم دائماً
    Route::get('my-fields-coaches', [CoachController::class, 'myFieldsCoaches']);
    Route::post('/bookings/contract', [FieldBookingController::class, 'createContractBooking']);
});
// Route لإرسال الإيميل عبر الـ API
Route::post('/send-mail', [MailController::class, 'sendMail']);
Route::post('/whatsapp/send', [WhatsAppWebhookController::class, 'sendMessageFromRequest']);
Route::get('getAllMessages', [WhatsAppWebhookController::class, 'getAllMessages']);

// مسارات استعادة كلمة المرور
Route::post('/forgot-password', [UsersController::class, 'forgotPassword']);
Route::post('/reset-password-otp', [UsersController::class, 'verifyOtpAndResetPassword']);

Route::middleware('auth:sanctum')->group(function (){
    Route::post('admin/users/create', [UsersController::class, 'adminCreateUser']);
});

Route::post(
    'fields/{fieldId}/periods/{periodId}/status',
    [FieldPeriodController::class, 'changeStatus']
)->middleware('auth:sanctum');

 
// ============================
// Public Routes (No Auth)
// ============================

// User registration & login
Route::post('/register', [UsersController::class, 'register']);
Route::post('/login', [UsersController::class, 'login']);
Route::post('/registerOwner', [UsersController::class, 'registerOwner']);

Route::get('/pending-registrations', [UsersController::class, 'getPendingRegistrations'])
    ->middleware('auth:sanctum');
    Route::post('/users/{userId}/role', [UsersController::class, 'updateRole'])
    ->middleware('auth:sanctum'); // أو حسب الحماية المطلوبة
    Route::get('fields/', [FieldController::class, 'index']); 
 Route::get('fields/{id}', [FieldController::class, 'show']);  
 
// ============================
// Protected Routes (Auth: Sanctum)
// ============================
Route::middleware('auth:sanctum')->group(function () {

    // ----------------------------
    // Users Management
    // ----------------------------
    Route::prefix('users')->group(function () {

        // Current authenticated user
        Route::get('profile', [UsersController::class, 'profile']);
        Route::post('profile', [UsersController::class, 'updateProfile']);
        Route::post('reset-password', [UsersController::class, 'resetPassword']);
        Route::post('logout', [UsersController::class, 'logout']);

        // Admin-only routes
        Route::middleware('auth:sanctum')->group(function (){
            Route::get('/', [UsersController::class, 'index']);
           
            Route::delete('{id}', [UsersController::class, 'destroy']);
        });
    });

    // ----------------------------
    // Fields Management
    // ----------------------------
    Route::get('/my-fields', [FieldController::class, 'myFields']);
    Route::get('/cities', [FieldController::class, 'cities']);
    Route::get('periods', [FieldPeriodController::class, 'index']);
    Route::get('allperiods', [FieldPeriodController::class, 'indexPeriod']);

Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/users/{id}/toggle-block', [UsersController::class, 'toggleBlock']);
    Route::get('/users/blocked', [UsersController::class, 'getBlockedUsers']);
});
Route::delete('field-images/{imageId}', [FieldImageController::class, 'destroy']);
    Route::prefix('fields')->group(function () {

          // List all fields
        Route::post('/', [FieldController::class, 'store']);      // Create new field
         // Show single field
        Route::put('{id}', [FieldController::class, 'update']);  // Update field
        Route::delete('{id}', [FieldController::class, 'destroy']); // Delete field

        // ----------------------------
        // Field Images
        // ----------------------------
        Route::get('{field}/images', [FieldImageController::class, 'index']);
        Route::post('{field}/images', [FieldImageController::class, 'store']);
 
        Route::post('images/{image}/make-icon', [FieldImageController::class, 'makeIcon']);

        // ----------------------------
        // Field Periods
        // ----------------------------
        Route::post('{field}/periods', [FieldPeriodController::class, 'store']);
        Route::get('{field}/periods/{period}', [FieldPeriodController::class, 'show']);
        Route::put('{field}/periods/{period}', [FieldPeriodController::class, 'update']);
        Route::delete('{field}/periods/{period}', [FieldPeriodController::class, 'destroy']);
    });

    // ----------------------------
    // Field Bookings
    // ----------------------------

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/bookings/inactive', [FieldBookingController::class, 'getInactiveAndDebtorCustomers']);
    Route::post('bookings/{id}/renew', [FieldBookingController::class, 'renew']);

Route::get('/bookings/statistics', [FieldBookingController::class, 'statistics']);
Route::get('/academybookings/statistics', [FieldBookingController::class, 'academyStatistics']);

    // Admin only – Update booking
Route::post('/admin/bookings/{id}', [FieldBookingController::class, 'update']);


    // عرض الحجوزات (Admin / Owner)
    Route::get('bookings', [FieldBookingController::class, 'index']);

    Route::get('academyBookings', [FieldBookingController::class, 'indexAcademy']);
    // عرض حجوزات المستخدم الحالي
    Route::get('my-bookings', [FieldBookingController::class, 'myBookings']);
    // إنشاء حجز جديد
    Route::post('bookings', [FieldBookingController::class, 'store']);
     // إنشاء حجز للمعلب 
      Route::post('bookField', [FieldBookingController::class, 'bookField']);
    // حذف حجز
    Route::delete('bookings/{id}', [FieldBookingController::class, 'destroy']);
    
    // ✅ إلغاء حجز (User / Admin)
    Route::post('bookings/{id}/cancel', [FieldBookingController::class, 'cancel']);

    
    // التحقق من QR Code
    Route::post('bookings/verify-qr', [FieldBookingController::class, 'verifyQr']);
});
});
  Route::get('nextbookings', [FieldBookingController::class, 'futureBookings']);


   // ----------------------------
    // App info
    // ----------------------------
Route::get('/app-info', [AppInfoController::class, 'show']);
Route::middleware('auth:sanctum')->group(function () {
Route::post('/app-info', [AppInfoController::class, 'store']);
});



   // ----------------------------
    // ContactMessageController
    // ----------------------------
Route::post('/contact-us', [ContactMessageController::class, 'store']);
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/contact-messages', [ContactMessageController::class, 'index']);
    Route::get('/contact-messages/{id}', [ContactMessageController::class, 'show']);
});


  // ----------------------------
    // PartnerController
    // ----------------------------
    Route::get('partners', [PartnerController::class, 'index']);       
Route::get('partners/{partner}', [PartnerController::class, 'show']); 
Route::middleware('auth:sanctum')->group(function () {

Route::post('partners', [PartnerController::class, 'store']);       
Route::post('partners/{partner}', [PartnerController::class, 'update']); 
Route::delete('partners/{partner}', [PartnerController::class, 'destroy']); 
});


 // ----------------------------
    // NewsEventController
    // ----------------------------
Route::get('news-events', [NewsEventController::class, 'index']);
Route::get('news-events/{newsEvent}', [NewsEventController::class, 'show']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('news-events', [NewsEventController::class, 'store']);
    Route::post('news-events/{newsEvent}', [NewsEventController::class, 'update']);
    Route::delete('news-events/{newsEvent}', [NewsEventController::class, 'destroy']);
});



 // ----------------------------
    // BlogController
    // ----------------------------
Route::get('blogs', [BlogController::class, 'index']);
Route::get('blogs/{blog}', [BlogController::class, 'show']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('blogs', [BlogController::class, 'store']);
    Route::post('blogs/{blog}', [BlogController::class, 'update']);
    Route::delete('blogs/{blog}', [BlogController::class, 'destroy']);
});



 // ----------------------------
    // ChairmanMessageController
    // ----------------------------
    

// 🔓 Public routes
Route::get('chairman-messages', [ChairmanMessageController::class, 'index']);
Route::get('chairman-messages/{chairmanMessage}', [ChairmanMessageController::class, 'show']);

// 🔐 Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('chairman-messages', [ChairmanMessageController::class, 'store']);
    Route::post('chairman-messages/{chairmanMessage}', [ChairmanMessageController::class, 'update']);
    Route::delete('chairman-messages/{chairmanMessage}', [ChairmanMessageController::class, 'destroy']);
});

// ----------------------------
    // BannerController
    // ----------------------------
Route::get('banners', [BannerController::class, 'index']);
Route::get('banners/{banner}', [BannerController::class, 'show']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('banners', [BannerController::class, 'store']);
    Route::post('banners/{banner}', [BannerController::class, 'update']);
    Route::delete('banners/{banner}', [BannerController::class, 'destroy']);
});


// ----------------------------
    // ProductController
    // ----------------------------
Route::get('products', [ProductController::class, 'index']);
Route::get('products/{id}', [ProductController::class, 'show']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('products', [ProductController::class, 'store']);
    Route::post('products/{product}', [ProductController::class, 'update']);
    Route::delete('products/{product}', [ProductController::class, 'destroy']);
});


// ----------------------------
    // OrderController
    // ----------------------------

Route::middleware('auth:sanctum')->group(function () {
    Route::get('orders', [OrderController::class, 'index']);
    Route::post('orders', [OrderController::class, 'store']);
    Route::get('orders/{order}', [OrderController::class, 'show']);
    Route::delete('orders/{order}', [OrderController::class, 'destroy']);
});


// ----------------------------
    // TransferRequest
    // ----------------------------
    Route::post('transfer-requests/{transferRequest}/reject', [TransferRequestController::class, 'reject'])
    ->middleware('auth:sanctum');
Route::middleware('auth:sanctum')->group(function () {

    Route::prefix('transfer-requests')->controller(TransferRequestController::class)->group(function () {
        Route::get('/', 'index');        
        Route::post('/', 'store');       
        Route::delete('/{transferRequest}', 'destroy'); 
        Route::post('/{transferRequest}/approve', 'approve'); 
    });
});



// ----------------------------
    // CoachController
    // ----------------------------


    Route::get('/coaches', [CoachController::class, 'index']);
    Route::get('/coaches/{id}', [CoachController::class, 'show']);
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/coaches', [CoachController::class, 'store']);
    Route::post('/coaches/{id}', [CoachController::class, 'update']);
    Route::delete('/coaches/{id}', [CoachController::class, 'destroy']);

});

// ----------------------------
    // RatingController
    // ----------------------------

Route::middleware('auth:sanctum')->post('/rate', [RatingController::class, 'store']);



//============================
 //Academies
//============================
// عرض كل الأكاديميات
Route::get('academies', [AcademyController::class, 'index']);
// عرض أكاديمية واحدة مع الملاعب
Route::get('academies/{id}', [AcademyController::class, 'show']);
Route::middleware('auth:sanctum')->group(function () {

    Route::prefix('academies')->group(function () {
Route::get('academies/me', [AcademyController::class, 'myAcademies']);

        // إنشاء أكاديمية جديدة
        Route::post('/', [AcademyController::class, 'store']);

        // تعديل أكاديمية
        Route::post('{id}', [AcademyController::class, 'update']);

        // حذف أكاديمية
        Route::delete('{id}', [AcademyController::class, 'destroy']);

    });
});

//============================
 //SubscriptionRenewalPriceController
//============================
// 📌 Routes عامة (بدون Auth)

Route::get(
    'subscription-renewal-prices',
    [SubscriptionRenewalPriceController::class, 'index']
);

Route::get(
    'subscription-renewal-prices/{subscription_renewal_price}',
    [SubscriptionRenewalPriceController::class, 'show']
);

// 🔒 Routes محمية (Auth)
Route::middleware('auth:sanctum')->group(function () {
Route::get(
    'subscription-renewal-indexByRole',
    [SubscriptionRenewalPriceController::class, 'indexByRole']
);

    Route::post(
        'subscription-renewal-prices',
        [SubscriptionRenewalPriceController::class, 'store']
    );

    Route::put(
        'subscription-renewal-prices/{subscription_renewal_price}',
        [SubscriptionRenewalPriceController::class, 'update']
    );

    Route::delete(
        'subscription-renewal-prices/{subscription_renewal_price}',
        [SubscriptionRenewalPriceController::class, 'destroy']
    );
});

//============================
 //CouponController
//============================
Route::middleware('auth:sanctum')->group(function () {

    // Coupons CRUD (Admin)
    Route::get('coupons', [CouponController::class, 'index']);
    Route::post('coupons', [CouponController::class, 'store']);
    Route::get('coupons/{id}', [CouponController::class, 'show']);
    Route::put('coupons/{id}', [CouponController::class, 'update']);
    Route::delete('coupons/{id}', [CouponController::class, 'destroy']);

    // Check coupon
    Route::post('coupons/check', [CouponController::class, 'check']);
});


Route::middleware('auth:sanctum')->group(function () {

Route::get('/payments', [PaymentController::class, 'index']);
Route::get('payments/{id}', [PaymentController::class, 'show'])
   ;
});

Route::get('/payment/callback', [PaymentController::class, 'paymentCallback'])->name('payment.callback');
//============================
 //LoyaltyPointController
//============================
Route::middleware(['auth:sanctum'])->group(function () {

    // Loyalty Points (Admin only)
    Route::apiResource('loyalty-points', LoyaltyPointController::class);

});
Route::post('/paymob/webhook', [PaymentController::class, 'webhook']);

Route::middleware('auth:sanctum')->group(function () {

Route::post('/payments', [PaymentController::class, 'store']);
Route::post('/payments/{payment}/intention', [PaymentController::class, 'createIntention']);

Route::post('/payments/{payment}/refund', [PaymentController::class, 'refund']);

});
