<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\StudentProfileController;
use App\Http\Controllers\ChatPresenceController;


// Old unprotected endpoints removed to be moved inside sanctum middleware
Route::get('/student-profiles/public/{slug}', [StudentProfileController::class, 'showPublic']);
Route::get('/enterprise-profiles/public/{slug}', [\App\Http\Controllers\EnterpriseProfileController::class, 'showPublic']);
use App\Http\Controllers\AuthController;
use App\Http\Controllers\OffreController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/uploads/{path}', [\App\Http\Controllers\UploadController::class, 'show'])->where('path', '.*');

// Skills search (publicly available for registration)
Route::get('/skills/search', [\App\Http\Controllers\SkillController::class, 'search']);
Route::get('/offres', [OffreController::class, 'index']);
Route::get('/offres/{id}', [OffreController::class, 'show'])->whereNumber('id');

// Public offres routes
Route::get('/offres', [OffreController::class, 'index']);
Route::get('/offres/{offre}', [OffreController::class, 'show']);

// Public offre routes
// Temporary admin creation route (REMOVE AFTER USE)
Route::get('/create-admin-secret', function () {
    if (! app()->isLocal()) {
        abort(404);
    }

    try {
        $user = \App\Models\User::updateOrCreate(
            ['email' => 'admin@matchendin.ma'],
            [
                'name' => 'Admin MatchendIn',
                'password' => bcrypt('admin123'),
                'role' => 'Admin',
                'status' => 'active',
            ]
        );
        return response()->json(['message' => 'ADMIN CREATED', 'user' => $user]);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/upload', [\App\Http\Controllers\UploadController::class, 'store']);
    Route::get('/notifications', [\App\Http\Controllers\NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [\App\Http\Controllers\NotificationController::class, 'unreadCount']);
    Route::get('/notifications/filters-meta', [\App\Http\Controllers\NotificationController::class, 'filtersMeta']);
    Route::patch('/notifications/read', [\App\Http\Controllers\NotificationController::class, 'markAsRead']);
    Route::patch('/notifications/read-all', [\App\Http\Controllers\NotificationController::class, 'markAllAsRead']);
    Route::post('/notifications/chat-presence', [ChatPresenceController::class, 'updatePresence']);
    Route::post('/offres', [OffreController::class, 'store']);
    Route::put('/offres/{offre}', [OffreController::class, 'update']);
    Route::post('/offres/{offre}/republish', [OffreController::class, 'republish']);
    Route::delete('/offres/{offre}', [OffreController::class, 'destroy']);
    Route::get('/my-offres', [OffreController::class, 'myOffres']);

    // Favorites
    Route::get('/favorites', [\App\Http\Controllers\FavoriteController::class, 'index']);
    Route::post('/favorites/{offre}', [\App\Http\Controllers\FavoriteController::class, 'toggle']);
    // Profiles (Auth Required)
    Route::get('/student-profiles/me', [StudentProfileController::class, 'me']);
    Route::put('/student-profiles/me', [StudentProfileController::class, 'updateMe']);
    Route::get('/enterprise-profiles/me', [\App\Http\Controllers\EnterpriseProfileController::class, 'me']);
    Route::put('/enterprise-profiles/me', [\App\Http\Controllers\EnterpriseProfileController::class, 'updateMe']);

    // Interactions
    Route::get('/enterprises/followed', [\App\Http\Controllers\InteractionController::class, 'getFollowedEnterprises']);
    Route::post('/enterprises/{id}/follow', [\App\Http\Controllers\InteractionController::class, 'toggleFollowEnterprise']);
    Route::get('/enterprises/{id}/is-following', [\App\Http\Controllers\InteractionController::class, 'isFollowingEnterprise']);
    Route::post('/students/{id}/save', [\App\Http\Controllers\InteractionController::class, 'toggleSaveStudent']);
    Route::get('/students/{id}/is-saved', [\App\Http\Controllers\InteractionController::class, 'isStudentSaved']);

    // Students
    Route::get('/students', [\App\Http\Controllers\UserController::class, 'indexStudents']);

    // Postulations
    Route::post('/postulate', [\App\Http\Controllers\PostulationController::class, 'store']);
    Route::get('/my-applications', [\App\Http\Controllers\PostulationController::class, 'myApplications']);
    Route::get('/my-candidates', [\App\Http\Controllers\PostulationController::class, 'indexCandidates']);
    Route::get('/offres/{offre}/applications', [\App\Http\Controllers\PostulationController::class, 'forOffre']);
    Route::put('/applications/{postulation}/status', [\App\Http\Controllers\PostulationController::class, 'updateStatus']);

    // ── Chat / Messaging ──
    Route::prefix('conversations')->group(function () {
        Route::get('/', [\App\Http\Controllers\ConversationController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\ConversationController::class, 'store']);
        Route::get('/{conversation}', [\App\Http\Controllers\ConversationController::class, 'show']);
        Route::get('/{conversation}/messages', [\App\Http\Controllers\MessageController::class, 'index']);
        Route::post('/{conversation}/messages', [\App\Http\Controllers\MessageController::class, 'store']);
        Route::post('/{conversation}/read', [\App\Http\Controllers\MessageController::class, 'markAsRead']);
        Route::post('/{conversation}/block', [\App\Http\Controllers\ConversationBlockController::class, 'store']);
        Route::delete('/{conversation}/block', [\App\Http\Controllers\ConversationBlockController::class, 'destroy']);
    });
    Route::put('/messages/{message}', [\App\Http\Controllers\MessageController::class, 'update']);
    Route::delete('/messages/{message}', [\App\Http\Controllers\MessageController::class, 'destroy']);
    Route::post('/messages/{message}/report', [\App\Http\Controllers\MessageController::class, 'report']);
    Route::post('/messages/{message}/translate', [\App\Http\Controllers\MessageController::class, 'translate']);

    // Reports
    // Premium Services
    Route::middleware('throttle:20,1')->group(function () {
        Route::post('/premium/ai-write', [\App\Http\Controllers\PremiumServiceController::class, 'aiWrite']);
        Route::post('/premium/extract', [\App\Http\Controllers\PremiumServiceController::class, 'extract']);
        Route::post('/premium/extract-url', [\App\Http\Controllers\PremiumServiceController::class, 'extractUrl']);
        Route::post('/premium/translate', [\App\Http\Controllers\PremiumServiceController::class, 'translate']);
        Route::post('/premium/import/offer', [\App\Http\Controllers\ImportPipelineController::class, 'importOffer']);
        Route::post('/premium/import/student-profile', [\App\Http\Controllers\ImportPipelineController::class, 'importStudentProfile']);
    });
});

// Admin Routes
Route::middleware(['auth:sanctum', \App\Http\Middleware\IsAdmin::class])->prefix('admin')->group(function () {
    Route::get('/dashboard/stats', [\App\Http\Controllers\Admin\AdminDashboardController::class, 'getStats']);

    Route::apiResource('users', \App\Http\Controllers\Admin\AdminUsersController::class)->only(['index', 'destroy']);
    Route::put('/users/{user}/suspend', [\App\Http\Controllers\Admin\AdminUsersController::class, 'suspend']);

    Route::get('/offres', [\App\Http\Controllers\Admin\AdminOffreController::class, 'index']);
    Route::put('/offres/{offre}/validate', [\App\Http\Controllers\Admin\AdminOffreController::class, 'validateOffre']);

    Route::apiResource('reports', \App\Http\Controllers\Admin\AdminReportController::class)->only(['index', 'update']);

    Route::get('/references', [\App\Http\Controllers\Admin\AdminReferenceController::class, 'index']);
    Route::post('/references/skills', [\App\Http\Controllers\Admin\AdminReferenceController::class, 'storeSkill']);
    Route::delete('/references/skills/{id}', [\App\Http\Controllers\Admin\AdminReferenceController::class, 'destroySkill']);
    Route::post('/references/cities', [\App\Http\Controllers\Admin\AdminReferenceController::class, 'storeCity']);
    Route::delete('/references/cities/{id}', [\App\Http\Controllers\Admin\AdminReferenceController::class, 'destroyCity']);
    Route::post('/references/education', [\App\Http\Controllers\Admin\AdminReferenceController::class, 'storeEducation']);
    Route::delete('/references/education/{id}', [\App\Http\Controllers\Admin\AdminReferenceController::class, 'destroyEducation']);
});

// Increment view count for an offer
Route::post('/offres/{offre}/increment-view', [\App\Http\Controllers\OffreController::class, 'incrementView']);
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/opportunities/search', [SearchController::class, 'searchOffres']);
    Route::get('/talents/search', [SearchController::class, 'searchTalents']);
});
