<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'keycloak_migrated_at')) {
                $table->timestamp('keycloak_migrated_at')->nullable()->after('keycloak_username');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'keycloak_migrated_at')) {
                $table->dropColumn('keycloak_migrated_at');
            }
        });
    }
};