<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Traçabilité des rallumages/coupures manuels : un rallumage manuel déclenché
 * pendant qu'une dette de lease reste ouverte pour aujourd'hui (non payée,
 * non pardonnée) doit être visible dans l'historique des commandes, pas
 * seulement dans les logs applicatifs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commands', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('type_commande');
        });
    }

    public function down(): void
    {
        Schema::table('commands', function (Blueprint $table) {
            $table->dropColumn('notes');
        });
    }
};
