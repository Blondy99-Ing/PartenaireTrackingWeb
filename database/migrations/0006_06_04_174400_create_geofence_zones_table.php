<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geofence_zones', function (Blueprint $table) {
            $table->id();

            $table->foreignId('partner_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('name');

            $table->string('code')->nullable();

            $table->longText('zone');

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index('partner_id');
            $table->index('created_by');
            $table->unique(['partner_id', 'code'], 'geofence_zones_partner_code_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geofence_zones');
    }
};