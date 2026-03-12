<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            try {
                $table->dropForeign(['partner_id']);
            } catch (\Throwable $e) {
                // ignore si l'ancienne FK n'existe plus
            }
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('partner_id')->nullable()->change();

            $table->foreign('partner_id')
                ->references('id')
                ->on('partners')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            try {
                $table->dropForeign(['partner_id']);
            } catch (\Throwable $e) {
                // ignore
            }

            $table->foreign('partner_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }
};