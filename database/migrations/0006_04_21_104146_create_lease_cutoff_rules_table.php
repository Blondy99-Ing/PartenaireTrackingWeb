<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lease_cutoff_rules', function (Blueprint $table) {
            $table->id();

            // Partenaire propriétaire de la règle
            $table->foreignId('partner_id')->constrained('users')->cascadeOnDelete();

            // Véhicule concerné par la règle
            // IMPORTANT : dans votre base, la table s'appelle "voitures"
            $table->foreignId('vehicle_id')->constrained('voitures')->cascadeOnDelete();

            // Active ou non la coupure automatique pour ce véhicule
            $table->boolean('is_enabled')->default(false);

            // Heure de coupure définie pour ce véhicule
            $table->time('cutoff_time')->nullable();

            // Fuseau horaire utilisé pour interpréter cutoff_time
            $table->string('timezone', 64)->nullable();

            // Utilisateur ayant créé la règle
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            // Utilisateur ayant modifié la règle
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['partner_id', 'vehicle_id'], 'lease_cutoff_rules_partner_vehicle_unique');
            $table->index(['partner_id', 'is_enabled'], 'lease_cutoff_rules_partner_enabled_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lease_cutoff_rules');
    }
};