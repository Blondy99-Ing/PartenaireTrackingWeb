<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY email VARCHAR(255) NULL");
        DB::statement("ALTER TABLE users MODIFY ville VARCHAR(255) NULL");
        DB::statement("ALTER TABLE users MODIFY quartier VARCHAR(255) NULL");
    }

    public function down(): void
    {
        DB::statement("UPDATE users SET email = CONCAT('user_', id, '@placeholder.local') WHERE email IS NULL");
        DB::statement("UPDATE users SET ville = 'Non renseigné' WHERE ville IS NULL");
        DB::statement("UPDATE users SET quartier = 'Non renseigné' WHERE quartier IS NULL");

        DB::statement("ALTER TABLE users MODIFY email VARCHAR(255) NOT NULL");
        DB::statement("ALTER TABLE users MODIFY ville VARCHAR(255) NOT NULL");
        DB::statement("ALTER TABLE users MODIFY quartier VARCHAR(255) NOT NULL");
    }
};