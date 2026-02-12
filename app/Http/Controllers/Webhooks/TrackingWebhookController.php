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

            $partnerIds = $this->partnerIdsFromMac($mac);
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

            $allPartnerIds = [];

            foreach ($byMac as $mac => $macItems) {
                $partnerIds = $forcedPartnerId ? [$forcedPartnerId] : $this->partnerIdsFromMac($mac);
                if (empty($partnerIds)) continue;

                foreach ($partnerIds as $partnerId) {
                    $allPartnerIds[] = $partnerId;
                    $this->cache->updateFleetBatchFromLocations($partnerId, $macItems);
                }
            }

            $allPartnerIds = array_values(array_unique(array_map('intval', $allPartnerIds)));

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
        // =========================
        if (in_array($event, ['alert.created', 'alert.updated', 'alert.processed'], true)) {
            $limit = isset($data['limit']) ? (int) $data['limit'] : 10;

            $partnerIds = [];
            if (!empty($data['partner_id'])) {
                $partnerIds = [(int) $data['partner_id']];
            } elseif (!empty($data['voiture_id'])) {
                $partnerIds = $this->partnerIdsFromVoitureId((int) $data['voiture_id']);
            } elseif (!empty($data['mac_id_gps'])) {
                $partnerIds = $this->partnerIdsFromMac((string) $data['mac_id_gps']);
            }

            if (empty($partnerIds)) {
                return response()->json(['ok' => true, 'event' => $event, 'partner_ids' => []]);
            }

            foreach ($partnerIds as $partnerId) {
                if ($this->cache->shouldRefreshAlertsNow($partnerId, 1)) {
                    $this->cache->rebuildStats($partnerId);
                    $this->cache->rebuildAlerts($partnerId, $limit);
                } else {
                    $this->cache->bumpVersionDebounced($partnerId, 1);
                }
            }

            return response()->json(['ok' => true, 'event' => $event, 'partner_ids' => $partnerIds]);
        }

        // =========================
        // 4) Alertes batch (items[])
        // Tu ne rebuild pas 300 fois: tu rebuild UNE fois par partner
        // =========================
        if ($event === 'alert.batch') {
            $items = (array) ($data['items'] ?? []);
            $limit = isset($data['limit']) ? (int) $data['limit'] : 10;

            // Si ton bridge met partner_id, c'est direct
            if (!empty($data['partner_id'])) {
                $partnerId = (int) $data['partner_id'];
                if ($this->cache->shouldRefreshAlertsNow($partnerId, 1)) {
                    $this->cache->rebuildStats($partnerId);
                    $this->cache->rebuildAlerts($partnerId, $limit);
                } else {
                    $this->cache->bumpVersionDebounced($partnerId, 1);
                }

                return response()->json(['ok' => true, 'event' => $event, 'partner_ids' => [$partnerId], 'items' => count($items)]);
            }

            // Sinon: on essaie de déduire via voiture_id / mac_id_gps présent dans les items
            $partnerIds = [];

            foreach ($items as $it) {
                if (!empty($it['voiture_id'])) {
                    $partnerIds = array_merge($partnerIds, $this->partnerIdsFromVoitureId((int)$it['voiture_id']));
                    continue;
                }
                if (!empty($it['mac_id_gps'])) {
                    $partnerIds = array_merge($partnerIds, $this->partnerIdsFromMac((string)$it['mac_id_gps']));
                }
            }

            $partnerIds = array_values(array_unique(array_map('intval', $partnerIds)));
            if (empty($partnerIds)) {
                return response()->json(['ok' => true, 'event' => $event, 'partner_ids' => [], 'items' => count($items)]);
            }

            foreach ($partnerIds as $partnerId) {
                if ($this->cache->shouldRefreshAlertsNow($partnerId, 1)) {
                    $this->cache->rebuildStats($partnerId);
                    $this->cache->rebuildAlerts($partnerId, $limit);
                } else {
                    $this->cache->bumpVersionDebounced($partnerId, 1);
                }
            }

            return response()->json(['ok' => true, 'event' => $event, 'partner_ids' => $partnerIds, 'items' => count($items)]);
        }

        return response()->json(['ok' => false, 'error' => 'Unknown event'], 422);
    }

    private function partnerIdsFromVoitureId(int $voitureId): array
    {
        return AssociationUserVoiture::query()
            ->where('voiture_id', $voitureId)
            ->pluck('user_id')
            ->map(fn($x) => (int) $x)
            ->unique()
            ->values()
            ->all();
    }

    private function partnerIdsFromMac(?string $macId): array
    {
        $macId = trim((string) $macId);
        if ($macId === '') return [];

        $v = Voiture::query()
            ->where('mac_id_gps', $macId)
            ->select(['id'])
            ->first();

        if (!$v) return [];

        return $this->partnerIdsFromVoitureId((int) $v->id);
    }
}