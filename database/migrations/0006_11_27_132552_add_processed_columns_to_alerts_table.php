<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alerts', function (Blueprint $table) {
            $table->boolean('processed')->default(false)->after('read'); // indique si l'alerte a été traitée
            $table->unsignedBigInteger('processed_by')->nullable()->after('processed'); // référence employé

            // Définir la clé étrangère vers la table employes
            $table->foreign('processed_by')
                  ->references('id')
                  ->on('employes')
                  ->onDelete('set null'); // si l'employé est supprimé, on met null
        });
    }

    public function down(): void
    {
        Schema::table('alerts', function (Blueprint $table) {
            $table->dropForeign(['processed_by']); // d'abord supprimer la FK
            $table->dropColumn(['processed', 'processed_by']);
        });
    }
};
