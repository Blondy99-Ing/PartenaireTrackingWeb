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