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
    /**
     * Même expiration que lease:cutoff:process ci-dessous, pour les mêmes
     * raisons : assez longue pour qu'un passage lent ne soit jamais doublé,
     * assez courte pour ne pas bloquer la planification une journée entière
     * après un plantage brutal (le défaut de Laravel est de 24 h).
     */
    ->withoutOverlapping(30);

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
     * L'expiration doit rester TRÈS supérieure à la durée réelle d'un passage.
     *
     * Anomalie corrigée : elle était fixée à 5 min pour qu'un passage planté
     * (crash, kill -9, exception fatale hors du try/catch) ne bloque pas les
     * suivants une journée entière. Mais le 21/08/2026, la lenteur de
     * LeaseApiClientService::fetchLeaseById() (corrigée depuis, commit 27b3044)
     * faisait durer chaque passage 5 à 6 min : le verrou expirait AVANT la fin
     * du passage qu'il protégeait, et le passage suivant démarrait pendant que
     * le précédent tournait encore. Les deux chargeaient les mêmes lignes de
     * queue puis agissaient chacun sur sa copie devenue périmée — d'où des
     * commandes de coupure envoyées en double, et des véhicules pardonnés
     * recoupés parce qu'un passage retardataire écrasait le pardon.
     *
     * 30 min laisse une marge très large (un passage dure aujourd'hui moins
     * d'une minute) tout en gardant un filet de sécurité raisonnable en cas de
     * plantage brutal. Si la durée d'un passage devait un jour s'en approcher,
     * la trace de durée écrite en fin de traitement
     * (LeaseCutoffQueueProcessorService::process) le signalerait avant que le
     * scénario ne redevienne possible.
     */
    ->withoutOverlapping(30);

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

/*
|--------------------------------------------------------------------------
| GPS - état live de toute la flotte
|--------------------------------------------------------------------------
| Remonte position + connectivité de TOUS les boîtiers en un seul appel
| (getDeviceListByCustomId) et les injecte dans le cache du tableau de bord.
|
| Pourquoi : l'état affiché était déduit du dernier point enregistré dans
| `locations`. Or un véhicule à l'arrêt cesse d'émettre des points tout en
| continuant son battement — sa dernière position peut donc dater de plusieurs
| heures alors que le boîtier va très bien. L'écran ne distinguait pas « garé,
| tout va bien » de « boîtier muet », et il fallait cliquer sur chaque véhicule
| pour le savoir. Mesuré sur la flotte réelle : 72 véhicules affichés « jamais
| localisé » et 87 « hors ligne » alors que 103 étaient en réalité connectés,
| dont 34 en mouvement.
|
| Cette commande n'écrit PAS dans `locations` : l'ingestion Node en reste seule
| responsable, car elle seule fournit la trace continue dont dépendent les
| trajets, les alertes et les coupures sur géorepère.
|
| Verrou de 10 minutes, choisi entre deux écueils opposés :
|  - trop court, il expire pendant que le passage tourne encore et un second
|    démarre en parallèle — c'est ce qui, le 21/08/2026, a fait couper six
|    véhicules pourtant pardonnés (verrou à 5 min, passages de 6 min) ;
|  - sans limite (le défaut de Laravel est de 24 h), un processus tué en plein
|    passage laisse le verrou posé et la tâche ne repart jamais de la journée.
|    Ce serveur tue régulièrement des processus faute de mémoire : le risque
|    est réel, pas théorique.
| 10 minutes valent ~20 fois le pire passage observé (31 s), tout en garantissant
| une reprise rapide après un arrêt brutal.
*/
Schedule::command('gps:refresh-live-fleet')
    ->everyMinute()
    ->withoutOverlapping(10);