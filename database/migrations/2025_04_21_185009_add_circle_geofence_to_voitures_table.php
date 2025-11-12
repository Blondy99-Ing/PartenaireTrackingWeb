<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('voitures', function (Blueprint $table) {
            $table->decimal('geofence_center_lat', 10, 8)->nullable();
            $table->decimal('geofence_center_lng', 11, 8)->nullable();
            $table->integer('geofence_radius')->nullable(); // meters
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('voitures', function (Blueprint $table) {
            //
        });
    }
};
