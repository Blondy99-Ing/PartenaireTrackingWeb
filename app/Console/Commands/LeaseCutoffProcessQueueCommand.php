<?php

namespace App\Console\Commands;

use App\Services\Leases\LeaseCutoffQueueProcessorService;
use Illuminate\Console\Command;

/**
 * Traite la queue de coupure lease.
 *
 * Cette commande revérifie le lease exact, la règle spécifique encore active,
 * puis l'état GPS. Elle n'envoie la commande que si le véhicule est arrêté.
 */
class LeaseCutoffProcessQueueCommand extends Command
{
    protected $signature = 'lease:cutoff:process';
    protected $description = 'Traite la queue de coupure lease en respectant paiement, règles spécifiques et sécurité GPS';

    public function handle(LeaseCutoffQueueProcessorService $service): int
    {
        $result = $service->process();

        $this->info('Traitement de queue terminé.');
        $this->line('Coupés/traités : ' . ($result['processed'] ?? 0));
        $this->line('En attente     : ' . ($result['waiting'] ?? 0));
        $this->line('Annulés        : ' . ($result['cancelled'] ?? 0));
        $this->line('Échecs         : ' . ($result['failed'] ?? 0));

        return self::SUCCESS;
    }
}
