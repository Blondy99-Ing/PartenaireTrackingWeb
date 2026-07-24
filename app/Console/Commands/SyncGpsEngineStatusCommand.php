<?php

namespace App\Console\Commands;

use App\Services\GpsControlService;
use Illuminate\Console\Command;

/**
 * Rafraîchit, hors requête web, l'état moteur/position réel de toute la
 * flotte directement depuis 18gps (table `locations`).
 *
 * Pourquoi cette commande existe :
 * la page de coupure manuelle affichait l'état moteur tel que reçu au
 * dernier heartbeat spontané du boîtier, qui peut mettre du temps à arriver
 * après une commande confirmée par le GPS (cas observé : rallumage confirmé
 * en direct puis page rechargée montrant encore "COUPÉ"). Un cache
 * applicatif du dernier état "confirmé" a été testé puis rejeté (il masque
 * la vraie fraîcheur de la donnée). La solution retenue : interroger 18gps
 * pour toute la flotte à intervalle rapproché, en parallèle (Http::pool)
 * pour que ça reste rapide, et écrire le résultat dans `locations` — la
 * page continue de lire uniquement cette table (donc instantanée) mais
 * celle-ci reste maintenant alignée sur l'état réel du provider en
 * permanence, sans action manuelle.
 */
class SyncGpsEngineStatusCommand extends Command
{
    protected $signature = 'gps:sync-engine-status {--pool=20 : Nombre d\'appels provider envoyés en parallèle par lot}';

    protected $description = "Synchronise l'état moteur/position de toute la flotte depuis 18gps (appels concurrents)";

    public function handle(GpsControlService $gps): int
    {
        $poolSize = max(1, (int) $this->option('pool'));

        $summary = $gps->syncEngineStatusFleetConcurrent($poolSize);

        $this->info(sprintf(
            'Sync moteur GPS : %d boîtier(s), %d sauvegardé(s), %d doublon(s), %d ignoré(s), %d échec(s).',
            $summary['total'] ?? 0,
            $summary['saved'] ?? 0,
            $summary['duplicate'] ?? 0,
            $summary['skipped'] ?? 0,
            $summary['failed'] ?? 0,
        ));

        if (!empty($summary['failures'])) {
            $this->warn(count($summary['failures']) . ' échec(s) détaillé(s) dans les logs.');
        }

        return self::SUCCESS;
    }
}
