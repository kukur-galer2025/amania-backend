<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// --- 1. Import User/Public Controllers ---
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\ArticleController;
use App\Http\Controllers\Api\RegistrationController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\LeaderboardController;
use App\Http\Controllers\Api\NotificationController; 
use App\Http\Controllers\Api\GlobalSearchController; 
use App\Http\Controllers\Api\EProductController;
use App\Http\Controllers\Api\MyEventController;
use App\Http\Controllers\Api\CourseController;

// --- 2. Import Checkout & Cart Controllers ---
use App\Http\Controllers\Api\EProductCheckoutController;
use App\Http\Controllers\Api\LessonCommentController;
use App\Http\Controllers\Api\CartController; // 🔥 IMPORT CART CONTROLLER 🔥

// --- 3. Import Admin Controllers ---
use App\Http\Controllers\Api\Admin\EventController as AdminEvent;
use App\Http\Controllers\Api\Admin\RegistrationController as AdminReg;
use App\Http\Controllers\Api\Admin\ArticleCategoryController as AdminCategory;
use App\Http\Controllers\Api\Admin\ArticleController as AdminArticle;
use App\Http\Controllers\Api\Admin\MaterialController as AdminMaterial;
use App\Http\Controllers\Api\Admin\SpeakerController as AdminSpeaker;
use App\Http\Controllers\Api\Admin\DashboardController as AdminDashboard;
use App\Http\Controllers\Api\Admin\UserController as AdminUser;
use App\Http\Controllers\Api\Admin\TransactionController as AdminTransaction;
use App\Http\Controllers\Api\Admin\TicketController as AdminTicket;
use App\Http\Controllers\Api\Admin\ReportController as AdminReport;
use App\Http\Controllers\Api\Admin\GlobalSearchController as AdminGlobalSearch;
use App\Http\Controllers\Api\Admin\NotificationController as AdminNotification;
use App\Http\Controllers\Api\Admin\EProductController as AdminEProduct;
use App\Http\Controllers\Api\Admin\EProductCategoryController as AdminEProductCategory;
use App\Http\Controllers\Api\Admin\ImageUploadController;
use App\Http\Controllers\Api\Admin\EProductTransactionController as AdminEProductTransaction;
use App\Http\Controllers\Api\Admin\EProductMaterialController as AdminEProductMaterial;
use App\Http\Controllers\Api\Admin\CourseCategoryController as AdminCourseCategory;
use App\Http\Controllers\Api\Admin\CourseController as AdminCourse;
use App\Http\Controllers\Api\Admin\CourseTransactionController as AdminCourseTransaction;
use App\Http\Controllers\Api\Admin\CourseSectionController as AdminCourseSection;
use App\Http\Controllers\Api\Admin\CourseLessonController as AdminCourseLesson;

/*
|--------------------------------------------------------------------------
| API Routes - Amania Nusantara Professional
|--------------------------------------------------------------------------
*/

// =========================================================================
// SECTION 1: PUBLIC ROUTES (Dapat diakses tanpa Login)
// =========================================================================
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/auth/google', [AuthController::class, 'googleLogin']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

Route::get('/events', [EventController::class, 'index']);
Route::get('/events/{slug}', [EventController::class, 'show']);
Route::get('/articles', [ArticleController::class, 'index']);
Route::get('/articles/{slug}', [ArticleController::class, 'show']);
Route::get('/leaderboard', [LeaderboardController::class, 'index']);
Route::get('/global-search', [GlobalSearchController::class, 'search']);

Route::get('/e-products', [EProductController::class, 'index']);
Route::get('/e-products/{slug}', [EProductController::class, 'show']);

Route::get('/e-product-categories', [AdminEProductCategory::class, 'index']);

Route::get('/courses', [CourseController::class, 'index']);
Route::get('/courses/{slug}', [CourseController::class, 'show']);
Route::get('/courses/{slug}/reviews', [CourseController::class, 'getReviews']);
Route::get('/course-categories', [AdminCourseCategory::class, 'index']);

// 🔥 AI COURSE ADVISOR 🔥
Route::post('/ai/course-advisor', [CourseController::class, 'askCourseAdvisor']);

// Download file lesson & certificate — diluar auth:sanctum karena browser <a target="_blank"> tidak kirim Authorization header
// Auth ditangani di controller via ?token= query parameter
Route::get('/courses/lessons/{lessonId}/download', [CourseController::class, 'downloadLessonFile']);
Route::get('/my-courses/{slug}/certificate', [CourseController::class, 'downloadCertificate']);

Route::post('/tripay/callback', [EProductCheckoutController::class, 'tripayWebhook']);

// =========================================================================
// SECTION 2: MEMBER ROUTES (Wajib Login)
// =========================================================================
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return response()->json(['success' => true, 'data' => $request->user()]);
    });
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/profile/update', [ProfileController::class, 'update']);
    
    Route::post('/register-event', [RegistrationController::class, 'store']);
    Route::get('/my-registrations', [RegistrationController::class, 'myRegistrations']);
    Route::post('/registrations/{id}/reupload', [RegistrationController::class, 'reuploadProof']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/read', [NotificationController::class, 'markAllAsRead']);
    
    // 🔥 API KERANJANG BELANJA (CART) 🔥
    Route::get('/cart', [CartController::class, 'index']);
    Route::post('/cart', [CartController::class, 'store']);
    Route::delete('/cart/{id}', [CartController::class, 'destroy']); // 🔥 RUTE DELETE DITAMBAHKAN 🔥

    Route::get('/checkout/payment-channels', [EProductCheckoutController::class, 'getPaymentChannels']);
    Route::post('/checkout/e-product', [EProductCheckoutController::class, 'purchaseEProduct']);
    
    Route::get('/my-e-products', [EProductController::class, 'myProducts']);
    Route::get('/my-e-products/{slug}', [EProductController::class, 'myProductDetail']);
    Route::get('/my-e-products/materials/{id}/download', [EProductController::class, 'downloadMaterial']);

    Route::post('/e-products/{id}/reviews', [EProductController::class, 'submitReview']);
    
    Route::get('/my-eproduct-transactions', [EProductController::class, 'myTransactions']);

    // 🔥 KURSUS ONLINE (MEMBER) 🔥
    Route::post('/checkout/course', [EProductCheckoutController::class, 'purchaseCourse']);
    Route::get('/my-courses', [CourseController::class, 'myCourses']);
    Route::get('/my-course-transactions', [CourseController::class, 'myTransactions']);
    Route::get('/courses/{slug}/learn', [CourseController::class, 'learnCourse']);
    Route::post('/courses/progress', [CourseController::class, 'markProgress']);
    // Aliases agar frontend LearnClient.tsx kompatibel
    Route::get('/my-courses/{slug}/learn', [CourseController::class, 'learnCourse']);
    Route::post('/my-courses/{slug}/progress', [CourseController::class, 'markProgressBySlug']);
    
    // Ujian Akhir (Member)
    Route::get('/my-courses/{slug}/exam', [\App\Http\Controllers\Api\ExamController::class, 'getExam']);
    Route::post('/my-courses/{slug}/exam/submit', [\App\Http\Controllers\Api\ExamController::class, 'submitExam']);
    // Download file lesson — dipindah ke public route (line ~80) karena browser <a target="_blank">
    // tidak bisa kirim Authorization header. Auth via ?token= di controller.

    // Rating / Review kursus
    Route::post('/courses/{slug}/reviews', [CourseController::class, 'submitReview']);
    Route::get('/courses/{slug}/reviews', [CourseController::class, 'getReviews']);

    // Diskusi / Q&A
    Route::get('/courses/lessons/{lessonId}/comments', [LessonCommentController::class, 'index']);
    Route::post('/courses/lessons/{lessonId}/comments', [LessonCommentController::class, 'store']);
    Route::delete('/courses/lessons/comments/{id}', [LessonCommentController::class, 'destroy']);

    // 🔥 AI MENTOR 🔥
    Route::get('/courses/lessons/{lessonId}/mentor-chats', [CourseController::class, 'getMentorChats']);
    Route::delete('/courses/lessons/{lessonId}/mentor-chats', [CourseController::class, 'clearMentorChats']);
    Route::post('/courses/lessons/{lessonId}/mentor', [CourseController::class, 'askMentor']);

    Route::get('/my-events/{slug}', [MyEventController::class, 'show']);
    Route::get('/my-events/{slug}/download-poster', [MyEventController::class, 'downloadPoster']);
    Route::get('/my-events/materials/{id}/download', [MyEventController::class, 'downloadMaterial']);
});

// =========================================================================
// SECTION 3A: RUTE BERSAMA (Superadmin | Creator)
// =========================================================================
Route::middleware(['auth:sanctum', 'role:superadmin|creator'])->prefix('admin')->group(function () {
    Route::get('/dashboard', [AdminDashboard::class, 'index']);
    Route::post('/upload-image', [ImageUploadController::class, 'upload']);
    
    Route::get('/global-search', [AdminGlobalSearch::class, 'search']);
    Route::get('/notifications', [AdminNotification::class, 'index']);
    Route::post('/notifications/read', [AdminNotification::class, 'markAllAsRead']);

    // Profil Admin
    Route::post('/profile', [ProfileController::class, 'update']);

    // E-Products (Creator & Superadmin)
    Route::get('/e-product-categories', [AdminEProductCategory::class, 'index']);
    Route::get('/e-products', [AdminEProduct::class, 'index']);
    Route::get('/e-products/{id}', [AdminEProduct::class, 'show']);
    Route::post('/e-products', [AdminEProduct::class, 'store']);
    Route::post('/e-products/{id}', [AdminEProduct::class, 'update']); 
    Route::delete('/e-products/{id}', [AdminEProduct::class, 'destroy']);
    Route::post('/e-product-materials', [AdminEProductMaterial::class, 'store']);
    Route::delete('/e-product-materials/{id}', [AdminEProductMaterial::class, 'destroy']);

    // Courses (Creator & Superadmin)
    Route::get('/course-categories', [AdminCourseCategory::class, 'index']);
    Route::get('/courses', [AdminCourse::class, 'index']);
    Route::get('/courses/{id}', [AdminCourse::class, 'show']);
    Route::post('/courses', [AdminCourse::class, 'store']);
    Route::post('/courses/{id}', [AdminCourse::class, 'update']);
    Route::delete('/courses/{id}', [AdminCourse::class, 'destroy']);
    Route::post('/courses/{courseId}/sections', [AdminCourseSection::class, 'store']);
    Route::put('/courses/{courseId}/sections/{sectionId}', [AdminCourseSection::class, 'update']);
    Route::delete('/courses/{courseId}/sections/{sectionId}', [AdminCourseSection::class, 'destroy']);
    Route::post('/courses/{courseId}/lessons', [AdminCourseLesson::class, 'store']);
    Route::post('/courses/{courseId}/lessons/{lessonId}', [AdminCourseLesson::class, 'update']); 
    Route::put('/courses/{courseId}/lessons/{lessonId}', [AdminCourseLesson::class, 'update']);
    Route::delete('/courses/{courseId}/lessons/{lessonId}', [AdminCourseLesson::class, 'destroy']);
    Route::get('/courses/{courseId}/lessons/{lessonId}/download', [AdminCourseLesson::class, 'downloadFile']);

    // Ujian / Kuis (Creator & Superadmin)
    Route::get('/courses/{courseId}/exam', [\App\Http\Controllers\Api\AdminExamController::class, 'show']);
    Route::post('/courses/{courseId}/exam', [\App\Http\Controllers\Api\AdminExamController::class, 'storeOrUpdate']);
    Route::post('/courses/exams/{examId}/questions', [\App\Http\Controllers\Api\AdminExamController::class, 'storeQuestion']);
    Route::put('/courses/exams/questions/{questionId}', [\App\Http\Controllers\Api\AdminExamController::class, 'updateQuestion']);
    Route::delete('/courses/exams/questions/{questionId}', [\App\Http\Controllers\Api\AdminExamController::class, 'destroyQuestion']);

    // Ruang Diskusi Q&A (Creator & Superadmin)
    Route::get('/discussions', [\App\Http\Controllers\Api\AdminDiscussionController::class, 'index']);
    Route::post('/discussions/{id}/reply', [\App\Http\Controllers\Api\AdminDiscussionController::class, 'reply']);
    Route::delete('/discussions/{id}', [\App\Http\Controllers\Api\AdminDiscussionController::class, 'destroy']);
});

// =========================================================================
// SECTION 3B: RUTE EKSKLUSIF SUPERADMIN (EVENTS, REPORTS, DLL)
// =========================================================================
Route::middleware(['auth:sanctum', 'role:superadmin'])->prefix('admin')->group(function () {

    Route::get('/events', [AdminEvent::class, 'index']);
    Route::get('/events/{id}', [AdminEvent::class, 'show']);
    Route::post('/events', [AdminEvent::class, 'store']);
    Route::post('/events/{id}', [AdminEvent::class, 'update']); 
    Route::delete('/events/{id}', [AdminEvent::class, 'destroy']);
    
    Route::post('/materials', [AdminMaterial::class, 'store']);
    Route::delete('/materials/{id}', [AdminMaterial::class, 'destroy']);
    
    Route::post('/speakers', [AdminSpeaker::class, 'store']); 
    Route::delete('/speakers/{id}', [AdminSpeaker::class, 'destroy']);

    Route::get('/registrations', [AdminReg::class, 'index']);
    Route::post('/registrations/{id}/verify', [AdminReg::class, 'verify']);
    Route::post('/registrations/{id}/reject', [AdminReg::class, 'reject']); 
    Route::post('/registrations/{id}/pending', [AdminReg::class, 'markAsPending']); 
    
    Route::put('/registrations/{id}/tier', [AdminReg::class, 'changeTier']);

    Route::get('/transactions', [AdminTransaction::class, 'index']);
    Route::get('/tickets', [AdminTicket::class, 'index']);
    Route::post('/tickets/scan', [AdminTicket::class, 'check']);
    Route::get('/reports', [AdminReport::class, 'index']);
    Route::get('/reports/export', [AdminReport::class, 'export']);

    Route::get('/article-categories', [AdminCategory::class, 'index']);
    Route::get('/articles', [AdminArticle::class, 'index']);
    Route::get('/articles/{id}', [AdminArticle::class, 'show']); 
    Route::post('/articles', [AdminArticle::class, 'store']);
    Route::post('/articles/{id}', [AdminArticle::class, 'update']); 
    Route::delete('/articles/{id}', [AdminArticle::class, 'destroy']);

    // Kelola User
    Route::get('/users', [AdminUser::class, 'index']);
    Route::post('/users', [AdminUser::class, 'store']); 
    Route::put('/users/{id}', [AdminUser::class, 'update']); 
    Route::post('/users/{id}/reset-password', [AdminUser::class, 'resetPassword']);
    Route::delete('/users/{id}', [AdminUser::class, 'destroy']);
    
    Route::post('/article-categories', [AdminCategory::class, 'store']);
    Route::put('/article-categories/{id}', [AdminCategory::class, 'update']);
    Route::delete('/article-categories/{id}', [AdminCategory::class, 'destroy']);

    Route::post('/e-product-categories', [AdminEProductCategory::class, 'store']);
    Route::put('/e-product-categories/{id}', [AdminEProductCategory::class, 'update']);
    Route::delete('/e-product-categories/{id}', [AdminEProductCategory::class, 'destroy']);



    Route::get('/e-product-transactions', [AdminEProductTransaction::class, 'index']);
    Route::post('/e-product-transactions/{id}/mark-paid', [AdminEProductTransaction::class, 'markAsPaid']);
    Route::get('/e-product-transactions/export', [AdminEProductTransaction::class, 'exportPdf']); 

    // 🔥 KURSUS ONLINE (ADMIN) 🔥
    Route::post('/course-categories', [AdminCourseCategory::class, 'store']);
    Route::put('/course-categories/{id}', [AdminCourseCategory::class, 'update']);
    Route::delete('/course-categories/{id}', [AdminCourseCategory::class, 'destroy']);



    Route::get('/course-transactions', [AdminCourseTransaction::class, 'index']);
});