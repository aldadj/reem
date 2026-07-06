<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Video extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'video_path',
        'thumbnail_path',
        'visibility',
    ];

    // Une vidéo appartient à un utilisateur
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Une vidéo contient plusieurs commentaires
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    // Les likes associés à une vidéo
    public function likes(): HasMany
    {
        return $this->hasMany(Like::class);
    }

    // Les favoris associés à une vidéo
    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }
}
