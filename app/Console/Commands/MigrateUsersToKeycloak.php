<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Keycloak\KeycloakAdminService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class MigrateUsersToKeycloak extends Command
{
    protected $signature = 'keycloak:migrate-users
                            {--only-id= : Migrer un seul user ID}
                            {--limit= : Limiter le nombre}
                            {--skip-existing : Ignorer ceux qui ont déjà keycloak_id}
                            {--dry-run : Simulation sans écriture}
                            {--temporary-password : Créer avec mot de passe temporaire}';

    protected $description = 'Créer les utilisateurs existants dans Keycloak et rattacher keycloak_id';

    public function handle(KeycloakAdminService $keycloak): int
    {
        $query = User::query()->with('role')->orderBy('id');

        if ($this->option('skip-existing')) {
            $query->whereNull('keycloak_id');
        }

        if ($onlyId = $this->option('only-id')) {
            $query->where('id', $onlyId);
        }

        if ($limit = $this->option('limit')) {
            $query->limit((int) $limit);
        }

        $users = $query->get();

        if ($users->isEmpty()) {
            $this->warn('Aucun utilisateur à migrer.');
            return self::SUCCESS;
        }

        $temporary = (bool) $this->option('temporary-password');
        $dryRun = (bool) $this->option('dry-run');

        $created = 0;
        $linked = 0;
        $errors = 0;

        $this->info("Utilisateurs trouvés : {$users->count()}");

        foreach ($users as $user) {
            try {
                if ($dryRun) {
                    $roleSlug = $user->role?->slug ?? 'aucun-role';
                    $this->line(
                        "DRY-RUN - user #{$user->id} | email: {$user->email} | phone: {$user->phone} | role: {$roleSlug}"
                    );
                    continue;
                }

                DB::beginTransaction();

                $result = $keycloak->createOrFindUser($user, $temporary);

                $user->keycloak_id = $result['id'];
                $user->keycloak_username = $result['username'];

                if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'keycloak_migrated_at')) {
                    $user->keycloak_migrated_at = now();
                }

                $user->save();

                $roleName = $keycloak->resolveKeycloakRoleName($user->role);

                if ($roleName) {
                    $keycloak->assignTrackingAppRole($user, $roleName);
                }

                DB::commit();

                if ($result['created']) {
                    $created++;
                    $this->info(
                        "Créé: user #{$user->id} -> {$result['username']}" .
                        ($result['temporary_password'] ? " | temp pwd: {$result['temporary_password']}" : '')
                    );
                } else {
                    $linked++;
                    $this->comment("Rattaché: user #{$user->id} -> {$result['username']}");
                }
            } catch (Throwable $e) {
                DB::rollBack();
                $errors++;
                $this->error("Erreur user #{$user->id}: " . $e->getMessage());
            }
        }

        $this->newLine();
        $this->info("Créés : {$created}");
        $this->info("Rattachés : {$linked}");
        $this->warn("Erreurs : {$errors}");

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}