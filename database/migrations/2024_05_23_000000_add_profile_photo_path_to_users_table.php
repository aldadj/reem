<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // La colonne profile_photo_path existe déjà dans create_users_table.php
    }

    public function down(): void
    {
        // Rien à supprimer
    }
};