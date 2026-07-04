<?php

namespace App\Services\Leases;

use App\Models\LeaseContractLink;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service LeaseContractLinkService.
 *
 * Contexte métier :
 * Recouvrement est responsable des contrats, sous-contrats, échéances et paiements.
 * Tracking est responsable des véhicules, règles de coupure, GPS et historique.
 *
 * Problème à résoudre :
 * Quand recouvrement retourne un contrat ou un sous-contrat impayé, Tracking doit
 * savoir quel véhicule local est concerné. Cette information n'est pas toujours
 * suffisante dans l'objet recouvrement, surtout pour les sous-contrats.
 *
 * Rôle de ce service :
 * Maintenir une table de liaison locale entre :
 * - le contrat recouvrement ;
 * - le contrat parent si c'est un sous-contrat ;
 * - le véhicule Tracking ;
 * - le chauffeur local si disponible ;
 * - le type de contrat.
 */
class LeaseContractLinkService
{
    /**
     * Retourne l'identifiant du partenaire propriétaire.
     *
     * Règle actuelle de ton projet :
     * - un partenaire a users.partner_id = null ;
     * - un utilisateur secondaire/chauffeur a users.partner_id = id du partenaire.
     */
    public function resolvePartnerId(User $user): int
    {
        return (int) ($user->partner_id ?: $user->id);
    }

    /**
     * Synchronise les liens locaux après création ou modification d'un contrat.
     *
     * Fonctionnement :
     * 1. on reçoit le payload envoyé à recouvrement ;
     * 2. on reçoit la réponse recouvrement ;
     * 3. on crée/met à jour le lien du contrat principal ;
     * 4. on crée/met à jour les liens des sous-contrats si l'API les retourne.
     *
     * Important :
     * Si l'API ne retourne pas encore les sous-contrats avec leurs IDs, on garde
     * au moins le lien du contrat principal. Les sous-contrats pourront être
     * synchronisés au prochain GET /contrats/.
     */
    public function syncAfterContractWrite(
        User $actor,
        int $vehicleId,
        array $payload,
        array $apiResponse
    ): void {
        $partnerId = $this->resolvePartnerId($actor);
        $sourceContractId = $this->extractContractId($apiResponse);

        if ($sourceContractId <= 0) {
            Log::warning('[LEASE_CONTRACT_LINK_SYNC_SKIPPED_NO_SOURCE_ID]', [
                'partner_id' => $partnerId,
                'vehicle_id' => $vehicleId,
                'api_response_keys' => array_keys($apiResponse),
            ]);

            return;
        }

        DB::transaction(function () use ($actor, $partnerId, $vehicleId, $payload, $apiResponse, $sourceContractId) {
            $driver = $this->findLocalDriverFromRecouvrementId($payload['chauffeur'] ?? null, $partnerId);

            $mainLink = $this->upsertLink(
                actor: $actor,
                partnerId: $partnerId,
                vehicleId: $vehicleId,
                driver: $driver,
                sourceContractId: $sourceContractId,
                sourceParentContractId: null,
                contractKind: LeaseContractLink::KIND_MAIN,
                row: $apiResponse,
                payload: $payload
            );

            $subRows = $this->extractSubContracts($apiResponse);

            foreach ($subRows as $subRow) {
                $subContractId = $this->extractContractId($subRow);

                if ($subContractId <= 0) {
                    continue;
                }

                $this->upsertLink(
                    actor: $actor,
                    partnerId: $partnerId,
                    vehicleId: $vehicleId,
                    driver: $driver,
                    sourceContractId: $subContractId,
                    sourceParentContractId: $mainLink->source_contract_id,
                    contractKind: LeaseContractLink::KIND_SUB,
                    row: $subRow,
                    payload: $payload
                );
            }
        });
    }

    /**
     * Synchronise une liste de contrats venant de GET /contrats/.
     *
     * Rôle :
     * Permet de reconstruire les liens locaux même si le contrat a été créé
     * depuis un autre écran ou si les sous-contrats n'étaient pas présents dans
     * la réponse POST initiale.
     */
    public function syncFetchedContracts(User $actor, array $contracts): void
    {
        $partnerId = $this->resolvePartnerId($actor);

        $rows = collect($contracts)
            ->filter(fn ($row) => is_array($row))
            ->values();

        $byId = $rows->keyBy(fn (array $contract) => $this->extractContractId($contract));

        DB::transaction(function () use ($actor, $partnerId, $rows, $byId) {
            foreach ($rows as $contract) {
                $raw = $contract['raw'] ?? $contract;
                $sourceContractId = $this->extractContractId($contract);
                $sourceParentContractId = $this->extractParentContractId($contract);

                if ($sourceContractId <= 0) {
                    continue;
                }

                $parentRow = $sourceParentContractId > 0 ? $byId->get($sourceParentContractId) : null;

                /**
                 * Pour un sous-contrat, recouvrement peut renvoyer immatriculation
                 * ou vin à null. Tracking hérite alors du véhicule du parent.
                 */
                $vehicleId = (int) ($contract['vehicle_id'] ?? 0);

                if ($vehicleId <= 0) {
                    $vehicleId = $this->findVehicleIdByImmatriculation((string) (
                        $contract['vehicule']
                        ?? $raw['immatriculation']
                        ?? $parentRow['vehicule']
                        ?? data_get($parentRow, 'raw.immatriculation')
                        ?? ''
                    )) ?: 0;
                }

                if ($vehicleId <= 0) {
                    Log::warning('[LEASE_CONTRACT_LINK_SYNC_SKIPPED_NO_VEHICLE]', [
                        'partner_id' => $partnerId,
                        'source_contract_id' => $sourceContractId,
                        'source_parent_contract_id' => $sourceParentContractId,
                        'immatriculation' => $contract['vehicule'] ?? $raw['immatriculation'] ?? null,
                    ]);

                    continue;
                }

                $driver = $this->findLocalDriverFromRecouvrementId(
                    $contract['chauffeur_id'] ?? $raw['chauffeur'] ?? data_get($parentRow, 'chauffeur_id') ?? data_get($parentRow, 'raw.chauffeur') ?? null,
                    $partnerId
                );

                $this->upsertLink(
                    actor: $actor,
                    partnerId: $partnerId,
                    vehicleId: $vehicleId,
                    driver: $driver,
                    sourceContractId: $sourceContractId,
                    sourceParentContractId: $sourceParentContractId > 0 ? $sourceParentContractId : null,
                    contractKind: $sourceParentContractId > 0 ? LeaseContractLink::KIND_SUB : LeaseContractLink::KIND_MAIN,
                    row: $raw,
                    payload: []
                );

                /**
                 * Si le parent contient déjà sous_contrats[], on les synchronise
                 * aussi. Le unique(partner_id, source_contract_id) évite les doublons.
                 */
                foreach (($contract['sub_contracts'] ?? $contract['sous_contrats'] ?? []) as $sub) {
                    if (! is_array($sub)) {
                        continue;
                    }

                    $subSourceId = $this->extractContractId($sub);

                    if ($subSourceId <= 0) {
                        continue;
                    }

                    $this->upsertLink(
                        actor: $actor,
                        partnerId: $partnerId,
                        vehicleId: $vehicleId,
                        driver: $driver,
                        sourceContractId: $subSourceId,
                        sourceParentContractId: $sourceContractId,
                        contractKind: LeaseContractLink::KIND_SUB,
                        row: $sub['raw'] ?? $sub,
                        payload: []
                    );
                }
            }
        });
    }

    private function extractParentContractId(array $contract): int
    {
        /**
         * Recouvrement peut retourner parent sous plusieurs formes :
         * - parent: 37
         * - parent: {id: 37, ...}
         * - raw.parent: 37
         * - raw.parent: {id: 37, ...}
         *
         * Il ne faut jamais caster directement un tableau en int, car PHP
         * transforme un tableau non vide en 1, ce qui casse la liaison parent.
         */
        $parent = $contract['parent']
            ?? $contract['source_parent_contract_id']
            ?? data_get($contract, 'raw.parent')
            ?? null;

        if (is_array($parent)) {
            return (int) ($parent['id'] ?? 0);
        }

        return (int) ($parent ?: data_get($contract, 'raw.parent.id') ?: 0);
    }

    /**
     * Synchronise un sous-contrat créé séparément à partir de son contrat parent.
     *
     * Utilisé quand la vue envoie parent=<id contrat principal> et que le sous-
     * contrat n'a pas besoin de vehicle_id pour recouvrement. Tracking retrouve
     * le véhicule local via lease_contract_links du parent.
     */
    public function syncSubContractFromParent(
        User $actor,
        int $parentContractId,
        array $payload,
        array $apiResponse
    ): void {
        $partnerId = $this->resolvePartnerId($actor);
        $sourceContractId = $this->extractContractId($apiResponse);

        if ($sourceContractId <= 0 || $parentContractId <= 0) {
            Log::warning('[LEASE_SUB_CONTRACT_LINK_SYNC_SKIPPED_INVALID_IDS]', [
                'partner_id' => $partnerId,
                'parent_contract_id' => $parentContractId,
                'source_contract_id' => $sourceContractId,
                'api_response_keys' => array_keys($apiResponse),
            ]);

            return;
        }

        $parentLink = LeaseContractLink::query()
            ->where('partner_id', $partnerId)
            ->where('source_contract_id', $parentContractId)
            ->first();

        if (! $parentLink) {
            Log::warning('[LEASE_SUB_CONTRACT_LINK_SYNC_SKIPPED_PARENT_NOT_FOUND]', [
                'partner_id' => $partnerId,
                'parent_contract_id' => $parentContractId,
                'source_contract_id' => $sourceContractId,
            ]);

            return;
        }

        $driver = $this->findLocalDriverFromRecouvrementId(
            $payload['chauffeur'] ?? $apiResponse['chauffeur'] ?? $parentLink->recouvrement_driver_id,
            $partnerId
        );

        $this->upsertLink(
            actor: $actor,
            partnerId: $partnerId,
            vehicleId: (int) $parentLink->vehicle_id,
            driver: $driver,
            sourceContractId: $sourceContractId,
            sourceParentContractId: $parentContractId,
            contractKind: LeaseContractLink::KIND_SUB,
            row: $apiResponse,
            payload: $payload
        );
    }

    /**
     * Désactive localement un lien contrat après suppression côté recouvrement.
     *
     * On ne supprime pas forcément la ligne afin de conserver une trace locale.
     */
    public function markContractDeleted(User $actor, int $sourceContractId): void
    {
        $partnerId = $this->resolvePartnerId($actor);

        LeaseContractLink::query()
            ->where('partner_id', $partnerId)
            ->where(function ($query) use ($sourceContractId) {
                $query->where('source_contract_id', $sourceContractId)
                    ->orWhere('source_parent_contract_id', $sourceContractId);
            })
            ->update([
                'status' => 'DELETED',
                'updated_by' => $actor->id,
                'updated_at' => now(),
            ]);
    }

    /**
     * Crée ou met à jour un lien local.
     */
    private function upsertLink(
        User $actor,
        int $partnerId,
        int $vehicleId,
        ?User $driver,
        int $sourceContractId,
        ?int $sourceParentContractId,
        string $contractKind,
        array $row,
        array $payload
    ): LeaseContractLink {
        $typeId = $this->extractTypeContractId($row, $payload);
        $typeLabel = $this->extractTypeContractLabel($row, $payload, $typeId);

        $immatriculation = (string) (
            $row['immatriculation']
            ?? Arr::get($row, 'vehicule.immatriculation')
            ?? $payload['immatriculation']
            ?? ''
        );

        return LeaseContractLink::updateOrCreate(
            [
                'partner_id' => $partnerId,
                'source_contract_id' => $sourceContractId,
            ],
            [
                'vehicle_id' => $vehicleId,
                'driver_id' => $driver?->id,
                'recouvrement_driver_id' => (int) ($row['chauffeur'] ?? $payload['chauffeur'] ?? $driver?->recouvrement_driver_id ?? 0) ?: null,
                'source_parent_contract_id' => $sourceParentContractId,
                'contract_kind' => $contractKind,
                'type_contrat_id' => $typeId ?: null,
                'type_contrat_label' => $typeLabel ?: null,
                'immatriculation' => $immatriculation,
                'vin' => (string) ($row['vin'] ?? $payload['vin'] ?? ''),
                'status' => (string) ($row['statut'] ?? $row['status'] ?? 'ACTIVE'),
                'last_payment_status' => (string) ($row['payment_status'] ?? $row['statut_paiement'] ?? ''),
                'last_snapshot' => $row,
                'last_payload' => $payload ?: null,
                'last_synced_at' => now(),
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]
        );
    }

    private function extractContractId(array $response): int
    {
        return (int) (
            $response['id']
            ?? $response['source_contract_id']
            ?? $response['source_contrat_id']
            ?? data_get($response, 'data.id')
            ?? data_get($response, 'contrat.id')
            ?? 0
        );
    }

    private function extractSubContracts(array $response): array
    {
        $rows = $response['sous_contrats']
            ?? $response['sousContrats']
            ?? $response['sub_contracts']
            ?? data_get($response, 'data.sous_contrats')
            ?? [];

        return is_array($rows) ? $rows : [];
    }

    private function findLocalDriverFromRecouvrementId(mixed $recouvrementDriverId, int $partnerId): ?User
    {
        $id = (int) $recouvrementDriverId;

        if ($id <= 0) {
            return null;
        }

        return User::query()
            ->where('partner_id', $partnerId)
            ->where('recouvrement_driver_id', $id)
            ->first();
    }

    private function findVehicleIdByImmatriculation(string $immatriculation): ?int
    {
        $immat = $this->normalizeImmatriculation($immatriculation);

        if ($immat === '') {
            return null;
        }

        $id = DB::table('voitures')
            ->whereRaw('REPLACE(UPPER(immatriculation), " ", "") = ?', [$immat])
            ->value('id');

        return $id ? (int) $id : null;
    }

    private function extractTypeContractId(array $row, array $payload = []): int
    {
        $type = $row['type_contrat']
            ?? Arr::get($row, 'raw.type_contrat')
            ?? $payload['type_contrat']
            ?? null;

        if (is_array($type)) {
            return (int) ($type['id'] ?? 0);
        }

        return (int) ($type ?: Arr::get($row, 'type_contrat.id') ?: 0);
    }

    private function extractTypeContractLabel(array $row, array $payload = [], int $typeId = 0): string
    {
        $type = $row['type_contrat'] ?? Arr::get($row, 'raw.type_contrat');

        $label = is_array($type)
            ? (string) ($type['libelle'] ?? $type['label'] ?? $type['nom'] ?? '')
            : '';

        $label = $label
            ?: (string) ($row['type_contrat_label'] ?? '')
            ?: (string) ($payload['type_contrat_label'] ?? '')
            ?: (string) Arr::get($row, 'raw.type_contrat.libelle', '');

        return trim($label) !== '' ? trim($label) : ($typeId > 0 ? 'Type #' . $typeId : '');
    }

    private function normalizeImmatriculation(?string $value): string
    {
        return strtoupper(preg_replace('/\s+/', '', trim((string) $value)));
    }
}
