<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Video;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function show(Request $request, int $id)
    {
        $user = User::findOrFail($id);
        $currentUser = $request->user();
        $isFollowing = false;

        if ($currentUser) {
            $isFollowing = $currentUser->following()->where('following_id', $user->id)->exists();
        }

        $totalLikesCount = Video::where('user_id', $user->id)
            ->withCount('likes')
            ->get()
            ->sum('likes_count');

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'profile_photo_path' => $user->profile_photo_path,
            'videos_count' => $user->videos()->count(),
            'followers_count' => $user->followers()->count(),
            'following_count' => $user->following()->count(),
            'total_likes_count' => $totalLikesCount,
            'is_following' => $isFollowing,
        ], 200);
    }

    public function toggleFollow(Request $request, int $id)
    {
        $user = $request->user();
        $target = User::findOrFail($id);

        if ($user->id === $target->id) {
            return response()->json(['message' => 'Vous ne pouvez pas vous abonner à votre propre compte.'], 400);
        }

        $isFollowing = $user->following()->where('following_id', $target->id)->exists();

        if ($isFollowing) {
            $user->following()->detach($target->id);
            $isFollowing = false;
        } else {
            $user->following()->attach($target->id);
            $isFollowing = true;
        }

        return response()->json([
            'following' => $isFollowing,
            'followers_count' => $target->followers()->count(),
        ], 200);
    }
}
