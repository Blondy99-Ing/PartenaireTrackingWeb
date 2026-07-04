<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Crée les règles de coupure spécifiques aux contrats/sous-contrats réels.
 *
 * Règle métier validée :
 * - une règle générale par type ne suffit pas ;
 * - une coupure n'est autorisée que si le contrat spécifique ou le sous-contrat
 *   spécifique réellement associé au chauffeur possède une règle active ;
 * - le paramétrage en masse ne crée/modifie que ces contrats réels.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('lease_cutoff_contract_rules')) {
            Schema::create('lease_cutoff_contract_rules', function (Blueprint $table) {
                $table->id();

                $table->foreignId('partner_id')
                    ->constrained('users')
                    ->cascadeOnDelete();

                $table->foreignId('vehicle_id')
                    ->constrained('voitures')
                    ->cascadeOnDelete();

                $table->foreignId('driver_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                /**
                 * Lien local vers le contrat/sous-contrat réel.
                 * C'est ce champ qui empêche de couper sur la base d'un type abstrait.
                 */
                $table->foreignId('contract_link_id')
                    ->constrained('lease_contract_links')
                    ->cascadeOnDelete();

                $table->unsignedBigInteger('source_contract_id');
                $table->unsignedBigInteger('source_parent_contract_id')->nullable();
                $table->string('contract_kind', 20)->default('MAIN');
                $table->unsignedBigInteger('type_contrat_id')->nullable();
                $table->string('type_contrat_label', 150)->nullable();

                $table->boolean('is_enabled')->default(false);
                $table->time('cutoff_time')->nullable();
                $table->string('timezone', 64)->default('Africa/Douala');
                $table->unsignedSmallInteger('grace_days')->default(0);
                $table->boolean('only_when_stopped')->default(true);
                $table->boolean('notify_before_cutoff')->default(false);

                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['partner_id', 'contract_link_id'], 'lease_contract_rule_partner_link_unique');
                $table->index(['partner_id', 'vehicle_id', 'is_enabled'], 'lease_contract_rule_partner_vehicle_enabled_idx');
                $table->index(['source_contract_id', 'is_enabled'], 'lease_contract_rule_source_enabled_idx');
                $table->index(['type_contrat_id', 'is_enabled'], 'lease_contract_rule_type_enabled_idx');
            });
        }

        /**
         * Migration douce depuis l'ancien paramétrage véhicule + type.
         * On ne crée jamais de règle pour un sous-contrat qui n'existe pas : on parcourt
         * uniquement lease_contract_links. Les anciennes règles deviennent donc des règles
         * spécifiques, à réviser ensuite dans l'interface.
         */
        if (
            Schema::hasTable('lease_cutoff_rule_contract_types')
            && Schema::hasTable('lease_contract_links')
        ) {
            DB::table('lease_contract_links')
                ->where('status', '!=', 'DELETED')
                ->chunkById(200, function ($links) {
                    foreach ($links as $link) {
                        if (empty($link->type_contrat_id)) {
                            continue;
                        }

                        $legacyTypeRule = DB::table('lease_cutoff_rule_contract_types')
                            ->where('partner_id', $link->partner_id)
                            ->where('vehicle_id', $link->vehicle_id)
                            ->where('type_contrat_id', $link->type_contrat_id)
                            ->orderByDesc('updated_at')
                            ->first();

                        if (! $legacyTypeRule) {
                            continue;
                        }

                        DB::table('lease_cutoff_contract_rules')->updateOrInsert(
                            [
                                'partner_id' => $link->partner_id,
                                'contract_link_id' => $link->id,
                            ],
                            [
                                'vehicle_id' => $link->vehicle_id,
                                'driver_id' => $link->driver_id,
                                'source_contract_id' => $link->source_contract_id,
                                'source_parent_contract_id' => $link->source_parent_contract_id,
                                'contract_kind' => $link->contract_kind ?: 'MAIN',
                                'type_contrat_id' => $link->type_contrat_id,
                                'type_contrat_label' => $link->type_contrat_label ?: $legacyTypeRule->type_contrat_label,
                                'is_enabled' => (bool) $legacyTypeRule->is_enabled,
                                'cutoff_time' => $legacyTypeRule->cutoff_time,
                                'timezone' => 'Africa/Douala',
                                'grace_days' => (int) ($legacyTypeRule->grace_days ?? 0),
                                'only_when_stopped' => $legacyTypeRule->only_when_stopped === null ? true : (bool) $legacyTypeRule->only_when_stopped,
                                'notify_before_cutoff' => $legacyTypeRule->notify_before_cutoff === null ? false : (bool) $legacyTypeRule->notify_before_cutoff,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]
                        );
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('lease_cutoff_contract_rules');
    }
};
