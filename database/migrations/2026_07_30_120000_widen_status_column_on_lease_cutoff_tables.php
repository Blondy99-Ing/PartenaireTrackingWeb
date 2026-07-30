<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Bug de production corrigé : `status` était en varchar(30) sur les deux
 * tables, mais deux statuts métier dépassent déjà cette taille —
 * REACTIVATION_REQUESTED_AFTER_FORGIVENESS (40) et
 * REACTIVATION_FAILED_AFTER_FORGIVENESS (37). Toute tentative d'écrire l'un
 * de ces deux statuts (rallumage en cascade après un pardon "Pardonner
 * tout") échouait avec SQLSTATE[22001] "Data too long for column 'status'",
 * et le message SQL brut de cette erreur remontait jusqu'au partenaire
 * (alerte navigateur avec la requête SQL visible).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE lease_cutoff_histories MODIFY status VARCHAR(64) NOT NULL DEFAULT 'PENDING'");
        DB::statement("ALTER TABLE lease_cutoff_queue MODIFY status VARCHAR(64) NOT NULL DEFAULT 'PENDING'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE lease_cutoff_histories MODIFY status VARCHAR(30) NOT NULL DEFAULT 'PENDING'");
        DB::statement("ALTER TABLE lease_cutoff_queue MODIFY status VARCHAR(30) NOT NULL DEFAULT 'PENDING'");
    }
};
