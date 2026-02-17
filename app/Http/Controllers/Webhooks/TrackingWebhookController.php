<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\AssociationUserVoiture;
use App\Models\Location;
use App\Services\DashboardCacheService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackingWebhookController extends Controller
{
    public function __construct(private DashboardCacheService $cache) {}

    public function handle(Request $request)
    {
        // Auth par secret (Header)
        $token = (string) $request->header('X-Webhook-Token', '');
        if ($token === '' || $token !== (string) config('services.tracking_webhook.token')) {
            return response()->json(['ok' => false, 'error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $event = (string) $request->input('event', '');
        $data  = (array)  $request->input('data', []);

        // =========================
        // 1) Location créée (single)
        // =========================
        if ($event === 'location.created') {
            $mac = $data['mac_id_gps'] ?? null;

            $partnerIds = $this->partnerIdsFromMacs([$mac]);
            if (empty($partnerIds)) {
                return response()->json(['ok' => true, 'event' => $event, 'partner_ids' => []]);
            }

            $loc = new Location();
            foreach ($data as $k => $v) $loc->setAttribute($k, $v);

            foreach ($partnerIds as $partnerId) {
                $this->cache->updateVehicleFromLocation($partnerId, $loc);
            }

            return response()->json(['ok' => true, 'event' => $event, 'partner_ids' => $partnerIds]);
        }

        // =========================
        // 2) Batch positions (items[])
        // =========================
        if ($event === 'location.batch') {
            $items = (array) ($data['items'] ?? []);
            if (empty($items)) {
                return response()->json(['ok' => true, 'event' => $event, 'partner_ids' => []]);
            }

            $forcedPartnerId = !empty($data['partner_id']) ? (int) $data['partner_id'] : null;

            // Group by mac
            $byMac = [];
            foreach ($items as $it) {
                $mac = trim((string)($it['mac_id_gps'] ?? ''));
                if ($mac === '') continue;
                $byMac[$mac][] = $it;
            }

            if (empty($byMac)) {
                return response()->json(['ok' => true, 'event' => $event, 'partner_ids' => [], 'macs' => 0, 'items' => count($items)]);
            }

            $allPartnerIds = [];

            if ($forcedPartnerId) {
                // Chemin rapide si partner_id imposé
                foreach ($byMac as $mac => $macItems) {
                    $this->cache->updateFleetBatchFromLocations($forcedPartnerId, $macItems);
                }
                $allPartnerIds = [$forcedPartnerId];
            } else {
                // On pré-résout les partnerIds par mac en une requête pivot
                $macs = array_keys($byMac);
                $partnersByMac = $this->partnerIdsByMac($macs);

                foreach ($byMac as $mac => $macItems) {
                    $partnerIds = $partnersByMac[$mac] ?? [];
                    if (empty($partnerIds)) continue;

                    foreach ($partnerIds as $partnerId) {
                        $allPartnerIds[] = $partnerId;
                        $this->cache->updateFleetBatchFromLocations($partnerId, $macItems);
                    }
                }

                $allPartnerIds = array_values(array_unique(array_map('intval', $allPartnerIds)));
            }

            return response()->json([
                'ok' => true,
                'event' => $event,
                'partner_ids' => $allPartnerIds,
                'macs' => count($byMac),
                'items' => count($items),
            ]);
        }

        // =========================
        // 3) Alertes (single events)
        // ✅ Solution 1 : rebuildStats + rebuildAlerts (pas juste bump)
        // =========================
        if (in_array($event, ['alert.created', 'alert.updated', 'alert.processed'], true)) {
            $limit = isset($data['limit']) ? (int) $data['limit'] : 10;

            $partnerIds = [];
            if (!empty($data['partner_id'])) {
                $partnerIds = [(int) $data['partner_id']];
            } else {
                // Essaie via voiture_id / mac_id_gps
                $voitureIds = [];
                if (!empty($data['voiture_id'])) $voitureIds[] = (int) $data['voiture_id'];

                $macs = [];
                if (!empty($data['mac_id_gps'])) $macs[] = (string) $data['mac_id_gps'];

                $partnerIds = $this->partnerIdsFromVoitureIds($voitureIds);
                if (empty($partnerIds) && !empty($macs)) {
                    $partnerIds = $this->partnerIdsFromMacs($macs);
                }
            }

            $partnerIds = array_values(array_unique(array_map('intval', $partnerIds)));
            if (empty($partnerIds)) {
                return response()->json(['ok' => true, 'event' => $event, 'partner_ids' => []]);
            }

            foreach ($partnerIds as $partnerId) {
                // Lock anti-tempête, mais quand on lock, on rebuild quand même dès que lock dispo
                if ($this->cache->shouldRefreshAlertsNow($partnerId, 1)) {
                    $this->cache->rebuildAlerts($partnerId, $limit);
                    $this->cache->rebuildStats($partnerId);
                } else {
                    // Dans Solution 1 on veut que ça remonte vite.
                    // On bump quand même pour rafraîchir, mais le rebuild arrivera sur prochain appel.
                    $this->cache->bumpVersionDebounced($partnerId, 1);
                }
            }

            return response()->json(['ok' => true, 'event' => $event, 'partner_ids' => $partnerIds]);
        }

        // =========================
        // 4) Alertes batch (items[])
        // ✅ Solution 1 : rebuildStats + rebuildAlerts UNE fois par partner
        // =========================
        if ($event === 'alert.batch') {
            $items = (array) ($data['items'] ?? []);
            $limit = isset($data['limit']) ? (int) $data['limit'] : 10;

            // Chemin ultra-rapide si partner_id fourni par le bridge
            if (!empty($data['partner_id'])) {
                $partnerId = (int) $data['partner_id'];

                if ($this->cache->shouldRefreshAlertsNow($partnerId, 1)) {
                    $this->cache->rebuildAlerts($partnerId, $limit);
                    $this->cache->rebuildStats($partnerId);
                } else {
                    $this->cache->bumpVersionDebounced($partnerId, 1);
                }

                return response()->json([
                    'ok' => true,
                    'event' => $event,
                    'partner_ids' => [$partnerId],
                    'items' => count($items),
                ]);
            }

            // Sinon on déduit via voiture_id (idéal) ou mac_id_gps
            $voitureIds = [];
            $macs = [];

            foreach ($items as $it) {
                if (!empty($it['voiture_id'])) {
                    $voitureIds[] = (int) $it['voiture_id'];
                    continue;
                }
                if (!empty($it['mac_id_gps'])) {
                    $macs[] = (string) $it['mac_id_gps'];
                }
            }

            $voitureIds = array_values(array_unique(array_filter(array_map('intval', $voitureIds))));
            $macs = array_values(array_unique(array_filter(array_map(fn($m) => trim((string)$m), $macs))));

            $partnerIds = [];

            if (!empty($voitureIds)) {
                $partnerIds = $this->partnerIdsFromVoitureIds($voitureIds);
            } elseif (!empty($macs)) {
                $partnerIds = $this->partnerIdsFromMacs($macs);
            }

            $partnerIds = array_values(array_unique(array_map('intval', $partnerIds)));
            if (empty($partnerIds)) {
                return response()->json(['ok' => true, 'event' => $event, 'partner_ids' => [], 'items' => count($items)]);
            }

            foreach ($partnerIds as $partnerId) {
                if ($this->cache->shouldRefreshAlertsNow($partnerId, 1)) {
                    $this->cache->rebuildAlerts($partnerId, $limit);
                    $this->cache->rebuildStats($partnerId);
                } else {
                    $this->cache->bumpVersionDebounced($partnerId, 1);
                }
            }

            return response()->json([
                'ok' => true,
                'event' => $event,
                'partner_ids' => $partnerIds,
                'items' => count($items),
            ]);
        }

        return response()->json(['ok' => false, 'error' => 'Unknown event'], 422);
    }

    // =========================
    // Helpers optimisés
    // =========================

    /**
     * Récupère les partnerIds à partir d'une liste de voitureIds (1 requête pivot).
     */
    private function partnerIdsFromVoitureIds(array $voitureIds): array
    {
        $voitureIds = array_values(array_unique(array_filter(array_map('intval', $voitureIds))));
        if (empty($voitureIds)) return [];

        return AssociationUserVoiture::query()
            ->whereIn('voiture_id', $voitureIds)
            ->pluck('user_id')
            ->map(fn ($x) => (int) $x)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Récupère les partnerIds à partir d'une liste de macs (2 requêtes max : voitures + pivot).
     */
    private function partnerIdsFromMacs(array $macs): array
    {
        $macs = array_values(array_unique(array_filter(array_map(fn($m) => trim((string)$m), $macs))));
        if (empty($macs)) return [];

        $voitureIds = \App\Models\Voiture::query()
            ->whereIn('mac_id_gps', $macs)
            ->pluck('id')
            ->map(fn ($x) => (int) $x)
            ->all();

        if (empty($voitureIds)) return [];

        return $this->partnerIdsFromVoitureIds($voitureIds);
    }

    /**
     * Retourne un mapping mac => [partnerIds...]
     * Optimisé pour location.batch (évite N requêtes).
     */
    private function partnerIdsByMac(array $macs): array
    {
        $macs = array_values(array_unique(array_filter(array_map(fn($m) => trim((string)$m), $macs))));
        if (empty($macs)) return [];

        // mac => voiture_id
        $rows = \App\Models\Voiture::query()
            ->whereIn('mac_id_gps', $macs)
            ->select(['id', 'mac_id_gps'])
            ->get();

        $macToVoitureId = [];
        $voitureIds = [];

        foreach ($rows as $r) {
            $m = trim((string) $r->mac_id_gps);
            if ($m === '') continue;
            $vid = (int) $r->id;
            $macToVoitureId[$m] = $vid;
            $voitureIds[] = $vid;
        }

        $voitureIds = array_values(array_unique(array_filter($voitureIds)));
        if (empty($voitureIds)) return [];

        // voiture_id => partner_ids
        $pivot = AssociationUserVoiture::query()
            ->whereIn('voiture_id', $voitureIds)
            ->select(['voiture_id', 'user_id'])
            ->get();

        $partnersByVoiture = [];
        foreach ($pivot as $p) {
            $vid = (int) $p->voiture_id;
            $uid = (int) $p->user_id;
            $partnersByVoiture[$vid][] = $uid;
        }

        // mac => partner_ids
        $out = [];
        foreach ($macToVoitureId as $mac => $vid) {
            $ids = $partnersByVoiture[$vid] ?? [];
            if (!empty($ids)) {
                $out[$mac] = array_values(array_unique(array_map('intval', $ids)));
            }
        }

        return $out;
    }
}