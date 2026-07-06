<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Video;
use Tests\TestCase;

class VideoFriendsFeedTest extends TestCase
{
    public function test_it_returns_only_videos_from_followed_users_for_friends_feed(): void
    {
        $user = User::factory()->create();
        $friend = User::factory()->create();
        $stranger = User::factory()->create();

        $user->following()->attach($friend->id);

        $friendVideo = Video::create([
            'user_id' => $friend->id,
            'title' => 'Ami video',
            'description' => 'Video d ami',
            'video_path' => '/storage/videos/friend.mp4',
            'thumbnail_path' => '/storage/thumbnails/friend.jpg',
            'visibility' => 'public',
        ]);

        Video::create([
            'user_id' => $stranger->id,
            'title' => 'Stranger video',
            'description' => 'Video d un inconnu',
            'video_path' => '/storage/videos/stranger.mp4',
            'thumbnail_path' => '/storage/thumbnails/stranger.jpg',
            'visibility' => 'public',
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/videos/friends');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $friendVideo->id);
    }
}
