<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Keycloak\KeycloakUserProvisioningService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Commande de migration progressive des utilisateurs locaux vers Keycloak.
 *
 * IMPORTANT :
 * Cette commande ne crée PAS de mot de passe par défaut.
 *
 * Par défaut, elle pré-crée les comptes Keycloak sans credential.
 * Le vrai mot de passe Keycloak sera défini à la première connexion,
 * après vérification du mot de passe local avec Hash::check().
 */
class MigrateUsersToKeycloak extends Command
{
    protected $signature = 'keycloak:migrate-users
        {--only-id= : Migrer uniquement un utilisateur local précis}
        {--partner-id= : Migrer un partenaire et ses chauffeurs}
        {--include-existing : Inclure les utilisateurs ayant déjà un keycloak_id}
        {--dry-run : Simuler sans créer/modifier dans Keycloak}
        {--limit=0 : Limiter le nombre d’utilisateurs traités, 0 = illimité}';

    protected $description = 'Pré-provisionne les anciens utilisateurs locaux vers Keycloak sans mot de passe par défaut.';

    public function __construct(
        private readonly KeycloakUserProvisioningService $provisioningService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $includeExisting = (bool) $this->option('include-existing');
        $onlyId = $this->option('only-id');
        $partnerId = $this->option('partner-id');
        $limit = (int) $this->option('limit');

        $this->warn('Migration progressive utilisateurs locaux → Keycloak');
        $this->line('Aucun mot de passe par défaut ne sera créé.');
        $this->line('Les comptes sont pré-créés sans password.');
        $this->line('Le password Keycloak sera défini au premier login après Hash::check() local.');
        $this->newLine();

        $this->line('Règles métier appliquées :');
        $this->line('- Partenaire : partner_id = NULL, compte_id = user.id, rôle = gestionnaire_plateforme');
        $this->line('- Chauffeur : partner_id = id partenaire, compte_id = partner_id, rôle = utilisateur_secondaire');
        $this->newLine();

        if ($dryRun) {
            $this->warn('MODE DRY-RUN : aucune création/mise à jour Keycloak ne sera effectuée.');
            $this->newLine();
        }

        $query = User::query()
            ->with('role')
            ->orderByRaw('partner_id IS NOT NULL')
            ->orderBy('partner_id')
            ->orderBy('id');

        if ($onlyId) {
            $query->where('id', (int) $onlyId);
        }

        /**
         * Migrer un partenaire + ses chauffeurs.
         */
        if ($partnerId) {
            $partnerId = (int) $partnerId;

            $query->where(function ($q) use ($partnerId) {
                $q->where('id', $partnerId)
                    ->orWhere('partner_id', $partnerId);
            });
        }

        /**
         * Par défaut, on ignore les users déjà liés à Keycloak.
         */
        if (! $includeExisting) {
            $query->where(function ($q) {
                $q->whereNull('keycloak_id')
                    ->orWhere('keycloak_id', '');
            });
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        $users = $query->get();

        if ($users->isEmpty()) {
            $this->info('Aucun utilisateur à traiter.');
            return self::SUCCESS;
        }

        $stats = [
            'total' => $users->count(),
            'migrated' => 0,
            'already_synced' => 0,
            'skipped' => 0,
            'failed' => 0,
            'partners' => 0,
            'drivers' => 0,
        ];

        $failedRows = [];
        $skippedRows = [];
        $migratedRows = [];

        Log::info('[KEYCLOAK_MIGRATE_USERS_START]', [
            'total' => $users->count(),
            'dry_run' => $dryRun,
            'include_existing' => $includeExisting,
            'only_id' => $onlyId,
            'partner_id' => $partnerId,
            'limit' => $limit,
        ]);

        $this->info("Utilisateurs trouvés : {$users->count()}");
        $this->newLine();

        foreach ($users as $user) {
            $businessType = $this->businessType($user);
            $compteId = $this->compteId($user);
            $roleName = $this->roleName($user);
            $username = $this->username($user);

            if ($businessType === 'PARTNER') {
                $stats['partners']++;
            } else {
                $stats['drivers']++;
            }

            $this->line('────────────────────────────────────────────');
            $this->line("User local      : #{$user->id}");
            $this->line("Type            : {$businessType}");
            $this->line("Nom             : " . $this->fullName($user));
            $this->line("Email           : " . ($user->email ?: '—'));
            $this->line("Téléphone       : " . ($user->phone ?: '—'));
            $this->line("partner_id      : " . ($user->partner_id ?: 'NULL'));
            $this->line("compte_id       : {$compteId}");
            $this->line("Username KC     : " . ($username ?: '—'));
            $this->line("Rôle tracking   : {$roleName}");
            $this->line("Keycloak actuel : " . ($user->keycloak_id ?: '—'));

            try {
                if ($user->keycloak_id && ! $includeExisting) {
                    $stats['already_synced']++;

                    $this->info('SKIP : déjà synchronisé avec Keycloak.');

                    $skippedRows[] = [
                        $user->id,
                        $businessType,
                        $username ?: '—',
                        'Déjà synchronisé',
                    ];

                    continue;
                }

                if ($username === '') {
                    $stats['skipped']++;

                    $reason = 'Aucun username possible : keycloak_username, phone et email sont vides.';

                    $this->warn("SKIP : {$reason}");

                    $user->forceFill([
                        'keycloak_sync_status' => 'SKIPPED',
                        'sync_error' => $reason,
                    ])->save();

                    $skippedRows[] = [
                        $user->id,
                        $businessType,
                        '—',
                        $reason,
                    ];

                    Log::warning('[KEYCLOAK_MIGRATE_USER_SKIPPED]', [
                        'user_id' => $user->id,
                        'reason' => $reason,
                    ]);

                    continue;
                }

                if ($dryRun) {
                    $stats['skipped']++;

                    $this->warn('DRY-RUN : pré-provision Keycloak non exécuté.');

                    $skippedRows[] = [
                        $user->id,
                        $businessType,
                        $username,
                        'DRY-RUN',
                    ];

                    continue;
                }

                /**
                 * Pré-création Keycloak sans mot de passe.
                 */
                $result = $this->provisioningService->preProvisionUserWithoutPassword($user);

                $stats['migrated']++;

                $migratedRows[] = [
                    $user->id,
                    $businessType,
                    $username,
                    $result['keycloak_user_id'] ?? '—',
                    $result['role_name'] ?? $roleName,
                    $result['created'] ? 'CREATED' : 'UPDATED/FOUND',
                ];

                $this->info('OK : utilisateur pré-provisionné dans Keycloak sans mot de passe.');

                Log::info('[KEYCLOAK_MIGRATE_USER_DONE]', [
                    'user_id' => $user->id,
                    'business_type' => $businessType,
                    'compte_id' => $compteId,
                    'username' => $username,
                    'role' => $roleName,
                    'keycloak_id' => $result['keycloak_user_id'] ?? null,
                    'created' => $result['created'] ?? null,
                ]);
            } catch (Throwable $e) {
                $stats['failed']++;

                $message = $e->getMessage();

                $this->error("FAILED : {$message}");

                $user->forceFill([
                    'keycloak_sync_status' => 'FAILED',
                    'sync_error' => $message,
                ])->save();

                $failedRows[] = [
                    $user->id,
                    $businessType,
                    $username ?: '—',
                    $message,
                ];

                Log::error('[KEYCLOAK_MIGRATE_USER_FAILED]', [
                    'user_id' => $user->id,
                    'business_type' => $businessType,
                    'compte_id' => $compteId,
                    'username' => $username,
                    'role' => $roleName,
                    'error' => $message,
                ]);
            }
        }

        $this->newLine();
        $this->info('Résumé migration Keycloak');

        $this->table(
            ['Total', 'Partenaires', 'Chauffeurs', 'Migrés', 'Déjà sync', 'Ignorés', 'Échecs'],
            [[
                $stats['total'],
                $stats['partners'],
                $stats['drivers'],
                $stats['migrated'],
                $stats['already_synced'],
                $stats['skipped'],
                $stats['failed'],
            ]]
        );

        if (! empty($migratedRows)) {
            $this->newLine();
            $this->info('Utilisateurs migrés / pré-provisionnés');
            $this->table(
                ['User ID', 'Type', 'Username', 'Keycloak ID', 'Rôle', 'Action'],
                $migratedRows
            );
        }

        if (! empty($skippedRows)) {
            $this->newLine();
            $this->warn('Utilisateurs ignorés');
            $this->table(
                ['User ID', 'Type', 'Username', 'Raison'],
                $skippedRows
            );
        }

        if (! empty($failedRows)) {
            $this->newLine();
            $this->error('Utilisateurs échoués');
            $this->table(
                ['User ID', 'Type', 'Username', 'Erreur'],
                $failedRows
            );
        }

        Log::info('[KEYCLOAK_MIGRATE_USERS_DONE]', [
            'stats' => $stats,
            'failed' => $failedRows,
            'skipped' => $skippedRows,
        ]);

        return $stats['failed'] > 0
            ? self::FAILURE
            : self::SUCCESS;
    }

    private function businessType(User $user): string
    {
        return empty($user->partner_id) ? 'PARTNER' : 'DRIVER';
    }

    private function compteId(User $user): int
    {
        return (int) ($user->partner_id ?: $user->id);
    }

    private function roleName(User $user): string
    {
        return empty($user->partner_id)
            ? 'gestionnaire_plateforme'
            : 'utilisateur_secondaire';
    }

    private function username(User $user): string
    {
        $username = trim((string) ($user->keycloak_username ?: ''));

        if ($username !== '') {
            return $username;
        }

        $phone = trim((string) ($user->phone ?: ''));

        if ($phone !== '') {
            return $phone;
        }

        $email = trim((string) ($user->email ?: ''));

        if ($email !== '') {
            return $email;
        }

        return '';
    }

    private function fullName(User $user): string
    {
        $name = trim((string) (
            $user->nom_complet
            ?? $user->full_name
            ?? trim(($user->prenom ?? '') . ' ' . ($user->nom ?? ''))
        ));

        return $name !== '' ? $name : '—';
    }
}