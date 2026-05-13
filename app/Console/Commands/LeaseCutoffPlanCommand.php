<?php

namespace App\Console\Commands;

use App\Services\Leases\LeaseCutoffPlannerService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Planifie les coupures lease pour une seule date d'échéance.
 *
 * Règle métier :
 * - par défaut, la commande traite uniquement les leases NON_PAYE du jour courant ;
 * - hier reste dans l'historique d'hier et n'est pas retraité automatiquement ;
 * - demain sera traité demain ;
 * - une autre date ne doit être traitée que manuellement avec --date=YYYY-MM-DD.
 *
 * Cette commande ne coupe jamais un véhicule. Elle crée seulement une ligne de queue
 * lorsque toutes les conditions métier sont réunies.
 */
class LeaseCutoffPlanCommand extends Command
{
    protected $signature = 'lease:cutoff:plan
        {--date= : Date d’échéance à traiter au format YYYY-MM-DD. Par défaut : aujourd’hui}';

    protected $description = 'Planifie les coupures des leases NON_PAYE du jour uniquement, sauf date explicite';

    public function handle(LeaseCutoffPlannerService $service): int
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

        $result = $service->plan($date);

        $this->info('Planification terminée.');
        $this->line('Date échéance traitée : ' . ($result['target_date_echeance'] ?? $date));
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
