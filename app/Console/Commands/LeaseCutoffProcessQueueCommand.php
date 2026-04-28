<?php

namespace App\Console\Commands;

use App\Services\Leases\LeaseCutoffQueueProcessorService;
use Illuminate\Console\Command;

class LeaseCutoffProcessQueueCommand extends Command
{
    protected $signature = 'lease:cutoff:process';
    protected $description = 'Traite la queue de coupure lease et coupe les véhicules arrêtés';

    public function handle(LeaseCutoffQueueProcessorService $service): int
    {
        $result = $service->process();

        $this->info('Traitement de queue terminé.');
        $this->line('Coupés/traités : ' . ($result['processed'] ?? 0));
        $this->line('En attente     : ' . ($result['waiting'] ?? 0));
        $this->line('Annulés payés  : ' . ($result['cancelled'] ?? 0));
        $this->line('Échecs         : ' . ($result['failed'] ?? 0));

        return self::SUCCESS;
    }
}