<?php

namespace App\Console\Commands;

use App\Services\Gps\ManualCommandConfirmationService;
use Illuminate\Console\Command;

/**
 * Vérifie l'état moteur réel des commandes manuelles récentes
 * (coupure_moteur) et les marque CONFIRMED ou, après ~20 minutes sans
 * confirmation, UNCONFIRMED (jamais "échec" : la commande a bien été
 * envoyée, seule sa confirmation manque).
 */
class ConfirmManualEngineCommandsCommand extends Command
{
    protected $signature = 'engine:confirm-manual-commands';

    protected $description = 'Confirme, via l’état moteur réel, les commandes moteur manuelles récentes';

    public function handle(ManualCommandConfirmationService $service): int
    {
        $result = $service->process();

        $this->info('Vérification des commandes manuelles terminée.');
        $this->line('Confirmées     : ' . ($result['confirmed'] ?? 0));
        $this->line('En attente     : ' . ($result['waiting'] ?? 0));
        $this->line('Non confirmées : ' . ($result['unconfirmed'] ?? 0));

        return self::SUCCESS;
    }
}
