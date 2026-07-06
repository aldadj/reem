<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class VideoThumbnailService
{
    public function generateFromUploadedFile(?UploadedFile $uploadedFile, string $disk = 'public'): ?string
    {
        if (!$uploadedFile || !$uploadedFile->isValid()) {
            return null;
        }

        $videoPath = $uploadedFile->getRealPath();
        if (!is_file($videoPath)) {
            return null;
        }

        return $this->generate($videoPath, $disk);
    }

    public function generate(string $videoPath, string $disk = 'public'): ?string
    {
        if (!is_file($videoPath)) {
            return null;
        }

        $ffmpegBinary = env('FFMPEG_PATH', 'ffmpeg');
        $outputPath = tempnam(sys_get_temp_dir(), 'thumb');
        if ($outputPath === false) {
            return null;
        }

        unlink($outputPath);
        $outputPath .= '.jpg';

        $command = sprintf(
            // La police de caractères est en commentaire pour éviter les erreurs sur des systèmes non-Windows.
            // Commande FFmpeg simplifiée pour une meilleure compatibilité.
            '%s -y -hwaccel auto -ss 00:00:01 -i %s -frames:v 1 -vf "scale=720:1280:force_original_aspect_ratio=decrease,pad=720:1280:(ow-iw)/2:(oh-ih)/2,format=yuv420p" %s 2>&1',
            escapeshellarg($ffmpegBinary),
            escapeshellarg($videoPath),
            escapeshellarg($outputPath)
        );

        exec($command, $output, $exitCode);
        if ($exitCode !== 0 || !is_file($outputPath)) {
            Log::error('Échec de la génération de la miniature FFmpeg.', [
                'exit_code' => $exitCode,
                'output' => $output,
                'command' => $command,
            ]);
            $placeholderPath = $this->createPlaceholderThumbnail($disk);
            return $placeholderPath;
        }

        $relativePath = 'thumbnails/' . basename($outputPath);
        Storage::disk($disk)->put($relativePath, file_get_contents($outputPath));
        @unlink($outputPath);

        return $relativePath;
    }

    protected function createPlaceholderThumbnail(string $disk): ?string
    {
        $placeholderData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAUAAAAFCAYAAAAfFcSJAAAACklEQVR4nGMAAIAAeIhvAAAAAElFTkSuQmCC');
        if ($placeholderData === false) {
            return null;
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'thumb-placeholder');
        if ($tempPath === false) {
            return null;
        }
        unlink($tempPath);
        $tempPath .= '.jpg';
        file_put_contents($tempPath, $placeholderData);

        $relativePath = 'thumbnails/' . basename($tempPath);
        Storage::disk($disk)->put($relativePath, file_get_contents($tempPath));
        @unlink($tempPath);

        return $relativePath;
    }
}
