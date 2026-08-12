<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Permet de créer, pour un partenaire donné, un compte staff (rôle
 * partner_admin) qui reste invisible dans les pages "Staff" et "Chauffeurs"
 * de ce partenaire — utilisé pour des comptes de support interne avec accès
 * opérationnel complet, sans apparaître dans les listes vues par le
 * partenaire.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_hidden')->default(false)->after('partner_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_hidden');
        });
    }
};
