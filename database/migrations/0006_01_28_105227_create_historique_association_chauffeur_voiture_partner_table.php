<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('historique_association_chauffeur_voiture_partner', function (Blueprint $table) {
        $table->id();

        $table->unsignedBigInteger('chauffeur_id');
        $table->unsignedBigInteger('voiture_id');
        $table->unsignedBigInteger('partner_id');
        $table->unsignedBigInteger('created_by')->nullable();

        $table->timestamp('started_at')->nullable();
        $table->timestamp('ended_at')->nullable();
        $table->text('note')->nullable();

        $table->timestamps();

        // ✅ FK names courts (<= 64 chars)
        $table->foreign('chauffeur_id', 'hk_chv_chauffeur_fk')
            ->references('id')->on('users')
            ->onDelete('cascade');

        $table->foreign('voiture_id', 'hk_chv_voiture_fk')
            ->references('id')->on('voitures')
            ->onDelete('cascade');

        $table->foreign('partner_id', 'hk_chv_partner_fk')
            ->references('id')->on('users')
            ->onDelete('cascade');

        $table->foreign('created_by', 'hk_chv_createdby_fk')
            ->references('id')->on('users')
            ->nullOnDelete();

        // ✅ index utiles
        $table->index(['partner_id', 'voiture_id'], 'hk_chv_partner_voiture_idx');
        $table->index(['partner_id', 'chauffeur_id'], 'hk_chv_partner_chauffeur_idx');
    });

    }

    public function down(): void
    {
        Schema::dropIfExists('historique_association_chauffeur_voiture_partner');
    }
};
