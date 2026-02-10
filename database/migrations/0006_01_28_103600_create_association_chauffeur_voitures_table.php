<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('association_chauffeur_voiture_partner', function (Blueprint $table) {
            $table->id();

            $table->foreignId('voiture_id')
                ->constrained('voitures')
                ->cascadeOnDelete();

            $table->foreignId('chauffeur_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // audit : qui a assigné
            $table->foreignId('assigned_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('assigned_at')->useCurrent();
            $table->text('note')->nullable();

            $table->timestamps();

            // ✅ 1 seul chauffeur actuel par voiture
            $table->unique('voiture_id', 'uniq_partner_current_driver_per_car');

            $table->index(['chauffeur_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('association_chauffeur_voiture_partner');
    }
};
