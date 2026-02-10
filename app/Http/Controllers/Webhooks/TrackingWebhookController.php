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
        // ✅ Auth par secret (Header)
        $token = (string) $request->header('X-Webhook-Token', '');
        if ($token === '' || $token !== (string) config('services.tracking_webhook.token')) {
            return response()->json(['ok' => false, 'error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $event = (string) $request->input('event', '');
        $data  = (array)  $request->input('data', []);

        // =========================
        // 1) Location créée (1)
        // =========================
        if ($event === 'location.created') {
            $mac = $data['mac_id_gps'] ?? null;

            $partnerIds = $this->partnerIdsFromMac($mac);
            if (empty($partnerIds)) {
                return response()->json(['ok' => true, 'event' => $event, 'partner_ids' => []]);
            }

            $loc = new Location();
            foreach ($data as $k => $v) {
                $loc->setAttribute($k, $v);
            }

            foreach ($partnerIds as $partnerId) {
                $this->cache->updateVehicleFromLocation($partnerId, $loc);
            }

            return response()->json(['ok' => true, 'event' => $event, 'partner_ids' => $partnerIds]);
        }

        // =========================
        // 2) Batch positions (items[])
        // data: { items: [ {...}, {...} ] }
        // =========================
        if ($event === 'location.batch') {
            $items = (array) ($data['items'] ?? []);
            if (empty($items)) {
                return response()->json(['ok' => true, 'event' => $event, 'partner_ids' => []]);
            }

            // On déduit partner_ids depuis le 1er item mac (ou tu peux faire mieux)
            $firstMac = $items[0]['mac_id_gps'] ?? null;
            $partnerIds = $this->partnerIdsFromMac($firstMac);

            if (empty($partnerIds)) {
                return response()->json(['ok' => true, 'event' => $event, 'partner_ids' => []]);
            }

            // ✅ Update batch
            foreach ($partnerIds as $partnerId) {
                $this->cache->updateFleetBatchFromLocations($partnerId, $items);
                // bumpVersionDebounced est déjà géré dans le service
            }

            return response()->json(['ok' => true, 'event' => $event, 'partner_ids' => $partnerIds, 'items' => count($items)]);
        }

        // =========================
        // 3) Alertes (stats + top10)
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
                $this->cache->rebuildStats($partnerId);
                $this->cache->rebuildAlerts($partnerId, $limit);
            }

            return response()->json(['ok' => true, 'event' => $event, 'partner_ids' => $partnerIds]);
        }

        return response()->json(['ok' => false, 'error' => 'Unknown event'], 422);
    }

    // =========================
    // Helpers: partnerIds via pivot association_user_voitures
    // =========================
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