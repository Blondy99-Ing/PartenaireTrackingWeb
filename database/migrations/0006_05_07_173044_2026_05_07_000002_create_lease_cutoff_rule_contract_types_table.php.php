<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration : types de contrats autorisés à déclencher la coupure.
 *
 * Contexte métier :
 * Une règle de coupure existe déjà par véhicule dans lease_cutoff_rules.
 *
 * Nouveau besoin :
 * Le partenaire doit pouvoir dire, pour chaque véhicule :
 * - le contrat véhicule peut déclencher la coupure ;
 * - le sous-contrat téléphone peut déclencher la coupure ;
 * - le sous-contrat kit sécurité ne déclenche pas la coupure ;
 * - le sous-contrat parasol peut déclencher la coupure ;
 * - etc.
 *
 * Cette table est volontairement rattachée à lease_cutoff_rules.
 * Elle évite de créer un deuxième système de coupure parallèle.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lease_cutoff_rule_contract_types')) {
            return;
        }

        Schema::create('lease_cutoff_rule_contract_types', function (Blueprint $table) {
            $table->id();

            /**
             * Règle véhicule parente.
             * Une règle véhicule peut avoir plusieurs types de contrats configurés.
             */
            $table->foreignId('rule_id')
                ->constrained('lease_cutoff_rules')
                ->cascadeOnDelete();

            /**
             * Champs dénormalisés pour accélérer les recherches métier.
             * Ils permettent de chercher rapidement :
             * partenaire + véhicule + type_contrat.
             */
            $table->foreignId('partner_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('vehicle_id')
                ->constrained('voitures')
                ->cascadeOnDelete();

            /**
             * Identifiant du type de contrat côté recouvrement.
             * Exemple :
             * 1 = Véhicule
             * 3 = Kit sécurité
             * 4 = Téléphone
             */
            $table->unsignedBigInteger('type_contrat_id');

            /**
             * Libellé local pour affichage Tracking.
             * Ce libellé pourra venir plus tard de l’API recouvrement.
             */
            $table->string('type_contrat_label', 150)->nullable();

            /**
             * Indique si ce type de contrat peut déclencher la coupure
             * pour ce véhicule.
             */
            $table->boolean('is_enabled')->default(false);

            /**
             * Paramètres optionnels spécifiques au type de contrat.
             * S’ils sont nuls, on peut utiliser les valeurs de la règle véhicule.
             */
            $table->unsignedSmallInteger('grace_days')->nullable();
            $table->time('cutoff_time')->nullable();
            $table->boolean('only_when_stopped')->nullable();
            $table->boolean('notify_before_cutoff')->nullable();

            $table->timestamps();

            $table->unique(
                ['rule_id', 'type_contrat_id'],
                'lease_rule_type_unique'
            );

            $table->index(
                ['partner_id', 'vehicle_id', 'type_contrat_id', 'is_enabled'],
                'lease_rule_type_lookup_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lease_cutoff_rule_contract_types');
    }
};