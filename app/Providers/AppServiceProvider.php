<?php

namespace App\Providers;

use App\Models\Alert;
use App\Models\AssociationUserVoiture;
use App\Models\Voiture;
use App\Observers\AlertObserver;
use App\Observers\AssociationUserVoitureObserver;
use App\Observers\VoitureObserver;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // ── Observers ──────────────────────────────────────────────────
        Voiture::observe(VoitureObserver::class);
        Alert::observe(AlertObserver::class);
        AssociationUserVoiture::observe(AssociationUserVoitureObserver::class);
    }
}