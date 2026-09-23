<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Str;

/**
 * Centralise la construction, la lecture et la suppression des médias
 * (vidéos, miniatures, photos de profil) stockés sur le disque « public ».
 */
class PublicMedia
{
    /**
     * Disque utilisé pour stocker et servir les médias.
     */
    public const DISK = 'public';

    /**
     * Préfixe public du disque, également exposé par le lien public/storage.
     */
    private const PUBLIC_PREFIX = '/storage/';

    /**
     * URL publique d'un chemin relatif du disque.
     */
    public static function url(string $path): string
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk(self::DISK);
    
        return $disk->url($path);
    }

    /**
     * Extrait le chemin relatif au disque à partir de ce qui a été persisté
     * en base : une URL absolue, une URL relative ou déjà un chemin relatif.
     */
    public static function relativePath(?string $stored): ?string
    {
        if ($stored === null) {
            return null;
        }

        $value = trim(str_replace('\\', '/', $stored));

        if ($value === '') {
            return null;
        }

        if (Str::contains($value, self::PUBLIC_PREFIX)) {
            $value = Str::after($value, self::PUBLIC_PREFIX);
        } elseif (Str::startsWith($value, 'storage/')) {
            $value = Str::after($value, 'storage/');
        }

        $relative = ltrim($value, '/');

        return $relative === '' ? null : $relative;
    }

    /**
     * Supprime du disque le média référencé en base, s'il existe.
     */
    public static function delete(?string $stored): void
    {
        $relative = self::relativePath($stored);

        if ($relative === null) {
            return;
        }

        Storage::disk(self::DISK)->delete($relative);
    }
}