<?php

namespace App\Services;

use App\Models\Location;
use App\Models\SimGps;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * GpsControlService (18GPS / ASMX)
 * --------------------------------------------------------------------
 * ✅ IMPORTANT (ton besoin)
 * Pour TOUTES les actions "par device" (macId) qui appellent l’API provider,
 * le service va d’abord lire sim_gps.account_name (tracking|mobility),
 * faire setAccount(account) => donc utiliser le BON token (cache isolé).
 *
 * ⚠️ Aucun nom de méthode existante n’a été modifié.
 */
class GpsControlService
{
    // Provider URLs (globales : identiques pour les 2 comptes)
    private string $apiBaseUrl;
    private string $loginUrl;
    private string $getDateUrl;

    // Credentials (varient selon account)
    private string $loginName = '';
    private string $loginPassword = '';
    private string $deviceCmdPassword = '';

    // Login params (globales)
    private string $loginType;
    private string $language;
    private string $timeZone;
    private string $apply;
    private int $isMd5;
    private string $providerLoginUrl;

    // HTTP/Cache
    private int $httpTimeoutSeconds;
    private string $tokenCacheKey = 'gps18gps:tracking:mds_token';
    private int $tokenTtlSeconds;

    // Account courant
    private string $account = 'tracking';

    // Status decoding (moteur/relais)
    private int $accBitIndex;
    private int $relayBitIndex;
    private bool $relayInvert;
    private string $bitOrder;

    // Latest location / connectivity
    private string $defaultMapType;
    private string $defaultOption;
    private int $offlineThresholdMinutes;

    // Cache résolution compte par macId (pour éviter trop de hits DB)
    private int $accountResolveTtlSeconds = 300; // 5 minutes

    public function __construct()
    {
        // ✅ API identique
        $this->apiBaseUrl = rtrim((string) env('GPS_API_URL', 'http://apitest.18gps.net/GetDateServices.asmx'), '/');
        $this->loginUrl   = $this->apiBaseUrl . '/loginSystem';
        $this->getDateUrl = $this->apiBaseUrl . '/GetDate';

        // ✅ params loginSystem (identiques)
        $this->loginType        = (string) env('GPS_LOGIN_TYPE', 'ENTERPRISE');
        $this->language         = (string) env('GPS_LANGUAGE', 'en');
        $this->timeZone         = (string) env('GPS_TIMEZONE', '8');
        $this->apply            = (string) env('GPS_APPLY', 'APP');
        $this->isMd5            = (int) env('GPS_IS_MD5', 0);
        $this->providerLoginUrl = (string) env('GPS_LOGIN_URL', 'http://appzzl.18gps.net/');

        $this->tokenTtlSeconds    = (int) env('GPS_TOKEN_TTL', 1140);
        $this->httpTimeoutSeconds = (int) env('GPS_HTTP_TIMEOUT', 20);

        $this->accBitIndex   = (int) env('GPS_STATUS_ACC_INDEX', 0);
        $this->relayBitIndex = (int) env('GPS_STATUS_RELAY_INDEX', 2);
        $this->relayInvert   = (bool) env('GPS_STATUS_RELAY_INVERT', false);
        $this->bitOrder      = strtoupper((string) env('GPS_STATUS_BIT_ORDER', 'MSB'));

        // ✅ defaults latest location/connectivity
        $this->defaultMapType = (string) env('GPS_MAP_TYPE', 'BAIDU');
        $this->defaultOption  = (string) env('GPS_MAP_OPTION', 'cn');
        $this->offlineThresholdMinutes = (int) env('GPS_OFFLINE_THRESHOLD_MINUTES', 25);

        $this->accountResolveTtlSeconds = (int) env('GPS_ACCOUNT_RESOLVE_TTL', 300);

        // ✅ compte par défaut
        $this->setAccount((string) env('GPS_DEFAULT_ACCOUNT', 'tracking'));
    }

    /**
     * ✅ Switch du compte (tracking / mobility)
     * - change loginName/loginPassword/deviceCmdPassword
     * - isole le token cache par compte
     */
    public function setAccount(string $account): void
    {
        $account = strtolower(trim($account)) ?: strtolower((string) env('GPS_DEFAULT_ACCOUNT', 'tracking'));

        if (!in_array($account, ['tracking', 'mobility'], true)) {
            $account = strtolower((string) env('GPS_DEFAULT_ACCOUNT', 'tracking')) ?: 'tracking';
        }

        $key = strtoupper($account); // TRACKING / MOBILITY

        $loginName = (string) env("GPS_{$key}_LOGIN_NAME", '');
        $password  = (string) env("GPS_{$key}_LOGIN_PASSWORD", '');
        $devicePwd = (string) env("GPS_{$key}_DEVICE_CMD_PASSWORD", $password);

        // fallback compat si variables multi-compte manquantes
        if ($loginName === '' || $password === '') {
            $loginName = (string) env('GPS_LOGIN_NAME', '');
            $password  = (string) env('GPS_LOGIN_PASSWORD', '');
            $devicePwd = (string) env('GPS_DEVICE_CMD_PASSWORD', $password);
        }

        $this->account           = $account;
        $this->loginName         = $loginName;
        $this->loginPassword     = $password;
        $this->deviceCmdPassword = $devicePwd;

        // ✅ token isolé par compte
        $this->tokenCacheKey = "gps18gps:{$this->account}:mds_token";
    }

    public function getAccount(): string
    {
        return $this->account;
    }

    /* =========================================================
     * ✅ NOUVEAU (sans casser les méthodes) : Résolution compte par macId (DB sim_gps)
     * ========================================================= */

    /**
     * Résout tracking|mobility depuis sim_gps.account_name.
     * - Si pas trouvé ou invalide => null (et on NE CHANGE PAS de compte)
     */
    private function resolveAccountFromDbByMacId(string $macId): ?string
    {
        $macId = trim($macId);
        if ($macId === '') return null;

        $cacheKey = "gps18gps:macid_account:" . $macId;

        try {
            $acc = Cache::remember($cacheKey, $this->accountResolveTtlSeconds, function () use ($macId) {
                $val = SimGps::query()
                    ->where('mac_id', $macId)
                    ->value('account_name');

                $val = strtolower(trim((string) $val));
                return in_array($val, ['tracking', 'mobility'], true) ? $val : '';
            });

            $acc = strtolower(trim((string) $acc));
            return $acc !== '' ? $acc : null;
        } catch (\Throwable $e) {
            Log::warning('[GPS] resolveAccountFromDbByMacId failed', [
                'macid' => $macId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Garantit que le service est positionné sur le bon compte pour CE macId.
     * - Si macId n’existe pas en DB => NO-OP (préserve un setAccount() explicite fait ailleurs)
     */
    private function ensureAccountForMacId(string $macId): void
    {
        $acc = $this->resolveAccountFromDbByMacId($macId);
        if (!$acc) return;

        if ($acc !== $this->account) {
            $this->setAccount($acc);
        }
    }

    /* =========================================================
     * Helpers (ASMX + formats rows/data)
     * ========================================================= */

    private function nowIso(): string
    {
        try { return now()->toISOString(); } catch (\Throwable) { return ''; }
    }

    /**
     * Convertit epoch ms vers Carbon (timezone app).
     */
    private function msToCarbon(?int $ms): ?\Carbon\Carbon
    {
        if (!$ms || $ms <= 0) return null;
        try {
            return \Carbon\Carbon::createFromTimestampMs($ms)->setTimezone(config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Retourne un tableau à partir d’une clé racine (ex: rows/data) même si :
     *  - la clé change de casse
     *  - la valeur est une string JSON
     */
    private function getRootArray(array $resp, array $keys): array
    {
        foreach ($keys as $k) {
            if (!array_key_exists($k, $resp)) continue;

            $v = $resp[$k];
            if (is_array($v)) return $v;

            if (is_string($v)) {
                $j = json_decode($v, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($j)) return $j;
            }

            return [];
        }
        return [];
    }

    /**
     * Chez toi, getDeviceList renvoie la liste sous "rows".
     */
    private function getProviderRows(array $resp): array
    {
        return $this->getRootArray($resp, ['rows', 'Rows', 'ROWS']);
    }

    /**
     * Certains endpoints (notamment key/records) renvoient sous "data".
     */
    private function getProviderData(array $resp): array
    {
        return $this->getRootArray($resp, ['data', 'Data', 'DATA']);
    }

    /**
     * Pour les endpoints key/records, le block est souvent data[0] (ou parfois rows[0]).
     */
    private function getProviderFirstBlockForKeyed(array $resp): ?array
    {
        $data = $this->getProviderData($resp);
        if (!empty($data) && is_array($data[0] ?? null)) return $data[0];

        $rows = $this->getProviderRows($resp);
        if (!empty($rows) && is_array($rows[0] ?? null)) return $rows[0];

        return null;
    }

    private function decodeAsmxJson(string $body): ?array
    {
        $body = trim($body);

        $json = json_decode($body, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
            if (isset($json['d']) && is_string($json['d'])) {
                $inner = trim($json['d']);
                $j2 = json_decode($inner, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($j2)) return $j2;
            }
            return $json;
        }

        if (preg_match('/<string[^>]*>(.*?)<\/string>/s', $body, $m)) {
            $inner = html_entity_decode($m[1], ENT_QUOTES | ENT_XML1, 'UTF-8');
            $inner = trim($inner);

            $json = json_decode($inner, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($json)) return $json;
        }

        $stripped = html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_XML1, 'UTF-8');
        $stripped = trim($stripped);

        $json = json_decode($stripped, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($json)) return $json;

        return null;
    }

    /**
     * Vérifie le succès provider (tolérant: true/"true"/"True"/1/"1")
     */
    private function isProviderSuccess(array $data): bool
    {
        $success   = $data['success'] ?? null;
        $errorCode = (string) ($data['errorCode'] ?? ($data['code'] ?? ''));

        $errorCode = trim($errorCode);

        $successOk = false;
        if ($success === true || $success === 1 || $success === '1') {
            $successOk = true;
        } elseif (is_string($success)) {
            $successOk = strtolower(trim($success)) === 'true';
        }

        $errorOk = ($errorCode === '' || $errorCode === '200' || $errorCode === '0');

        return $successOk && $errorOk;
    }

    private function getWithParams(string $url, array $params): array
    {
        try {
            $res  = Http::timeout($this->httpTimeoutSeconds)->get($url, $params);
            $body = $res->body();

            if (!$res->successful()) {
                Log::warning('[GPS] HTTP non OK', [
                    'account' => $this->account,
                    'url' => $url,
                    'status' => $res->status(),
                    'body' => $body,
                ]);

                return [
                    'success' => 'false',
                    'errorCode' => (string) $res->status(),
                    'errorDescribe' => 'HTTP error',
                    'raw' => $body,
                ];
            }

            $json = $res->json();
            if (is_array($json)) {
                if (isset($json['d']) && is_string($json['d'])) {
                    $inner = json_decode($json['d'], true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($inner)) return $inner;
                }
                return $json;
            }

            $decoded = $this->decodeAsmxJson($body);
            if (is_array($decoded)) return $decoded;

            return [
                'success' => 'false',
                'errorCode' => '500',
                'errorDescribe' => 'Réponse non décodable (ASMX)',
                'raw' => $body,
            ];
        } catch (\Throwable $e) {
            Log::error('[GPS] Exception HTTP', [
                'account' => $this->account,
                'url' => $url,
                'params' => $params,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => 'false',
                'errorCode' => '500',
                'errorDescribe' => 'Exception HTTP: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Appel générique vers /GetDate?method=...&mds=...
     * - Injecte automatiquement le token mds (loginGps)
     * - Retry 1 fois si errorCode=403 (token expiré)
     */
    private function callGetDate(string $method, array $params, bool $retryOn403 = true): array
    {
        $token = $this->loginGps(false);
        if (!$token) {
            return [
                'success' => 'false',
                'errorCode' => '401',
                'errorDescribe' => "Token (mds) indisponible (login échoué) [account={$this->account}]",
                'data' => [],
            ];
        }

        $payload = array_merge($params, [
            'method' => $method,
            'mds'    => $token,
        ]);

        $data = $this->getWithParams($this->getDateUrl, $payload);

        // Token expiré -> refresh + retry 1 fois
        if ($retryOn403 && (string)($data['errorCode'] ?? '') === '403') {
            $this->resetGpsToken();
            $token2 = $this->loginGps(true);
            if ($token2) {
                $payload['mds'] = $token2;
                $data = $this->getWithParams($this->getDateUrl, $payload);
            }
        }

        return $data;
    }

    /* =========================================================
     * 1) LOGIN (mds)
     * ========================================================= */

    public function loginGps(bool $forceRefresh = false): ?string
    {
        if (!$forceRefresh) {
            $cached = Cache::get($this->tokenCacheKey);
            if (!empty($cached)) return (string) $cached;
        }

        if ($this->loginName === '' || $this->loginPassword === '') {
            Log::error('[GPS] Identifiants manquants', [
                'account' => $this->account,
                'hint_env' => "GPS_" . strtoupper($this->account) . "_LOGIN_NAME / PASSWORD",
            ]);
            return null;
        }

        $params = [
            'LoginName'     => $this->loginName,
            'LoginPassword' => $this->loginPassword,
            'LoginType'     => $this->loginType,
            'language'      => $this->language,
            'timeZone'      => $this->timeZone,
            'apply'         => $this->apply,
            'ISMD5'         => $this->isMd5,
            'loginUrl'      => $this->providerLoginUrl,
        ];

        $data  = $this->getWithParams($this->loginUrl, $params);
        $token = $data['mds'] ?? null;

        if ($this->isProviderSuccess($data) && !empty($token)) {
            Cache::put($this->tokenCacheKey, (string) $token, now()->addSeconds($this->tokenTtlSeconds));
            return (string) $token;
        }

        Log::warning('[GPS] Login échoué', [
            'account' => $this->account,
            'errorCode' => $data['errorCode'] ?? null,
            'errorDescribe' => $data['errorDescribe'] ?? ($data['msg'] ?? null),
            'raw' => $data['raw'] ?? $data,
        ]);

        return null;
    }

    public function resetGpsToken(): void
    {
        Cache::forget($this->tokenCacheKey);
    }

    /* =========================================================
     * 2) SEND COMMANDS
     * ========================================================= */

    public function sendCommand(string $macId, string $command): array
    {
        return $this->sendCommandRaw($macId, $command, '');
    }

    public function sendCommandWithParam(string $macId, string $command, string $param): array
    {
        return $this->sendCommandRaw($macId, $command, $param);
    }

    private function sendCommandRaw(string $macId, string $command, string $param = ''): array
    {
        // ✅ AUTO-ACCOUNT: bascule selon sim_gps.account_name (si dispo)
        $this->ensureAccountForMacId($macId);

        $macId   = trim($macId);
        $command = strtoupper(trim($command));

        $payload = [
            'macid'    => $macId,
            'cmd'      => $command,
            'param'    => $param,
            'pwd'      => $this->deviceCmdPassword,
            'sendTime' => $this->nowIso(),
        ];

        return $this->callGetDate('SendCommands', $payload, true);
    }

    // Wrappers
    public function openRelay(string $macId): array { return $this->sendCommand($macId, 'OPENRELAY'); }
    public function closeRelay(string $macId): array { return $this->sendCommand($macId, 'CLOSERELAY'); }

    public function cutEngine(string $macId): array { return $this->closeRelay($macId); }
    public function restoreEngine(string $macId): array { return $this->openRelay($macId); }

    public function safeOn(string $macId): array { return $this->sendCommand($macId, 'SAFEON'); }
    public function safeOff(string $macId): array { return $this->sendCommand($macId, 'SAFEOFF'); }

    public function cutOffPetrol(string $macId): array { return $this->sendCommand($macId, 'CUTOFFPETROL'); }
    public function resumePetrol(string $macId): array { return $this->sendCommand($macId, 'RESUMEPETROL'); }

    public function passThroughAscii(string $macId, string $asciiPayload): array
    {
        return $this->sendCommandWithParam($macId, 'PASSTHROUGH', '0,' . $asciiPayload);
    }

    public function passThroughHex(string $macId, string $hexPayload): array
    {
        return $this->sendCommandWithParam($macId, 'PASSTHROUGH', '1,' . $hexPayload);
    }

    /* =========================================================
     * 3) HISTORY (commandes)
     * ========================================================= */

    public function getSendCmdList(
        string $macId,
        ?string $cmdNo = null,
        ?string $startTime = null,
        ?string $endTime = null
    ): array {
        // ✅ AUTO-ACCOUNT
        $this->ensureAccountForMacId($macId);

        $payload = [
            'macid' => trim($macId),
            'cmdNo' => $cmdNo ? trim($cmdNo) : '',
            'startTime' => $startTime ? trim($startTime) : '',
            'endTime' => $endTime ? trim($endTime) : '',
        ];

        return $this->callGetDate('GetSendCmdList', $payload, true);
    }

    public function clearCmdList(string $macId): array
    {
        // ✅ AUTO-ACCOUNT
        $this->ensureAccountForMacId($macId);

        return $this->callGetDate('ClearCmdList', ['macid' => trim($macId)], true);
    }

    /* =========================================================
     * 4) STATUT MOTEUR depuis DB
     * ========================================================= */

    private function normalizeStatusToBits(?string $status): ?string
    {
        if ($status === null) return null;
        $s = trim($status);
        if ($s === '') return null;

        if (preg_match('/^[01]{3,}$/', $s)) return $s;

        if (preg_match('/^0x[0-9a-f]+$/i', $s)) {
            $n = hexdec(substr($s, 2));
            return str_pad(decbin($n), 16, '0', STR_PAD_LEFT);
        }

        if (preg_match('/^\d+$/', $s)) {
            $n = (int) $s;
            return str_pad(decbin($n), 16, '0', STR_PAD_LEFT);
        }

        return null;
    }

    private function getBit(?string $bits, int $index): ?bool
    {
        if (!$bits || $index < 0) return null;

        $len = strlen($bits);
        if ($len === 0) return null;

        $pos = ($this->bitOrder === 'LSB') ? ($len - 1 - $index) : $index;

        if ($pos < 0 || $pos >= $len) return null;

        return $bits[$pos] === '1';
    }

    public function decodeEngineStatus(?string $status): array
    {
        $bits  = $this->normalizeStatusToBits($status);
        $acc   = $this->getBit($bits, $this->accBitIndex);
        $relay = $this->getBit($bits, $this->relayBitIndex);

        if ($relay !== null && $this->relayInvert) {
            $relay = !$relay;
        }

        $engineState = 'UNKNOWN';
        if ($relay === false) $engineState = 'CUT';
        elseif ($relay === true && $acc === true) $engineState = 'ON';
        elseif ($relay === true && $acc === false) $engineState = 'OFF';

        return [
            'status_raw'   => $status,
            'status_bits'  => $bits,
            'accState'     => $acc,
            'relayState'   => $relay,
            'engineState'  => $engineState,
        ];
    }

    public function getEngineStatusFromLastLocation(string $macId): array
{
    $macId = trim($macId);
    if ($macId === '') {
        return ['success' => false, 'message' => 'mac_id_gps vide'];
    }

    /**
     * ✅ Provider FIRST
     * - on se met sur le bon compte via sim_gps.account_name
     * - on récupère objectid (user_id provider) depuis la DB
     * - on appelle getUserAndGpsInfoByIDsUtcNew (via getLatestLocationByUserId)
     */
    try {
        // ✅ AUTO-ACCOUNT selon sim_gps.account_name
        $this->ensureAccountForMacId($macId);

        // (optionnel mais utile) petit cache anti-spam UI : 8 secondes
        $cacheKey = "gps18gps:engine_status:{$this->account}:{$macId}";

        $cached = Cache::get($cacheKey);
        if (is_array($cached)) return $cached;

        // 1) userId direct depuis DB (beaucoup + rapide que getDeviceList à chaque fois)
        $userId = (string) (SimGps::query()->where('mac_id', $macId)->value('objectid') ?? '');
        $userId = trim($userId);

        // fallback si objectid absent en DB
        if ($userId === '') {
            $devices = $this->getAccountDeviceList();
            $userId = (string) ($this->resolveUserIdFromDeviceList($macId, $devices) ?? '');
            $userId = trim($userId);
        }

        if ($userId === '') {
            $resp = [
                'success' => false,
                'message' => 'Impossible de résoudre user_id/objectid pour ce mac_id (device introuvable)',
                'mac_id_gps' => $macId,
            ];
            Cache::put($cacheKey, $resp, now()->addSeconds(5));
            return $resp;
        }

        // 2) dernier record provider
        $record = $this->getLatestLocationByUserId($userId, null, null);

        if (!$record || !is_array($record)) {
            $resp = [
                'success' => false,
                'message' => 'Aucun dernier record provider (ou erreur provider)',
                'mac_id_gps' => $macId,
                'user_id' => $userId,
            ];
            Cache::put($cacheKey, $resp, now()->addSeconds(5));
            return $resp;
        }

        // 3) décodage du statut moteur
        $decoded = $this->decodeEngineStatus((string)($record['status'] ?? null));

        $speed = 0.0;
        if (isset($record['su'])) $speed = (float) $record['su'];
        elseif (isset($record['speed'])) $speed = (float) $record['speed'];

        $heart = $this->parseTimeToDateTimeString($record['heart_time'] ?? null);
        $sys   = $this->parseTimeToDateTimeString($record['sys_time'] ?? null);
        $dt    = $this->parseTimeToDateTimeString($record['datetime'] ?? null);

        $datetime = (string) ($this->firstNonEmpty($dt, $heart, $sys) ?? '');

        $resp = [
            'success' => true,
            'mac_id_gps' => $macId,
            'datetime' => $datetime,
            'speed' => $speed,
            'decoded' => $decoded,
            'location' => [
                'longitude' => (float) ($record['jingdu'] ?? $record['longitude'] ?? 0),
                'latitude'  => (float) ($record['weidu'] ?? $record['latitude'] ?? 0),
                'direction' => $record['hangxiang'] ?? $record['direction'] ?? null,
                'sys_time'  => $sys,
                'heart_time'=> $heart,
            ],
            // optionnel : aide debug
            'source' => 'provider',
            'account' => $this->account,
            'user_id' => $userId,
        ];

        Cache::put($cacheKey, $resp, now()->addSeconds(8));
        return $resp;

    } catch (\Throwable $e) {
        Log::warning('[GPS] getEngineStatusFromLastLocation provider failed', [
            'macid' => $macId,
            'account' => $this->account,
            'error' => $e->getMessage(),
        ]);
        // on continue en fallback DB ci-dessous
    }

    /**
     * 🔁 Fallback DB (au cas où le provider est down)
     * Tu peux le supprimer si tu veux ABSOLUMENT uniquement provider.
     */
    $loc = Location::query()
        ->where('mac_id_gps', $macId)
        ->orderByDesc('datetime')
        ->first();

    if (!$loc) {
        return [
            'success' => false,
            'message' => 'Aucune location trouvée (DB) et provider indisponible',
            'mac_id_gps' => $macId,
        ];
    }

    $decoded = $this->decodeEngineStatus($loc->status);

    return [
        'success' => true,
        'mac_id_gps' => $macId,
        'datetime' => (string) $loc->datetime,
        'speed' => (float) ($loc->speed ?? 0),
        'decoded' => $decoded,
        'location' => [
            'longitude' => (float) $loc->longitude,
            'latitude'  => (float) $loc->latitude,
            'direction' => $loc->direction,
            'sys_time'  => $loc->sys_time,
            'heart_time'=> $loc->heart_time,
        ],
        'source' => 'db_fallback',
    ];
}


    /* =========================================================
     * 5) DEVICE LIST
     * ========================================================= */

    private function extractKeyedRecords(array $resp): array
    {
        if (!$this->isProviderSuccess($resp)) return [];

        $block = $this->getProviderFirstBlockForKeyed($resp);
        if (!is_array($block)) return [];

        $key     = $block['key'] ?? null;
        $records = $block['records'] ?? null;

        if (!is_array($key) || !is_array($records)) return [];

        $out = [];
        foreach ($records as $row) {
            if (!is_array($row)) continue;

            $item = [];
            foreach ($key as $field => $idx) {
                $item[$field] = $row[$idx] ?? null;
            }
            $out[] = $item;
        }

        return $out;
    }

    /**
     * ✅ Liste normalisée des devices du compte courant.
     */
    public function getAccountDeviceList(): array
    {
        $resp = $this->callGetDate('getDeviceList', [], true);
        if (!$this->isProviderSuccess($resp)) return [];

        // ✅ format "rows" (ton cas)
        $rows = $this->getProviderRows($resp);
        if (!empty($rows)) return $rows;

        // 🔁 fallback si un jour le provider renvoie "data"
        $data = $this->getProviderData($resp);
        if (!empty($data)) {
            $first = $data[0] ?? null;
            if (is_array($first) && (isset($first['objectid']) || isset($first['macid']))) return $data;
            return $this->extractKeyedRecords($resp);
        }

        return [];
    }

    public function getAccountDeviceListRaw(): array
    {
        return $this->callGetDate('getDeviceList', [], true);
    }

    public function getSubUnitDeviceList(string $unitId, string $mapType = ''): array
    {
        $payload = [
            'id' => trim($unitId),
            'mapType' => $mapType,
        ];

        $resp = $this->callGetDate('getDeviceListByCustomId', $payload, true);

        $rows = $this->getProviderRows($resp);
        if (!empty($rows)) return $rows;

        return $this->extractKeyedRecords($resp);
    }

    public function getSubUnitDeviceListRaw(string $unitId, string $mapType = ''): array
    {
        $payload = ['id' => trim($unitId), 'mapType' => $mapType];
        return $this->callGetDate('getDeviceListByCustomId', $payload, true);
    }

    /* =========================================================
     * 6) Latest Location (getUserAndGpsInfoByIDsUtcNew)
     * ========================================================= */

    public function getLatestLocationRawByUserId(string $userId, ?string $mapType = null, ?string $option = null): array
    {
        $userId  = trim($userId);
        $mapType = $mapType !== null ? trim($mapType) : $this->defaultMapType;
        $option  = $option  !== null ? trim($option)  : $this->defaultOption;

        if ($userId === '') {
            return [
                'success' => 'false',
                'errorCode' => '400',
                'errorDescribe' => 'user_id is required',
                'data' => [],
            ];
        }

        return $this->callGetDate('getUserAndGpsInfoByIDsUtcNew', [
            'mapType' => $mapType,
            'option'  => $option,
            'user_id' => $userId,
        ], true);
    }

    public function getLatestLocationByUserId(string $userId, ?string $mapType = null, ?string $option = null): ?array
    {
        $resp = $this->getLatestLocationRawByUserId($userId, $mapType, $option);
        if (!$this->isProviderSuccess($resp)) return null;

        $items = $this->extractKeyedRecords($resp);
        if (!empty($items)) return $items[0] ?? null;

        $data = $this->getProviderData($resp);
        $first = $data[0] ?? null;
        if (is_array($first)) return $first;

        return null;
    }

    public function resolveUserIdFromDeviceList(string $macId, ?array $deviceList = null): ?string
    {
        $macId = trim($macId);
        if ($macId === '') return null;

        // ✅ AUTO-ACCOUNT (si on appelle cette méthode directement)
        $this->ensureAccountForMacId($macId);

        $deviceList = $deviceList ?? $this->getAccountDeviceList();

        foreach ($deviceList as $d) {
            if (!is_array($d)) continue;

            $dMac = (string) ($d['macid'] ?? '');
            if ($dMac === $macId) {
                $id = (string) ($d['user_id'] ?? ($d['objectid'] ?? ''));
                $id = trim($id);
                return $id !== '' ? $id : null;
            }
        }

        return null;
    }

    public function getLatestLocationByMacId(string $macId, ?string $mapType = null, ?string $option = null): ?array
    {
        // ✅ AUTO-ACCOUNT
        $this->ensureAccountForMacId($macId);

        $devices = $this->getAccountDeviceList();
        $userId = $this->resolveUserIdFromDeviceList($macId, $devices);
        if (!$userId) return null;

        return $this->getLatestLocationByUserId($userId, $mapType, $option);
    }

    /* =========================================================
     * 7) Online/Offline + Durée offline
     * ========================================================= */

    public function decodeOfflineCodeFromDeviceList($offlineCode): array
    {
        $code = (string) $offlineCode;

        return match ($code) {
            '0' => ['state' => 'OFFLINE', 'is_online' => false],
            '1' => ['state' => 'ONLINE_STATIONARY', 'is_online' => true],
            '2' => ['state' => 'ONLINE_MOVING', 'is_online' => true],
            default => ['state' => 'UNKNOWN', 'is_online' => null],
        };
    }

    public function computeConnectivityFromLatestRecord(array $record, ?int $thresholdMinutes = null): array
    {
        $thresholdMinutes = $thresholdMinutes ?? $this->offlineThresholdMinutes;
        $thresholdMs = max(1, $thresholdMinutes) * 60 * 1000;

        $serverMs = isset($record['server_time']) ? (int) $record['server_time'] : 0;
        $heartMs  = isset($record['heart_time']) ? (int) $record['heart_time'] : 0;
        $dtMs     = isset($record['datetime']) ? (int) $record['datetime'] : 0;

        $speed = 0.0;
        if (isset($record['su'])) $speed = (float) $record['su'];
        elseif (isset($record['speed'])) $speed = (float) $record['speed'];

        if ($serverMs <= 0 || $heartMs <= 0) {
            return [
                'state' => 'UNKNOWN',
                'is_online' => null,
                'reason' => 'missing server_time or heart_time',
                'threshold_minutes' => $thresholdMinutes,
                'server_time_ms' => $serverMs ?: null,
                'heart_time_ms' => $heartMs ?: null,
            ];
        }

        $offlineMs = $serverMs - $heartMs;
        $staticMs  = ($dtMs > 0) ? ($serverMs - $dtMs) : null;

        $isOffline = $offlineMs >= $thresholdMs;
        $state = $isOffline ? 'OFFLINE' : (($speed == 0.0) ? 'ONLINE_STATIONARY' : 'ONLINE_MOVING');

        if ($speed == -9.0 && $isOffline) {
            $state = 'DISABLED';
        }

        $expireMs = isset($record['expire_date']) ? (int) $record['expire_date'] : 0;
        $isExpired = null;
        $expiredSinceMs = null;
        if ($expireMs > 0) {
            $isExpired = $serverMs > $expireMs;
            if ($isExpired) $expiredSinceMs = $serverMs - $expireMs;
        }

        return [
            'state' => $state,
            'is_online' => !$isOffline,
            'threshold_minutes' => $thresholdMinutes,

            'offline_time_ms' => $offlineMs,
            'offline_time_seconds' => (int) floor($offlineMs / 1000),

            'static_time_ms' => $staticMs,
            'static_time_seconds' => $staticMs !== null ? (int) floor($staticMs / 1000) : null,

            'offline_since_ms' => $heartMs,
            'offline_since_at' => $this->msToCarbon($heartMs)?->toDateTimeString(),

            'server_time_ms' => $serverMs,
            'server_time_at' => $this->msToCarbon($serverMs)?->toDateTimeString(),

            'expire_date_ms' => $expireMs ?: null,
            'expire_date_at' => $expireMs ? $this->msToCarbon($expireMs)?->toDateTimeString() : null,
            'is_expired' => $isExpired,
            'expired_since_ms' => $expiredSinceMs,
            'expired_since_seconds' => $expiredSinceMs !== null ? (int) floor($expiredSinceMs / 1000) : null,
        ];
    }

    public function getConnectivityByUserId(string $userId, ?string $mapType = null, ?string $option = null, ?int $thresholdMinutes = null): array
    {
        $record = $this->getLatestLocationByUserId($userId, $mapType, $option);

        if (!$record) {
            return [
                'success' => false,
                'message' => 'No latest location record (or provider error)',
                'user_id' => $userId,
            ];
        }

        return [
            'success' => true,
            'user_id' => $userId,
            'record' => $record,
            'connectivity' => $this->computeConnectivityFromLatestRecord($record, $thresholdMinutes),
        ];
    }

    public function getConnectivityByMacId(string $macId, ?string $mapType = null, ?string $option = null, ?int $thresholdMinutes = null): array
    {
        // ✅ AUTO-ACCOUNT
        $this->ensureAccountForMacId($macId);

        $devices = $this->getAccountDeviceList();
        $userId = $this->resolveUserIdFromDeviceList($macId, $devices);

        if (!$userId) {
            return [
                'success' => false,
                'message' => 'Device not found in device list (cannot resolve user_id)',
                'macid' => $macId,
            ];
        }

        $resp = $this->getConnectivityByUserId($userId, $mapType, $option, $thresholdMinutes);
        $resp['macid'] = $macId;
        return $resp;
    }

    /* =========================================================
     * 8) Persistance DB (Location)
     * ========================================================= */

  

    public function syncLatestLocationByMacId(string $macId, ?string $mapType = null, ?string $option = null): array
    {
        // ✅ AUTO-ACCOUNT (via getLatestLocationByMacId)
        $record = $this->getLatestLocationByMacId($macId, $mapType, $option);
        if (!$record) {
            return [
                'success' => false,
                'message' => 'No latest location record',
                'macid' => $macId,
            ];
        }

        $connectivity = $this->computeConnectivityFromLatestRecord($record, null);
        $saved = $this->saveLatestLocationRecordToDb($record, $macId);

        return [
            'success' => true,
            'macid' => $macId,
            'record' => $record,
            'connectivity' => $connectivity,
            'saved' => (bool) $saved,
            'location_id' => $saved?->id,
        ];
    }






    // helper 

    private function parseTimeToDateTimeString($value): ?string
{
    if ($value === null) return null;

    // numeric (int/float/string)
    if (is_numeric($value)) {
        $n = (int) $value;

        // heuristique : > 10^12 = millisecondes
        if ($n > 1000000000000) {
            return $this->msToCarbon($n)?->toDateTimeString();
        }

        // secondes (epoch)
        if ($n > 1000000000) {
            try {
                return \Carbon\Carbon::createFromTimestamp($n)->setTimezone(config('app.timezone'))->toDateTimeString();
            } catch (\Throwable) {
                return null;
            }
        }
    }

    // string date
    if (is_string($value)) {
        $s = trim($value);
        if ($s === '') return null;

        // si c'est une string numérique ms/sec
        if (is_numeric($s)) return $this->parseTimeToDateTimeString((int)$s);

        try {
            return \Carbon\Carbon::parse($s)->setTimezone(config('app.timezone'))->toDateTimeString();
        } catch (\Throwable) {
            return null;
        }
    }

    return null;
}

private function firstNonEmpty(...$values)
{
    foreach ($values as $v) {
        if ($v === null) continue;
        if (is_string($v) && trim($v) === '') continue;
        return $v;
    }
    return null;
}




public function getCommandResults(string $macId, string $cmdNo): array
{
    $this->ensureAccountForMacId($macId);

    return $this->callGetDate('GetCommandResults', [
        'macid' => trim($macId),
        'cmdNo' => trim($cmdNo),
    ], true);
}










// test l'enregistrement des coordonné dans locations 
/**
 * ✅ Sync 1 device (macId) -> enregistre la dernière position 18GPS dans la table locations
 * - Respecte le principe multi-compte via sim_gps.account_name (tracking|mobility)
 * - Utilise objectid si dispo (plus rapide), sinon fallback macId -> device list
 * - Optionnel: anti-dup (si la dernière datetime identique existe déjà)
 *
 * @param string $macId
 * @param bool $avoidDuplicate  éviter d'insérer si datetime identique déjà en DB
 * @return array{success:bool,message?:string,account?:string,source?:string,location_id?:int,mac_id_gps?:string,user_id?:string,record?:array}
 */
public function syncAndSaveLatestLocationByMacId(string $macId, bool $avoidDuplicate = true): array
{
    $macId = trim($macId);
    if ($macId === '') {
        return ['success' => false, 'message' => 'mac_id_gps vide'];
    }

    // ✅ se positionner sur le bon compte (tracking/mobility) via sim_gps.account_name
    $this->ensureAccountForMacId($macId);

    try {
        // 1) userId/objectid depuis DB (le plus rapide)
        $userId = (string) (SimGps::query()->where('mac_id', $macId)->value('objectid') ?? '');
        $userId = trim($userId);

        // 2) dernier record provider
        $record = null;

        if ($userId !== '') {
            $record = $this->getLatestLocationByUserId($userId, null, null);
        } else {
            // fallback si objectid absent
            $record = $this->getLatestLocationByMacId($macId, null, null);
        }

        if (!$record || !is_array($record)) {
            return [
                'success' => false,
                'message' => 'Aucun record provider (ou erreur provider)',
                'mac_id_gps' => $macId,
                'account' => $this->account,
                'user_id' => $userId ?: null,
            ];
        }

        // 3) anti-dup simple (si datetime identique déjà en DB)
        if ($avoidDuplicate) {
            $dt = $record['datetime'] ?? $record['heart_time'] ?? $record['sys_time'] ?? null;

            if ($dt !== null) {
                $exists = Location::query()
                    ->where('mac_id_gps', $macId)
                    ->where('datetime', $dt)
                    ->exists();

                if ($exists) {
                    return [
                        'success' => true,
                        'message' => 'Déjà enregistré (duplicate datetime)',
                        'mac_id_gps' => $macId,
                        'account' => $this->account,
                        'source' => 'provider',
                        'user_id' => $userId ?: null,
                        'location_id' => null,
                    ];
                }
            }
        }

        // 4) save DB (mapping déjà géré par ta méthode)
        $loc = $this->saveLatestLocationRecordToDb($record, $macId);

        if (!$loc) {
            return [
                'success' => false,
                'message' => "Échec enregistrement DB",
                'mac_id_gps' => $macId,
                'account' => $this->account,
                'source' => 'provider',
                'user_id' => $userId ?: null,
            ];
        }

        return [
            'success' => true,
            'message' => 'Location enregistrée',
            'mac_id_gps' => $macId,
            'account' => $this->account,
            'source' => 'provider',
            'user_id' => $userId ?: null,
            'location_id' => $loc->id,
            'record' => $record, // optionnel (debug)
        ];
    } catch (\Throwable $e) {
        Log::warning('[GPS] syncAndSaveLatestLocationByMacId failed', [
            'macid' => $macId,
            'account' => $this->account,
            'error' => $e->getMessage(),
        ]);

        return [
            'success' => false,
            'message' => 'Exception: ' . $e->getMessage(),
            'mac_id_gps' => $macId,
            'account' => $this->account,
        ];
    }
}





/**
 * Sauvegarde d'un record location (déduplication simple)
 * ✅ utilise normalizeLocationForDb()
 */
public function saveLatestLocationRecordToDb(array $record, string $macId, string $account): array
{
    try {
        $macId = trim($macId);
        if ($macId === '') {
            return ['success' => false, 'reason' => 'mac_id missing'];
        }

        $data = $this->normalizeLocationForDb($record, $macId);

        // on exige au moins sys_time (sinon DB peut planter ou donnée inutile)
        if (empty($data['sys_time'])) {
            return ['success' => false, 'reason' => 'sys_time invalid', 'account' => $account];
        }

        // dédup : mac + sys_time
        $exists = Location::query()
            ->where('mac_id_gps', $macId)
            ->where('sys_time', $data['sys_time'])
            ->exists();

        if ($exists) {
            return ['success' => true, 'duplicate' => true, 'account' => $account];
        }

        Location::create($data);

        return ['success' => true, 'saved' => true, 'account' => $account];
    } catch (\Throwable $e) {
        Log::error('[GPS] saveLatestLocationRecordToDb exception', [
            'account' => $account,
            'macid' => $macId,
            'error' => $e->getMessage(),
        ]);

        return ['success' => false, 'reason' => 'exception', 'account' => $account];
    }
}










/* ========================================================================
 |  GPS -> LOCATIONS (BATCH + NORMALISATION)  ✅ A COLLER EN BAS DU SERVICE
 |  (Juste avant la dernière accolade "}" de la class GpsControlService)
 |
 |  But :
 |   - Lire tous les mac_id dans sim_gps
 |   - Utiliser account_name (tracking|mobility) pour l’auth (setAccount)
 |   - Récupérer le dernier record via objectid (getLatestLocationByUserId)
 |   - Normaliser via normalizedlactionfordb()
 |   - Enregistrer dans locations
 * ====================================================================== */

/**
 * Normalise le nom de compte (tracking|mobility) avec fallback tracking
 */
private function gpsNormalizeAccount(?string $account): string
{
    $a = strtolower(trim((string) $account));
    return in_array($a, ['tracking', 'mobility'], true) ? $a : 'tracking';
}

/**
 * Parse date provider -> "Y-m-d H:i:s" (compatible MySQL DATETIME 1000..9999)
 * - accepte ms / sec / string date
 * - retourne null si invalide (0, vide, hors plage MySQL, parse impossible)
 */
private function gpsParseDateTimeForMysql($value): ?string
{
    if ($value === null) return null;

    // string "0" / vide / null-like
    if (is_string($value)) {
        $s = trim($value);
        if ($s === '' || $s === '0' || $s === '0000-00-00 00:00:00') return null;

        // numeric en string
        if (is_numeric($s)) $value = (int) $s;
        else {
            try {
                $dt = \Carbon\Carbon::parse($s)->setTimezone(config('app.timezone'));
                $year = (int) $dt->format('Y');
                if ($year < 1000 || $year > 9999) return null;
                return $dt->format('Y-m-d H:i:s');
            } catch (\Throwable) {
                return null;
            }
        }
    }

    // numeric (ms/sec)
    if (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value))) {
        $n = (int) $value;
        if ($n <= 0) return null;

        try {
            // heuristique ms vs sec
            $dt = ($n >= 1000000000000)
                ? \Carbon\Carbon::createFromTimestampMs($n)
                : \Carbon\Carbon::createFromTimestamp($n);

            $dt = $dt->setTimezone(config('app.timezone'));
            $year = (int) $dt->format('Y');
            if ($year < 1000 || $year > 9999) return null;

            return $dt->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    return null;
}

/**
 * ✅ Normalisation record provider -> format DB locations
 * (utilise gpsParseDateTimeForMysql)
 */
public function normalizedlactionfordb(array $record, string $macId): array
{
    $macId = trim($macId);

    // sys_time (priorités)
    $sysTime =
        $this->gpsParseDateTimeForMysql($record['sys_time'] ?? null)
        ?? $this->gpsParseDateTimeForMysql($record['server_time'] ?? null)
        ?? $this->gpsParseDateTimeForMysql($record['heart_time'] ?? null)
        ?? $this->gpsParseDateTimeForMysql($record['datetime'] ?? null);

    // datetime GPS (priorité datetime sinon sys_time)
    $gpsTime =
        $this->gpsParseDateTimeForMysql($record['datetime'] ?? null)
        ?? $sysTime;

    $heartTime = $this->gpsParseDateTimeForMysql($record['heart_time'] ?? null);

    // coords
    $lng = $record['jingdu'] ?? $record['longitude'] ?? $record['lng'] ?? null;
    $lat = $record['weidu']  ?? $record['latitude']  ?? $record['lat'] ?? null;

    $lng = is_numeric($lng) ? (float) $lng : null;
    $lat = is_numeric($lat) ? (float) $lat : null;

    // speed
    $speed = $record['su'] ?? $record['speed'] ?? null;
    $speed = is_numeric($speed) ? (float) $speed : null;
    if ($speed !== null && $speed < 0) $speed = null; // chez toi -9 => invalide

    // direction
    $direction = $record['hangxiang'] ?? $record['direction'] ?? null;
    $direction = is_numeric($direction) ? (int) $direction : null;

    // user name
    $userName = $record['user_name'] ?? $record['userName'] ?? $record['name'] ?? null;
    $userName = is_string($userName) ? trim($userName) : null;
    if ($userName === '') $userName = null;

    // status (bits/hex/etc)
    $status = array_key_exists('status', $record) ? (string) $record['status'] : null;

    return [
        'sys_time'   => $sysTime,
        'user_name'  => $userName,
        'longitude'  => $lng,
        'latitude'   => $lat,
        'datetime'   => $gpsTime,
        'heart_time' => $heartTime,
        'speed'      => $speed,
        'status'     => $status,
        'direction'  => $direction,
        'mac_id_gps' => $macId,
    ];
}

/**
 * ✅ Save DB (V2) : utilise normalizedlactionfordb()
 * - skip si sys_time invalide
 * - anti-dup sur (mac_id_gps + sys_time)
 */
private function saveLatestLocationRecordToDbNormalized(array $record, string $macId, bool $avoidDuplicate = true): array
{
    try {
        $macId = trim($macId);
        if ($macId === '') {
            return ['success' => false, 'reason' => 'mac_id missing'];
        }

        $data = $this->normalizedlactionfordb($record, $macId);

        if (empty($data['sys_time'])) {
            return [
                'success' => false,
                'reason' => 'sys_time invalid (skip)',
                'mac_id_gps' => $macId,
            ];
        }

        if ($avoidDuplicate) {
            $exists = \App\Models\Location::query()
                ->where('mac_id_gps', $macId)
                ->where('sys_time', $data['sys_time'])
                ->exists();

            if ($exists) {
                return [
                    'success' => true,
                    'duplicate' => true,
                    'mac_id_gps' => $macId,
                    'sys_time' => $data['sys_time'],
                ];
            }
        }

        $loc = \App\Models\Location::create($data);

        return [
            'success' => true,
            'saved' => true,
            'location_id' => $loc->id,
            'mac_id_gps' => $macId,
            'sys_time' => $data['sys_time'],
        ];
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::error('[GPS] saveLatestLocationRecordToDbNormalized exception', [
            'account' => $this->account ?? null,
            'macid' => $macId ?? null,
            'error' => $e->getMessage(),
        ]);

        return ['success' => false, 'reason' => 'exception: ' . $e->getMessage()];
    }
}

/**
 * ✅ 1 device : prend SimGps(mac_id) -> account_name -> objectid -> last record -> save locations
 */
public function syncAndSaveLatestLocationFromSimGpsByMacId(string $macId, bool $avoidDuplicate = true): array
{
    $macId = trim($macId);
    if ($macId === '') return ['success' => false, 'message' => 'mac_id vide'];

    $sim = \App\Models\SimGps::query()
        ->select(['mac_id', 'objectid', 'account_name'])
        ->where('mac_id', $macId)
        ->first();

    if (!$sim) {
        return ['success' => false, 'message' => 'mac_id introuvable dans sim_gps', 'mac_id' => $macId];
    }

    $account = $this->gpsNormalizeAccount($sim->account_name);
    $userId  = trim((string) ($sim->objectid ?? ''));

    if ($userId === '') {
        return ['success' => false, 'message' => 'objectid vide dans sim_gps', 'mac_id' => $macId, 'account' => $account];
    }

    $this->setAccount($account);
    $this->loginGps(false);

    $record = $this->getLatestLocationByUserId($userId, null, null);
    if (!$record || !is_array($record)) {
        return ['success' => false, 'message' => 'Aucun record provider', 'mac_id' => $macId, 'account' => $account, 'user_id' => $userId];
    }

    $save = $this->saveLatestLocationRecordToDbNormalized($record, $macId, $avoidDuplicate);

    return [
        'success' => (bool)($save['success'] ?? false),
        'account' => $account,
        'mac_id'  => $macId,
        'user_id' => $userId,
        'save'    => $save,
    ];
}

/**
 * ✅ BATCH : tous les devices de sim_gps -> locations
 * Appel tinker :
 *   $gps->syncAndSaveLatestLocationsFromAllSimGps(true, 200, 50);
 */
public function syncAndSaveLatestLocationsFromAllSimGps(
    bool $forceRefreshLogin = false,
    int $limit = 200,
    int $chunkSize = 50,
    int $sleepMsBetweenDevices = 0,
    bool $avoidDuplicate = true
): array {
    $limit = max(1, (int) $limit);
    $chunkSize = max(1, (int) $chunkSize);
    $sleepMsBetweenDevices = max(0, (int) $sleepMsBetweenDevices);

    $rows = \App\Models\SimGps::query()
        ->select(['mac_id', 'objectid', 'account_name'])
        ->whereNotNull('mac_id')
        ->where('mac_id', '<>', '')
        ->limit($limit)
        ->get();

    $summary = [
        'success' => true,
        'total' => $rows->count(),
        'saved' => 0,
        'duplicate' => 0,
        'skipped' => 0, // sys_time invalid, objectid missing, etc.
        'failed' => 0,
        'by_account' => [
            'tracking' => ['total' => 0, 'saved' => 0, 'duplicate' => 0, 'skipped' => 0, 'failed' => 0],
            'mobility' => ['total' => 0, 'saved' => 0, 'duplicate' => 0, 'skipped' => 0, 'failed' => 0],
        ],
        'failures' => [],
    ];

    if ($rows->isEmpty()) return $summary;

    // grouper par compte (plus efficace, respecte quand même le compte de chaque mac_id)
    $groups = $rows->groupBy(function ($r) {
        $a = strtolower(trim((string) $r->account_name));
        return in_array($a, ['tracking', 'mobility'], true) ? $a : 'tracking';
    });

    foreach ($groups as $account => $items) {
        $account = $this->gpsNormalizeAccount($account);

        $summary['by_account'][$account]['total'] += $items->count();

        $this->setAccount($account);
        if ($forceRefreshLogin) $this->resetGpsToken();
        $token = $this->loginGps($forceRefreshLogin);

        if (!$token) {
            $summary['failed'] += $items->count();
            $summary['by_account'][$account]['failed'] += $items->count();
            $summary['failures'][] = ['account' => $account, 'reason' => 'login failed', 'count' => $items->count()];
            continue;
        }

        foreach ($items->chunk($chunkSize) as $chunk) {
            foreach ($chunk as $sim) {
                $macId = trim((string) $sim->mac_id);
                $userId = trim((string) ($sim->objectid ?? ''));

                if ($userId === '') {
                    $summary['skipped']++;
                    $summary['by_account'][$account]['skipped']++;
                    $summary['failures'][] = ['mac_id' => $macId, 'account' => $account, 'reason' => 'objectid missing'];
                    continue;
                }

                try {
                    $record = $this->getLatestLocationByUserId($userId, null, null);

                    if (!$record || !is_array($record)) {
                        $summary['failed']++;
                        $summary['by_account'][$account]['failed']++;
                        $summary['failures'][] = [
                            'mac_id' => $macId,
                            'account' => $account,
                            'user_id' => $userId,
                            'reason' => 'no provider record',
                        ];
                        continue;
                    }

                    $save = $this->saveLatestLocationRecordToDbNormalized($record, $macId, $avoidDuplicate);

                    if (($save['success'] ?? false) && ($save['saved'] ?? false)) {
                        $summary['saved']++;
                        $summary['by_account'][$account]['saved']++;
                    } elseif (($save['success'] ?? false) && ($save['duplicate'] ?? false)) {
                        $summary['duplicate']++;
                        $summary['by_account'][$account]['duplicate']++;
                    } elseif (($save['success'] ?? false) === false && (($save['reason'] ?? '') === 'sys_time invalid (skip)')) {
                        $summary['skipped']++;
                        $summary['by_account'][$account]['skipped']++;
                    } else {
                        $summary['failed']++;
                        $summary['by_account'][$account]['failed']++;
                        $summary['failures'][] = [
                            'mac_id' => $macId,
                            'account' => $account,
                            'user_id' => $userId,
                            'reason' => $save['reason'] ?? 'save failed',
                        ];
                    }

                } catch (\Throwable $e) {
                    $summary['failed']++;
                    $summary['by_account'][$account]['failed']++;
                    $summary['failures'][] = [
                        'mac_id' => $macId,
                        'account' => $account,
                        'user_id' => $userId,
                        'reason' => $e->getMessage(),
                    ];
                }

                if ($sleepMsBetweenDevices > 0) {
                    usleep($sleepMsBetweenDevices * 1000);
                }
            }
        }
    }

    return $summary;
}

/* =========================
 |  TESTS TINKER (COPIER)
 |==========================
 | $gps = app(\App\Services\GpsControlService::class);
 |
 | // 1) Test 1 device
 | $mac = \App\Models\SimGps::whereNotNull('mac_id')->value('mac_id');
 | dd($gps->syncAndSaveLatestLocationFromSimGpsByMacId($mac, true));
 |
 | // 2) Vérifier en DB
 | \App\Models\Location::where('mac_id_gps', $mac)->orderByDesc('sys_time')->first();
 |
 | // 3) Test BATCH petit volume
 | dd($gps->syncAndSaveLatestLocationsFromAllSimGps(true, 20, 10, 50));
 */

















}
