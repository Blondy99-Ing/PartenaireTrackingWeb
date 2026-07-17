<?php

namespace App\Console\Commands;

use App\Services\GpsControlService;
use Illuminate\Console\Command;

/**
 * Rafraîchit, hors requête web, le cache des statuts online des boîtiers GPS.
 *
 * Pourquoi cette commande existe :
 * getDeviceList (18gps) est un appel lourd (~84 Ko, plusieurs centaines de
 * boîtiers) qui dépasse régulièrement les 20 s. L'appeler pendant une requête
 * web faisait expirer le fetch du front (15 s) et affichait TOUS les véhicules
 * en « N/A » sur la page de coupure manuelle.
 *
 * On déplace donc ce coût ici (chaque minute) : la page se contente de lire le
 * cache et répond instantanément.
 */
class RefreshGpsOnlineMapCommand extends Command
{
    protected $signature = 'gps:refresh-online-map';

    protected $description = 'Rafraîchit le cache des statuts online GPS (device-list 18gps)';

    public function handle(GpsControlService $gps): int
    {
        $map = $gps->refreshLiveOnlineMap();

        if (empty($map)) {
            $this->warn('Aucun boîtier retourné par le provider : le cache précédent est conservé.');

            return self::SUCCESS;
        }

        $this->info('Statuts online GPS rafraîchis : ' . count($map) . ' boîtier(s).');

        return self::SUCCESS;
    }
}
