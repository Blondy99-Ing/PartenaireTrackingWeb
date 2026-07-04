<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Ajout / modification des colonnes
            if (!Schema::hasColumn('users', 'user_unique_id')) {
                $table->string('user_unique_id')->unique()->after('id');
            }

            if (!Schema::hasColumn('users', 'nom')) {
                $table->string('nom')->nullable()->after('user_unique_id');
            }

            if (!Schema::hasColumn('users', 'prenom')) {
                $table->string('prenom')->nullable()->after('nom');
            }

            if (!Schema::hasColumn('users', 'phone')) {
                $table->string('phone')->nullable()->after('prenom');
            }

            if (!Schema::hasColumn('users', 'email')) {
                $table->string('email')->nullable()->change();
            }

            if (!Schema::hasColumn('users', 'ville')) {
                $table->string('ville')->nullable()->after('email');
            }

            if (!Schema::hasColumn('users', 'quartier')) {
                $table->string('quartier')->nullable()->after('ville');
            }

            if (!Schema::hasColumn('users', 'photo')) {
                $table->string('photo')->nullable()->after('quartier');
            }

            if (!Schema::hasColumn('users', 'password')) {
                $table->string('password')->nullable()->after('photo');
            }

            // Le champ remember_token existe souvent déjà, donc on le laisse tranquille
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'user_unique_id',
                'nom',
                'prenom',
                'phone',
                'ville',
                'quartier',
                'photo',
            ]);
        });
    }
};
