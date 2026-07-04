<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lease_cutoff_queue', function (Blueprint $table) {
            $table->id();

            // Partenaire propriétaire du véhicule à couper
            $table->foreignId('partner_id')->constrained('users')->cascadeOnDelete();

            // Véhicule à couper
            $table->foreignId('vehicle_id')->constrained('voitures')->cascadeOnDelete();

            // Contrat lease concerné
            $table->unsignedBigInteger('contract_id');

            // Ligne lease / échéance concernée
            $table->unsignedBigInteger('lease_id')->nullable();

            // Règle qui a déclenché l'entrée dans la queue
            $table->foreignId('rule_id')->constrained('lease_cutoff_rules')->cascadeOnDelete();

            // Historique associé
            // On le garde simple pour éviter les conflits de création de FK
            $table->unsignedBigInteger('history_id')->nullable();

            // Date/heure théorique de coupure
            $table->dateTime('scheduled_for');

            // Etat courant de la ligne dans la queue
            $table->string('status', 30)->default('PENDING');

            // Dernière vérification du cron
            $table->dateTime('last_checked_at')->nullable();

            // Nombre de tentatives / vérifications
            $table->unsignedInteger('retry_count')->default(0);

            // Prochaine vérification prévue
            $table->dateTime('next_check_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'next_check_at'], 'lease_cutoff_queue_status_next_check_index');
            $table->index(['vehicle_id', 'status'], 'lease_cutoff_queue_vehicle_status_index');
            $table->index(['history_id'], 'lease_cutoff_queue_history_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lease_cutoff_queue');
    }
};