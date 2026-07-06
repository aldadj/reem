<?php

namespace App\Http\Controllers;

use App\Models\Video;
use App\Models\UserNotification;
use Illuminate\Http\Request;
use App\Services\PointService;
use App\Services\VideoThumbnailService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class VideoController extends Controller
{
    // Récupérer le flux de vidéos
    public function index(Request $request)
    {
        $query = Video::with('user')
            ->withCount(['likes', 'comments'])
            ->inRandomOrder();

        if ($request->user()) {
            $query->withExists(['likes as liked_by_user' => function ($query) use ($request) {
                $query->where('user_id', $request->user()->id);
            }]);
            $query->withExists(['favorites as favorited_by_user' => function ($query) use ($request) {
                $query->where('user_id', $request->user()->id);
            }]);
        }

        $videos = $query->paginate(5);

        return response()->json($videos, 200);
    }

    public function friends(Request $request)
    {
        if (!$request->user()) {
            return response()->json(['message' => 'Non authentifié'], 401);
        }

        $followedIds = $request->user()->following()->pluck('users.id');

        $query = Video::with('user')
            ->withCount(['likes', 'comments'])
            ->whereIn('user_id', $followedIds)
            ->where(function ($query) {
                $query->where('visibility', 'public')->orWhere('visibility', 'friends');
            })
            ->orderByDesc('created_at');

        $query->withExists(['likes as liked_by_user' => function ($query) use ($request) {
            $query->where('user_id', $request->user()->id);
        }]);
        $query->withExists(['favorites as favorited_by_user' => function ($query) use ($request) {
            $query->where('user_id', $request->user()->id);
        }]);

        $videos = $query->paginate(10);

        return response()->json($videos, 200);
    }

    // Upload d'une vidéo
    public function store(Request $request)
    {
        try {
            if (!$request->user()) {
                return response()->json(['message' => 'Connexion requise pour publier une vidéo.'], 401);
            }

            $validatedData = $request->validate([
                'video' => 'required|file|mimes:mp4,mov,avi,mkv|mimetypes:video/mp4,video/quicktime,video/x-msvideo,video/x-matroska,application/octet-stream|max:102400',
                'thumbnail' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
                'title' => 'nullable|string|max:255',
                'description' => 'nullable|string|max:2000',
                'caption' => 'nullable|string|max:2000',
                'visibility' => 'nullable|string|in:public,friends,private',
            ]);

            $caption = $request->input('caption', $request->input('description', $request->input('title')));
            $title = $request->input('title');
            $description = $request->input('description');
            $visibility = $request->input('visibility', 'public');

            if ($request->hasFile('video') && $request->file('video')->isValid()) {
                $path = $request->file('video')->store('videos', 'public');
                $thumbnailPath = null;

                if ($request->file('thumbnail')) {
                    $thumbnailPath = $request->file('thumbnail')->store('thumbnails', 'public');
                } else {
                    $generatedThumbnail = app(VideoThumbnailService::class)->generateFromUploadedFile($request->file('video'));
                    if ($generatedThumbnail) {
                        $thumbnailPath = $generatedThumbnail;
                    }
                }

                $videoTitle = $title ?: $caption ?: ($validatedData['description'] ?? 'Vidéo sans titre');
                $videoDescription = $description ?: $caption ?: ($validatedData['description'] ?? null);

                $video = Video::create([
                    'user_id' => $request->user()->id,
                    'title' => substr($videoTitle, 0, 255),
                    'description' => $videoDescription,
                    'video_path' => Storage::url($path),
                    'thumbnail_path' => $thumbnailPath ? Storage::url($thumbnailPath) : null,
                    'visibility' => $visibility,
                ]);

                try {
                    app(PointService::class)->addPoints($request->user(), PointService::ACTION_PUBLISH, "Publication de : " . ($video->title ?: 'nouvelle vidéo'));
                } catch (\Throwable $e) {
                    Log::warning('Video publish points failed: ' . $e->getMessage());
                }

                return response()->json([
                    'message' => 'Vidéo publiée avec succès !',
                    'video' => $video
                ], 201);
            }

            return response()->json(['message' => 'Fichier vidéo introuvable'], 400);
        } catch (ValidationException $e) {
            // Spécifiquement pour les erreurs de validation, pour un message clair.
            return response()->json([
                'message' => 'Échec de la publication : ' . $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            // Pour toutes les autres erreurs (ex: FFmpeg, disque plein, etc.)
            Log::error('Échec de la publication de la vidéo.', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Une erreur inattendue est survenue lors de la publication de la vidéo.',
            ], 500);
        }
    }

    public function toggleLike(Request $request, int $id)
    {
        $video = Video::findOrFail($id);
        $user = $request->user();

        $existingLike = $video->likes()->where('user_id', $user->id)->first();

        if ($existingLike) {
            $existingLike->delete();
            $liked = false;
            UserNotification::where('user_id', $video->user_id)
                ->where('actor_id', $user->id)
                ->where('type', 'like')
                ->where('subject_type', 'video')
                ->where('subject_id', $video->id)
                ->delete();
        } else {
            $video->likes()->create([
                'user_id' => $user->id,
            ]);
            $liked = true;

            if ($video->user_id !== $user->id) {
                UserNotification::create([
                    'user_id' => $video->user_id,
                    'actor_id' => $user->id,
                    'type' => 'like',
                    'subject_type' => 'video',
                    'subject_id' => $video->id,
                    'message' => "{$user->name} a aimé votre vidéo.",
                    'data' => json_encode(['video_id' => $video->id]),
                ]);

                // Attribution de points au créateur pour le like reçu
                app(PointService::class)->addPoints(
                    $video->user, 
                    PointService::ACTION_LIKE_RECEIVED, 
                    "Interaction reçue sur : {$video->title}"
                );
            }
        }

        return response()->json([
            'liked' => $liked,
            'likes_count' => $video->likes()->count(),
        ], 200);
    }

    public function toggleFavorite(Request $request, int $id)
    {
        $video = Video::findOrFail($id);
        $user = $request->user();

        $existingFavorite = $video->favorites()->where('user_id', $user->id)->first();

        if ($existingFavorite) {
            $existingFavorite->delete();
            $favorited = false;
            UserNotification::where('user_id', $video->user_id)
                ->where('actor_id', $user->id)
                ->where('type', 'favorite')
                ->where('subject_type', 'video')
                ->where('subject_id', $video->id)
                ->delete();
        } else {
            $video->favorites()->create([
                'user_id' => $user->id,
            ]);
            $favorited = true;

            if ($video->user_id !== $user->id) {
                UserNotification::create([
                    'user_id' => $video->user_id,
                    'actor_id' => $user->id,
                    'type' => 'favorite',
                    'subject_type' => 'video',
                    'subject_id' => $video->id,
                    'message' => "{$user->name} a ajouté votre vidéo aux favoris.",
                    'data' => json_encode(['video_id' => $video->id]),
                ]);
            }
        }

        return response()->json([
            'favorited' => $favorited,
        ], 200);
    }

    public function userVideos(Request $request)
    {
        $query = Video::withCount(['likes', 'comments'])
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at');

        if ($request->user()) {
            $query->withExists(['likes as liked_by_user' => function ($query) use ($request) {
                $query->where('user_id', $request->user()->id);
            }]);
            $query->withExists(['favorites as favorited_by_user' => function ($query) use ($request) {
                $query->where('user_id', $request->user()->id);
            }]);
        }

        $videos = $query->get();

        return response()->json($videos, 200);
    }

    public function userLikedVideos(Request $request)
    {
        $query = Video::withCount(['likes', 'comments'])
            ->whereHas('likes', function ($query) use ($request) {
                $query->where('user_id', $request->user()->id);
            })
            ->orderByDesc('created_at');

        if ($request->user()) {
            $query->withExists(['likes as liked_by_user' => function ($query) use ($request) {
                $query->where('user_id', $request->user()->id);
            }]);
            $query->withExists(['favorites as favorited_by_user' => function ($query) use ($request) {
                $query->where('user_id', $request->user()->id);
            }]);
        }

        $videos = $query->get();

        return response()->json($videos, 200);
    }

    public function userVideosById(Request $request, int $id)
    {
        $query = Video::withCount(['likes', 'comments'])
            ->where('user_id', $id)
            ->orderByDesc('created_at');

        if ($request->user()) {
            $query->withExists(['likes as liked_by_user' => function ($query) use ($request) {
                $query->where('user_id', $request->user()->id);
            }]);
            $query->withExists(['favorites as favorited_by_user' => function ($query) use ($request) {
                $query->where('user_id', $request->user()->id);
            }]);
        }

        $videos = $query->get();

        return response()->json($videos, 200);
    }

//supression de video
    public function destroy(Request $request, int $id)
    {
        $video = Video::findOrFail($id);

        if ($request->user()->id !== $video->user_id) {
            return response()->json(['message' => 'Non autorisé'], 403);
        }

        if ($video->video_path) {
            $videoPath = $video->video_path;
            if (str_starts_with($videoPath, '/storage/')) {
                $videoPath = substr($videoPath, 9);
            } elseif (str_contains($videoPath, '/storage/')) {
                $videoPath = explode('/storage/', $videoPath, 2)[1];
            }
            Storage::disk('public')->delete($videoPath);
        }

        if ($video->thumbnail_path) {
            $thumbnailPath = $video->thumbnail_path;
            if (str_starts_with($thumbnailPath, '/storage/')) {
                $thumbnailPath = substr($thumbnailPath, 9);
            } elseif (str_contains($thumbnailPath, '/storage/')) {
                $thumbnailPath = explode('/storage/', $thumbnailPath, 2)[1];
            }
            Storage::disk('public')->delete($thumbnailPath);
        }

        $video->delete();

        return response()->json(['message' => 'Vidéo supprimée avec succès'], 200);
    }
}