<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('villes', function (Blueprint $table) {
            $table->id();
            $table->string('code_ville')->unique();
            $table->string('name'); // Nom de la ville / zone
            $table->json('geom');   // GeoJSON du polygone
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('villes');
    }
};
