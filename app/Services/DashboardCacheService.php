<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\AssociationChauffeurVoiturePartner;
use App\Models\AssociationUserVoiture;
use App\Models\GeofenceZone;
use App\Models\Location;
use App\Models\User;
use App\Models\Voiture;
use Carbon\Carbon;
use Illuminate\Support\Facades\Redis;

/**
 * DashboardCacheService — version corrigée
 *
 * Architecture actuelle :
 * - partenaire = User avec partner_id NULL
 * - chauffeur  = User avec partner_id = id du partenaire
 */
class DashboardCacheService
{
    private int $ttlStats  = 900;
    private int $ttlFleet  = 600;
    private int $ttlAlerts = 600;

    private int $gpsOfflineMinutes;
    private float $movingThreshold = 5.0;

    public function __construct()
    {
        // 18GPS documentation: online/offline is judged with a 25-minute signal threshold.
        $this->gpsOfflineMinutes = (int) config('gps.offline_threshold_minutes', 25);
    }

    private function kStats(int $partnerId): string      { return "dash:p:$partnerId:stats"; }
    private function kFleetH(int $partnerId): string     { return "dash:p:$partnerId:fleet:h"; }
    private function kAlerts(int $partnerId): string     { return "dash:p:$partnerId:alerts"; }
    private function kVersion(int $partnerId): string    { return "dash:p:$partnerId:version"; }
    private function kDebounce(int $partnerId): string   { return "dash:p:$partnerId:debounce"; }
    private function kAlertsLock(int $partnerId): string { return "dash:p:$partnerId:alerts:lock"; }
    private function kVehicleIds(int $partnerId): string { return "dash:p:$partnerId:vehicle_ids"; }
    private function kAssocCheckLock(int $partnerId): string
{
    return "dash:p:$partnerId:assoc:check:lock";
}
    private function kFleetReset(int $partnerId): string { return "dash:p:$partnerId:fleet:reset"; }
    private function kDriverSignature(int $partnerId): string { return "dash:p:$partnerId:drivers:sig"; }

    private function kDirtyVehicles(int $partnerId): string { return "dash:p:$partnerId:dirty:vehicles"; }
    private function kDirtyAlerts(int $partnerId): string   { return "dash:p:$partnerId:dirty:alerts"; }
    private function kDirtyStats(int $partnerId): string    { return "dash:p:$partnerId:dirty:stats"; }

    public function getVersion(int $partnerId): int
    {
        return (int) (Redis::get($this->kVersion($partnerId)) ?? 0);
    }

    public function bumpVersion(int $partnerId): void
    {
        Redis::incr($this->kVersion($partnerId));
    }

    public function bumpVersionDebounced(int $partnerId, int $seconds = 1): void
    {
        $ok = Redis::set($this->kDebounce($partnerId), '1', 'EX', $seconds, 'NX');
        if ($ok) {
            $this->bumpVersion($partnerId);
        }
    }

    public function shouldRefreshAlertsNow(int $partnerId, int $seconds = 2): bool
    {
        return (bool) Redis::set($this->kAlertsLock($partnerId), '1', 'EX', $seconds, 'NX');
    }

    public function partnerVehicleIds(int $partnerId): array
    {
        $cached = Redis::get($this->kVehicleIds($partnerId));
        if ($cached) {
            $arr = json_decode($cached, true);
            if (is_array($arr)) {
                return array_values(array_unique(array_map('intval', $arr)));
            }
        }

        $ids = AssociationUserVoiture::query()
            ->join('users', 'users.id', '=', 'association_user_voitures.user_id')
            ->whereNull('users.partner_id')
            ->where('association_user_voitures.user_id', $partnerId)
            ->pluck('association_user_voitures.voiture_id')
            ->map(fn ($x) => (int) $x)
            ->unique()
            ->values()
            ->all();

        Redis::setex($this->kVehicleIds($partnerId), $this->ttlFleet, json_encode($ids, JSON_UNESCAPED_UNICODE));

        return $ids;
    }

    public function invalidateVehicleIds(int $partnerId): void
    {
        Redis::del($this->kVehicleIds($partnerId));
    }


    public function shouldCheckExternalAssociationsNow(int $partnerId, int $seconds = 5): bool
{
    return (bool) Redis::set(
        $this->kAssocCheckLock($partnerId),
        '1',
        'EX',
        max(1, $seconds),
        'NX'
    );
}

/**
 * Lit les vrais véhicules du partenaire directement depuis MySQL,
 * sans utiliser le cache Redis.
 */
public function freshPartnerVehicleIdsFromDb(int $partnerId): array
{
    return AssociationUserVoiture::query()
        ->join('users', 'users.id', '=', 'association_user_voitures.user_id')
        ->whereNull('users.partner_id')
        ->where('association_user_voitures.user_id', $partnerId)
        ->pluck('association_user_voitures.voiture_id')
        ->map(fn ($x) => (int) $x)
        ->unique()
        ->values()
        ->all();
}

/**
 * Empreinte des affectations chauffeur -> véhicule d'un partenaire.
 *
 * Pourquoi : le contrôle de fraîcheur ne comparait que la LISTE des véhicules.
 * Changer le chauffeur d'un véhicule déjà présent ne modifiait pas cette liste,
 * donc rien ne le détectait — et comme la durée de vie du cache est remise à
 * zéro toutes les 30 s par dashboard:refresh-offline-statuses, le filet
 * d'expiration ne se déclenchait jamais non plus. Résultat : une affectation
 * pouvait rester invisible indéfiniment, y compris faite depuis cette
 * application (aucun observateur n'écoute cette table).
 *
 * L'empreinte combine trois valeurs pour couvrir tous les cas :
 *  - le NOMBRE d'affectations       -> ajout ou suppression ;
 *  - le plus grand identifiant      -> une suppression suivie d'un ajout, qui
 *                                      laisserait le nombre inchangé ;
 *  - la dernière date de mise à jour -> changement de chauffeur sur une ligne
 *                                      existante.
 *
 * Une seule requête d'agrégat sur une petite table : le coût est négligeable
 * devant le chargement de page qu'elle rend juste.
 *
 * @param array<int> $vehicleIds véhicules du partenaire, déjà résolus
 */
/**
 * Mémorise l'empreinte des affectations qu'on vient de reconstruire.
 *
 * SANS DURÉE DE VIE, volontairement. Une empreinte qui expire alors que le
 * hash de flotte, lui, n'expire jamais — sa durée de vie étant remise à zéro
 * toutes les 30 s par dashboard:refresh-offline-statuses — provoquerait une
 * reconstruction complète de la flotte à intervalle régulier, dans un worker
 * web, sur un serveur qui n'en a que cinq pour quatre sites.
 *
 * La clé est minuscule et réécrite à chaque reconstruction ; la laisser
 * survivre ne coûte rien.
 */
private function storeDriverSignature(int $partnerId, array $vehicleIds): void
{
    try {
        Redis::set($this->kDriverSignature($partnerId), $this->driverAssignmentsSignature($vehicleIds));
    } catch (\Throwable $e) {
        // Sans empreinte, on paiera une reconstruction de plus au prochain
        // chargement : jamais un affichage faux.
    }
}

public function driverAssignmentsSignature(array $vehicleIds): string
{
    if (empty($vehicleIds)) {
        return 'vide';
    }

    $row = AssociationChauffeurVoiturePartner::query()
        ->whereIn('voiture_id', $vehicleIds)
        ->selectRaw('COUNT(*) AS n, COALESCE(MAX(id), 0) AS dernier, COALESCE(MAX(updated_at), 0) AS maj')
        ->first();

    return sprintf(
        '%d|%d|%s',
        (int) ($row->n ?? 0),
        (int) ($row->dernier ?? 0),
        (string) ($row->maj ?? '0')
    );
}

/**
 * Vérifie si Redis correspond encore à la vraie association en base.
 *
 * Cas traité :
 * - véhicule nouvellement associé au partenaire ;
 * - véhicule retiré du partenaire ;
 * - véhicule déplacé d’un partenaire A vers un partenaire B depuis un autre projet.
 */
public function ensurePartnerAssociationsFresh(int $partnerId): bool
{
    $freshIds = $this->freshPartnerVehicleIdsFromDb($partnerId);
    $cachedIds = $this->partnerVehicleIds($partnerId);

    sort($freshIds);
    sort($cachedIds);

    $listeChangee = ($freshIds !== $cachedIds);

    /*
     * Deuxième contrôle, indépendant du premier : les affectations de
     * chauffeur. Un changement de chauffeur sur un véhicule déjà présent ne
     * modifie pas la liste ci-dessus et passait donc totalement inaperçu.
     */
    $signature = $this->driverAssignmentsSignature($freshIds);
    $chauffeursChanges = ($signature !== (string) (Redis::get($this->kDriverSignature($partnerId)) ?? ''));

    if (! $listeChangee && ! $chauffeursChanges) {
        return false;
    }

    // Les statistiques ne dépendent que de la composition de la flotte : inutile
    // de les recalculer quand seul un chauffeur a changé.
    if ($listeChangee) {
        $this->invalidateVehicleIds($partnerId);
        $this->rebuildStats($partnerId);
    }

    $this->rebuildFleet($partnerId);

    return true;
}

    public function partnerIdsForVehicle(int $voitureId): array
    {
        return AssociationUserVoiture::query()
            ->where('voiture_id', $voitureId)
            ->join('users', 'users.id', '=', 'association_user_voitures.user_id')
            ->whereNull('users.partner_id')
            ->pluck('association_user_voitures.user_id')
            ->map(fn ($x) => (int) $x)
            ->unique()
            ->values()
            ->all();
    }

    public function getStatsFromRedis(int $partnerId): ?array
    {
        $json = Redis::get($this->kStats($partnerId));
        return $json ? json_decode($json, true) : null;
    }

    public function getAlertsFromRedis(int $partnerId): array
    {
        $json = Redis::get($this->kAlerts($partnerId));
        return $json ? (json_decode($json, true) ?: []) : [];
    }

    public function getFleetFromRedis(int $partnerId): array
    {
        try {
            $all = Redis::hgetall($this->kFleetH($partnerId));
            if (is_array($all) && !empty($all)) {
                $out = [];
                foreach ($all as $json) {
                    $row = json_decode($json, true);
                    if (is_array($row)) {
                        $out[] = $this->applyDynamicLiveStatusOnRow($row);
                    }
                }
                return $out;
            }
        } catch (\Throwable) {
        }

        return [];
    }

    public function getFleetVehicleRowFromRedis(int $partnerId, int $vehicleId): ?array
    {
        try {
            $json = Redis::hget($this->kFleetH($partnerId), (string) $vehicleId);
            if (!$json) {
                return null;
            }
            $row = json_decode($json, true);
            return is_array($row) ? $row : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function consumeDirtyVehicleRows(int $partnerId): array
    {
        $key = $this->kDirtyVehicles($partnerId);
        $ids = Redis::smembers($key);

        if (empty($ids)) {
            return [];
        }

        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (empty($ids)) {
            Redis::del($key);
            return [];
        }

        $rows = Redis::pipeline(function ($pipe) use ($partnerId, $ids) {
            foreach ($ids as $id) {
                $pipe->hget($this->kFleetH($partnerId), (string) $id);
            }
            $pipe->del($this->kDirtyVehicles($partnerId));
        });

        $out = [];
        $countRows = count($ids);

        for ($i = 0; $i < $countRows; $i++) {
            $json = $rows[$i] ?? null;
            if (!$json) {
                continue;
            }

            $row = json_decode($json, true);
            if (is_array($row)) {
                $out[] = $this->applyDynamicLiveStatusOnRow($row);
            }
        }

        return $out;
    }

    public function consumeDirtyAlerts(int $partnerId): ?array
    {
        $flag = Redis::get($this->kDirtyAlerts($partnerId));
        if (!$flag) {
            return null;
        }

        $alerts = $this->getAlertsFromRedis($partnerId);
        Redis::del($this->kDirtyAlerts($partnerId));

        return $alerts;
    }

    public function consumeDirtyStats(int $partnerId): ?array
    {
        $flag = Redis::get($this->kDirtyStats($partnerId));
        if (!$flag) {
            return null;
        }

        $stats = $this->getStatsFromRedis($partnerId);
        Redis::del($this->kDirtyStats($partnerId));

        return $stats;
    }

    public function markFleetResetDirty(int $partnerId): void
    {
        Redis::setex($this->kFleetReset($partnerId), 60, '1');
    }

    public function consumeFleetReset(int $partnerId): bool
    {
        $flag = Redis::get($this->kFleetReset($partnerId));
        if (!$flag) {
            return false;
        }

        Redis::del($this->kFleetReset($partnerId));
        return true;
    }

    private function markVehiclesDirty(int $partnerId, array $vehicleIds): void
    {
        $vehicleIds = array_values(array_unique(array_map('intval', $vehicleIds)));
        if (empty($vehicleIds)) {
            return;
        }

        Redis::pipeline(function ($pipe) use ($partnerId, $vehicleIds) {
            foreach ($vehicleIds as $id) {
                $pipe->sadd($this->kDirtyVehicles($partnerId), (string) $id);
            }
            $pipe->expire($this->kDirtyVehicles($partnerId), $this->ttlFleet);
        });
    }

    private function markAlertsDirty(int $partnerId): void
    {
        Redis::setex($this->kDirtyAlerts($partnerId), 60, '1');
    }

    private function markStatsDirty(int $partnerId): void
    {
        Redis::setex($this->kDirtyStats($partnerId), 60, '1');
    }

    public function rebuildStats(int $partnerId): array
    {
        $driversCount = User::query()
            ->where('partner_id', $partnerId)
            ->count();

        $vehicleIds = $this->partnerVehicleIds($partnerId);
        $vehiclesCount = count($vehicleIds);

        $associationsCount = AssociationUserVoiture::query()
            ->join('users', 'users.id', '=', 'association_user_voitures.user_id')
            ->whereNull('users.partner_id')
            ->where('association_user_voitures.user_id', $partnerId)
            ->count();

        $alertsCount = 0;
        $alertsByType = [
            'stolen'    => 0,
            'geofence'  => 0,
            'safe_zone' => 0,
            'speed'     => 0,
            'time_zone' => 0,
            'unknown'   => 0,
        ];

        if (!empty($vehicleIds)) {
            $start = now()->startOfDay();
            $end   = now()->endOfDay();

            $baseOpenToday = Alert::query()
                ->whereIn('voiture_id', $vehicleIds)
                ->partnerVisible()
                ->where(function ($q) {
                    $q->where('processed', 0)->orWhereNull('processed');
                })
                ->where(function ($q) use ($start, $end) {
                    $q->whereBetween('alerted_at', [$start, $end])
                        ->orWhere(function ($qq) use ($start, $end) {
                            $qq->whereNull('alerted_at')
                                ->whereBetween('created_at', [$start, $end]);
                        });
                });

            $alertsCount = (clone $baseOpenToday)->count();

            $rows = (clone $baseOpenToday)
                ->selectRaw("COALESCE(alert_type, 'unknown') as t, COUNT(*) as c")
                ->groupBy('t')
                ->get();

            foreach ($rows as $r) {
                $norm = $this->normalizeAlertType((string) $r->t);
                $alertsByType[$norm] = ($alertsByType[$norm] ?? 0) + (int) $r->c;
            }
        }

        $payload = [
            'usersCount'        => (int) $driversCount,
            'vehiclesCount'     => (int) $vehiclesCount,
            'associationsCount' => (int) $associationsCount,
            'alertsCount'       => (int) $alertsCount,
            'alertsByType'      => $alertsByType,
        ];

        Redis::setex($this->kStats($partnerId), $this->ttlStats, json_encode($payload, JSON_UNESCAPED_UNICODE));
        $this->markStatsDirty($partnerId);
        $this->bumpVersionDebounced($partnerId, 1);

        return $payload;
    }
public function rebuildFleet(int $partnerId): array
    {
        $vehicleIds = $this->partnerVehicleIds($partnerId);

        if (empty($vehicleIds)) {
            Redis::pipeline(function ($pipe) use ($partnerId) {
                $pipe->del($this->kFleetH($partnerId));
                $pipe->del($this->kDirtyVehicles($partnerId));
            });

            /*
             * Mémoriser l'empreinte AVANT de sortir. Sans cela, un partenaire
             * sans véhicule n'en avait jamais : le contrôle de fraîcheur la
             * trouvait toujours différente et reconstruisait la flotte à chaque
             * chargement de page ET toutes les 5 secondes dans la boucle temps
             * réel — soit des centaines de diffusions par heure pour un tableau
             * de bord vide.
             */
            $this->storeDriverSignature($partnerId, []);

            $this->markFleetResetDirty($partnerId);
            $this->bumpVersionDebounced($partnerId, 1);

            return [];
        }

        $voitures = Voiture::query()
            ->whereIn('id', $vehicleIds)
            ->select(['id', 'immatriculation', 'marque', 'model', 'mac_id_gps', 'geofence_zone'])
            ->get();

        $macIds = $voitures->pluck('mac_id_gps')->filter()->unique()->values()->all();

        $latestByMac = [];
        if (!empty($macIds)) {
            $sub = Location::query()
                ->selectRaw('MAX(id) as max_id, mac_id_gps')
                ->whereIn('mac_id_gps', $macIds)
                ->groupBy('mac_id_gps');

            $latestRows = Location::query()
                ->joinSub($sub, 't', function ($join) {
                    $join->on('locations.id', '=', 't.max_id');
                })
                ->select('locations.*')
                ->get();

            foreach ($latestRows as $loc) {
                $latestByMac[(string) $loc->mac_id_gps] = $loc->toArray();
            }
        }

        $assignmentsGrouped = AssociationChauffeurVoiturePartner::query()
            ->whereIn('voiture_id', $vehicleIds)
            ->whereHas('chauffeur', fn ($q) => $q->where('partner_id', $partnerId))
            ->with('chauffeur:id,prenom,nom,partner_id')
            ->orderByDesc('assigned_at')
            ->get()
            ->groupBy('voiture_id');

        $fleet = [];
        $hashPayload = [];

        foreach ($voitures as $v) {
            // A vehicle must remain visible even when it has never sent a GPS point.
            $loc = $latestByMac[(string) $v->mac_id_gps] ?? [];

            $chauffeurRow = $assignmentsGrouped->get((int) $v->id)?->first();
            $chauffeur = $chauffeurRow?->chauffeur;

            $row = $this->buildVehicleRowWithDriver($partnerId, $v, $loc, null, $chauffeur);
            if (!$row) {
                continue;
            }

            $fleet[] = $row;
            $hashPayload[(string) $v->id] = json_encode($row, JSON_UNESCAPED_UNICODE);
        }

        Redis::pipeline(function ($pipe) use ($partnerId, $hashPayload) {
            $pipe->del($this->kFleetH($partnerId));

            if (!empty($hashPayload)) {
                $pipe->hMSet($this->kFleetH($partnerId), $hashPayload);
                $pipe->expire($this->kFleetH($partnerId), $this->ttlFleet);
            }
        });

        /*
         * Démarrage à froid. La flotte vient d'être reconstruite depuis
         * `locations`, donc avec l'état déduit du dernier point enregistré : un
         * véhicule garé depuis des heures y paraît « hors ligne » ou « jamais
         * localisé », alors que son boîtier va très bien.
         *
         * On applique aussitôt le dernier relevé fournisseur DÉJÀ EN CACHE —
         * lecture seule, aucun appel réseau, donc aucun coût ajouté à la
         * requête web — pour que la page affiche l'état réel dès son premier
         * chargement, au lieu d'attendre le prochain balayage.
         *
         * Sans cela, chaque expiration du cache ramenait l'écran à l'état
         * trompeur d'avant ce chantier pendant une minute entière.
         */
        try {
            $live = app(GpsControlService::class)->getLiveFleetMap(false);

            if (! empty($live)) {
                $this->updateFleetFromLiveProviderMap($live, $partnerId);

                // Renvoyer ce que le cache contient RÉELLEMENT : sinon la page
                // afficherait les lignes d'avant correction.
                $corrige = $this->getFleetFromRedis($partnerId);
                if (! empty($corrige)) {
                    $fleet = $corrige;
                }
            }
        } catch (\Throwable $e) {
            // Correction d'affichage : son échec ne doit jamais empêcher la
            // reconstruction de la flotte d'aboutir.
        }

        /*
         * Mémoriser l'empreinte des affectations qu'on vient de reconstruire.
         * Sans cela, le contrôle de fraîcheur la trouverait toujours différente
         * et reconstruirait la flotte à CHAQUE chargement de page.
         *
         * Durée de vie volontairement plus longue que celle du hash : si
         * l'empreinte disparaissait la première, on paierait une reconstruction
         * inutile.
         */
        $this->storeDriverSignature($partnerId, $this->freshPartnerVehicleIdsFromDb($partnerId));

        $this->markFleetResetDirty($partnerId);
        $this->bumpVersionDebounced($partnerId, 1);

        return $fleet;
    }


    public function rebuildFleetForVehicleAssociations(Voiture $voiture): void
    {
        $partnerIds = AssociationUserVoiture::query()
            ->join('users', 'users.id', '=', 'association_user_voitures.user_id')
            ->whereNull('users.partner_id')
            ->where('association_user_voitures.voiture_id', $voiture->id)
            ->pluck('association_user_voitures.user_id')
            ->map(fn ($x) => (int) $x)
            ->unique()
            ->values()
            ->all();

        foreach ($partnerIds as $partnerId) {
            $this->rebuildFleet((int) $partnerId);
        }
    }

    public function updateVehicleFromLocation(int|Location $partnerIdOrLocation, ?Location $location = null, bool $bump = true): void
    {
        if ($partnerIdOrLocation instanceof Location) {
            $incomingLocation = $partnerIdOrLocation;
            $mac = trim((string) ($incomingLocation->mac_id_gps ?? ''));

            if ($mac === '') {
                return;
            }

            $vehicles = Voiture::query()
                ->where('mac_id_gps', $mac)
                ->get();

            if ($vehicles->isEmpty()) {
                return;
            }

            $vehicleIds = $vehicles->pluck('id')->map(fn ($x) => (int) $x)->all();

            $partnerIds = AssociationUserVoiture::query()
                ->join('users', 'users.id', '=', 'association_user_voitures.user_id')
                ->whereNull('users.partner_id')
                ->whereIn('association_user_voitures.voiture_id', $vehicleIds)
                ->pluck('association_user_voitures.user_id')
                ->map(fn ($x) => (int) $x)
                ->unique()
                ->values()
                ->all();

            foreach ($partnerIds as $partnerId) {
                $this->updateFleetBatchFromLocations((int) $partnerId, [$incomingLocation->toArray()], $bump);
            }

            return;
        }

        $partnerId = (int) $partnerIdOrLocation;

        if (!$location) {
            return;
        }

        $this->updateFleetBatchFromLocations($partnerId, [$location->toArray()], $bump);
    }

    /**
     * Rafraîchit la ligne de cache d'UN véhicule à partir d'un relevé live
     * 18gps (interrogation directe au clic sur une fiche), sans passer par la
     * table `locations`.
     *
     * Motivation : l'interrogation directe corrigeait bien la fiche affichée,
     * mais laissait le cache inchangé. Au premier événement temps réel reçu,
     * la fiche était réécrite depuis le cache — donc avec l'ancienne valeur —
     * ce qui produisait une bascule visible « hors ligne » / « en ligne ».
     * Mettre le cache à jour ici traite la cause et non le symptôme.
     *
     * `live_status` est recalculé par le MÊME constructeur que le chemin
     * normal : c'est lui qui fait autorité, applyDynamicLiveStatusOnRow()
     * réécrivant `gps.*` à partir de lui à chaque lecture. Écrire `gps.*`
     * seul serait silencieusement annulé.
     *
     * Donnée strictement dérivée : en cas d'échec on ne touche à rien, le
     * cycle de reconstruction normal reprendra la main.
     */
    /**
     * Applique un relevé fournisseur normalisé à une ligne de flotte, sous garde
     * d'horodatage.
     *
     * POURQUOI DEUX GARDES SÉPARÉES, et non une seule sur la ligne entière :
     * un véhicule garé a une position vieille de plusieurs heures mais un
     * battement de deux minutes. Rejeter tout le relevé parce que la position
     * est ancienne reviendrait à ne jamais rafraîchir son état — exactement le
     * défaut qu'on cherche à corriger. Position et état sont donc arbitrés
     * indépendamment, chacun sur SON horodatage.
     *
     * RÈGLE D'HYGIÈNE : heart_time ne se compare qu'à heart_time, datetime qu'à
     * datetime. Jamais à l'heure locale : l'horloge du fournisseur n'a aucune
     * raison d'être alignée sur la nôtre, et comparer les deux ferait basculer
     * la ligne à chaque écriture.
     *
     * @param array<string,mixed> $row    ligne de flotte telle qu'en cache
     * @param array<string,mixed> $sample relevé normalisé (GpsControlService)
     * @return array{0: array<string,mixed>, 1: bool} [ligne, changement visible]
     */
    /**
     * Une coordonnée tombe-t-elle dans la zone d'exploitation ?
     *
     * Mêmes bornes que l'ingestion Node, qui rejette déjà ces points avec la
     * raison « outside_operational_bounds ».
     */
    private function positionVraisemblable(float $lat, float $lon): bool
    {
        $b = (array) config('gps.bounds', []);

        return $lat >= (float) ($b['lat_min'] ?? 1.5)
            && $lat <= (float) ($b['lat_max'] ?? 13.5)
            && $lon >= (float) ($b['lon_min'] ?? 8.0)
            && $lon <= (float) ($b['lon_max'] ?? 16.5);
    }

    private function applyProviderSampleToRow(array $row, array $sample): array
    {
        $avant = [
            'ui'     => $row['live_status']['ui_status'] ?? null,
            'online' => $row['gps']['online'] ?? null,
            'lat'    => isset($row['lat']) ? round((float) $row['lat'], 5) : null,
            'lon'    => isset($row['lon']) ? round((float) $row['lon'], 5) : null,
        ];

        /* ---- Garde A : POSITION, arbitrée sur l'horodatage GPS ---- */
        $lat = $sample['latitude'] ?? null;
        $lon = $sample['longitude'] ?? null;
        $posMs = (int) ($sample['datetime_ms'] ?? 0);

        /*
         * pos_ts_ms est un champ neuf : les lignes déjà en cache ne l'ont pas.
         * On se rabat alors sur l'horodatage GPS déjà porté par live_status,
         * plutôt que de traiter l'absence comme un zéro — ce qui ferait
         * accepter, au tout premier balayage, une position plus ancienne que
         * celle affichée.
         */
        $posCacheMs = (int) ($row['pos_ts_ms'] ?? ($row['live_status']['datetime_ms'] ?? 0));

        $positionAcceptee = false;

        /*
         * Contrôle de vraisemblance géographique. Le fournisseur renvoie
         * parfois des coordonnées aberrantes — un boîtier de Yaoundé localisé
         * au Tchad, un autre à Shenzhen (la position d'usine du fabricant).
         * Tant que l'affichage venait de `locations`, ces points étaient déjà
         * filtrés par l'ingestion Node ; le balayage fournisseur, lui, les
         * écrivait tels quels.
         *
         * Seule la POSITION est rejetée : le battement du même relevé reste
         * valable et continue d'alimenter l'état.
         */
        if ($lat !== null && $lon !== null && ! $this->positionVraisemblable($lat, $lon)) {
            $lat = null;
            $lon = null;
        }

        if ($lat !== null && $lon !== null && $posMs > 0
            && ($posMs > $posCacheMs || ! isset($row['lat']) || $row['lat'] === null)) {
            $row['lat'] = $lat;
            $row['lon'] = $lon;
            $positionAcceptee = true;
        }

        /*
         * Ce marqueur ne doit JAMAIS reculer, y compris quand le relevé vient
         * d'être rejeté. Sinon la garde B, qui réécrit live_status juste après,
         * abaisserait la référence — et un relevé suivant, pourtant plus ancien
         * que la position affichée, redeviendrait acceptable : le véhicule
         * reculerait sur la carte.
         */
        if ($posMs > 0 || $posCacheMs > 0) {
            $row['pos_ts_ms'] = max($posCacheMs, $posMs);
        }

        /* ---- Garde B : ÉTAT, arbitré sur le battement ---- */
        $heartMs = (int) ($sample['heart_time_ms'] ?? 0);
        $precedent = (array) ($row['live_status'] ?? []);
        $heartCacheMs = (int) ($precedent['heart_time_ms'] ?? 0);
        $jamaisLocalise = (($precedent['ui_status'] ?? null) === 'NO_LOCATION');

        if ($heartMs > 0 && ($heartMs > $heartCacheMs || $heartCacheMs === 0 || $jamaisLocalise)) {
            /*
             * Même constructeur que le chemin normal : c'est live_status qui
             * fait autorité, applyDynamicLiveStatusOnRow() réécrivant gps.* à
             * partir de lui à chaque lecture. Écrire gps.* seul serait annulé.
             * On passe l'état précédent pour conserver la continuité des
             * chronomètres (arrêté depuis, hors ligne depuis).
             */
            /*
             * La vitesse est un attribut du POINT GPS, pas du battement. Si la
             * position vient d'être rejetée comme plus ancienne que l'affichée,
             * sa vitesse l'est tout autant : la reprendre ferait afficher
             * « en mouvement » sur un véhicule immobile depuis longtemps, ou
             * « statut inconnu » sur un boîtier qui bat sans avoir jamais émis
             * (le fournisseur renvoie alors su = -9). On reconduit donc la
             * dernière vitesse connue.
             */
            $aUnePosition = ($lat !== null && $lon !== null && $posMs > 0);

            /*
             * On ne se méfie de la vitesse que si le relevé PORTE une position
             * qu'on vient de rejeter comme périmée. Quand il n'en porte aucune,
             * il n'y a rien de périmé : le boîtier bat sans avoir jamais été
             * localisé, et sa vitesse décrit bien son état actuel.
             *
             * Écarter la vitesse dans ce second cas faisait basculer en
             * « statut inconnu » neuf véhicules pourtant déclarés en ligne et à
             * l'arrêt par le fournisseur.
             */
            $vitesse = ($aUnePosition && ! $positionAcceptee)
                ? ($precedent['speed'] ?? null)
                : ($sample['speed'] ?? null);

            // Même raison que pour pos_ts_ms : ne jamais abaisser l'horodatage
            // GPS, qui sert de référence à la garde de position.
            $gpsMs = max((int) ($sample['datetime_ms'] ?? 0), (int) ($precedent['datetime_ms'] ?? 0));

            $liveStatus = $this->buildLiveStatusFromLocation([
                'speed'       => $vitesse,
                'heart_time'  => $heartMs,
                'datetime'    => $gpsMs > 0 ? $gpsMs : null,
                'server_time' => $sample['server_time_ms'] ?? null,
            ], $precedent !== [] ? $precedent : null);

            $row['live_status'] = $liveStatus;
            $row['gps'] = [
                'online'    => $liveStatus['is_online'] ?? null,
                'state'     => ($liveStatus['is_online'] ?? null) === true ? 'ONLINE' : 'OFFLINE',
                'last_seen' => $liveStatus['heart_time']
                    ?? $liveStatus['datetime']
                    ?? $liveStatus['sys_time']
                    ?? ($row['gps']['last_seen'] ?? null),
                'message'   => null,
            ];
        }

        /*
         * `engine` n'est VOLONTAIREMENT jamais écrit ici. Le bit de relais du
         * fournisseur s'est révélé non fiable sur près d'un quart du parc : des
         * véhicules mesurés à 89 km/h le rapportent moteur coupé. Afficher un
         * moteur coupé à tort serait plus grave que de ne rien afficher.
         *
         * `loc_id` reste également inchangé : le remettre à zéro réactiverait à
         * tort la garde isNewerLocIdThanCached() du chemin `locations`.
         */

        $apres = [
            'ui'     => $row['live_status']['ui_status'] ?? null,
            'online' => $row['gps']['online'] ?? null,
            'lat'    => isset($row['lat']) ? round((float) $row['lat'], 5) : null,
            'lon'    => isset($row['lon']) ? round((float) $row['lon'], 5) : null,
        ];

        return [$row, $avant !== $apres];
    }

    /**
     * Rafraîchit la ligne de cache d'UN véhicule à partir d'un relevé live 18gps
     * obtenu au clic sur sa fiche.
     *
     * Sans cela, la fiche était corrigée à l'écran mais le cache gardait
     * l'ancienne valeur : au premier événement temps réel, la fiche se
     * réécrivait depuis le cache et l'état affiché basculait.
     *
     * Donnée strictement dérivée : en cas d'échec on ne touche à rien, le cycle
     * de reconstruction normal reprend la main.
     */
    public function updateVehicleRowFromLiveProviderStatus(Voiture $voiture, array $providerStatus): void
    {
        try {
            $vehicleId = (int) $voiture->id;
            $loc = (array) ($providerStatus['location'] ?? []);

            // Un boîtier jamais localisé renvoie 0/0 : on ne remplace pas une
            // position connue par des coordonnées au large du golfe de Guinée.
            $lat = (float) ($loc['latitude'] ?? 0);
            $lon = (float) ($loc['longitude'] ?? 0);
            $hasPosition = ($lat !== 0.0 || $lon !== 0.0);

            $sample = [
                'latitude'       => $hasPosition ? $lat : null,
                'longitude'      => $hasPosition ? $lon : null,
                'speed'          => $providerStatus['speed'] ?? null,
                'heart_time_ms'  => $this->toMs($loc['heart_time'] ?? null),
                'server_time_ms' => $this->toMs($loc['sys_time'] ?? null),
                /*
                 * `datetime` est à la RACINE de la réponse, pas dans `location`
                 * (voir GpsControlService::getEngineStatusFromLastLocation).
                 * Le lire au mauvais endroit donnait toujours null : la garde
                 * de position ne passait jamais, et l'horodatage GPS déjà en
                 * cache était écrasé — la fiche était donc moins juste après
                 * un clic qu'avant.
                 */
                'datetime_ms'    => $this->toMs($providerStatus['datetime'] ?? null),
            ];

            // Sans battement NI position, le relevé n'apprend rien : le laisser
            // passer ferait basculer un véhicule « jamais localisé » en « hors
            // ligne », ce qui serait faux.
            if (empty($sample['heart_time_ms']) && ! $hasPosition) {
                return;
            }

            foreach ($this->partnerIdsForVehicle($vehicleId) as $partnerId) {
                $row = $this->getFleetVehicleRowFromRedis($partnerId, $vehicleId);
                if (! $row) {
                    continue;   // rien en cache : le cycle normal le construira
                }

                [$row, $visible] = $this->applyProviderSampleToRow($row, $sample);

                Redis::hset(
                    $this->kFleetH($partnerId),
                    (string) $vehicleId,
                    json_encode($row, JSON_UNESCAPED_UNICODE)
                );

                if ($visible) {
                    $this->markVehiclesDirty($partnerId, [$vehicleId]);
                    $this->bumpVersionDebounced($partnerId, 1);
                }
            }
        } catch (\Throwable $e) {
            // Le rafraîchissement du cache ne doit jamais faire échouer la
            // réponse : l'affichage live reste correct sans lui.
            \Illuminate\Support\Facades\Log::debug('[DASH_CACHE] rafraichissement live ignore', [
                'voiture_id' => $voiture->id ?? null,
                'erreur'     => $e->getMessage(),
            ]);
        }
    }

    /**
     * Applique un relevé fournisseur de TOUTE la flotte au cache du tableau de
     * bord, en un balayage.
     *
     * C'est ce qui permet d'afficher l'état réel (en ligne / en mouvement / à
     * l'arrêt) de chaque véhicule DÈS LE CHARGEMENT de la page, sans attendre
     * qu'on clique dessus. La page, elle, ne fait que lire le cache : l'appel
     * fournisseur a lieu ici, hors requête web.
     *
     * Ce que ce balayage n'écrit PAS, volontairement :
     *  - la table `locations`, qui reste alimentée par l'ingestion Node. Elle
     *    seule porte la trace continue nécessaire aux trajets, aux alertes et
     *    aux coupures sur géorepère ; un instantané par minute ne la remplace
     *    pas. Ce balayage ne sert que l'affichage.
     *  - l'état moteur (voir applyProviderSampleToRow).
     *  - le TTL du hash : le rafraîchir à chaque minute supprimerait le filet
     *    d'auto-réparation qui fait reconstruire la flotte depuis la base quand
     *    le cache expire.
     *
     * @param array<string,array<string,mixed>> $map relevés indexés par mac
     * @param int|null $onlyPartnerId restreint le balayage à un seul partenaire
     *                 (utilisé au démarrage à froid, sur un chargement de page)
     * @return array<string,int> compteurs de diagnostic
     */
    public function updateFleetFromLiveProviderMap(array $map, ?int $onlyPartnerId = null): array
    {
        $bilan = [
            'boitiers_recus'   => count($map),
            'lignes_examinees' => 0,
            'lignes_ecrites'   => 0,
            'changements_vus'  => 0,
            'absents_du_cache' => 0,
            'absents_du_lot'   => 0,
        ];

        if (empty($map)) {
            return $bilan;
        }

        /*
         * Un véhicule peut porter le même boîtier qu'un autre, et appartenir à
         * plusieurs partenaires : chaque relevé se ventile donc en N lignes
         * (partenaire, véhicule) indépendantes. Une seule requête les résout
         * toutes — surtout pas une par véhicule.
         */
        $triplets = AssociationUserVoiture::query()
            ->join('users', 'users.id', '=', 'association_user_voitures.user_id')
            ->join('voitures', 'voitures.id', '=', 'association_user_voitures.voiture_id')
            ->whereNull('users.partner_id')
            ->whereNotNull('voitures.mac_id_gps')
            ->where('voitures.mac_id_gps', '<>', '')
            ->when($onlyPartnerId !== null, fn ($q) => $q->where('association_user_voitures.user_id', $onlyPartnerId))
            ->select([
                'association_user_voitures.user_id as partner_id',
                'voitures.id as voiture_id',
                'voitures.mac_id_gps as mac',
            ])
            ->get();

        $parPartenaire = [];
        foreach ($triplets as $t) {
            $parPartenaire[(int) $t->partner_id][(int) $t->voiture_id] = trim((string) $t->mac);
        }

        foreach ($parPartenaire as $partnerId => $vehicules) {
            try {
                $hash = Redis::hgetall($this->kFleetH($partnerId));
            } catch (\Throwable $e) {
                continue;
            }

            if (empty($hash)) {
                // Rien en cache pour ce partenaire : le cycle normal le
                // construira. On ne crée pas de lignes partielles ici.
                continue;
            }

            $aEcrire = [];
            $modifies = [];
            $trouves = 0;

            foreach ($vehicules as $vehicleId => $mac) {
                $bilan['lignes_examinees']++;

                $sample = $map[$mac] ?? null;
                if (! $sample) {
                    $bilan['absents_du_lot']++;
                    continue;   // aucune écriture : refreshOfflineStatuses les fera vieillir
                }

                $json = $hash[(string) $vehicleId] ?? null;
                if (! $json) {
                    $bilan['absents_du_cache']++;
                    continue;
                }

                $row = json_decode($json, true);
                if (! is_array($row)) {
                    continue;
                }

                $trouves++;

                [$row, $visible] = $this->applyProviderSampleToRow($row, $sample);

                /*
                 * Ne reecrire que ce qui a reellement bouge. En regime etabli, la
                 * plupart des lignes sont rejetees par les gardes et ressortent
                 * identiques : les reecrire couterait 159 ecritures Redis par
                 * minute pour rien, sur un serveur deja a court de memoire.
                 */
                $encode = json_encode($row, JSON_UNESCAPED_UNICODE);
                if ($encode === $json) {
                    continue;
                }

                $aEcrire[(string) $vehicleId] = $encode;

                if ($visible) {
                    $modifies[] = (int) $vehicleId;
                }
            }

            /*
             * Garde-fou contre une reponse partielle du fournisseur : si plus de
             * la moitie des vehicules du partenaire sont absents du releve, on
             * n ecrit rien. Ecrire quand meme laisserait une flotte a moitie
             * fraiche et a moitie figee, sans que rien ne le signale -- c est
             * exactement le mode de defaillance silencieuse qu un test reel a
             * fait apparaitre ici.
             */
            $examines = count($vehicules);
            if ($examines > 0 && $trouves < ($examines * 0.8)) {
                \Illuminate\Support\Facades\Log::warning('[GPS_LIVE_FLEET] releve partiel ignore', [
                    'partenaire' => $partnerId,
                    'attendus'   => $examines,
                    'trouves'    => $trouves,
                ]);

                continue;
            }

            if (empty($aEcrire)) {
                continue;
            }

            /*
             * hMSet ne réinitialise pas le TTL d'une clé existante : ne pas
             * appeler expire() ici suffit à préserver le filet d'expiration.
             */
            Redis::hMSet($this->kFleetH($partnerId), $aEcrire);
            $bilan['lignes_ecrites'] += count($aEcrire);

            // Une seule notification par balayage, et seulement si quelque
            // chose a réellement bougé à l'écran : sinon on inonderait le flux
            // temps réel d'événements identiques.
            if (! empty($modifies)) {
                $this->markVehiclesDirty($partnerId, $modifies);
                $this->bumpVersionDebounced($partnerId, 1);
                $bilan['changements_vus'] += count($modifies);
            }
        }

        return $bilan;
    }

    public function updateFleetBatchFromLocations(int|iterable $partnerIdOrLocations, array $items = [], bool $bump = true): void
    {
        if (is_iterable($partnerIdOrLocations) && !is_int($partnerIdOrLocations)) {
            $latestByMac = [];

            foreach ($partnerIdOrLocations as $location) {
                if (!$location instanceof Location) {
                    continue;
                }

                $mac = trim((string) ($location->mac_id_gps ?? ''));
                if ($mac === '') {
                    continue;
                }

                $current = $latestByMac[$mac] ?? null;
                if (!$current || (int) $location->id > (int) $current->id) {
                    $latestByMac[$mac] = $location;
                }
            }

            if (empty($latestByMac)) {
                return;
            }

            $macs = array_keys($latestByMac);

            $vehicles = Voiture::query()
                ->whereIn('mac_id_gps', $macs)
                ->select(['id', 'immatriculation', 'marque', 'model', 'mac_id_gps', 'geofence_zone'])
                ->get();

            if ($vehicles->isEmpty()) {
                return;
            }

            $vehicleIds = $vehicles->pluck('id')->map(fn ($x) => (int) $x)->all();

            $associations = AssociationUserVoiture::query()
                ->join('users', 'users.id', '=', 'association_user_voitures.user_id')
                ->whereNull('users.partner_id')
                ->whereIn('association_user_voitures.voiture_id', $vehicleIds)
                ->get(['association_user_voitures.user_id', 'association_user_voitures.voiture_id']);

            if ($associations->isEmpty()) {
                return;
            }

            $vehicleToPartners = [];
            foreach ($associations as $assoc) {
                $vehicleToPartners[(int) $assoc->voiture_id][] = (int) $assoc->user_id;
            }

            $dirtyByPartner = [];

            foreach ($vehicles as $vehicle) {
                $mac = trim((string) ($vehicle->mac_id_gps ?? ''));
                $location = $latestByMac[$mac] ?? null;
                if (!$location) {
                    continue;
                }

                $existingPartners = array_values(array_unique(array_map('intval', $vehicleToPartners[(int) $vehicle->id] ?? [])));
                if (empty($existingPartners)) {
                    continue;
                }

                foreach ($existingPartners as $partnerId) {
                    $existingRow = $this->getFleetVehicleRowFromRedis((int) $partnerId, (int) $vehicle->id);
                    $row = $this->buildVehicleRow((int) $partnerId, $vehicle, $location->toArray(), $existingRow);

                    if (!$row) {
                        continue;
                    }

                    Redis::hset(
                        $this->kFleetH((int) $partnerId),
                        (string) $vehicle->id,
                        json_encode($row, JSON_UNESCAPED_UNICODE)
                    );
                    Redis::expire($this->kFleetH((int) $partnerId), $this->ttlFleet);

                    $dirtyByPartner[(int) $partnerId][] = (int) $vehicle->id;
                }
            }

            foreach ($dirtyByPartner as $partnerId => $vIds) {
                $this->markVehiclesDirty((int) $partnerId, $vIds);
                $this->bumpVersionDebounced((int) $partnerId, 1);
            }

            return;
        }

        $partnerId = (int) $partnerIdOrLocations;

        if (empty($items)) {
            return;
        }

        $latestItems = $this->pickLatestPerMacByLocId($items);
        if (empty($latestItems)) {
            return;
        }

        $latestByMac = [];
        $macs = [];

        foreach ($latestItems as $it) {
            $mac = trim((string) ($it['mac_id_gps'] ?? ''));
            if ($mac === '') {
                continue;
            }

            $latestByMac[$mac] = $it;
            $macs[] = $mac;
        }

        $macs = array_values(array_unique($macs));
        if (empty($macs)) {
            return;
        }

        $partnerVehicleIds = $this->partnerVehicleIds($partnerId);
        if (empty($partnerVehicleIds)) {
            return;
        }

        $voitures = Voiture::query()
            ->whereIn('id', $partnerVehicleIds)
            ->whereIn('mac_id_gps', $macs)
            ->select(['id', 'immatriculation', 'marque', 'model', 'mac_id_gps', 'geofence_zone'])
            ->get();

        if ($voitures->isEmpty()) {
            return;
        }

        $hashPayload = [];
        $dirtyIds = [];

        foreach ($voitures as $voiture) {
            $mac = trim((string) $voiture->mac_id_gps);
            $data = $latestByMac[$mac] ?? null;
            if (!$data) {
                continue;
            }

            $incomingLocId = (int) ($data['id'] ?? 0);
            if ($incomingLocId > 0 && !$this->isNewerLocIdThanCached($partnerId, (int) $voiture->id, $incomingLocId)) {
                continue;
            }

            $existingRow = $this->getFleetVehicleRowFromRedis($partnerId, (int) $voiture->id);
            $row = $this->buildVehicleRow($partnerId, $voiture, $data, $existingRow);
            if (!$row) {
                continue;
            }

            $hashPayload[(string) $voiture->id] = json_encode($row, JSON_UNESCAPED_UNICODE);
            $dirtyIds[] = (int) $voiture->id;
        }

        if (empty($hashPayload)) {
            return;
        }

        Redis::pipeline(function ($pipe) use ($partnerId, $hashPayload) {
            $pipe->hMSet($this->kFleetH($partnerId), $hashPayload);
            $pipe->expire($this->kFleetH($partnerId), $this->ttlFleet);
        });

        $this->markVehiclesDirty($partnerId, $dirtyIds);

        if ($bump) {
            $this->bumpVersionDebounced($partnerId, 1);
        }
    }

    public function rebuildAlerts(int $partnerId, int $limit = 10): array
    {
        $vehicleIds = $this->partnerVehicleIds($partnerId);

        if (empty($vehicleIds)) {
            Redis::setex($this->kAlerts($partnerId), $this->ttlAlerts, json_encode([], JSON_UNESCAPED_UNICODE));
            $this->markAlertsDirty($partnerId);
            $this->bumpVersionDebounced($partnerId, 1);
            return [];
        }

        $start = now()->startOfDay();
        $end   = now()->endOfDay();

        $alerts = Alert::query()
            ->with(['voiture'])
            ->whereIn('voiture_id', $vehicleIds)
            ->partnerVisible()
            ->where(function ($q) {
                $q->where('processed', 0)->orWhereNull('processed');
            })
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('alerted_at', [$start, $end])
                    ->orWhere(function ($qq) use ($start, $end) {
                        $qq->whereNull('alerted_at')
                            ->whereBetween('created_at', [$start, $end]);
                    });
            })
            ->orderBy('alerted_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function (Alert $a) {
                $v = $a->voiture;
                $typeNorm = $this->normalizeAlertType($a->alert_type);

                return [
                    'id'           => $a->id,
                    'voiture_id'   => $a->voiture_id,
                    'vehicle'      => $v?->immatriculation ?? 'N/A',
                    'type'         => $typeNorm,
                    'type_label'   => $this->alertTypeLabel($typeNorm),
                    'time'         => optional($a->alerted_at ?? $a->created_at)->format('d/m/Y H:i:s'),
                    'processed'    => (bool) ($a->processed ?? false),
                    'read'         => (bool) ($a->read ?? false),
                    'status'       => 'Ouvert',
                    'status_color' => 'bg-red-500',
                    'lat'          => $a->latitude,
                    'lng'          => $a->longitude,
                ];
            })
            ->values()
            ->toArray();

        Redis::setex($this->kAlerts($partnerId), $this->ttlAlerts, json_encode($alerts, JSON_UNESCAPED_UNICODE));
        $this->markAlertsDirty($partnerId);
        $this->bumpVersionDebounced($partnerId, 1);

        return $alerts;
    }

    public function rebuildAll(int $partnerId): array
    {
        $stats  = $this->rebuildStats($partnerId);
        $fleet  = $this->rebuildFleet($partnerId);
        $alerts = $this->rebuildAlerts($partnerId, 10);

        return compact('stats', 'fleet', 'alerts');
    }

    public function refreshOfflineStatusesFromRedis(int $partnerId): array
    {
        $fleet = $this->getFleetFromRedis($partnerId);
        if (!is_array($fleet) || empty($fleet)) {
            return ['partner_id' => $partnerId, 'updated' => 0, 'changed' => 0];
        }

        $changed = 0;
        $hashPayload = [];
        $dirtyIds = [];

        foreach ($fleet as $vehicle) {
            if (!is_array($vehicle)) {
                continue;
            }

            $oldVehicle = $vehicle;
            $vehicle = $this->applyDynamicLiveStatusOnRow($vehicle);

            /*
             * `updated_at_ms` est l'heure de la dernière écriture, pas une
             * information vue par l'utilisateur — et recomputeOfflineLiveStatus-
             * FromRedis la remet à `now()` inconditionnellement. La comparaison
             * brute était donc TOUJOURS vraie : chaque véhicule était réécrit et
             * diffusé 120 fois par heure pour une information inchangée, chaque
             * onglet ouvert monopolisant un worker sur les cinq du serveur.
             *
             * En l'excluant, on ne diffuse plus que les changements réels.
             */
            $avant = $oldVehicle;
            $apres = $vehicle;
            unset($avant['live_status']['updated_at_ms'], $apres['live_status']['updated_at_ms']);

            if ($apres !== $avant) {
                $changed++;

                if (isset($vehicle['id'])) {
                    $hashPayload[(string) $vehicle['id']] = json_encode($vehicle, JSON_UNESCAPED_UNICODE);
                    $dirtyIds[] = (int) $vehicle['id'];
                }
            }
        }

        if ($changed > 0) {
            Redis::pipeline(function ($pipe) use ($partnerId, $hashPayload) {
                $pipe->hMSet($this->kFleetH($partnerId), $hashPayload);
                $pipe->expire($this->kFleetH($partnerId), $this->ttlFleet);
            });

            $this->markVehiclesDirty($partnerId, $dirtyIds);
            $this->bumpVersionDebounced($partnerId, 1);
        }

        return ['partner_id' => $partnerId, 'updated' => count($fleet), 'changed' => $changed];
    }

    public function refreshAllPartnersOfflineStatusesFromRedis(): array
    {
        $partnerIds = User::query()
            ->whereNull('partner_id')
            ->pluck('id')
            ->map(fn ($x) => (int) $x)
            ->values()
            ->all();

        if (empty($partnerIds)) {
            return ['partners' => 0, 'changed' => 0, 'details' => []];
        }

        $details = [];
        $changed = 0;

        foreach ($partnerIds as $partnerId) {
            $result = $this->refreshOfflineStatusesFromRedis($partnerId);
            $details[] = $result;
            $changed += (int) ($result['changed'] ?? 0);
        }

        return [
            'partners' => count($partnerIds),
            'changed'  => $changed,
            'details'  => $details,
        ];
    }

    private function pickLatestPerMacByLocId(array $items): array
    {
        $best = [];

        foreach ($items as $it) {
            $mac = trim((string) ($it['mac_id_gps'] ?? ''));
            if ($mac === '') {
                continue;
            }

            $id = (int) ($it['id'] ?? 0);

            if (!isset($best[$mac])) {
                $best[$mac] = $it;
                $best[$mac]['__id'] = $id;
                continue;
            }

            $prev = (int) ($best[$mac]['__id'] ?? 0);
            if ($id >= $prev) {
                $best[$mac] = $it;
                $best[$mac]['__id'] = $id;
            }
        }

        foreach ($best as &$b) {
            unset($b['__id']);
        }

        return array_values($best);
    }

    private function buildVehicleRow(int $partnerId, Voiture $voiture, array $locationData, ?array $existingRow = null): ?array
    {
        $chauffeur = $voiture->chauffeurActuelPourPartner($partnerId);
        return $this->buildVehicleRowWithDriver($partnerId, $voiture, $locationData, $existingRow, $chauffeur);
    }
private function buildVehicleRowWithDriver(
        int $partnerId,
        Voiture $voiture,
        array $locationData,
        ?array $existingRow,
        mixed $chauffeur
    ): ?array {
        $lat = $locationData['latitude'] ?? null;
        $lon = $locationData['longitude'] ?? null;

        $lat = is_numeric($lat) ? (float) $lat : null;
        $lon = is_numeric($lon) ? (float) $lon : null;

        $driverLabel = $chauffeur
            ? trim(($chauffeur->prenom ?? '') . ' ' . ($chauffeur->nom ?? ''))
            : 'Non associé';

        $lastSeen = $locationData['heart_time'] ?? $locationData['sys_time'] ?? $locationData['datetime'] ?? null;
        $hasLocation = !empty($locationData) && ($lat !== null || $lon !== null || $lastSeen !== null);
        $gpsOnline = $hasLocation ? $this->isGpsOnlineFromLocation($locationData) : null;

        $engineDecoded = app(\App\Services\GpsControlService::class)->decodeEngineStatus($locationData['status'] ?? null);
        $engineCut = ($engineDecoded['engineState'] ?? 'UNKNOWN') === 'CUT';

        $previousLiveStatus = (array) ($existingRow['live_status'] ?? []);
        $liveStatus = $hasLocation
            ? $this->buildLiveStatusFromLocation($locationData, $previousLiveStatus)
            : $this->buildNoSignalLiveStatus();

        $geofence = null;

        if (!empty($voiture->geofence_zone) && is_numeric($voiture->geofence_zone)) {
            $zone = GeofenceZone::query()
                ->where('partner_id', $partnerId)
                ->where('id', (int) $voiture->geofence_zone)
                ->first();

            if ($zone) {
                $geofence = [
                    'id' => (int) $zone->id,
                    'name' => $zone->name,
                    'code' => $zone->code,
                    'zone' => json_decode($zone->zone, true) ?: [],
                ];
            }
        }

        return [
            'id'              => (int) $voiture->id,
            'immatriculation' => $voiture->immatriculation,
            'marque'          => $voiture->marque,
            'model'           => $voiture->model,
            'mac_id_gps'      => $voiture->mac_id_gps,
            'geofence'       => $geofence,
            'driver' => [
                'label' => $driverLabel,
                'id'    => $chauffeur?->id,
            ],
            'lat' => $lat,
            'lon' => $lon,
            'engine' => [
                'cut'         => $hasLocation ? $engineCut : null,
                'engineState' => $hasLocation ? ($engineDecoded['engineState'] ?? 'UNKNOWN') : 'UNKNOWN',
            ],
            'gps' => [
                'online'    => $gpsOnline,
                'state'     => $hasLocation ? ($gpsOnline === true ? 'ONLINE' : 'OFFLINE') : 'NO_LOCATION',
                'last_seen' => $lastSeen ? \App\Support\LocalTime::displayRaw((string) $lastSeen, 'Y-m-d H:i:s') : null,
                'message'   => $hasLocation ? null : 'GPS jamais reçu',
            ],
            'live_status' => $liveStatus,
            'loc_id' => (int) ($locationData['id'] ?? 0),
        ];
    }


    private function isNewerLocIdThanCached(int $partnerId, int $vehicleId, int $incomingLocId): bool
    {
        try {
            $json = Redis::hget($this->kFleetH($partnerId), (string) $vehicleId);
            if (!$json) {
                return true;
            }
            $row = json_decode($json, true);
            $cachedLocId = (int) ($row['loc_id'] ?? 0);
            return $incomingLocId >= $cachedLocId;
        } catch (\Throwable) {
            return true;
        }
    }
private function applyDynamicLiveStatusOnRow(array $vehicle): array
    {
        $oldLiveStatus = (array) ($vehicle['live_status'] ?? []);

        if (($oldLiveStatus['ui_status'] ?? null) === 'NO_LOCATION') {
            $vehicle['gps']['online'] = null;
            $vehicle['gps']['state'] = 'NO_LOCATION';
            $vehicle['gps']['last_seen'] = $vehicle['gps']['last_seen'] ?? null;
            $vehicle['gps']['message'] = $vehicle['gps']['message'] ?? 'GPS jamais reçu';
            return $vehicle;
        }

        if (!empty($oldLiveStatus)) {
            $newLiveStatus = $this->recomputeOfflineLiveStatusFromRedis($oldLiveStatus);
            $vehicle['live_status'] = $newLiveStatus;

            $vehicle['gps']['online']    = $newLiveStatus['is_online'] ?? null;
            $vehicle['gps']['state']     = ($newLiveStatus['is_online'] ?? null) === true ? 'ONLINE' : 'OFFLINE';
            $vehicle['gps']['last_seen'] = (string) (
                $newLiveStatus['heart_time']
                ?? $newLiveStatus['datetime']
                ?? $newLiveStatus['sys_time']
                ?? ($vehicle['gps']['last_seen'] ?? '')
            );
        }

        return $vehicle;
    }

private function isGpsOnline($lastSeen): ?bool
    {
        $ms = $this->toMs($lastSeen);
        if (!$ms) {
            return null;
        }

        $diffMs = now()->getTimestampMs() - $ms;
        return $diffMs <= ($this->gpsOfflineMinutes * 60 * 1000);
    }

    private function isGpsOnlineFromLocation(array $location): ?bool
    {
        $heartMs = $this->toMs($location['heart_time'] ?? null);
        $serverMs = $this->toMs($location['server_time'] ?? $location['sys_time'] ?? null);

        if (!$heartMs) {
            return null;
        }

        // 18GPS doc: online/offline = server_time - heart_time.
        // If sys_time is unavailable, use app time as fallback only.
        $referenceMs = $serverMs ?: now()->getTimestampMs();
        $diffMs = $referenceMs - $heartMs;

        return $diffMs <= ($this->gpsOfflineMinutes * 60 * 1000);
    }


    private function durationHuman(?int $seconds): ?string
    {
        if ($seconds === null || $seconds < 0) {
            return null;
        }

        $days    = intdiv($seconds, 86400);
        $hours   = intdiv($seconds % 86400, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $secs    = $seconds % 60;

        if ($days > 0) {
            return "{$days}j {$hours}h {$minutes}min";
        }
        if ($hours > 0) {
            return "{$hours}h {$minutes}min";
        }
        if ($minutes > 0) {
            return "{$minutes}min" . ($secs > 0 ? " {$secs}s" : '');
        }

        return "{$secs}s";
    }

    private function toMs($value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_numeric($value)) {
            $n = (int) $value;
            if ($n <= 0) {
                return null;
            }
            if ($n >= 1000000000000) {
                return $n;
            }
            if ($n >= 1000000000) {
                return $n * 1000;
            }
        }

        if (is_string($value)) {
            $s = trim((string) $value);
            if ($s === '') {
                return null;
            }
            if (is_numeric($s)) {
                return $this->toMs((int) $s);
            }

            try {
                return Carbon::parse($s)->getTimestampMs();
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    private function msToDateTime(?int $ms): ?string
    {
        if (!$ms || $ms <= 0) {
            return null;
        }

        try {
            return Carbon::createFromTimestampMs($ms)
                ->setTimezone(config('app.display_timezone', 'Africa/Douala'))
                ->toDateTimeString();
        } catch (\Throwable) {
            return null;
        }
    }
private function buildLiveStatusFromLocation(array $location, ?array $previousLiveStatus = null): array
    {
        $offlineThresholdMinutes = $this->gpsOfflineMinutes;
        $offlineThresholdMs = $offlineThresholdMinutes * 60 * 1000;

        $speedRaw = $location['speed'] ?? $location['su'] ?? null;
        $speed = is_numeric($speedRaw) ? (float) $speedRaw : null;

        $heartMs = $this->toMs($location['heart_time'] ?? null);
        $gpsMs   = $this->toMs($location['datetime'] ?? null);
        $sysMs   = $this->toMs($location['server_time'] ?? $location['sys_time'] ?? null);

        $nowMs = now()->getTimestampMs();
        $referenceMs = $sysMs ?: $nowMs;
        $onlineRefMs = $heartMs ?: $gpsMs ?: $sysMs;
        $isOnline = $heartMs ? (($referenceMs - $heartMs) < $offlineThresholdMs) : false;

        $prevMovementState  = (string) ($previousLiveStatus['movement_state'] ?? '');
        $prevStoppedSinceMs = isset($previousLiveStatus['stopped_since_ms']) ? (int) $previousLiveStatus['stopped_since_ms'] : null;
        $prevOfflineSinceMs = isset($previousLiveStatus['offline_since_ms']) ? (int) $previousLiveStatus['offline_since_ms'] : null;

        $movementState     = 'UNKNOWN';
        $connectivityState = 'UNKNOWN';
        $uiStatus          = 'UNKNOWN';
        $isMoving          = null;

        $stoppedSinceMs = $prevStoppedSinceMs;
        $offlineSinceMs = $prevOfflineSinceMs;

        if ($isOnline === false) {
            $movementState     = 'OFFLINE';
            $connectivityState = 'OFFLINE';
            $uiStatus          = 'OFFLINE';
            $isMoving          = null;

            if (!$offlineSinceMs) {
                $offlineSinceMs = $onlineRefMs ?: $nowMs;
            }
        } else {
            $offlineSinceMs = null;

            if ($speed !== null && $speed >= $this->movingThreshold) {
                $movementState     = 'MOVING';
                $connectivityState = 'ONLINE_MOVING';
                $uiStatus          = 'ONLINE_MOVING';
                $isMoving          = true;
                $stoppedSinceMs    = null;
            } elseif ($speed !== null && $speed >= 0) {
                $movementState     = 'STOPPED';
                $connectivityState = 'ONLINE_STATIONARY';
                $uiStatus          = 'ONLINE_STOPPED';
                $isMoving          = false;

                if (!$stoppedSinceMs || $prevMovementState !== 'STOPPED') {
                    $stoppedSinceMs = $gpsMs ?: $sysMs ?: $onlineRefMs ?: $nowMs;
                }
            }
        }

        $stoppedSinceSeconds = $stoppedSinceMs ? max(0, (int) floor(($nowMs - $stoppedSinceMs) / 1000)) : null;
        $offlineSinceSeconds = $offlineSinceMs ? max(0, (int) floor(($nowMs - $offlineSinceMs) / 1000)) : null;

        return [
            'ui_status'                 => $uiStatus,
            'movement_state'            => $movementState,
            'connectivity_state'        => $connectivityState,
            'is_online'                 => $isOnline,
            'is_moving'                 => $isMoving,
            'speed'                     => $speed,
            'speed_raw'                 => $speedRaw,
            'moving_threshold'          => $this->movingThreshold,
            'stopped_since_ms'          => $stoppedSinceMs,
            'stopped_since_seconds'     => $stoppedSinceSeconds,
            'stopped_since_human'       => $this->durationHuman($stoppedSinceSeconds),
            'offline_since_ms'          => $offlineSinceMs,
            'offline_since_seconds'     => $offlineSinceSeconds,
            'offline_since_human'       => $this->durationHuman($offlineSinceSeconds),
            'datetime'                  => $this->msToDateTime($gpsMs),
            'heart_time'                => $this->msToDateTime($heartMs),
            'sys_time'                  => $this->msToDateTime($sysMs),
            'heart_time_ms'             => $heartMs,
            'datetime_ms'               => $gpsMs,
            'sys_time_ms'               => $sysMs,
            'updated_at_ms'             => $nowMs,
            'offline_threshold_minutes' => $offlineThresholdMinutes,
        ];
    }

    private function buildNoSignalLiveStatus(): array
    {
        return [
            'ui_status' => 'NO_LOCATION',
            'movement_state' => 'NO_LOCATION',
            'connectivity_state' => 'NO_LOCATION',
            'is_online' => null,
            'is_moving' => null,
            'speed' => null,
            'speed_raw' => null,
            'moving_threshold' => $this->movingThreshold,
            'stopped_since_ms' => null,
            'stopped_since_seconds' => null,
            'stopped_since_human' => null,
            'offline_since_ms' => null,
            'offline_since_seconds' => null,
            'offline_since_human' => null,
            'datetime' => null,
            'heart_time' => null,
            'sys_time' => null,
            'heart_time_ms' => null,
            'datetime_ms' => null,
            'sys_time_ms' => null,
            'updated_at_ms' => now()->getTimestampMs(),
            'offline_threshold_minutes' => $this->gpsOfflineMinutes,
            'message' => 'GPS jamais reçu',
        ];
    }


    private function recomputeOfflineLiveStatusFromRedis(array $liveStatus): array
    {
        $offlineThresholdMinutes = (int) ($liveStatus['offline_threshold_minutes'] ?? $this->gpsOfflineMinutes);
        $offlineThresholdMs = $offlineThresholdMinutes * 60 * 1000;
        $nowMs = now()->getTimestampMs();

        $heartMs    = isset($liveStatus['heart_time_ms']) ? (int) $liveStatus['heart_time_ms'] : null;
        $datetimeMs = isset($liveStatus['datetime_ms']) ? (int) $liveStatus['datetime_ms'] : null;
        $sysMs      = isset($liveStatus['sys_time_ms']) ? (int) $liveStatus['sys_time_ms'] : null;

        $onlineRefMs = $heartMs ?: $datetimeMs ?: $sysMs;
        $isOnline    = $onlineRefMs ? (($nowMs - $onlineRefMs) < $offlineThresholdMs) : false;

        $offlineSinceMs = isset($liveStatus['offline_since_ms']) ? (int) $liveStatus['offline_since_ms'] : null;
        $movementState  = (string) ($liveStatus['movement_state'] ?? 'UNKNOWN');

        if ($isOnline === false) {
            if (!$offlineSinceMs) {
                $offlineSinceMs = $onlineRefMs ?: $nowMs;
            }

            $offlineSinceSeconds = max(0, (int) floor(($nowMs - $offlineSinceMs) / 1000));
            $liveStatus['ui_status']             = 'OFFLINE';
            $liveStatus['movement_state']        = 'OFFLINE';
            $liveStatus['connectivity_state']    = 'OFFLINE';
            $liveStatus['is_online']             = false;
            $liveStatus['is_moving']             = null;
            $liveStatus['offline_since_ms']      = $offlineSinceMs;
            $liveStatus['offline_since_seconds'] = $offlineSinceSeconds;
            $liveStatus['offline_since_human']   = $this->durationHuman($offlineSinceSeconds);
        } else {
            $liveStatus['is_online']             = true;
            $liveStatus['offline_since_ms']      = null;
            $liveStatus['offline_since_seconds'] = null;
            $liveStatus['offline_since_human']   = null;

            if ($movementState === 'STOPPED') {
                $liveStatus['ui_status']          = 'ONLINE_STOPPED';
                $liveStatus['connectivity_state'] = 'ONLINE_STATIONARY';
                $liveStatus['is_moving']          = false;
            } elseif ($movementState === 'MOVING') {
                $liveStatus['ui_status']          = 'ONLINE_MOVING';
                $liveStatus['connectivity_state'] = 'ONLINE_MOVING';
                $liveStatus['is_moving']          = true;
            }
        }

        $stoppedSinceMs = isset($liveStatus['stopped_since_ms']) ? (int) $liveStatus['stopped_since_ms'] : null;
        if ($stoppedSinceMs && ($liveStatus['movement_state'] ?? null) === 'STOPPED') {
            $stoppedSinceSeconds = max(0, (int) floor(($nowMs - $stoppedSinceMs) / 1000));
            $liveStatus['stopped_since_seconds'] = $stoppedSinceSeconds;
            $liveStatus['stopped_since_human']   = $this->durationHuman($stoppedSinceSeconds);
        }

        $liveStatus['updated_at_ms'] = $nowMs;

        return $liveStatus;
    }

    private function normalizeAlertType(?string $t): string
    {
        $t = strtolower(trim((string) $t));
        if ($t === '') {
            return 'unknown';
        }

        return match ($t) {
            'overspeed', 'speeding', 'speed'                                              => 'speed',
            'safezone', 'safe-zone', 'safe_zone'                                          => 'safe_zone',
            'geo_fence', 'geofence', 'geofence_enter', 'geofence_exit', 'geofence_breach' => 'geofence',
            'stolen', 'theft', 'stolen_vehicle'                                           => 'stolen',
            'timezone', 'time_zone', 'time-zone'                                          => 'time_zone',
            default                                                                        => $t,
        };
    }

    private function alertTypeLabel(string $type): string
    {
        return match ($type) {
            'geofence'  => 'GeoFence Breach',
            'safe_zone' => 'Safe Zone',
            'speed'     => 'Survitesse',
            'stolen'    => 'Véhicule Volé',
            'time_zone' => 'Time Zone',
            default     => ucfirst(str_replace('_', ' ', $type)),
        };
    }















}