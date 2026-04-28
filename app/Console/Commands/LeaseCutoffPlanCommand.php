<?php

namespace App\Console\Commands;

use App\Services\Leases\LeaseCutoffPlannerService;
use Illuminate\Console\Command;

class LeaseCutoffPlanCommand extends Command
{
    protected $signature = 'lease:cutoff:plan';
    protected $description = 'Détecte les leases NON_PAYE concernés par une règle et alimente la queue de coupure';

    public function handle(LeaseCutoffPlannerService $service): int
    {
        $result = $service->plan();

        $this->info('Planification terminée.');
        $this->line('Créés   : ' . ($result['created'] ?? 0));
        $this->line('Ignorés : ' . ($result['skipped'] ?? 0));

        $skipReasons = $result['skip_reasons'] ?? [];

        if (!empty($skipReasons)) {
            $this->newLine();
            $this->info('Détail des rejets :');

            foreach ($skipReasons as $reason => $count) {
                $this->line("- {$reason} : {$count}");
            }
        }

        return self::SUCCESS;
    }
}