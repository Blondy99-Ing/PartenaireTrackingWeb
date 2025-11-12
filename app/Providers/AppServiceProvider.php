<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

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
         // Vérifiez si l'agence est authentifiée dans la session
        if (session()->has('agence')) {
            // Récupérer l'ID de l'agence stocké dans la session
            $agenceId = session('agence')->id;

            // Récupérer toutes les informations de l'agence dans la base de données
            $agence = Agence::find($agenceId);

            // Partager toutes les informations de l'agence dans toutes les vues
            View::share('agence', $agence);
        }
    }
}
