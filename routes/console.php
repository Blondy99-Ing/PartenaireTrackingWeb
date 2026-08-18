<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

/*
|--------------------------------------------------------------------------
| Dashboard
|--------------------------------------------------------------------------
*/
Schedule::command('dashboard:refresh-offline-statuses')
    ->everyThirtySeconds()
    ->withoutOverlapping();

/*
|--------------------------------------------------------------------------
| Lease cutoff - planification
|--------------------------------------------------------------------------
| Détecte les leases NON_PAYE concernés par une règle active
| et alimente la table lease_cutoff_queue + lease_cutoff_histories.
*/
Schedule::command('lease:cutoff:plan')
    ->everyMinute()
    ->withoutOverlapping();

/*
|--------------------------------------------------------------------------
| Lease cutoff - traitement de la queue
|--------------------------------------------------------------------------
| Vérifie les véhicules à couper :
| - si en mouvement => attente
| - si à l’arrêt => envoi de la commande de coupure
*/
Schedule::command('lease:cutoff:process')
    ->everyMinute()
    /**
     * Expiration explicite à 5 min (au lieu des 24h par défaut de Laravel) :
     * si un passage plante sans libérer son verrou (crash, kill -9, exception
     * fatale hors du try/catch), les passages suivants ne restent bloqués que
     * 5 min max, pas une journée entière.
     */
    ->withoutOverlapping(5);

// Confirmation des commandes moteur manuelles (coupure_moteur) sur l'état
// moteur réel — même principe que lease:cutoff:process, pour le manuel.
Schedule::command('engine:confirm-manual-commands')
    ->everyMinute()
    ->withoutOverlapping();

/*
|--------------------------------------------------------------------------
| GPS - cache des statuts online
|--------------------------------------------------------------------------
| Rafraîchit hors requête la carte mac => online/mouvement (device-list 18gps).
| getDeviceList est lent (~84 Ko, >20 s parfois) : la page de coupure manuelle
| lit uniquement ce cache et répond donc instantanément (fini les « N/A »).
*/
Schedule::command('gps:refresh-online-map')
    ->everyMinute()
    ->withoutOverlapping();