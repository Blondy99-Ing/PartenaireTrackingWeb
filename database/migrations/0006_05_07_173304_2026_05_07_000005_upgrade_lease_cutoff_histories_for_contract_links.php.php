<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration : enrichissement de l’historique de coupure.
 *
 * Contexte existant :
 * lease_cutoff_histories garde déjà les décisions et résultats de coupure.
 *
 * Nouveau besoin :
 * L’historique doit expliquer clairement :
 * - si la coupure vient du contrat principal ;
 * - si elle vient d’un sous-contrat ;
 * - quel type de sous-contrat est concerné ;
 * - quelle règle a autorisé la coupure.
 *
 * Cette migration améliore la traçabilité.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lease_cutoff_histories', function (Blueprint $table) {
            if (! Schema::hasColumn('lease_cutoff_histories', 'contract_link_id')) {
                $table->foreignId('contract_link_id')
                    ->nullable()
                    ->after('lease_id')
                    ->constrained('lease_contract_links')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('lease_cutoff_histories', 'parent_contract_id')) {
                $table->unsignedBigInteger('parent_contract_id')
                    ->nullable()
                    ->after('contract_link_id');
            }

            if (! Schema::hasColumn('lease_cutoff_histories', 'type_contrat_id')) {
                $table->unsignedBigInteger('type_contrat_id')
                    ->nullable()
                    ->after('parent_contract_id');
            }

            if (! Schema::hasColumn('lease_cutoff_histories', 'type_contrat_label')) {
                $table->string('type_contrat_label', 150)
                    ->nullable()
                    ->after('type_contrat_id');
            }

            if (! Schema::hasColumn('lease_cutoff_histories', 'contract_kind')) {
                $table->string('contract_kind', 20)
                    ->default('MAIN')
                    ->after('type_contrat_label');
            }

            if (! Schema::hasColumn('lease_cutoff_histories', 'trigger_label')) {
                $table->string('trigger_label', 255)
                    ->nullable()
                    ->after('contract_kind');
            }

            if (! Schema::hasColumn('lease_cutoff_histories', 'trigger_payload')) {
                $table->json('trigger_payload')
                    ->nullable()
                    ->after('trigger_label');
            }
        });

        Schema::table('lease_cutoff_histories', function (Blueprint $table) {
            $table->index(
                ['contract_link_id', 'status'],
                'lease_cutoff_histories_contract_link_status_idx'
            );

            $table->index(
                ['vehicle_id', 'type_contrat_id', 'status'],
                'lease_cutoff_histories_vehicle_type_status_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('lease_cutoff_histories', function (Blueprint $table) {
            if (Schema::hasColumn('lease_cutoff_histories', 'contract_link_id')) {
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
                if (Schema::hasColumn('lease_cutoff_histories', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};