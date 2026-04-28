<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'type_partner')) {
                $table->enum('type_partner', ['SIMPLE_PARTNER', 'LEASE_PARTNER'])
                    ->default('SIMPLE_PARTNER')
                    ->after('partner_id')
                    ->index();
            }

            if (! Schema::hasColumn('users', 'keycloak_sync_status')) {
                $table->string('keycloak_sync_status', 30)
                    ->nullable()
                    ->after('keycloak_username');
            }

            if (! Schema::hasColumn('users', 'recouvrement_driver_id')) {
                $table->string('recouvrement_driver_id')
                    ->nullable()
                    ->after('keycloak_sync_status');
            }

            if (! Schema::hasColumn('users', 'recouvrement_sync_status')) {
                $table->string('recouvrement_sync_status', 30)
                    ->nullable()
                    ->after('recouvrement_driver_id');
            }

            if (! Schema::hasColumn('users', 'sync_error')) {
                $table->text('sync_error')
                    ->nullable()
                    ->after('recouvrement_sync_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach ([
                'type_partner',
                'keycloak_sync_status',
                'recouvrement_driver_id',
                'recouvrement_sync_status',
                'sync_error',
            ] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};