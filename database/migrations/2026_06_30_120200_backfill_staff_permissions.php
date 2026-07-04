<?php

use App\Enums\PartnerPermission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Rollout safety:
     *  1. Populate the `permissions` catalog from the enum (idempotent).
     *  2. Grant ALL permissions to EVERY existing staff member, so nobody
     *     loses access the moment this ships. Partners can then dial each
     *     staff member down from the UI.
     *
     * "Staff" = a user attached to a partner (partner_id IS NOT NULL) whose
     * role is `partner_admin`.
     */
    public function up(): void
    {
        // 1. Catalog upsert
        $now = now();

        foreach (PartnerPermission::cases() as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['key' => $permission->value],
                [
                    'label'        => $permission->label(),
                    'group'        => $permission->group(),
                    'description'  => $permission->description(),
                    'is_sensitive' => $permission->isSensitive(),
                    'updated_at'   => $now,
                    'created_at'   => $now,
                ],
            );
        }

        $permissionIds = DB::table('permissions')->pluck('id');

        // 2. Existing staff members (partner_admin attached to a partner)
        $staff = DB::table('users')
            ->join('roles', 'roles.id', '=', 'users.role_id')
            ->whereNotNull('users.partner_id')
            ->where('roles.slug', 'partner_admin')
            ->get(['users.id', 'users.partner_id']);

        $rows = [];

        foreach ($staff as $member) {
            foreach ($permissionIds as $permissionId) {
                $rows[] = [
                    'permission_id' => $permissionId,
                    'user_id'       => $member->id,
                    'granted_by'    => $member->partner_id,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ];
            }
        }

        // insertOrIgnore respects the (permission_id, user_id) unique index,
        // so re-running never duplicates a grant.
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('permission_user')->insertOrIgnore($chunk);
        }
    }

    public function down(): void
    {
        // Remove every grant created by the backfill scope (all staff).
        $staffIds = DB::table('users')
            ->join('roles', 'roles.id', '=', 'users.role_id')
            ->whereNotNull('users.partner_id')
            ->where('roles.slug', 'partner_admin')
            ->pluck('users.id');

        DB::table('permission_user')->whereIn('user_id', $staffIds)->delete();
    }
};
