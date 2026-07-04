<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('historique_association_chauffeur_voiture_partner', function (Blueprint $table) {
            // si tu veux pouvoir tracer qui a fait l’action
            $table->unsignedBigInteger('assigned_by')->nullable()->after('chauffeur_id');

            // optionnel : si users.id est unsignedBigInteger
            // $table->foreign('assigned_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('historique_association_chauffeur_voiture_partner', function (Blueprint $table) {
            // $table->dropForeign(['assigned_by']); // si tu as activé foreign()
            $table->dropColumn('assigned_by');
        });
    }
};
