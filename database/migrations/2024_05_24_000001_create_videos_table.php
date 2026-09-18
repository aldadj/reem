<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
{
    Schema::create('videos', function (Blueprint $table) {
        $table->id();
        // Relie la vidéo à un utilisateur. Si l'utilisateur supprime son compte, ses vidéos sont supprimées (on-delete cascade).
        $table->foreignId('user_id')->constrained()->onDelete('cascade');
        $table->string('title');
        $table->text('description')->nullable();
        $table->string('video_path'); // Stockera le chemin du fichier sur le serveur
        $table->string('thumbnail_path')->nullable(); // Pour l'image de couverture (miniature)
        $table->integer('views_count')->default(0);
        $table->string('visibility')->default('public'); // public, friends, private
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('videos');
    }
};
