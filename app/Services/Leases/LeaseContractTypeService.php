<?php

namespace App\Services\Leases;

use Illuminate\Support\Collection;

class LeaseContractTypeService
{
    public function __construct(
        private readonly PartnerLeaseApiService $leaseApiService
    ) {}

    public function getAllContractTypes(): Collection
    {
        return collect($this->leaseApiService->fetchContractTypes())->values();
    }

    public function getMainContractTypes(): Collection
    {
        return $this->getAllContractTypes()
            ->filter(fn ($type) => ! empty($type['is_main']))
            ->values();
    }

    public function getSubContractTypes(): Collection
    {
        return $this->getAllContractTypes()
            ->filter(fn ($type) => empty($type['is_main']))
            ->values();
    }

    public function createContractType(array $payload): array
    {
        return $this->leaseApiService->createContractType([
            'libelle' => $payload['libelle'],
            'code' => $payload['code'] ?? null,
            'est_principal' => (bool) $payload['est_principal'],
        ]);
    }
}