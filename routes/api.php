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
use App\Http\Controllers\Api\CheckoutController;

// --- 2. Import Admin Controllers ---
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

/*
|--------------------------------------------------------------------------
| API Routes - Amania Nusantara Professional
|--------------------------------------------------------------------------
*/

// =========================================================================
// SECTION 1: PUBLIC ROUTES (Guest & User Bisa Akses)
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

// 🔥 ROUTE WEBHOOK MIDTRANS (HARUS PUBLIC!) 🔥
Route::post('/webhooks/midtrans', [CheckoutController::class, 'webhook']);

// =========================================================================
// SECTION 2: MEMBER ROUTES (Wajib Login - Role User & Admin)
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
    
    // Proses Checkout Member
    Route::post('/checkout/e-product', [CheckoutController::class, 'purchaseEProduct']);
    
    // 🔥 TAMBAHAN RUTE SUBMIT ULASAN E-PRODUK 🔥
    Route::post('/e-products/{id}/reviews', [EProductController::class, 'submitReview']);
});

// =========================================================================
// SECTION 3A: RUTE MULTI-TENANT (Hanya Role: superadmin | organizer)
// =========================================================================
Route::middleware(['auth:sanctum', 'role:superadmin|organizer'])
    ->prefix('admin')
    ->group(function () {
    
    Route::get('/dashboard', [AdminDashboard::class, 'index']);

    // Kelola Event
    Route::get('/events', [AdminEvent::class, 'index']);
    Route::get('/events/{id}', [AdminEvent::class, 'show']);
    Route::post('/events', [AdminEvent::class, 'store']);
    Route::post('/events/{id}', [AdminEvent::class, 'update']); 
    Route::delete('/events/{id}', [AdminEvent::class, 'destroy']);

    Route::post('/materials', [AdminMaterial::class, 'store']);
    Route::delete('/materials/{id}', [AdminMaterial::class, 'destroy']);
    Route::post('/speakers', [AdminSpeaker::class, 'store']); 
    Route::delete('/speakers/{id}', [AdminSpeaker::class, 'destroy']);

    // Kelola Pendaftar & Transaksi
    Route::get('/registrations', [AdminReg::class, 'index']);
    Route::post('/registrations/{id}/verify', [AdminReg::class, 'verify']);
    Route::post('/registrations/{id}/reject', [AdminReg::class, 'reject']); 
    Route::post('/registrations/{id}/pending', [AdminReg::class, 'markAsPending']); 

    Route::get('/transactions', [AdminTransaction::class, 'index']);
    Route::get('/tickets', [AdminTicket::class, 'index']);
    Route::post('/tickets/scan', [AdminTicket::class, 'check']);
    Route::get('/reports', [AdminReport::class, 'index']);
    Route::get('/reports/export', [AdminReport::class, 'export']);

    // 🔥 CMS ARTIKEL DIPINDAHKAN KESINI AGAR ORGANIZER BISA NULIS 🔥
    Route::get('/article-categories', [AdminCategory::class, 'index']);
    Route::get('/articles', [AdminArticle::class, 'index']);
    Route::get('/articles/{id}', [AdminArticle::class, 'show']); 
    Route::post('/articles', [AdminArticle::class, 'store']);
    Route::post('/articles/{id}', [AdminArticle::class, 'update']); 
    Route::delete('/articles/{id}', [AdminArticle::class, 'destroy']);

    Route::get('/global-search', [AdminGlobalSearch::class, 'search']);
    Route::get('/notifications', [AdminNotification::class, 'index']);
    Route::post('/notifications/read', [AdminNotification::class, 'markAllAsRead']);
});

// =========================================================================
// SECTION 3B: RUTE EKSKLUSIF SUPERADMIN (Hanya Role: superadmin)
// =========================================================================
Route::middleware(['auth:sanctum', 'role:superadmin'])
    ->prefix('admin')
    ->group(function () {
    
    // 🔥 KELOLA USER (Di sinilah fitur Tambah Organizer & Edit berada)
    Route::get('/users', [AdminUser::class, 'index']);
    Route::post('/users', [AdminUser::class, 'store']); // Rute Create User
    Route::put('/users/{id}', [AdminUser::class, 'update']); // 🔥 INI SUDAH DIUBAH MENJADI PUT 🔥
    Route::post('/users/{id}/reset-password', [AdminUser::class, 'resetPassword']);
    Route::delete('/users/{id}', [AdminUser::class, 'destroy']);
    
    // Kelola Kategori Artikel tetap khusus Superadmin
    Route::post('/article-categories', [AdminCategory::class, 'store']);
    Route::put('/article-categories/{id}', [AdminCategory::class, 'update']);
    Route::delete('/article-categories/{id}', [AdminCategory::class, 'destroy']);

    // Kelola E-Produk (Katalog Digital)
    Route::get('/e-products', [AdminEProduct::class, 'index']);
    Route::get('/e-products/{id}', [AdminEProduct::class, 'show']);
    Route::post('/e-products', [AdminEProduct::class, 'store']);
    Route::post('/e-products/{id}', [AdminEProduct::class, 'update']); // Update pakai POST karena ada File
    Route::delete('/e-products/{id}', [AdminEProduct::class, 'destroy']);
});