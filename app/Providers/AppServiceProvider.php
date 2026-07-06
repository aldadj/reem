<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema; // 1. BIEN AJOUTER CETTE LIGNE

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 2. FORCE LA LONGUEUR PAR DÉFAUT DES CLÉS UNIQUES A 191 CARACTÈRES
        Schema::defaultStringLength(191);
    }
}