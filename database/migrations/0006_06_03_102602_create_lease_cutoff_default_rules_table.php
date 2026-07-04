<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lease_cutoff_default_rules', function (Blueprint $table) {
            $table->id();

            $table->foreignId('partner_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->unsignedBigInteger('type_contrat_id');
            $table->string('type_contrat_label', 150);
            $table->string('type_contrat_code', 80)->nullable();

            $table->boolean('is_enabled')->default(false);
            $table->time('cutoff_time')->nullable();
            $table->string('timezone', 64)->default('Africa/Douala');

            $table->unsignedSmallInteger('grace_days')->default(0);

            // Exemple : ["monday","tuesday","wednesday","thursday","friday","saturday"]
            $table->json('active_days')->nullable();

            $table->boolean('only_when_stopped')->default(true);
            $table->boolean('notify_before_cutoff')->default(false);

            $table->foreignId('created_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('updated_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique(
                ['partner_id', 'type_contrat_id'],
                'lease_default_rules_partner_type_unique'
            );

            $table->index(
                ['partner_id', 'is_enabled'],
                'lease_default_rules_partner_enabled_idx'
            );

            $table->index(
                ['type_contrat_id', 'is_enabled'],
                'lease_default_rules_type_enabled_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lease_cutoff_default_rules');
    }
};