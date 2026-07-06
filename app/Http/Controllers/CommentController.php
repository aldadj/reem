<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Video;
use App\Models\UserNotification;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function index(int $videoId)
    {
        $comments = Comment::where('video_id', $videoId)
            ->with('user')
            ->latest()
            ->get();
        return response()->json($comments);
    }

    public function store(Request $request, int $videoId)
    {
        $request->validate(['comment' => 'required|string']);
        $comment = Comment::create([
            'user_id' => $request->user()->id,
            'video_id' => $videoId,
            'comment' => $request->comment,
        ]);

        $video = Video::find($videoId);
        if ($video && $video->user_id !== $request->user()->id) {
            UserNotification::create([
                'user_id' => $video->user_id,
                'actor_id' => $request->user()->id,
                'type' => 'comment',
                'subject_type' => 'video',
                'subject_id' => $video->id,
                'message' => "{$request->user()->name} a commenté votre vidéo.",
                'data' => json_encode(['video_id' => $video->id, 'comment_id' => $comment->id]),
            ]);
        }

        return response()->json($comment->load('user'), 201);
    }
}