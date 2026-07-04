<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lease_cutoff_contract_rules', function (Blueprint $table) {
            if (! Schema::hasColumn('lease_cutoff_contract_rules', 'active_days')) {
                $table->json('active_days')
                    ->nullable()
                    ->after('grace_days');
            }
        });

        DB::table('lease_cutoff_contract_rules')
            ->whereNull('active_days')
            ->update([
                'active_days' => json_encode([
                    'monday',
                    'tuesday',
                    'wednesday',
                    'thursday',
                    'friday',
                    'saturday',
                ]),
            ]);
    }

    public function down(): void
    {
        Schema::table('lease_cutoff_contract_rules', function (Blueprint $table) {
            if (Schema::hasColumn('lease_cutoff_contract_rules', 'active_days')) {
                $table->dropColumn('active_days');
            }
        });
    }
};