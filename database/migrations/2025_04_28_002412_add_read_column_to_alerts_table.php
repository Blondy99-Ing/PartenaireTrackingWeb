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
        Schema::table('alerts', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->boolean('read')->default(false)->after('sent');
        });
    }

    public function down()
    {
        Schema::table('alerts', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->dropColumn('read');
        });
    }

};
