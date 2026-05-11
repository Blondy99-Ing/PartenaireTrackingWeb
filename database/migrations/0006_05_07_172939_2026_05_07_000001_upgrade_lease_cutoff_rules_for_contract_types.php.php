<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration : amélioration des règles de coupure existantes.
 *
 * Contexte métier :
 * La table lease_cutoff_rules existe déjà dans le projet Tracking.
 * Elle permet actuellement de dire si un véhicule peut être coupé automatiquement
 * et à quelle heure.
 *
 * Nouveau besoin :
 * Avec les sous-contrats recouvrement, la coupure doit aussi prendre en compte :
 * - un délai de grâce avant coupure ;
 * - l'obligation de ne couper que si le véhicule est arrêté ;
 * - une notification éventuelle avant coupure.
 *
 * Cette migration ne remplace pas la table existante.
 * Elle l'améliore pour conserver la cohérence avec l’architecture actuelle.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lease_cutoff_rules', function (Blueprint $table) {
            if (! Schema::hasColumn('lease_cutoff_rules', 'grace_days')) {
                $table->unsignedSmallInteger('grace_days')
                    ->default(0)
                    ->after('timezone')
                    ->comment('Nombre de jours de grâce avant que la coupure puisse être planifiée.');
            }

            if (! Schema::hasColumn('lease_cutoff_rules', 'only_when_stopped')) {
                $table->boolean('only_when_stopped')
                    ->default(true)
                    ->after('grace_days')
                    ->comment('Indique si la coupure doit attendre que le véhicule soit arrêté.');
            }

            if (! Schema::hasColumn('lease_cutoff_rules', 'notify_before_cutoff')) {
                $table->boolean('notify_before_cutoff')
                    ->default(false)
                    ->after('only_when_stopped')
                    ->comment('Indique si une notification doit être envoyée avant la coupure.');
            }
        });
    }

    public function down(): void
    {
        Schema::table('lease_cutoff_rules', function (Blueprint $table) {
            if (Schema::hasColumn('lease_cutoff_rules', 'notify_before_cutoff')) {
                $table->dropColumn('notify_before_cutoff');
            }

            if (Schema::hasColumn('lease_cutoff_rules', 'only_when_stopped')) {
                $table->dropColumn('only_when_stopped');
            }

            if (Schema::hasColumn('lease_cutoff_rules', 'grace_days')) {
                $table->dropColumn('grace_days');
            }
        });
    }
};