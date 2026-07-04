<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('keycloak_id')->nullable()->unique()->after('id');
            $table->string('keycloak_username')->nullable()->after('keycloak_id');
            $table->timestamp('last_synced_at')->nullable()->after('remember_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'keycloak_id',
                'keycloak_username',
                'last_synced_at',
            ]);
        });
    }
};