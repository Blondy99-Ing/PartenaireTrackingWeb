<?php

namespace App\Console\Commands;

use App\Services\Leases\LeaseCutoffPlannerService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Planifie les coupures lease.
 *
 * Cette commande ne coupe jamais un véhicule. Elle lit les leases NON_PAYE,
 * vérifie les règles spécifiques par contrat/sous-contrat réel et crée la queue.
 */
class LeaseCutoffPlanCommand extends Command
{
    protected $signature = 'lease:cutoff:plan
        {--date= : Date d’échéance à traiter au format YYYY-MM-DD. Exemple : 2026-05-10}';

    protected $description = 'Planifie les coupures des leases NON_PAYE uniquement si une règle spécifique contrat/sous-contrat est active';

    public function handle(LeaseCutoffPlannerService $service): int
    {
        $date = $this->option('date');

        if ($date) {
            try {
                $date = Carbon::parse($date)->toDateString();
            } catch (\Throwable) {
                $this->error('Date invalide. Format attendu : YYYY-MM-DD.');
                return self::FAILURE;
            }
        }

        $result = $service->plan($date);

        $this->info('Planification terminée.');

        if ($date) {
            $this->line('Date échéance : ' . $date);
        }

        $this->line('Créés      : ' . ($result['created'] ?? 0));
        $this->line('Réutilisés : ' . ($result['reused'] ?? 0));
        $this->line('Ignorés    : ' . ($result['skipped'] ?? 0));

        $skipReasons = $result['skip_reasons'] ?? [];
        if (! empty($skipReasons)) {
            $this->newLine();
            $this->info('Détail des rejets :');

            foreach ($skipReasons as $reason => $count) {
                if ((int) $count > 0) {
                    $this->line("- {$reason} : {$count}");
                }
            }
        }

        return self::SUCCESS;
    }
}
