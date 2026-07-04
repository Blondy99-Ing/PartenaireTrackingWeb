<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ajoute les informations permettant de savoir
     * qui a accordé le pardon d'un lease.
     */
    public function up(): void
    {
        Schema::table('lease_cutoff_histories', function (Blueprint $table) {
            if (! Schema::hasColumn('lease_cutoff_histories', 'forgiven_by_user_id')) {
                $table->unsignedBigInteger('forgiven_by_user_id')
                    ->nullable()
                    ->after('reason');
            }

            if (! Schema::hasColumn('lease_cutoff_histories', 'forgiven_by_name')) {
                $table->string('forgiven_by_name', 150)
                    ->nullable()
                    ->after('forgiven_by_user_id');
            }

            if (! Schema::hasColumn('lease_cutoff_histories', 'forgiven_at')) {
                $table->timestamp('forgiven_at')
                    ->nullable()
                    ->after('forgiven_by_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('lease_cutoff_histories', function (Blueprint $table) {
            if (Schema::hasColumn('lease_cutoff_histories', 'forgiven_at')) {
                $table->dropColumn('forgiven_at');
            }

            if (Schema::hasColumn('lease_cutoff_histories', 'forgiven_by_name')) {
                $table->dropColumn('forgiven_by_name');
            }

            if (Schema::hasColumn('lease_cutoff_histories', 'forgiven_by_user_id')) {
                $table->dropColumn('forgiven_by_user_id');
            }
        });
    }
};