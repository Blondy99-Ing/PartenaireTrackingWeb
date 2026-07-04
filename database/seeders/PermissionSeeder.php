<?php

namespace Database\Seeders;

use App\Enums\PartnerPermission;
use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Sync the predefined permission catalog (enum) into the `permissions`
     * table. Idempotent: upsert by `key`, so it can be re-run safely.
     */
    public function run(): void
    {
        foreach (PartnerPermission::cases() as $permission) {
            Permission::updateOrCreate(
                ['key' => $permission->value],
                [
                    'label'        => $permission->label(),
                    'group'        => $permission->group(),
                    'description'  => $permission->description(),
                    'is_sensitive' => $permission->isSensitive(),
                ],
            );
        }
    }
}
