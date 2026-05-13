<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute la traçabilité de la règle spécifique dans la queue et l'historique.
 *
 * On garde rule_id pour compatibilité avec l'ancien code, mais il devient nullable :
 * la vraie autorisation métier est désormais contract_rule_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lease_cutoff_queue') && Schema::hasColumn('lease_cutoff_queue', 'rule_id')) {
            DB::statement('ALTER TABLE lease_cutoff_queue MODIFY rule_id BIGINT UNSIGNED NULL');
        }

        Schema::table('lease_cutoff_queue', function (Blueprint $table) {
            if (! Schema::hasColumn('lease_cutoff_queue', 'contract_rule_id')) {
                $table->foreignId('contract_rule_id')
                    ->nullable()
                    ->after('rule_id')
                    ->constrained('lease_cutoff_contract_rules')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('lease_cutoff_queue', 'lease_date_echeance')) {
                $table->date('lease_date_echeance')
                    ->nullable()
                    ->after('lease_id')
                    ->comment('Date d’échéance utilisée avec lease_id pour revalider le NON_PAYE exact avant coupure.');
            }
        });

        Schema::table('lease_cutoff_queue', function (Blueprint $table) {
            $table->index(['contract_rule_id', 'status'], 'lease_cutoff_queue_contract_rule_status_idx');
            $table->index(['lease_id', 'lease_date_echeance'], 'lease_cutoff_queue_lease_due_idx');
        });

        Schema::table('lease_cutoff_histories', function (Blueprint $table) {
            if (! Schema::hasColumn('lease_cutoff_histories', 'contract_rule_id')) {
                $table->foreignId('contract_rule_id')
                    ->nullable()
                    ->after('rule_id')
                    ->constrained('lease_cutoff_contract_rules')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('lease_cutoff_histories', 'lease_date_echeance')) {
                $table->date('lease_date_echeance')
                    ->nullable()
                    ->after('lease_id')
                    ->comment('Date d’échéance du lease impayé.');
            }
        });

        Schema::table('lease_cutoff_histories', function (Blueprint $table) {
            $table->index(['contract_rule_id', 'status'], 'lease_cutoff_histories_contract_rule_status_idx');
            $table->index(['lease_id', 'lease_date_echeance'], 'lease_cutoff_histories_lease_due_idx');
        });
    }

    public function down(): void
    {
        Schema::table('lease_cutoff_queue', function (Blueprint $table) {
            if (Schema::hasColumn('lease_cutoff_queue', 'contract_rule_id')) {
                $table->dropConstrainedForeignId('contract_rule_id');
            }

            if (Schema::hasColumn('lease_cutoff_queue', 'lease_date_echeance')) {
                $table->dropColumn('lease_date_echeance');
            }
        });

        Schema::table('lease_cutoff_histories', function (Blueprint $table) {
            if (Schema::hasColumn('lease_cutoff_histories', 'contract_rule_id')) {
                $table->dropConstrainedForeignId('contract_rule_id');
            }

            if (Schema::hasColumn('lease_cutoff_histories', 'lease_date_echeance')) {
                $table->dropColumn('lease_date_echeance');
            }
        });
    }
};
