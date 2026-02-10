<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
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
        // ✅ auth par secret
        $token = (string) $request->header('X-Webhook-Token', '');
        if ($token === '' || $token !== (string) config('services.tracking_webhook.token')) {
            return response()->json(['ok' => false, 'error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $event = (string) $request->input('event', '');
        $data  = (array) $request->input('data', []);

        if ($event === 'location.created') {
            // Node envoie mac_id_gps + lat/lon/status/time
            $loc = new Location();
            foreach ($data as $k => $v) $loc->{$k} = $v;

            // ✅ update fleet hash + bumpVersion partner
            $this->cache->updateVehicleFromLocationScoped($loc);

            return response()->json(['ok' => true, 'event' => $event]);
        }

        if (in_array($event, ['alert.created','alert.updated','alert.processed'], true)) {
            $limit = isset($data['limit']) ? (int)$data['limit'] : 10;

            // ✅ on déduit partnerId depuis voiture_id
            $partnerId = null;
            if (!empty($data['partner_id'])) {
                $partnerId = (int)$data['partner_id'];
            } elseif (!empty($data['voiture_id'])) {
                $partnerId = $this->guessPartnerIdFromVoitureId((int)$data['voiture_id']);
            } elseif (!empty($data['mac_id_gps'])) {
                $partnerId = $this->guessPartnerIdFromMac((string)$data['mac_id_gps']);
            }

            if ($partnerId) {
                $this->cache->rebuildStats($partnerId);
                $this->cache->rebuildAlerts($partnerId, $limit);
            }

            return response()->json(['ok' => true, 'event' => $event, 'partner_id' => $partnerId]);
        }

        return response()->json(['ok' => false, 'error' => 'Unknown event'], 422);
    }

    private function guessPartnerIdFromVoitureId(int $voitureId): ?int
    {
        $v = Voiture::query()
            ->with(['utilisateur:id,partner_id'])
            ->select(['id','mac_id_gps'])
            ->find($voitureId);

        if (!$v) return null;

        // ✅ stratégie B: prendre un driver lié à la voiture qui a partner_id = (ID partner)
        $driver = $v->utilisateur?->firstWhere(fn($u) => !empty($u->partner_id));
        if ($driver && $driver->partner_id) return (int)$driver->partner_id;

        return null;
    }

    private function guessPartnerIdFromMac(string $macId): ?int
    {
        $v = Voiture::query()
            ->with(['utilisateur:id,partner_id'])
            ->where('mac_id_gps', $macId)
            ->select(['id','mac_id_gps'])
            ->first();

        if (!$v) return null;

        $driver = $v->utilisateur?->firstWhere(fn($u) => !empty($u->partner_id));
        if ($driver && $driver->partner_id) return (int)$driver->partner_id;

        return null;
    }
}