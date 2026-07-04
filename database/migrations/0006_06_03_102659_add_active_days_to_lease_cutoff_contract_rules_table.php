<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        if (Schema::hasTable('lease_cutoff_queue') && Schema::hasColumn('lease_cutoff_queue', 'rule_id')) {
            try {
                Schema::table('lease_cutoff_queue', function (Blueprint $table) {
                    $table->dropForeign(['rule_id']);
                });
            } catch (Throwable $e) {
                // FK déjà absente ou nom différent.
            }

            Schema::table('lease_cutoff_queue', function (Blueprint $table) {
                $table->dropColumn('rule_id');
            });
        }

        if (Schema::hasTable('lease_cutoff_histories') && Schema::hasColumn('lease_cutoff_histories', 'rule_id')) {
            try {
                Schema::table('lease_cutoff_histories', function (Blueprint $table) {
                    $table->dropForeign(['rule_id']);
                });
            } catch (Throwable $e) {
                // FK déjà absente ou nom différent.
            }

            Schema::table('lease_cutoff_histories', function (Blueprint $table) {
                $table->dropColumn('rule_id');
            });
        }

        Schema::dropIfExists('lease_cutoff_rule_contract_types');
        Schema::dropIfExists('lease_cutoff_rules');

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::create('lease_cutoff_rules', function (Blueprint $table) {
            $table->id();

            $table->foreignId('partner_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('vehicle_id')
                ->constrained('voitures')
                ->cascadeOnDelete();

            $table->boolean('is_enabled')->default(false);
            $table->time('cutoff_time')->nullable();
            $table->string('timezone', 64)->default('Africa/Douala');
            $table->unsignedSmallInteger('grace_days')->default(0);
            $table->boolean('only_when_stopped')->default(true);
            $table->boolean('notify_before_cutoff')->default(false);

            $table->timestamps();

            $table->unique(['partner_id', 'vehicle_id'], 'lease_cutoff_rules_partner_vehicle_unique');
        });

        Schema::create('lease_cutoff_rule_contract_types', function (Blueprint $table) {
            $table->id();

            $table->foreignId('rule_id')
                ->constrained('lease_cutoff_rules')
                ->cascadeOnDelete();

            $table->foreignId('partner_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('vehicle_id')
                ->constrained('voitures')
                ->cascadeOnDelete();

            $table->unsignedBigInteger('type_contrat_id');
            $table->string('type_contrat_label', 150)->nullable();

            $table->boolean('is_enabled')->default(false);
            $table->unsignedSmallInteger('grace_days')->default(0);
            $table->time('cutoff_time')->nullable();
            $table->boolean('only_when_stopped')->default(true);
            $table->boolean('notify_before_cutoff')->default(false);

            $table->timestamps();

            $table->unique(['rule_id', 'type_contrat_id'], 'lease_rule_contract_type_unique');
        });

        Schema::table('lease_cutoff_queue', function (Blueprint $table) {
            if (! Schema::hasColumn('lease_cutoff_queue', 'rule_id')) {
                $table->foreignId('rule_id')
                    ->nullable()
                    ->after('trigger_payload')
                    ->constrained('lease_cutoff_rules')
                    ->nullOnDelete();
            }
        });

        Schema::table('lease_cutoff_histories', function (Blueprint $table) {
            if (! Schema::hasColumn('lease_cutoff_histories', 'rule_id')) {
                $table->foreignId('rule_id')
                    ->nullable()
                    ->after('trigger_payload')
                    ->constrained('lease_cutoff_rules')
                    ->nullOnDelete();
            }
        });
    }
};