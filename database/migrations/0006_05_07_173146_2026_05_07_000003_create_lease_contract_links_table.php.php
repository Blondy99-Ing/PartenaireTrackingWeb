<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration : liaison locale entre les contrats recouvrement et Tracking.
 *
 * Contexte métier :
 * Recouvrement est la source de vérité pour :
 * - les contrats ;
 * - les sous-contrats ;
 * - les paiements ;
 * - les impayés.
 *
 * Tracking est la source de vérité pour :
 * - les véhicules ;
 * - le GPS ;
 * - la coupure moteur ;
 * - les règles locales de coupure.
 *
 * Problème :
 * Un sous-contrat téléphone, parasol ou crédit est lié au contrat véhicule,
 * mais Tracking doit savoir à quel véhicule local il correspond.
 *
 * Cette table stocke uniquement le lien technique :
 * contrat recouvrement -> véhicule Tracking -> chauffeur Tracking.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lease_contract_links')) {
            return;
        }

        Schema::create('lease_contract_links', function (Blueprint $table) {
            $table->id();

            /**
             * Partenaire propriétaire du véhicule et du contrat.
             */
            $table->foreignId('partner_id')
                ->constrained('users')
                ->cascadeOnDelete();

            /**
             * Véhicule local Tracking concerné par le contrat ou sous-contrat.
             */
            $table->foreignId('vehicle_id')
                ->constrained('voitures')
                ->cascadeOnDelete();

            /**
             * Chauffeur local Tracking lié au contrat.
             * Nullable pour éviter de perdre l’historique si le chauffeur est supprimé.
             */
            $table->foreignId('driver_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            /**
             * ID chauffeur côté recouvrement.
             * Ce champ existe déjà dans users.recouvrement_driver_id,
             * mais on le garde ici pour snapshot et audit.
             */
            $table->unsignedBigInteger('recouvrement_driver_id')->nullable();

            /**
             * Identifiant du contrat ou sous-contrat côté recouvrement.
             */
            $table->unsignedBigInteger('source_contract_id');

            /**
             * Identifiant du contrat parent côté recouvrement.
             * Null pour le contrat principal.
             * Rempli pour les sous-contrats.
             */
            $table->unsignedBigInteger('source_parent_contract_id')->nullable();

            /**
             * MAIN = contrat véhicule principal.
             * SUB = sous-contrat lié au contrat principal.
             */
            $table->string('contract_kind', 20)->default('MAIN');

            /**
             * Type de contrat côté recouvrement.
             */
            $table->unsignedBigInteger('type_contrat_id')->nullable();
            $table->string('type_contrat_label', 150)->nullable();

            /**
             * Informations véhicule envoyées/reçues depuis recouvrement.
             */
            $table->string('immatriculation', 100)->nullable();
            $table->string('vin', 100)->nullable();

            /**
             * Statut local du lien.
             * Exemple : ACTIVE, CLOSED, CANCELLED, UNKNOWN.
             */
            $table->string('status', 40)->default('ACTIVE');

            /**
             * Dernier statut de paiement connu depuis recouvrement.
             * Exemple : A_JOUR, NON_PAYE, EN_RETARD, PARTIEL.
             */
            $table->string('last_payment_status', 50)->nullable();

            /**
             * Snapshot JSON du dernier contrat reçu depuis recouvrement.
             * Utile pour debug et audit.
             */
            $table->json('last_snapshot')->nullable();

            /**
             * Payload envoyé à recouvrement lors de la création/modification.
             * Utile pour audit technique.
             */
            $table->json('last_payload')->nullable();

            $table->timestamp('last_synced_at')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique(
                ['partner_id', 'source_contract_id'],
                'lease_contract_links_partner_source_unique'
            );

            $table->index(
                ['partner_id', 'vehicle_id', 'contract_kind'],
                'lease_contract_links_partner_vehicle_kind_idx'
            );

            $table->index(
                ['source_parent_contract_id'],
                'lease_contract_links_parent_idx'
            );

            $table->index(
                ['type_contrat_id', 'last_payment_status'],
                'lease_contract_links_type_payment_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lease_contract_links');
    }
};