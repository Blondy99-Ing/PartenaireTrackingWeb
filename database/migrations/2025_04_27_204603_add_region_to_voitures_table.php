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
            $table->unsignedBigInteger('region_id')->nullable()->after('photo');
            $table->string('region_name')->nullable()->after('region_id');
        });
    }

    public function down()
    {
        Schema::table('voitures', function (Blueprint $table) {
            $table->dropColumn('region_id');
            $table->dropColumn('region_name');
        });
    }

};
