<?php

namespace App\Console\Commands;

use App\Services\Leases\LeaseCutoffQueueProcessorService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Traite la queue de coupure lease pour une seule date d'échéance.
 *
 * Règle métier :
 * - par défaut, la commande traite uniquement les queues du jour courant ;
 * - les queues d'hier restent dans l'historique d'hier et ne polluent pas aujourd'hui ;
 * - une date passée peut être rejouée uniquement volontairement avec --date=YYYY-MM-DD ;
 * - la commande continue à respecter paiement, règle spécifique et sécurité GPS.
 */
class LeaseCutoffProcessQueueCommand extends Command
{
    protected $signature = 'lease:cutoff:process
        {--date= : Date d’échéance à traiter au format YYYY-MM-DD. Par défaut : aujourd’hui}';

    protected $description = 'Traite uniquement les queues de coupure lease du jour, sauf date explicite';

    public function handle(LeaseCutoffQueueProcessorService $service): int
    {
        $timezone = config('app.timezone', 'Africa/Douala');
        $optionDate = $this->option('date');

        try {
            $date = $optionDate
                ? Carbon::parse($optionDate, $timezone)->toDateString()
                : Carbon::now($timezone)->toDateString();
        } catch (\Throwable) {
            $this->error('Date invalide. Format attendu : YYYY-MM-DD.');
            return self::FAILURE;
        }

        $result = $service->process($date);

        $this->info('Traitement de queue terminé.');
        $this->line('Date échéance traitée : ' . ($result['target_date_echeance'] ?? $date));
        $this->line('Coupés/traités : ' . ($result['processed'] ?? 0));
        $this->line('En attente     : ' . ($result['waiting'] ?? 0));
        $this->line('Annulés        : ' . ($result['cancelled'] ?? 0));
        $this->line('Échecs         : ' . ($result['failed'] ?? 0));

        return self::SUCCESS;
    }
}
