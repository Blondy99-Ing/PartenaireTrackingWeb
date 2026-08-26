<?php

namespace App\Http\Controllers\Gps;

use App\Http\Controllers\Controller;
use App\Models\Commande;
use App\Models\LeaseCutoffHistory;
use App\Models\Location;
use App\Models\SimGps;
use App\Models\User;
use App\Models\Voiture;
use App\Services\Gps\ManualCommandConfirmationService;
use App\Services\DashboardCacheService;
use App\Services\GpsControlService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Support\UserMessages;

class ControlGpsController extends Controller
{
    public function __construct(
        private GpsControlService $gps,
        private ManualCommandConfirmationService $manualCommandConfirmation,
        private DashboardCacheService $dashboardCache
    ) {}

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
                /**
                 * Le numéro de SIM du boîtier vit dans la table sim_gps
                 * (reliée par mac_id), pas dans la colonne voitures.sim_gps
                 * qui porte le même nom mais n'a jamais été alimentée —
                 * vérifié en production : 0 véhicule sur 290 la renseigne,
                 * contre 121 boîtiers sur 289 dans sim_gps.
                 */
                'simGps:id,mac_id,sim_number',
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

        /*
         * Relevé live enrichi de toute la flotte, alimenté hors requête par
         * gps:refresh-live-fleet (chaque minute). Lecture de cache uniquement :
         * aucun appel fournisseur n'est déclenché ici, la page reste immédiate.
         *
         * C'est lui qui permet d'afficher l'état moteur SANS cliquer sur chaque
         * véhicule. L'objection d'origine — un état lu dans `locations` pouvait
         * dériver de près d'une heure — ne tient plus : cette carte vient
         * directement du fournisseur et a moins d'une minute.
         */
        $liveFleet = [];
        try {
            $liveFleet = $this->gps->getLiveFleetMap();
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
             * Toujours AUCUNE lecture de `locations` ici : afficher un état
             * moteur tiré de la dernière position enregistrée s'était montré
             * peu fiable, avec des écarts allant jusqu'à ~1h sur certains
             * boîtiers. C'est cette dérive qui avait fait retirer l'état
             * moteur du tableau, le reléguant à un clic par véhicule.
             *
             * L'état vient désormais du relevé live de toute la flotte
             * (gps:refresh-live-fleet, chaque minute, directement chez le
             * fournisseur) : la dérive d'une heure n'existe plus, et le
             * tableau peut afficher l'état moteur sans clic.
             *
             * IMPORTANT — même donnée, mêmes limites que le bouton de
             * vérification : c'est le même bit de relais, lu au même endroit.
             * Ce bit s'est révélé non fiable sur près d'un quart du parc, des
             * véhicules roulant à 89 km/h s'y déclarant moteur coupé. L'écran
             * n'est donc ni plus ni moins juste qu'avant — seulement plus
             * immédiat. `checked_live` reste à false pour que l'interface
             * puisse continuer à distinguer cet état d'une vérification
             * explicite.
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

            /*
             * État moteur, depuis le relevé live de la flotte. Décodé par la
             * même méthode que le bouton de vérification, sur la même chaîne
             * de statut : le résultat est identique à ce qu'un clic afficherait.
             *
             * Si le boîtier est absent du relevé — carte froide, panne
             * fournisseur, boîtier récent — on laisse UNKNOWN plutôt que
             * d'inventer : l'utilisateur garde alors le bouton de vérification.
             */
            $releve = $liveFleet[$mac] ?? null;

            if ($releve !== null && ! empty($releve['status_raw'])) {
                $decode = $this->gps->decodeEngineStatus((string) $releve['status_raw']);
                $etat = $decode['engineState'] ?? 'UNKNOWN';

                /*
                 * Garde de cohérence. Le bit de relais du fournisseur est faux
                 * sur une partie du parc : mesuré en production, 9 véhicules
                 * sur 41 en mouvement (22 %) déclarent un moteur « OFF » ou
                 * « COUPÉ » alors qu'ils roulent jusqu'à 29 km/h.
                 *
                 * Un véhicule qui roule a forcément son moteur en marche. Quand
                 * le boîtier prétend le contraire, on préfère ne rien affirmer :
                 * la fiche retombe sur « Cliquer pour vérifier ». Afficher
                 * « moteur coupé » sur un véhicule en circulation, sur la page
                 * qui sert précisément à couper des moteurs, tromperait
                 * l'exploitant sur l'état réel de sa flotte.
                 *
                 * Ce garde ne rattrape que les contradictions visibles : un
                 * véhicule à l'arrêt dont le bit est faux reste indétectable.
                 */
                $enMouvement = ($releve['state'] ?? null) === 'ONLINE_MOVING';

                if ($enMouvement && $etat !== 'ON') {
                    $etat = 'UNKNOWN';
                    $payload['meta']['engine_incoherent'] = true;
                }

                if ($etat !== 'UNKNOWN') {
                    $payload['engine']['engineState'] = $etat;
                    $payload['engine']['cut'] = ($etat === 'CUT');
                    $payload['meta']['engine_source'] = 'live_fleet';
                    $payload['meta']['engine_seen_at_ms'] = $releve['heart_time_ms'] ?? null;
                }
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

            /**
             * L'appel getUserAndGpsInfoByIDsUtcNew (position/moteur) ne porte
             * pas le même bit "offline" que getDeviceList : sa connectivité
             * n'est ici qu'une estimation par écart de temps (seuil de
             * offlineThresholdMinutes, 25 min par défaut), alors que le badge
             * affiché sur la ligne AVANT le clic vient de la classification
             * 18gps elle-même (device-list, rafraîchie chaque minute). Les
             * deux pouvaient donc se contredire (ex: "hors-ligne" affiché
             * puis "en ligne" après vérification). On aligne ici sur la même
             * source que le badge pour que le résultat du clic ne contredise
             * jamais ce qui était affiché juste avant.
             */
            try {
                $liveOnline = $this->gps->getLiveOnlineMap();
            } catch (\Throwable $e) {
                $liveOnline = [];
            }

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

            /*
             * Le relevé qui vient d'être obtenu est aussi écrit dans le cache
             * du tableau de bord. Sans cela, la fiche était corrigée à
             * l'écran mais le cache gardait l'ancienne valeur : au premier
             * événement temps réel, la fiche se réécrivait depuis le cache et
             * l'état affiché basculait (« hors ligne » puis « en ligne »).
             *
             * Volontairement après la réponse construite et sans condition de
             * réussite : c'est une donnée dérivée, un échec ici ne doit pas
             * dégrader la réponse.
             */
            $this->dashboardCache->updateVehicleRowFromLiveProviderStatus($voiture, $status);

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

        /**
         * Traçabilité : un rallumage manuel envoyé pendant qu'une dette de
         * lease reste ouverte AUJOURD'HUI (non payée, non pardonnée) doit
         * être visible dans l'historique des commandes — pas seulement dans
         * les logs applicatifs — pour que le partenaire sache que ce
         * chauffeur roule sans pardon officiel. Portée strictement au jour
         * courant : une dette d'un jour précédent ne compte plus (chaque
         * jour est traité indépendamment).
         */
        $notes = null;
        if ($action === 'restore') {
            $notes = $this->buildRestoreWithoutForgivenessNote($voiture->id);
        }

        $commande = null;
        if ($cmdNo !== '') {
            $commande = Commande::updateOrCreate(
                ['CmdNo' => $cmdNo],
                [
                    'user_id' => $user->id,
                    'employe_id' => null,
                    'vehicule_id' => $voiture->id,
                    'status' => $commandStatus,
                    'type_commande' => $typeCommande,
                    'notes' => $notes,
                ]
            );
        }

        Cache::forget("gps18gps:engine_status:tracking:{$mac}");
        Cache::forget("gps18gps:engine_status:mobility:{$mac}");

        $after = $this->getLiveEngineStatusWithAccountRetry($mac, true);

        /**
         * Confirmation par l'état moteur réel — pas seulement l'accusé de
         * réception du provider. Premier contrôle immédiat ici (beaucoup de
         * commandes confirment en quelques secondes) ; sinon, la boucle
         * planifiée engine:confirm-manual-commands reprend pendant ~20 min.
         */
        if ($commande) {
            $this->manualCommandConfirmation->recordInitialCheck($commande, $after);
        }

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

        $voitures = $this->tenantPartner($user)->voitures()
            ->select(['voitures.id', 'voitures.immatriculation'])
            ->orderBy('voitures.immatriculation', 'asc')
            ->get();

        $voitureIds = $voitures->pluck('id')->all();

        $vehiculeId = (int) $request->query('vehicule_id', 0);
        $type = trim((string) $request->query('type', ''));

        $commandes = Commande::query()
            ->with([
                'vehicule:id,immatriculation,marque,model',
                'vehicule.chauffeurActuelPartner.chauffeur:id,nom,prenom,phone',
                'user:id,nom,prenom,phone',
            ])
            ->whereIn('vehicule_id', $voitureIds)
            ->where('user_id', $user->id)
            ->when($vehiculeId > 0, fn ($q) => $q->where('vehicule_id', $vehiculeId))
            ->when(in_array($type, ['COUPURE', 'ALLUMAGE'], true), fn ($q) => $q->where('type_commande', $type))
            ->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString();

        return view('coupure_moteur.historique', compact('voitures', 'commandes'));
    }

    /* ====================== Helpers ====================== */

    /**
     * Si ce véhicule a encore, AUJOURD'HUI, une dette de lease ouverte
     * (planifiée, en attente, commande envoyée ou confirmée coupée — jamais
     * payée ni pardonnée), on le dit explicitement dans la note de la
     * commande de rallumage : ce chauffeur vient d'être rallumé sans pardon
     * officiel. Une dette d'un jour précédent n'est jamais prise en compte
     * ici (chaque jour est traité strictement indépendamment).
     */
    private function buildRestoreWithoutForgivenessNote(int $vehicleId): ?string
    {
        /**
         * Anomalie corrigée : le repli 'Africa/Douala' ne s'appliquait jamais
         * puisque la clé config('app.timezone') EXISTE et vaut 'UTC' — c'est
         * le fuseau de stockage, pas d'affichage. "Aujourd'hui" était donc
         * calculé avec la frontière minuit UTC (23h00 heure Douala), pas
         * minuit Douala. Trouvé et corrigé le 24/08/2026.
         */
        $today = now(config('app.display_timezone', 'Africa/Douala'))->toDateString();

        $openHistories = LeaseCutoffHistory::query()
            ->where('vehicle_id', $vehicleId)
            ->whereDate('lease_date_echeance', $today)
            ->whereIn('status', ['PENDING', 'WAITING_STOP', 'COMMAND_SENT', 'CUT_OFF'])
            ->get();

        if ($openHistories->isEmpty()) {
            return null;
        }

        $leaseIds = $openHistories->pluck('lease_id')->filter()->unique()->implode(', ');

        $totalDue = $openHistories->sum(
            fn (LeaseCutoffHistory $h) => (float) (data_get($h->payment_status_snapshot, 'reste_a_payer') ?? 0)
        );

        return sprintf(
            "Le chauffeur vient d'être allumé sans être pardonné. Dette toujours ouverte aujourd'hui sur le(s) lease(s) #%s (reste à payer estimé : %s FCFA).",
            $leaseIds !== '' ? $leaseIds : '?',
            number_format($totalDue, 0, ',', ' ')
        );
    }

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

    /**
     * Convertit un horodatage venu du fournisseur GPS en heure locale
     * affichable.
     *
     * Volontairement tolérant : LocalTime::displayRaw() impose le format
     * strict 'Y-m-d H:i:s' et lève une InvalidFormatException ("Trailing
     * data") dès que le fournisseur renvoie autre chose -- fractions de
     * seconde, suffixe de fuseau, format ISO. Cette valeur vient d'un
     * service externe dont on ne maîtrise pas le format : une date
     * d'affichage ne doit jamais faire tomber la réponse entière.
     *
     * Renvoie null quand la date est absente ou illisible : l'interface
     * conserve alors l'horodatage qu'elle affichait déjà.
     */
    private function formatProviderTime($valeur, string $format = 'd/m/Y H:i'): ?string
    {
        if (empty($valeur)) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($valeur, 'UTC')
                ->setTimezone(config('app.display_timezone', 'Africa/Douala'))
                ->format($format);
        } catch (\Throwable $e) {
            Log::debug('[ENGINE_STATUS] horodatage fournisseur illisible', [
                'valeur' => is_scalar($valeur) ? (string) $valeur : gettype($valeur),
                'erreur' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function buildEnginePayloadFromProviderStatus(array $status): array
    {
        $engineState = $status['decoded']['engineState'] ?? 'UNKNOWN';
        $cut = ($engineState === 'CUT');

        $connectivity = $this->buildGpsStateFromProviderStatus($status);

        /**
         * Position et vitesse remontées telles que le fournisseur les a
         * renvoyées lors de cet appel. Elles étaient déjà présentes dans
         * $status mais n'étaient pas exposées : sans elles, un clic sur un
         * véhicule hors ligne actualisait son état moteur sans jamais
         * rafraîchir sa localisation, ce qui est précisément l'information
         * attendue.
         *
         * Ajout purement additif : aucune clé existante n'est modifiée, les
         * consommateurs actuels de cette réponse ne voient aucun changement.
         *
         * latitude/longitude valent 0 quand le boîtier n'a jamais été
         * localisé : on renvoie null plutôt que des coordonnées au large du
         * golfe de Guinée, qui s'afficheraient comme une position valide.
         */
        $lat = (float) ($status['location']['latitude'] ?? 0);
        $lon = (float) ($status['location']['longitude'] ?? 0);
        $hasPosition = ($lat !== 0.0 || $lon !== 0.0);

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
            'position' => [
                'latitude' => $hasPosition ? $lat : null,
                'longitude' => $hasPosition ? $lon : null,
                'direction' => $status['location']['direction'] ?? null,
                'speed' => isset($status['speed']) ? (float) $status['speed'] : null,
                // Horodatage réel de la position : sur un véhicule hors ligne
                // le fournisseur renvoie sa DERNIÈRE position connue, pas une
                // position actuelle. L'afficher évite de laisser croire le
                // contraire.
                'fixed_at' => $this->formatProviderTime($status['location']['sys_time'] ?? null),
                /*
                 * Même instant que fixed_at, mais dans le format déjà employé
                 * par la fiche pour « Dernière MàJ » (celui du cache). Sans
                 * lui, l'actualisation écrivait une date au format court, que
                 * le rafraîchissement temps réel réécrivait aussitôt au format
                 * long : même valeur, mais saut visible à l'écran.
                 *
                 * Aligné sur heart_time, la source que la fiche privilégie.
                 */
                'fixed_at_raw' => $this->formatProviderTime(
                    $status['location']['heart_time'] ?? $status['location']['sys_time'] ?? null,
                    'Y-m-d H:i:s'
                ),
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

    /**
     * Convertit un horodatage brut du fournisseur GPS (timestamp unix, ms ou
     * s) en chaîne prête à afficher à l'utilisateur — heure de Douala.
     *
     * Anomalie corrigée : les trois branches appelaient
     * ->setTimezone(config('app.timezone')), qui ressemble à une conversion
     * volontaire mais ne fait RIEN puisque config('app.timezone') vaut 'UTC'
     * (fuseau de stockage, pas d'affichage) — la valeur restait donc en UTC
     * brut, une heure en retard sur Douala. Plus trompeur qu'une simple
     * omission : le code semblait déjà "gérer" le fuseau. Le
     * ->toDateTimeString() final ne portant pas le fuseau, chaque appelant
     * qui l'affiche directement (ex. dashboard GPS) doit le traiter comme
     * une heure de Douala déjà prête, pas comme de l'UTC.
     * Trouvé et corrigé le 24/08/2026.
     */
    private function dateTimeString($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $tz = config('app.display_timezone', 'Africa/Douala');

        if ($value instanceof Carbon) {
            return $value->copy()->setTimezone($tz)->toDateTimeString();
        }

        if (is_numeric($value)) {
            $n = (int) $value;
            if ($n <= 0) {
                return null;
            }
            try {
                return ($n >= 1000000000000)
                    ? Carbon::createFromTimestampMs($n)->setTimezone($tz)->toDateTimeString()
                    : Carbon::createFromTimestamp($n)->setTimezone($tz)->toDateTimeString();
            } catch (\Throwable) {
                return null;
            }
        }

        try {
            return Carbon::parse((string) $value)->setTimezone($tz)->toDateTimeString();
        } catch (\Throwable) {
            return null;
        }
    }
}