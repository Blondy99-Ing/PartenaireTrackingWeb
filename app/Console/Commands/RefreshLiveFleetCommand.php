<?php

namespace App\Console\Commands;

use App\Services\DashboardCacheService;
use App\Services\GpsControlService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Rafraîchit, hors requête web, l'état LIVE de toute la flotte depuis 18gps.
 *
 * Pourquoi cette commande existe :
 * l'état affiché (en ligne / en mouvement / à l'arrêt) était déduit du dernier
 * point enregistré dans `locations`. Or un véhicule à l'arrêt cesse d'émettre
 * des points tout en continuant son battement — sa dernière position peut donc
 * dater de plusieurs heures alors que le boîtier va très bien. L'écran ne
 * pouvait pas distinguer « garé, tout va bien » de « boîtier muet », et il
 * fallait cliquer sur chaque véhicule pour le savoir.
 *
 * `getDeviceListByCustomId` renvoie l'état de toute la flotte en un seul appel
 * (~4 s pour les deux comptes). On le fait donc ici, hors requête, et la page
 * se contente de lire le cache — en moins d'une milliseconde.
 *
 * Ce que cette commande ne fait PAS : écrire dans `locations`. L'ingestion Node
 * en reste seule responsable, car elle seule fournit la trace continue dont
 * dépendent les trajets, les alertes et les coupures sur géorepère. Un
 * instantané par minute ne remplace pas un flux.
 */
class RefreshLiveFleetCommand extends Command
{
    protected $signature = 'gps:refresh-live-fleet
                            {--dry-run : Affiche ce que contient le cache fournisseur, sans appel ni ecriture}';

    protected $description = 'Rafraîchit l\'état live de la flotte depuis 18gps (position + connectivité)';

    public function handle(GpsControlService $gps, DashboardCacheService $cache): int
    {
        $debut = microtime(true);

        /*
         * En simulation, on lit le cache au lieu d'interroger le fournisseur :
         * autrement l'option écrivait quand même la carte, ce qui contredit son
         * nom et fausse toute vérification faite juste avant une mise en
         * production.
         */
        $map = $this->option('dry-run')
            ? $gps->getLiveFleetMap(false)
            : $gps->refreshLiveFleetMap();

        if ($this->option('dry-run')) {
            $this->info(sprintf(
                'Simulation : %d boîtier(s) actuellement en cache, aucune écriture, aucun appel fournisseur.',
                count($map)
            ));

            return self::SUCCESS;
        }

        /*
         * « La carte n'est pas vide » ne veut PAS dire « le fournisseur a
         * répondu » : en cas de panne, la carte précédente est conservée pour
         * que l'affichage ne bascule pas en « inconnu ». Se fier au seul
         * contenu de la carte ferait donc journaliser une panne de plusieurs
         * heures comme une série de succès — exactement le mode de défaillance
         * silencieuse qui a laissé l'ingestion GPS arrêtée huit heures sans que
         * personne ne s'en aperçoive.
         */
        if (empty($gps->derniersComptesServis())) {
            Log::warning('[GPS_LIVE_FLEET] aucun compte fournisseur n a repondu', [
                'boitiers_servis_depuis_le_cache' => count($map),
            ]);

            $this->warn('Aucun compte fournisseur n\'a répondu : cache inchangé.');

            return self::FAILURE;
        }

        $dureeAppel = microtime(true) - $debut;

        $bilan = $cache->updateFleetFromLiveProviderMap($map);
        $duree = microtime(true) - $debut;

        $this->info(sprintf(
            'Flotte rafraîchie en %.0f ms (dont %.0f ms de fournisseur) : %d reçus, %d lignes écrites, %d changements visibles.',
            $duree * 1000,
            $dureeAppel * 1000,
            $bilan['boitiers_recus'],
            $bilan['lignes_ecrites'],
            $bilan['changements_vus']
        ));

        /*
         * Seul témoin qu'un balayage a RÉELLEMENT abouti. Sans lui, rien ne
         * distingue « la flotte est calme » de « le fournisseur est muet depuis
         * trois heures » : au bout de 25 minutes sans battement neuf, toute la
         * flotte basculerait « hors ligne » et on croirait à une panne de
         * boîtiers là où c'est l'infrastructure qui est en cause.
         */
        Cache::forever('gps18gps:live_fleet_ok_ms', now()->getTimestampMs());

        // Tracé volontairement compact : cette commande tourne chaque minute, et
        // le journal de ce projet a déjà atteint plusieurs gigaoctets à cause de
        // boucles trop bavardes.
        Log::info('[GPS_LIVE_FLEET] balayage', $bilan + ['duree_ms' => (int) round($duree * 1000)]);

        return self::SUCCESS;
    }
}
