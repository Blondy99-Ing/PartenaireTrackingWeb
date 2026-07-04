<?php

namespace App\Providers;

use App\Models\Alert;
use App\Models\AssociationUserVoiture;
use App\Models\Voiture;
use App\Enums\PartnerPermission;
use App\Models\User;
use App\Observers\AlertObserver;
use App\Observers\AssociationUserVoitureObserver;
use App\Observers\VoitureObserver;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
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

        // ── Permission gates ───────────────────────────────────────────
        // The main partner (no partner_id) implicitly passes every gate;
        // staff are checked against their granted permissions.
        Gate::before(function (User $user) {
            return is_null($user->partner_id) ? true : null;
        });

        foreach (PartnerPermission::cases() as $permission) {
            Gate::define($permission->value, function (User $user) use ($permission) {
                return $user->hasPermission($permission);
            });
        }

        /**
         * Accès au dashboard : le tableau de bord héberge plusieurs modules
         * (flotte, trajets, alertes). Un staff n'ayant que la permission trajets
         * OU alertes doit pouvoir ouvrir le dashboard (il n'y verra que son
         * module). On autorise donc l'accès dès qu'une de ces permissions est
         * présente ; le filtrage fin des onglets se fait dans la vue.
         */
        Gate::define('dashboard.access', function (User $user) {
            foreach (['dashboard.view', 'tracking.view', 'trajets.view', 'alerts.view'] as $perm) {
                if ($user->hasPermission($perm)) {
                    return true;
                }
            }

            return false;
        });

        // Reserved: managing staff & their permissions is never delegable.
        // Gate::before lets the main partner through; staff fall here → denied.
        Gate::define('manage-staff', fn (User $user) => false);
    }
}