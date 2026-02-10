<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {


            $table->foreignId('partner_id')
                ->nullable()
                ->after('role_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('created_by')
                ->nullable()
                ->after('partner_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->index(['role_id']);
            $table->index(['partner_id']);
            $table->index(['created_by']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
            $table->dropForeign(['partner_id']);
            $table->dropForeign(['created_by']);

            $table->dropIndex(['role_id']);
            $table->dropIndex(['partner_id']);
            $table->dropIndex(['created_by']);

            $table->dropColumn(['role_id', 'partner_id', 'created_by']);
        });
    }
};
