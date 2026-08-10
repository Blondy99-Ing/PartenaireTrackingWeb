<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Journal détaillé (append-only) de chaque vérification effectuée pendant un
 * cycle de coupure/rallumage automatique.
 *
 * Besoin métier : lease_cutoff_histories ne conserve que le DERNIER statut
 * écrit — chaque nouvelle vérification (véhicule offline, en mouvement,
 * commande encore en attente...) écrase reason/notes de la vérification
 * précédente. Un véhicule dont l'heure de coupure est 12h mais confirmé
 * coupé seulement à 16h n'a donc plus aucune trace de ce qui s'est passé
 * entre les deux — impossible de savoir s'il était offline, en mouvement,
 * ou autre, à chaque étape.
 *
 * Cette table ajoute une ligne à CHAQUE vérification, sans jamais rien
 * écraser : le vrai cycle complet reste consultable après coup.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lease_cutoff_events', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('history_id');
            $table->unsignedBigInteger('queue_id')->nullable();

            // WAITING_OFFLINE, WAITING_MOVING, WAITING_MOVEMENT_UNCERTAIN,
            // WAITING_STATE_UNKNOWN, COMMAND_SENT, COMMAND_PENDING_CONFIRMATION,
            // CUT_OFF_CONFIRMED, CANCELLED_PAID, CANCELLED_UNVERIFIED,
            // CANCELLED_RULE_MISSING, CANCELLED_RULE_DISABLED,
            // REACTIVATION_CONFIRMED, REACTIVATION_PENDING_CONFIRMATION,
            // REACTIVATION_FAILED, FAILED.
            $table->string('event_type', 64);

            $table->text('message');
            $table->decimal('speed_at_check', 8, 2)->nullable();
            $table->string('ignition_state', 32)->nullable();
            $table->unsignedInteger('retry_count')->nullable();

            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->foreign('history_id')
                ->references('id')->on('lease_cutoff_histories')
                ->cascadeOnDelete();

            $table->index(['history_id', 'occurred_at']);
            $table->index('queue_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lease_cutoff_events');
    }
};
