<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration : enrichissement de la file de coupure.
 *
 * Contexte existant :
 * lease_cutoff_queue permet déjà de mettre une coupure en attente.
 *
 * Nouveau besoin :
 * La coupure peut maintenant venir :
 * - d’un contrat principal véhicule ;
 * - d’un sous-contrat téléphone ;
 * - d’un sous-contrat parasol ;
 * - d’un crédit ;
 * - etc.
 *
 * On enrichit donc la queue pour savoir exactement quel contrat
 * ou sous-contrat a déclenché la coupure.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lease_cutoff_queue', function (Blueprint $table) {
            if (! Schema::hasColumn('lease_cutoff_queue', 'contract_link_id')) {
                $table->foreignId('contract_link_id')
                    ->nullable()
                    ->after('lease_id')
                    ->constrained('lease_contract_links')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('lease_cutoff_queue', 'parent_contract_id')) {
                $table->unsignedBigInteger('parent_contract_id')
                    ->nullable()
                    ->after('contract_link_id')
                    ->comment('ID recouvrement du contrat parent si le déclencheur est un sous-contrat.');
            }

            if (! Schema::hasColumn('lease_cutoff_queue', 'type_contrat_id')) {
                $table->unsignedBigInteger('type_contrat_id')
                    ->nullable()
                    ->after('parent_contract_id');
            }

            if (! Schema::hasColumn('lease_cutoff_queue', 'type_contrat_label')) {
                $table->string('type_contrat_label', 150)
                    ->nullable()
                    ->after('type_contrat_id');
            }

            if (! Schema::hasColumn('lease_cutoff_queue', 'contract_kind')) {
                $table->string('contract_kind', 20)
                    ->default('MAIN')
                    ->after('type_contrat_label');
            }

            if (! Schema::hasColumn('lease_cutoff_queue', 'trigger_label')) {
                $table->string('trigger_label', 255)
                    ->nullable()
                    ->after('contract_kind')
                    ->comment('Phrase lisible expliquant le déclencheur de la coupure.');
            }

            if (! Schema::hasColumn('lease_cutoff_queue', 'trigger_payload')) {
                $table->json('trigger_payload')
                    ->nullable()
                    ->after('trigger_label')
                    ->comment('Snapshot JSON de l’impayé ayant déclenché la queue.');
            }
        });

        Schema::table('lease_cutoff_queue', function (Blueprint $table) {
            $table->index(
                ['contract_link_id', 'status'],
                'lease_cutoff_queue_contract_link_status_idx'
            );

            $table->index(
                ['vehicle_id', 'type_contrat_id', 'status'],
                'lease_cutoff_queue_vehicle_type_status_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('lease_cutoff_queue', function (Blueprint $table) {
            if (Schema::hasColumn('lease_cutoff_queue', 'contract_link_id')) {
                $table->dropConstrainedForeignId('contract_link_id');
            }

            foreach ([
                'parent_contract_id',
                'type_contrat_id',
                'type_contrat_label',
                'contract_kind',
                'trigger_label',
                'trigger_payload',
            ] as $column) {
                if (Schema::hasColumn('lease_cutoff_queue', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};