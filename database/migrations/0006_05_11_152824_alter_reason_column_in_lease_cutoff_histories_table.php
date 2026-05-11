<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Migration : agrandir la colonne reason de l'historique de coupure lease.
 *
 * Pourquoi :
 * Les messages d'historique doivent être lisibles côté utilisateur.
 * Exemple :
 * "Le type de contrat Moto a causé la coupure de PROXYMMERC2 car..."
 *
 * La colonne actuelle est un VARCHAR(255), trop courte pour expliquer :
 * - le type de contrat déclencheur ;
 * - le lease exact ;
 * - le contrat ou sous-contrat ;
 * - le véhicule ;
 * - la règle ;
 * - la raison métier.
 *
 * On passe donc reason en TEXT.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE lease_cutoff_histories
            MODIFY reason TEXT NULL
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE lease_cutoff_histories
            MODIFY reason VARCHAR(255) NULL
        ");
    }
};