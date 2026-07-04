<?php

namespace App\Observers;

use App\Models\AssociationUserVoiture;
use App\Services\DashboardCacheService;

/**
 * AssociationUserVoitureObserver — NOUVEAU FICHIER
 *
 * PROBLÈME RÉSOLU :
 *   partnerVehicleIds() met en cache la liste des véhicules d'un partenaire
 *   avec un TTL de 600s. Si une association est créée ou supprimée, le cache
 *   stale reste actif jusqu'à 10 minutes — un véhicule retiré continue d'être
 *   visible dans le dashboard du partenaire, et un véhicule ajouté n'apparaît pas.
 *
 * Ce observer invalide immédiatement le cache dès qu'une association change,
 * puis force un rebuild de la flotte pour que le front soit notifié via SSE.
 *
 * ENREGISTREMENT dans AppServiceProvider ou ObserverServiceProvider :
 *   use App\Models\AssociationUserVoiture;
 *   use App\Observers\AssociationUserVoitureObserver;
 *   AssociationUserVoiture::observe(AssociationUserVoitureObserver::class);
 *
 * Architecture actuelle respectée :
 *   user_id dans AssociationUserVoiture = id du partenaire (User avec partner_id NULL)
 */
class AssociationUserVoitureObserver
{
    public function __construct(private DashboardCacheService $cache) {}

    /**
     * Un véhicule est ajouté au partenaire.
     * Invalider le cache vehicle_ids + rebuild fleet pour notification SSE.
     */
    public function created(AssociationUserVoiture $association): void
    {
        $partnerId = (int) $association->user_id;
        $this->refreshPartnerFleet($partnerId);
    }

    /**
     * Un véhicule est retiré du partenaire.
     * Invalider le cache vehicle_ids + rebuild fleet pour notification SSE.
     */
    public function deleted(AssociationUserVoiture $association): void
    {
        $partnerId = (int) $association->user_id;
        $this->refreshPartnerFleet($partnerId);
    }

    /**
     * Invalide le cache vehicle_ids et rebuild la flotte.
     * rebuildFleet() émet fleet.reset via SSE → le front voit immédiatement
     * la liste mise à jour.
     */
    private function refreshPartnerFleet(int $partnerId): void
    {
        // Invalide le cache vehicle_ids pour forcer la relecture en DB
        $this->cache->invalidateVehicleIds($partnerId);

        // Rebuild fleet complet → markFleetResetDirty + bumpVersion
        // Le front recevra fleet.reset via SSE dans le prochain cycle (< 1s)
        $this->cache->rebuildFleet($partnerId);

        // Rebuild stats pour mettre à jour vehiclesCount et associationsCount
        $this->cache->rebuildStats($partnerId);
    }
}