<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\VideoController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\NotificationController;
use Illuminate\Http\Request; // Importation manquante corrigée au passage
use Illuminate\Support\Facades\Route;
use Cloudinary\Cloudinary;

//cloudinary temporaire
Route::get('/test-cloudinary', function () {
    try {
        $cloudinary = new Cloudinary();

        $result = $cloudinary->uploadApi()->upload(
            'https://res.cloudinary.com/demo/image/upload/sample.jpg'
        );

        return response()->json([
            'success' => true,
            'secure_url' => $result['secure_url'] ?? null,
            'resource_type' => $result['resource_type'] ?? null,
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
        ], 500);
    }
});

// --- ROUTES PUBLIQUES ---
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/google-login', [AuthController::class, 'googleLogin']);

Route::get('/videos', [VideoController::class, 'index']); // Flux vidéo
Route::get('/videos/{id}/comments', [CommentController::class, 'index']);
Route::get('/users/{id}', [\App\Http\Controllers\Api\UserController::class, 'show']);
Route::get('/users/{id}/videos', [VideoController::class, 'userVideosById']);
Route::get('/test-video-storage', [VideoController::class, 'testVideoStorage']);

// --- ROUTES PROTÉGÉES (Sanctum) ---
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        $user = $request->user();
        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'profile_photo_path' => $user->profile_photo_path,
            'videos_count' => $user->videos()->count(),
            'followers_count' => $user->followers()->count(),
            'following_count' => $user->following()->count(),
            'total_likes_count' => $user->videos()->withCount('likes')->get()->sum('likes_count'),
        ]);
    });

    Route::post('/user/update-photo', [AuthController::class, 'updateProfile']);
    Route::patch('/user/profile', [AuthController::class, 'updateProfileInfo']);
    Route::delete('/user/photo', [AuthController::class, 'deleteProfilePhoto']);

    Route::post('/videos', [VideoController::class, 'store']); // Upload sécurisé
    Route::get('/videos/friends', [VideoController::class, 'friends']);
    Route::get('/user/videos', [VideoController::class, 'userVideos']); // Vidéos de l'utilisateur
    Route::get('/user/liked-videos', [VideoController::class, 'userLikedVideos']); // Vidéos aimées de l'utilisateur

    // Interactions sociales
    Route::post('/videos/{id}/like', [VideoController::class, 'toggleLike']); 
    Route::post('/videos/{id}/favorite', [VideoController::class, 'toggleFavorite']);
    Route::post('/videos/{id}/comments', [CommentController::class, 'store']);
    Route::delete('/videos/{id}', [VideoController::class, 'destroy']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);

    Route::post('/users/{id}/follow', [\App\Http\Controllers\Api\UserController::class, 'toggleFollow']);

    Route::get('/conversations', [ConversationController::class, 'index']);
    Route::get('/conversations/{id}', [ConversationController::class, 'show']);
    Route::post('/conversations', [ConversationController::class, 'store']);
    Route::post('/conversations/{id}/messages', [ConversationController::class, 'sendMessage']);
});