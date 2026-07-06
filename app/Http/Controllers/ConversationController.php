<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        $conversations = Conversation::whereHas('participants', function ($query) use ($userId) {
            $query->where('user_id', $userId);
        })
        ->with(['participants', 'messages' => function ($query) {
            $query->latest()->limit(1);
        }])
        ->orderByDesc('updated_at')
        ->get();

        return response()->json($conversations, 200);
    }

    public function show(Request $request, int $id)
    {
        $conversation = Conversation::with(['participants', 'messages.sender'])
            ->where('id', $id)
            ->whereHas('participants', function ($query) use ($request) {
                $query->where('user_id', $request->user()->id);
            })
            ->firstOrFail();

        return response()->json($conversation, 200);
    }

    public function store(Request $request)
    {
        $request->validate([
            'other_user_id' => 'required|integer|exists:users,id',
        ]);

        $currentUserId = $request->user()->id;
        $otherUserId = $request->input('other_user_id');

        if ($otherUserId === $currentUserId) {
            return response()->json(['message' => 'Vous ne pouvez pas démarrer une conversation avec vous-même.'], 422);
        }

        $conversation = Conversation::whereHas('participants', function ($query) use ($currentUserId) {
            $query->where('user_id', $currentUserId);
        })->whereHas('participants', function ($query) use ($otherUserId) {
            $query->where('user_id', $otherUserId);
        })->first();

        if (!$conversation) {
            $conversation = Conversation::create(['created_by' => $currentUserId]);
            $conversation->participants()->attach([$currentUserId, $otherUserId]);
        }

        return response()->json($conversation->load('participants'), 200);
    }

    public function sendMessage(Request $request, int $id)
    {
        $conversation = Conversation::whereHas('participants', function ($query) use ($request, $id) {
            $query->where('conversation_id', $id)
                  ->where('user_id', $request->user()->id);
        })->firstOrFail();

        $request->validate([
            'body' => 'required|string',
        ]);

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $request->user()->id,
            'body' => $request->input('body'),
        ]);

        $conversation->touch();

        return response()->json($message->load('sender'), 201);
    }
}
