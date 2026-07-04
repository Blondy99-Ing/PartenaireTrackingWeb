<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * NEUTRALIZED (2026-06).
 *
 * This migration originally repointed users.partner_id from users.id to
 * partners.id. That direction was abandoned: the `partners` table is unused
 * (empty) and the whole application still treats partner_id as a reference to
 * users.id (User::partner(), PartnerStaffService::resolveTenantPartner(), …).
 *
 * Forcing the repoint breaks both the data (no matching partners rows) and the
 * live code. So this migration now just guarantees the self-referencing FK
 * users.partner_id -> users.id exists, keeping the schema consistent with the
 * code that actually runs.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Drop any stray FK on partner_id (a previous failed run may have left
        // it without one), then (re)create the self-referencing FK to users.
        $this->dropPartnerIdForeignKey();

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('partner_id')->nullable()->change();

            $table->foreign('partner_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        // No-op: nothing meaningful to roll back for a neutralized migration.
    }

    /**
     * Drop any foreign key constraint on users.partner_id, whatever its name,
     * doing nothing if none exists.
     */
    private function dropPartnerIdForeignKey(): void
    {
        $constraints = DB::select(
            "SELECT CONSTRAINT_NAME
               FROM information_schema.KEY_COLUMN_USAGE
              WHERE TABLE_SCHEMA = ?
                AND TABLE_NAME = 'users'
                AND COLUMN_NAME = 'partner_id'
                AND REFERENCED_TABLE_NAME IS NOT NULL",
            [DB::getDatabaseName()]
        );

        foreach ($constraints as $constraint) {
            DB::statement("ALTER TABLE `users` DROP FOREIGN KEY `{$constraint->CONSTRAINT_NAME}`");
        }
    }
};
