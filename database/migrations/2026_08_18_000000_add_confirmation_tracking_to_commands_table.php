<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Les coupures/rallumages MANUELS n'avaient jusqu'ici aucune boucle de
 * confirmation : la table "commands" ne trace que l'accusé de réception du
 * provider (SEND_OK / QUEUED_OFFLINE), jamais si le boîtier a réellement
 * exécuté la commande — contrairement au système automatique (lease), qui
 * vérifie l'état moteur réel pendant ~20 minutes avant de conclure.
 *
 * Ajoute le même mécanisme de confirmation aux commandes manuelles :
 * - confirmation_status : NULL (pas encore résolu) / CONFIRMED / UNCONFIRMED
 *   (jamais confirmé après la fenêtre de vérification — délibérément
 *   distinct de "FAILED", qui donnerait l'impression que la commande n'est
 *   même pas partie)
 * - confirmed_at : horodatage de la confirmation réelle
 * - retry_count / next_check_at : pilotage de la boucle de vérification
 *   périodique (même schéma que lease_cutoff_queue).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commands', function (Blueprint $table) {
            $table->string('confirmation_status', 32)->nullable()->after('status');
            $table->timestamp('confirmed_at')->nullable()->after('confirmation_status');
            $table->unsignedInteger('retry_count')->default(0)->after('confirmed_at');
            $table->timestamp('next_check_at')->nullable()->after('retry_count');

            $table->index(['confirmation_status', 'next_check_at']);
        });
    }

    public function down(): void
    {
        Schema::table('commands', function (Blueprint $table) {
            $table->dropIndex(['confirmation_status', 'next_check_at']);
            $table->dropColumn(['confirmation_status', 'confirmed_at', 'retry_count', 'next_check_at']);
        });
    }
};
