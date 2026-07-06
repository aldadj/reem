<?php

namespace Tests\Feature;

use App\Services\VideoThumbnailService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VideoThumbnailGenerationTest extends TestCase
{
    public function test_it_generates_a_thumbnail_from_uploaded_video_when_ffmpeg_is_available(): void
    {
        Storage::fake('public');

        $ffmpegScript = tempnam(sys_get_temp_dir(), 'ffmpeg') . '.bat';
        file_put_contents($ffmpegScript, "@echo off\r\nset output=%~dp0thumbnail.jpg\r\nfor %%I in (%*) do (\r\n  if /I \"%%~I\"==\"-i\" set \"skipNext=1\"\r\n  if defined skipNext (\r\n    set \"output=%%~I\"\r\n    set \"skipNext=\"\r\n  )\r\n)\r\necho dummy > \"%output%\"\r\n");

        putenv('FFMPEG_PATH=' . $ffmpegScript);

        $uploadedFile = UploadedFile::fake()->create('clip.mp4', 1024);
        $service = new VideoThumbnailService();
        $relativePath = $service->generateFromUploadedFile($uploadedFile);

        $this->assertNotNull($relativePath);
        $this->assertStringContainsString('thumbnails', $relativePath);
        $this->assertTrue(Storage::disk('public')->exists($relativePath));
    }
}
