<?php

namespace App\Http\Controllers\Gps;

use App\Http\Controllers\Controller;
use App\Models\Commande;
use App\Models\Location;
use App\Models\SimGps;
use App\Models\User;
use App\Models\Voiture;
use App\Services\GpsControlService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Support\UserMessages;

class ControlGpsController extends Controller
{
    public function __construct(private GpsControlService $gps) {}

    /**
     * Resolve the tenant partner that owns the fleet.
     *
     * Vehicles are associated with the partner account. A staff member
     * (partner_id set) must see the partner's vehicles, not their own
     * (empty) association — otherwise the fleet appears empty.
     */
    private function tenantPartner(User $user): User
    {
        return $user->partner_id
            ? (User::find($user->partner_id) ?? $user)
            : $user;
    }

    /**
     * Partner engine-control page.
     * GET /engine/actions
     */
    public function index(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = auth('web')->user();
        $tenant = $this->tenantPartner($user);

        $voitures = $tenant->voitures()
            ->select([
                'voitures.id',
                'voitures.immatriculation',
                'voitures.model',
                'voitures.marque',
                'voitures.couleur',
                'voitures.mac_id_gps',
            ])
            ->with([
                'chauffeurActuelPartner.chauffeur:id,nom,prenom,phone,photo',
            ])
            ->orderBy('voitures.immatriculation', 'asc')
            ->get();

        return view('coupure_moteur.index', compact('voitures'));
    }

    /**
     * Batch engine status.
     *
     * IMPORTANT 18GPS alignment:
     * - Mass display must not call getUserAndGpsInfoByIDsUtcNew for each vehicle.
     * - This endpoint reads the last known local location from DB/cache only.
     * - Live provider calls stay reserved for one-vehicle actions/confirmations.
     *
     * GET /voitures/engine-status/batch?ids=1,2,3
     */
    public function engineStatusBatch(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = auth('web')->user();

        $ids = collect(explode(',', (string) $request->query('ids', '')))
            ->map(fn ($v) => (int) trim($v))
            ->filter(fn ($v) => $v > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun véhicule sélectionné.',
                'data' => [],
            ], 422);
        }

        $voitures = $this->tenantPartner($user)->voitures()
            ->whereIn('voitures.id', $ids->all())
            ->get(['voitures.id', 'voitures.mac_id_gps'])
            ->keyBy('id');

        // Statut online LIVE (device-list 18gps, 1 appel par compte, caché 15 s).
        $liveOnline = [];
        try {
            $liveOnline = $this->gps->getLiveOnlineMap();
        } catch (\Throwable $e) {
            report($e);
        }

        $out = [];

        foreach ($ids as $id) {
            $v = $voitures[$id] ?? null;

            if (!$v) {
                $out[$id] = [
                    'success' => false,
                    'message' => UserMessages::ACCESS_DENIED,
                ];
                continue;
            }

            $mac = trim((string) $v->mac_id_gps);
            if ($mac === '') {
                $out[$id] = [
                    'success' => false,
                    'message' => 'NO_MAC_ID',
                ];
                continue;
            }

            /**
             * Volontairement AUCUNE lecture de `locations` ici (ni cache
             * "confirmé", ni sync planifié) : afficher un état moteur en
             * masse pour toute la flotte s'est montré peu fiable, avec des
             * écarts observés allant jusqu'à ~1h par rapport à la réalité
             * sur certains boîtiers. L'état moteur n'est donc plus affiché
             * qu'au clic sur un véhicule précis, via un appel live 18gps
             * pour CE véhicule seul (voir engineStatus()) juste avant
             * d'ouvrir la modale de confirmation. Ce tableau ne sert plus
             * qu'à afficher la connexion GPS (online/offline), qui elle
             * provient d'un cache réellement rafraîchi chaque minute
             * (gps:refresh-online-map) et n'a pas cette dérive.
             */
            $payload = [
                'success' => true,
                'engine' => [
                    'cut' => null,
                    'engineState' => 'UNKNOWN',
                ],
                'gps' => [
                    'online' => null,
                    'state' => 'UNKNOWN',
                    'message' => 'État GPS inconnu',
                ],
                'meta' => [
                    'checked_live' => false,
                ],
            ];

            if (isset($liveOnline[$mac])) {
                $lo = $liveOnline[$mac];
                $payload['gps']['online']  = $lo['is_online'];
                $payload['gps']['state']   = $lo['state'];
                $payload['gps']['message'] = match ($lo['state']) {
                    'ONLINE_MOVING'     => 'GPS en mouvement',
                    'ONLINE_STATIONARY' => 'GPS connecté - véhicule arrêté',
                    'OFFLINE'           => 'GPS hors ligne',
                    default             => 'État GPS inconnu',
                };
            }

            $out[$id] = $payload;
        }

        return response()->json(['success' => true, 'data' => $out]);
    }

    /**
     * One vehicle live status.
     *
     * This endpoint may call 18GPS live because it targets a single vehicle.
     * If provider fails, we still return the local cached status when available.
     *
     * GET /voitures/{voiture}/engine-status
     */
    public function engineStatus(Request $request, Voiture $voiture)
    {
        /** @var \App\Models\User $user */
        $user = auth('web')->user();

        $allowed = $this->tenantPartner($user)->voitures()->where('voitures.id', $voiture->id)->exists();
        if (!$allowed) {
            return response()->json(['success' => false, 'message' => UserMessages::ACCESS_DENIED], 403);
        }

        $mac = trim((string) $voiture->mac_id_gps);
        if ($mac === '') {
            return response()->json(['success' => false, 'message' => UserMessages::VEHICLE_UNAVAILABLE], 422);
        }

        $status = $this->getLiveEngineStatusWithAccountRetry($mac, true);

        if (($status['success'] ?? false) === true) {
            $payload = $this->buildEnginePayloadFromProviderStatus($status);
            $payload['meta']['is_live'] = true;

            return response()->json($payload);
        }

        $local = $this->buildEnginePayloadFromLocalLocation($mac, $this->latestLocationForMac($mac));
        if (($local['success'] ?? false) === true) {
            $local['meta']['provider_error'] = $status['message'] ?? 'ENGINE_STATUS_PROVIDER_FAILED';
            return response()->json($local);
        }

        Log::warning('[ENGINE_STATUS_FAILED]', [
            'vehicle_id' => $voiture->id,
            'mac_id' => $mac,
            'provider_status' => $status,
        ]);

        return response()->json([
            'success' => false,
            'message' => UserMessages::VEHICLE_UNAVAILABLE,
        ], 502);
    }

    /**
     * Manual engine command.
     * POST /voitures/{voiture}/toggle-engine
     * Body: { action: "cut" | "restore" }
     */
    public function toggleEngine(Request $request, Voiture $voiture)
    {
        /** @var \App\Models\User $user */
        $user = auth('web')->user();

        $allowed = $this->tenantPartner($user)->voitures()->where('voitures.id', $voiture->id)->exists();
        if (!$allowed) {
            return response()->json(['success' => false, 'message' => UserMessages::ACCESS_DENIED], 403);
        }

        /*
         | Confirmation par mot de passe.
         |
         | Couper/rallumer un moteur immobilise un véhicule réel : on redemande le
         | mot de passe du partenaire connecté avant d'exécuter. On réutilise la
         | règle `current_password` (même mécanisme que l'écran de changement de
         | mot de passe de l'app, qui maintient le hash local à jour).
         */
        $request->validate([
            'password' => ['required', 'string', 'current_password:web'],
        ], [
            'password.required' => 'Veuillez saisir votre mot de passe pour confirmer.',
            'password.current_password' => 'Mot de passe incorrect.',
        ]);

        $mac = trim((string) $voiture->mac_id_gps);
        if ($mac === '') {
           return response()->json(['success' => false, 'message' => UserMessages::VEHICLE_UNAVAILABLE], 422);
        }

        $action = strtolower(trim((string) $request->input('action', '')));

        if (!in_array($action, ['cut', 'restore'], true)) {
            $statusLive = $this->getLiveEngineStatusWithAccountRetry($mac, true);
            $engineState = $statusLive['decoded']['engineState'] ?? 'UNKNOWN';
            $currentlyCut = ($engineState === 'CUT');
            $action = $currentlyCut ? 'restore' : 'cut';
        }

        $accDb = $this->getAccountFromDb($mac);
        if ($accDb) {
            $this->gps->setAccount($accDb);
        }

        $providerResp = $action === 'cut'
            ? $this->gps->cutEngine($mac)
            : $this->gps->restoreEngine($mac);

        $parsed = $this->parseSendCommandResponse($providerResp);

        if (!$parsed['ok'] && strtoupper((string) $parsed['returnMsg']) === 'CMD_EXCEEDLENGTH') {
            $this->gps->clearCmdList($mac);

            $providerResp = $action === 'cut'
                ? $this->gps->cutEngine($mac)
                : $this->gps->restoreEngine($mac);

            $parsed = $this->parseSendCommandResponse($providerResp);
        }

        if (!$parsed['ok'] && $this->isWrongAccountMsg($parsed['returnMsg'] ?? '')) {
            $current = $this->gps->getAccount();
            $other = ($current === 'tracking') ? 'mobility' : 'tracking';

            $this->upsertAccountForMac($mac, $other);

            $this->gps->setAccount($other);
            $this->gps->resetGpsToken();

            $providerResp = $action === 'cut'
                ? $this->gps->cutEngine($mac)
                : $this->gps->restoreEngine($mac);

            $parsed = $this->parseSendCommandResponse($providerResp);
        }

        if (!$parsed['ok']) {
            Log::warning('[ENGINE_COMMAND_FAILED]', [
            'vehicle_id' => $voiture->id,
            'mac_id' => $mac,
            'parsed' => $parsed,
            'provider_response' => $providerResp,
        ]);

        return response()->json([
            'success' => false,
            'message' => UserMessages::VEHICLE_UNAVAILABLE,
        ], 422);
        }

        $cmdNo = $parsed['cmdNo'];
        $typeCommande = $action === 'cut' ? 'COUPURE' : 'ALLUMAGE';
        $commandStatus = $parsed['queued'] ? 'QUEUED_OFFLINE' : 'SEND_OK';

        if ($cmdNo !== '') {
            Commande::updateOrCreate(
                ['CmdNo' => $cmdNo],
                [
                    'user_id' => $user->id,
                    'employe_id' => null,
                    'vehicule_id' => $voiture->id,
                    'status' => $commandStatus,
                    'type_commande' => $typeCommande,
                ]
            );
        }

        Cache::forget("gps18gps:engine_status:tracking:{$mac}");
        Cache::forget("gps18gps:engine_status:mobility:{$mac}");

        $after = $this->getLiveEngineStatusWithAccountRetry($mac, true);

        return response()->json([
            'success' => true,
            'message' => $parsed['message'],
            'cmd_no' => $cmdNo,
            'return_msg' => $parsed['returnMsg'],
            'queued' => $parsed['queued'],
            'requested_action' => $action,
            'engine' => [
                'cut' => ($action === 'cut'),
            ],
            'status_after' => $after,
        ]);
    }

    /**
     * Partner command history.
     * GET /engine/history
     */
    public function history(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = auth('web')->user();

        $voitureIds = $this->tenantPartner($user)->voitures()->pluck('voitures.id')->all();

        $items = Commande::query()
            ->with([
                'vehicule:id,immatriculation,marque,model',
                'user:id,nom,prenom,phone',
            ])
            ->whereIn('vehicule_id', $voitureIds)
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->paginate(30);

        return view('coupure_moteur.history', compact('items'));
    }

    /* ====================== Helpers ====================== */

    private function parseSendCommandResponse(array $resp): array
    {
        $success = $resp['success'] ?? null;
        $errorCode = trim((string) ($resp['errorCode'] ?? ($resp['code'] ?? '')));

        $globalOk = ($success === true || $success === 'true' || $success === 1 || $success === '1')
            && ($errorCode === '' || $errorCode === '200' || $errorCode === '0');

        if (!$globalOk) {
            $msg = (string) ($resp['errorDescribe'] ?? $resp['msg'] ?? $resp['message'] ?? 'Commande échouée');

            return [
                'ok' => false,
                'cmdNo' => null,
                'returnMsg' => $errorCode ?: null,
                'message' => $this->humanCommandMessage($errorCode ?: $msg),
                'queued' => false,
            ];
        }

        $row = $resp['data'][0] ?? null;
        if (!is_array($row)) {
            return [
                'ok' => false,
                'cmdNo' => null,
                'returnMsg' => null,
                'message' => 'Commande non confirmée par le provider GPS.',
                'queued' => false,
            ];
        }

        $returnMsgRaw = (string) ($row['ReturnMsg'] ?? $row['returnMsg'] ?? '');
        $returnMsg = strtoupper(trim($returnMsgRaw));
        $cmdNo = trim((string) ($row['CmdNo'] ?? $row['cmdNo'] ?? ''));

        $acceptedNow = ['SEND_OK', 'SEND_SUCCESS', 'SENDOK', 'SUCCESS'];
        $queuedOffline = ['USER_LEAVE', 'NOT ONLINE', 'NOT_ONLINE'];

        if (in_array($returnMsg, $acceptedNow, true)) {
            if ($cmdNo === '') {
                return [
                    'ok' => false,
                    'cmdNo' => null,
                    'returnMsg' => $returnMsgRaw,
                    'message' => 'Commande acceptée mais reçu CmdNo manquant.',
                    'queued' => false,
                ];
            }

            return [
                'ok' => true,
                'cmdNo' => $cmdNo,
                'returnMsg' => $returnMsg,
                'message' => 'Commande envoyée au GPS.',
                'queued' => false,
            ];
        }

        if (in_array($returnMsg, $queuedOffline, true)) {
            return [
                'ok' => true,
                'cmdNo' => $cmdNo,
                'returnMsg' => $returnMsg,
                'message' => 'GPS hors ligne : commande mise en attente par le provider.',
                'queued' => true,
            ];
        }

        return [
            'ok' => false,
            'cmdNo' => null,
            'returnMsg' => $returnMsgRaw,
            'message' => $this->humanCommandMessage($returnMsgRaw !== '' ? $returnMsgRaw : 'Commande refusée'),
            'queued' => false,
        ];
    }

    private function humanCommandMessage(string $providerMessage): string
    {
        $msg = strtoupper(trim($providerMessage));

        return match (true) {
            $msg === '510' || str_contains($msg, 'PREVIOUS') => 'Une commande précédente est encore en cours. Réessayez après confirmation.',
            str_contains($msg, 'CMD_EXCEEDLENGTH') || str_contains($msg, 'QUEUE') => 'La file de commandes GPS est pleine.',
            str_contains($msg, 'DEVICENOT') || str_contains($msg, 'DEVICE NOT') => 'Boîtier GPS introuvable chez le provider.',
            str_contains($msg, 'PERMISSIONS') => 'Droits insuffisants pour envoyer cette commande GPS.',
            str_contains($msg, 'NONSUPPORT') => 'Cette commande n’est pas supportée par ce boîtier GPS.',
            str_contains($msg, 'PWD') => 'Mot de passe de commande GPS incorrect.',
            default => $providerMessage,
        };
    }

    private function getAccountFromDb(string $macId): ?string
    {
        $acc = SimGps::query()->where('mac_id', $macId)->value('account_name');
        $acc = strtolower(trim((string) $acc));

        return in_array($acc, ['tracking', 'mobility'], true) ? $acc : null;
    }

    private function upsertAccountForMac(string $macId, string $account): void
    {
        $account = strtolower(trim($account));
        if (!in_array($account, ['tracking', 'mobility'], true)) {
            return;
        }

        SimGps::query()->updateOrCreate(
            ['mac_id' => $macId],
            ['account_name' => $account]
        );

        Cache::forget('gps18gps:macid_account:' . $macId);
    }

    private function isWrongAccountMsg(string $returnMsg): bool
    {
        $msg = trim((string) $returnMsg);
        if ($msg === '') {
            return false;
        }

        if (str_contains($msg, '不属于本账号') || str_contains($msg, '不存在')) {
            return true;
        }

        $low = strtolower($msg);

        return str_contains($low, 'not belong')
            || str_contains($low, 'does not belong')
            || str_contains($low, 'not exist')
            || str_contains($low, 'does not belong to this account');
    }

    /**
     * Live engine status, with account retry when the provider says the device
     * does not belong to the current account.
     */
    private function getLiveEngineStatusWithAccountRetry(string $mac, bool $forceRefresh = false): array
    {
        $accDb = $this->getAccountFromDb($mac);
        if ($accDb) {
            $this->gps->setAccount($accDb);
        }

        if ($forceRefresh) {
            Cache::forget("gps18gps:engine_status:tracking:{$mac}");
            Cache::forget("gps18gps:engine_status:mobility:{$mac}");
        }

        $status = $this->gps->getEngineStatusFromLastLocation($mac);
        if (($status['success'] ?? false) === true) {
            return $status;
        }

        $msg = (string) ($status['message'] ?? '');
        if ($this->isWrongAccountMsg($msg)) {
            $current = $this->gps->getAccount();
            $other = ($current === 'tracking') ? 'mobility' : 'tracking';

            $this->upsertAccountForMac($mac, $other);

            $this->gps->setAccount($other);
            $this->gps->resetGpsToken();

            if ($forceRefresh) {
                Cache::forget("gps18gps:engine_status:{$other}:{$mac}");
            }

            return $this->gps->getEngineStatusFromLastLocation($mac);
        }

        return $status;
    }

    private function latestLocationForMac(string $mac): ?array
    {
        if (trim($mac) === '') {
            return null;
        }

        $loc = Location::query()
            ->where('mac_id_gps', $mac)
            ->orderByDesc('id')
            ->first();

        return $loc?->toArray();
    }

    private function buildEnginePayloadFromProviderStatus(array $status): array
    {
        $engineState = $status['decoded']['engineState'] ?? 'UNKNOWN';
        $cut = ($engineState === 'CUT');

        $connectivity = $this->buildGpsStateFromProviderStatus($status);

        return [
            'success' => true,
            'engine' => [
                'cut' => $cut,
                'engineState' => $engineState,
            ],
            'gps' => [
                'online' => $connectivity['online'],
                'state' => $connectivity['state'],
                'last_seen' => $connectivity['last_seen'],
                'message' => $connectivity['message'],
            ],
            'meta' => [
                'source' => $status['source'] ?? null,
                'account' => $status['account'] ?? null,
                'user_id' => $status['user_id'] ?? null,
            ],
        ];
    }

    private function buildEnginePayloadFromLocalLocation(string $mac, ?array $loc): array
    {
        if (!$loc) {
            return [
                'success' => true,
                'message' => 'NO_LOCATION',
                'engine' => [
                    'cut' => null,
                    'engineState' => 'UNKNOWN',
                ],
                'gps' => [
                    'online' => null,
                    'state' => 'NO_LOCATION',
                    'last_seen' => null,
                    'message' => 'GPS jamais reçu',
                ],
                'meta' => [
                    'source' => 'db',
                    'mac_id_gps' => $mac,
                ],
            ];
        }

        $decoded = $this->gps->decodeEngineStatus($loc['status'] ?? null);
        $engineState = $decoded['engineState'] ?? 'UNKNOWN';
        $connectivity = $this->buildGpsStateFromLocation($loc);

        return [
            'success' => true,
            'engine' => [
                'cut' => $engineState === 'CUT',
                'engineState' => $engineState,
            ],
            'gps' => [
                'online' => $connectivity['online'],
                'state' => $connectivity['state'],
                'last_seen' => $connectivity['last_seen'],
                'message' => $connectivity['message'],
            ],
            'meta' => [
                'source' => 'db',
                'mac_id_gps' => $mac,
                'loc_id' => (int) ($loc['id'] ?? 0),
            ],
        ];
    }

    private function buildGpsStateFromProviderStatus(array $status): array
    {
        $record = [
            'server_time' => $status['location']['sys_time'] ?? $status['datetime'] ?? null,
            'sys_time' => $status['location']['sys_time'] ?? null,
            'heart_time' => $status['location']['heart_time'] ?? null,
            'datetime' => $status['datetime'] ?? $status['location']['sys_time'] ?? null,
            'speed' => $status['speed'] ?? 0,
            'su' => $status['speed'] ?? 0,
        ];

        return $this->buildGpsStateFromRecord($record);
    }

    private function buildGpsStateFromLocation(array $loc): array
    {
        $record = [
            'server_time' => $loc['sys_time'] ?? null,
            'sys_time' => $loc['sys_time'] ?? null,
            'heart_time' => $loc['heart_time'] ?? null,
            'datetime' => $loc['datetime'] ?? null,
            'speed' => $loc['speed'] ?? null,
            'su' => $loc['speed'] ?? null,
        ];

        return $this->buildGpsStateFromRecord($record);
    }

    private function buildGpsStateFromRecord(array $record): array
    {
        $connectivity = $this->gps->computeConnectivityFromLatestRecord($record);
        $lastSeen = $record['heart_time'] ?? $record['datetime'] ?? $record['sys_time'] ?? null;
        $lastSeenText = $this->dateTimeString($lastSeen);

        $state = (string) ($connectivity['state'] ?? 'UNKNOWN');
        $online = $connectivity['is_online'] ?? null;

        $message = match ($state) {
            'ONLINE_MOVING' => 'GPS en mouvement',
            'ONLINE_STATIONARY' => 'GPS connecté - véhicule arrêté',
            'OFFLINE' => 'GPS hors ligne',
            'DISABLED' => 'GPS désactivé ou expiré',
            default => 'État GPS inconnu',
        };

        return [
            'online' => $online,
            'state' => $state,
            'last_seen' => $lastSeenText,
            'message' => $message,
        ];
    }

    private function dateTimeString($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->toDateTimeString();
        }

        if (is_numeric($value)) {
            $n = (int) $value;
            if ($n <= 0) {
                return null;
            }
            try {
                return ($n >= 1000000000000)
                    ? Carbon::createFromTimestampMs($n)->setTimezone(config('app.timezone'))->toDateTimeString()
                    : Carbon::createFromTimestamp($n)->setTimezone(config('app.timezone'))->toDateTimeString();
            } catch (\Throwable) {
                return null;
            }
        }

        try {
            return Carbon::parse((string) $value)->setTimezone(config('app.timezone'))->toDateTimeString();
        } catch (\Throwable) {
            return null;
        }
    }
}