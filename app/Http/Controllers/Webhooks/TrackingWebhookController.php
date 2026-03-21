<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\AssociationUserVoiture;
use App\Models\Location;
use App\Models\Voiture;
use App\Services\DashboardCacheService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackingWebhookController extends Controller
{
    public function __construct(private DashboardCacheService $cache) {}

    public function handle(Request $request)
    {
        $token = (string) $request->header('X-Webhook-Token', '');
        if ($token === '' || $token !== (string) config('services.tracking_webhook.token')) {
            return response()->json(['ok' => false, 'error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $event = (string) $request->input('event', '');
        $data  = (array) $request->input('data', []);

        if ($event === 'location.created') {
            $mac = trim((string) ($data['mac_id_gps'] ?? ''));
            if ($mac === '') {
                return response()->json(['ok' => true, 'event' => $event, 'partner_ids' => []]);
            }

            $partnerIds = $this->partnerIdsFromMacs([$mac]);
            if (empty($partnerIds)) {
                return response()->json(['ok' => true, 'event' => $event, 'partner_ids' => []]);
            }

            $loc = new Location();
            foreach ($data as $k => $v) {
                $loc->setAttribute($k, $v);
            }

            foreach ($partnerIds as $partnerId) {
                $this->cache->updateVehicleFromLocation((int) $partnerId, $loc);
            }

            return response()->json([
                'ok' => true,
                'event' => $event,
                'partner_ids' => array_values(array_unique(array_map('intval', $partnerIds))),
            ]);
        }

        if ($event === 'location.batch') {
            $items = (array) ($data['items'] ?? []);
            if (empty($items)) {
                return response()->json(['ok' => true, 'event' => $event, 'partner_ids' => []]);
            }

            $forcedPartnerId = !empty($data['partner_id']) ? (int) $data['partner_id'] : null;

            $byMac = [];
            foreach ($items as $it) {
                $mac = trim((string) ($it['mac_id_gps'] ?? ''));
                if ($mac === '') {
                    continue;
                }
                $byMac[$mac][] = $it;
            }

            if (empty($byMac)) {
                return response()->json([
                    'ok' => true,
                    'event' => $event,
                    'partner_ids' => [],
                    'macs' => 0,
                    'items' => count($items),
                ]);
            }

            if ($forcedPartnerId) {
                $this->cache->updateFleetBatchFromLocations($forcedPartnerId, $items);

                return response()->json([
                    'ok' => true,
                    'event' => $event,
                    'partner_ids' => [$forcedPartnerId],
                    'macs' => count($byMac),
                    'items' => count($items),
                ]);
            }

            $partnersByMac = $this->partnerIdsByMac(array_keys($byMac));

            $itemsByPartner = [];
            foreach ($byMac as $mac => $macItems) {
                $partnerIds = $partnersByMac[$mac] ?? [];
                if (empty($partnerIds)) {
                    continue;
                }

                foreach ($partnerIds as $partnerId) {
                    $partnerId = (int) $partnerId;
                    if (!isset($itemsByPartner[$partnerId])) {
                        $itemsByPartner[$partnerId] = [];
                    }
                    foreach ($macItems as $it) {
                        $itemsByPartner[$partnerId][] = $it;
                    }
                }
            }

            $allPartnerIds = [];
            foreach ($itemsByPartner as $partnerId => $partnerItems) {
                $partnerId = (int) $partnerId;
                $this->cache->updateFleetBatchFromLocations($partnerId, $partnerItems);
                $allPartnerIds[] = $partnerId;
            }

            return response()->json([
                'ok' => true,
                'event' => $event,
                'partner_ids' => array_values(array_unique(array_map('intval', $allPartnerIds))),
                'macs' => count($byMac),
                'items' => count($items),
            ]);
        }

        if (in_array($event, ['alert.created', 'alert.updated', 'alert.processed'], true)) {
            $limit = isset($data['limit']) ? (int) $data['limit'] : 10;
            $partnerIds = $this->resolvePartnerIdsFromAlertPayload($data);

            if (empty($partnerIds)) {
                return response()->json(['ok' => true, 'event' => $event, 'partner_ids' => []]);
            }

            foreach ($partnerIds as $partnerId) {
                $this->refreshPartnerAlertsAndStats((int) $partnerId, $limit);
            }

            return response()->json(['ok' => true, 'event' => $event, 'partner_ids' => $partnerIds]);
        }

        if ($event === 'alert.batch') {
            $items = (array) ($data['items'] ?? []);
            $limit = isset($data['limit']) ? (int) $data['limit'] : 10;

            if (!empty($data['partner_id'])) {
                $partnerId = (int) $data['partner_id'];
                $this->refreshPartnerAlertsAndStats($partnerId, $limit);

                return response()->json([
                    'ok' => true,
                    'event' => $event,
                    'partner_ids' => [$partnerId],
                    'items' => count($items),
                ]);
            }

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

            $partnerIds = [];
            if (!empty($voitureIds)) {
                $partnerIds = $this->partnerIdsFromVoitureIds($voitureIds);
            } elseif (!empty($macs)) {
                $partnerIds = $this->partnerIdsFromMacs($macs);
            }

            $partnerIds = array_values(array_unique(array_map('intval', $partnerIds)));

            if (empty($partnerIds)) {
                return response()->json([
                    'ok' => true,
                    'event' => $event,
                    'partner_ids' => [],
                    'items' => count($items),
                ]);
            }

            foreach ($partnerIds as $partnerId) {
                $this->refreshPartnerAlertsAndStats((int) $partnerId, $limit);
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

    private function refreshPartnerAlertsAndStats(int $partnerId, int $limit = 10): void
    {
        if ($this->cache->shouldRefreshAlertsNow($partnerId, 2)) {
            $this->cache->rebuildAlerts($partnerId, $limit);
            $this->cache->rebuildStats($partnerId);
            return;
        }

        $this->cache->bumpVersionDebounced($partnerId, 1);
    }

    private function resolvePartnerIdsFromAlertPayload(array $data): array
    {
        if (!empty($data['partner_id'])) {
            return [(int) $data['partner_id']];
        }

        $voitureIds = [];
        if (!empty($data['voiture_id'])) {
            $voitureIds[] = (int) $data['voiture_id'];
        }

        $macs = [];
        if (!empty($data['mac_id_gps'])) {
            $macs[] = (string) $data['mac_id_gps'];
        }

        $partnerIds = $this->partnerIdsFromVoitureIds($voitureIds);
        if (empty($partnerIds) && !empty($macs)) {
            $partnerIds = $this->partnerIdsFromMacs($macs);
        }

        return array_values(array_unique(array_map('intval', $partnerIds)));
    }

    private function partnerIdsFromVoitureIds(array $voitureIds): array
    {
        $voitureIds = array_values(array_unique(array_filter(array_map('intval', $voitureIds))));
        if (empty($voitureIds)) {
            return [];
        }

        return AssociationUserVoiture::query()
            ->whereIn('voiture_id', $voitureIds)
            ->join('users', 'users.id', '=', 'association_user_voitures.user_id')
            ->whereNull('users.partner_id')
            ->pluck('association_user_voitures.user_id')
            ->map(fn ($x) => (int) $x)
            ->unique()
            ->values()
            ->all();
    }

    private function partnerIdsFromMacs(array $macs): array
    {
        $macs = array_values(array_unique(array_filter(array_map(fn ($m) => trim((string) $m), $macs))));
        if (empty($macs)) {
            return [];
        }

        $voitureIds = Voiture::query()
            ->whereIn('mac_id_gps', $macs)
            ->pluck('id')
            ->map(fn ($x) => (int) $x)
            ->all();

        if (empty($voitureIds)) {
            return [];
        }

        return $this->partnerIdsFromVoitureIds($voitureIds);
    }

    private function partnerIdsByMac(array $macs): array
    {
        $macs = array_values(array_unique(array_filter(array_map(fn ($m) => trim((string) $m), $macs))));
        if (empty($macs)) {
            return [];
        }

        $rows = Voiture::query()
            ->whereIn('mac_id_gps', $macs)
            ->select(['id', 'mac_id_gps'])
            ->get();

        $macToVoitureId = [];
        $voitureIds = [];

        foreach ($rows as $r) {
            $m = trim((string) $r->mac_id_gps);
            if ($m === '') {
                continue;
            }

            $vid = (int) $r->id;
            $macToVoitureId[$m] = $vid;
            $voitureIds[] = $vid;
        }

        $voitureIds = array_values(array_unique(array_filter($voitureIds)));
        if (empty($voitureIds)) {
            return [];
        }

        $pivot = AssociationUserVoiture::query()
            ->join('users', 'users.id', '=', 'association_user_voitures.user_id')
            ->whereNull('users.partner_id')
            ->whereIn('association_user_voitures.voiture_id', $voitureIds)
            ->select(['association_user_voitures.voiture_id', 'association_user_voitures.user_id'])
            ->get();

        $partnersByVoiture = [];
        foreach ($pivot as $p) {
            $vid = (int) $p->voiture_id;
            $uid = (int) $p->user_id;
            $partnersByVoiture[$vid][] = $uid;
        }

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