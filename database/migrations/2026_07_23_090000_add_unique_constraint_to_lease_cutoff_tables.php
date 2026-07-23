<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Verrou anti-doublon au niveau base de données.
 *
 * Jusqu'ici, l'unicité d'une ligne de queue/historique de coupure pour un
 * (véhicule, contrat/sous-contrat, lease, échéance) donné n'était garantie
 * qu'au niveau applicatif (vérification "existe déjà ?" puis création),
 * sans verrou partagé entre le planificateur automatique (cron) et le
 * pardon manuel (action partenaire). Les deux peuvent s'exécuter au même
 * moment et créer chacun leur propre ligne pour la même échéance :
 * un pardon accordé peut alors être contredit par une coupure planifiée
 * juste après par le cron, sans qu'aucune erreur ne soit visible.
 *
 * On dédoublonne d'abord les lignes existantes (en gardant la plus
 * récente), puis on ajoute la contrainte unique qui empêchera
 * définitivement ce scénario : toute tentative de création en double
 * échoue au niveau SQL et le code applicatif doit alors relire/mettre à
 * jour la ligne existante au lieu d'en créer une nouvelle.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->dedupeHistories();
        $this->dedupeQueue();

        Schema::table('lease_cutoff_histories', function (Blueprint $table) {
            $table->unique(
                ['vehicle_id', 'contract_link_id', 'lease_id', 'lease_date_echeance'],
                'lease_cutoff_histories_unique_entity_due'
            );
        });

        Schema::table('lease_cutoff_queue', function (Blueprint $table) {
            $table->unique(
                ['vehicle_id', 'contract_link_id', 'lease_id', 'lease_date_echeance'],
                'lease_cutoff_queue_unique_entity_due'
            );
        });
    }

    public function down(): void
    {
        Schema::table('lease_cutoff_histories', function (Blueprint $table) {
            $table->dropUnique('lease_cutoff_histories_unique_entity_due');
        });

        Schema::table('lease_cutoff_queue', function (Blueprint $table) {
            $table->dropUnique('lease_cutoff_queue_unique_entity_due');
        });
    }

    /**
     * Pour chaque groupe en double, on garde l'id le plus récent et on
     * repointe les queues qui référencent un id supprimé vers le survivant,
     * avant de supprimer les doublons (history_id n'a pas de contrainte FK
     * réelle, donc rien n'empêcherait une référence orpheline sinon).
     */
    private function dedupeHistories(): void
    {
        $groups = DB::table('lease_cutoff_histories')
            ->select('vehicle_id', 'contract_link_id', 'lease_id', 'lease_date_echeance')
            ->whereNotNull('contract_link_id')
            ->whereNotNull('lease_id')
            ->whereNotNull('lease_date_echeance')
            ->groupBy('vehicle_id', 'contract_link_id', 'lease_id', 'lease_date_echeance')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($groups as $group) {
            $ids = DB::table('lease_cutoff_histories')
                ->where('vehicle_id', $group->vehicle_id)
                ->where('contract_link_id', $group->contract_link_id)
                ->where('lease_id', $group->lease_id)
                ->where('lease_date_echeance', $group->lease_date_echeance)
                ->orderByDesc('id')
                ->pluck('id');

            $survivor = $ids->first();
            $toDelete = $ids->slice(1)->values();

            if ($toDelete->isEmpty()) {
                continue;
            }

            DB::table('lease_cutoff_queue')
                ->whereIn('history_id', $toDelete)
                ->update(['history_id' => $survivor]);

            DB::table('lease_cutoff_histories')->whereIn('id', $toDelete)->delete();
        }
    }

    private function dedupeQueue(): void
    {
        $groups = DB::table('lease_cutoff_queue')
            ->select('vehicle_id', 'contract_link_id', 'lease_id', 'lease_date_echeance')
            ->whereNotNull('contract_link_id')
            ->whereNotNull('lease_id')
            ->whereNotNull('lease_date_echeance')
            ->groupBy('vehicle_id', 'contract_link_id', 'lease_id', 'lease_date_echeance')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($groups as $group) {
            $ids = DB::table('lease_cutoff_queue')
                ->where('vehicle_id', $group->vehicle_id)
                ->where('contract_link_id', $group->contract_link_id)
                ->where('lease_id', $group->lease_id)
                ->where('lease_date_echeance', $group->lease_date_echeance)
                ->orderByDesc('id')
                ->pluck('id');

            $toDelete = $ids->slice(1)->values();

            if ($toDelete->isEmpty()) {
                continue;
            }

            DB::table('lease_cutoff_queue')->whereIn('id', $toDelete)->delete();
        }
    }
};
