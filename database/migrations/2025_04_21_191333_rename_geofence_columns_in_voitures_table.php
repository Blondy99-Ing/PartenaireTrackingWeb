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
            $table->renameColumn('geofence_center_lat', 'geofence_latitude');
            $table->renameColumn('geofence_center_lng', 'geofence_longitude');
        });
    }

    public function down()
    {
        Schema::table('voitures', function (Blueprint $table) {
            $table->renameColumn('geofence_latitude', 'geofence_center_lat');
            $table->renameColumn('geofence_longitude', 'geofence_center_lng');
        });
    }


};
