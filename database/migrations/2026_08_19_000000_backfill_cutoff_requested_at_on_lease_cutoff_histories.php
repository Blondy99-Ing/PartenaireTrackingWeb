<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Corrige la chronologie détecté -> envoyé -> confirmé pour les coupures
 * déjà en base : quand le moteur était trouvé déjà coupé avant tout envoi
 * de commande par cette ligne (contrat frère ayant déjà coupé le même
 * véhicule, coupure manuelle antérieure...), cutoff_requested_at n'était
 * jamais renseigné. Constaté sur 2010 des 3131 coupures confirmées en
 * production (64%) au 18/08/2026.
 *
 * LeaseCutoffQueueProcessorService::markProcessedCutOff() comble désormais
 * ce trou pour toutes les NOUVELLES coupures ; cette migration corrige les
 * lignes déjà existantes, une seule fois, sans jamais écraser un
 * horodatage d'envoi réel déjà présent (WHERE cutoff_requested_at IS NULL).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "UPDATE lease_cutoff_histories
             SET cutoff_requested_at = cutoff_executed_at
             WHERE status = 'CUT_OFF'
               AND cutoff_requested_at IS NULL
               AND cutoff_executed_at IS NOT NULL"
        );
    }

    public function down(): void
    {
        // Correction de donnees : pas de retour en arriere significatif
        // (on ne peut pas distinguer les lignes qu'on vient de combler des
        // lignes qui avaient deja cutoff_requested_at = cutoff_executed_at
        // par coincidence).
    }
};
