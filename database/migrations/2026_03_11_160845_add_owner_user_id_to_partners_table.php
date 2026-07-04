<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->unsignedBigInteger('owner_user_id')->nullable()->after('created_by');

            $table->index('owner_user_id', 'partners_owner_user_id_index');

            $table->foreign('owner_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            try {
                $table->dropForeign(['owner_user_id']);
            } catch (\Throwable $e) {
                // ignore
            }

            $table->dropIndex('partners_owner_user_id_index');
            $table->dropColumn('owner_user_id');
        });
    }
};