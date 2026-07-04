<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lease_cutoff_histories', function (Blueprint $table) {
            $table->id();

            // Partenaire propriétaire du véhicule concerné
            $table->foreignId('partner_id')->constrained('users')->cascadeOnDelete();

            // Véhicule concerné
            $table->foreignId('vehicle_id')->constrained('voitures')->cascadeOnDelete();

            // Contrat lease concerné
            $table->unsignedBigInteger('contract_id');

            // Ligne lease / échéance concernée
            $table->unsignedBigInteger('lease_id')->nullable();

            // Règle de coupure utilisée
            $table->foreignId('rule_id')->nullable()->constrained('lease_cutoff_rules')->nullOnDelete();

            // Date/heure théorique de coupure
            $table->dateTime('scheduled_for');

            // Date/heure de détection du véhicule à couper
            $table->dateTime('detected_at');

            // Date/heure où la commande a été demandée
            $table->dateTime('cutoff_requested_at')->nullable();

            // Date/heure de coupure réelle
            $table->dateTime('cutoff_executed_at')->nullable();

            // Etat global
            $table->string('status', 30)->default('PENDING');

            // Raison métier ou technique
            $table->string('reason', 255)->nullable();

            // Vitesse observée lors du contrôle
            $table->decimal('speed_at_check', 10, 2)->nullable();

            // Etat du moteur/contact
            $table->string('ignition_state', 30)->nullable();

            // Snapshot JSON de l'état de paiement
            $table->json('payment_status_snapshot')->nullable();

            // Réponse JSON de la commande de coupure
            $table->json('command_response')->nullable();

            // Notes complémentaires
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['partner_id', 'scheduled_for'], 'lease_cutoff_histories_partner_scheduled_index');
            $table->index(['vehicle_id', 'scheduled_for'], 'lease_cutoff_histories_vehicle_scheduled_index');
            $table->index(['status', 'scheduled_for'], 'lease_cutoff_histories_status_scheduled_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lease_cutoff_histories');
    }
};