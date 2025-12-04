<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * ================================================================
 *  SERVICE : GpsControlService
 *  Fournit une intégration complète avec l’API 18GPS
 * ================================================================
 *
 *  Ce service permet :
 *   ✔ Se connecter à l’API 18GPS (token MDS)
 *   ✔ Récupérer l’identifiant interne du device (device_id / user_id)
 *   ✔ Récupérer le statut moteur (ACC ON/OFF)
 *   ✔ Récupérer l’état ONLINE / OFFLINE du GPS
 *   ✔ Envoyer des commandes moteur (SAFEON / SAFEOFF)
 *   ✔ Normalisation des réponses pour d’autres développeurs
 * 
 *  IMPORTANT :
 *  L’API 18GPS nécessite :
 *   - macid (IMEI du GPS physique)
 *   - device_id (ID interne dans la base 18GPS)
 *
 *  device_id est nécessaire pour *toutes* les fonctions avancées.
 */
class GpsControlService
{
    // =============================================================
    // ENDPOINTS
    // =============================================================
    private const GPS_API_URL = "http://apitest.18gps.net/GetDateServices.asmx";
    private const LOGIN_URL   = self::GPS_API_URL . "/loginSystem";
    private const COMMAND_URL = self::GPS_API_URL . "/GetDate";

    // Identifiants API (doivent être dans .env)
    private string $login;
    private string $password;

    // Token MDS GPS (cache mémoire)
    private ?string $gpsToken = null;

    /**
     * Récupère les identifiants depuis .env
     */
    public function __construct()
    {
        $this->login    = env("GPS_LOGIN", "Proxym_tracking");
        $this->password = env("GPS_PASSWORD", "proxym123");
    }

    // =============================================================
    // UTILITAIRES
    // =============================================================

    private function nowIso(): string
    {
        return now()->toISOString();
    }

    private function httpGet(string $url, array $params)
    {
        return Http::timeout(15)->get($url, $params)->json();
    }

    private function toBool($value): ?bool
    {
        if (is_bool($value)) return $value;
        if (is_numeric($value)) return ((int)$value) !== 0;

        if (is_string($value)) {
            return in_array(strtolower($value), ["1", "true", "yes", "on"]);
        }
        return null;
    }

    /**
     * Décode "status" ex. : "10000010"
     *  - bit 1 = ACC (moteur ON/OFF)
     *  - bit 3 = oil (coupe carburant)
     */
    private function parseStatusBits(?string $status): array
    {
        if (!$status || strlen($status) < 3) return [];
        return [
            "accState" => $status[0] === "1", // moteur allumé
            "oilState" => $status[2] === "1", // carburant activé/coupé
        ];
    }

    // =============================================================
    // 1. LOGIN GPS : récupère le token MDS
    // =============================================================

    /**
     * Se connecte au serveur GPS et retourne le token MDS.
     *
     * PARAMÈTRES REQUIS :
     *   - GPS_LOGIN (nom d’utilisateur)
     *   - GPS_PASSWORD
     *
     * RETOUR :
     *   string|null  → token MDS si succès, sinon null
     */
    public function loginGps(): ?string
    {
        if ($this->gpsToken) return $this->gpsToken;

        $params = [
            "LoginName"     => $this->login,
            "LoginPassword" => $this->password,
            "LoginType"     => "ENTERPRISE",
            "language"      => "en",
            "timeZone"      => 8,
            "apply"         => "APP",
            "ISMD5"         => 0,
            "loginUrl"      => "http://appzzl.18gps.net/",
        ];

        try {
            $response = $this->httpGet(self::LOGIN_URL, $params);

            if (($response["success"] ?? null) === "true") {
                $this->gpsToken = $response["mds"];
                return $this->gpsToken;
            }

            return null;

        } catch (\Exception $e) {
            Log::error("Erreur login GPS : ".$e->getMessage());
            return null;
        }
    }

    // =============================================================
    // 2. Récupération du device_id via IMEI (indispensable)
    // =============================================================

    /**
     * Retourne le device_id interne utilisé par 18GPS pour un IMEI donné.
     *
     * Input :
     *   - string $macId  → IMEI du GPS
     *
     * Output :
     *   - string|null → device_id si trouvé
     */
    private function getDeviceIdFromMac(string $macId, string $token): ?string
    {
        $params = [
            "method" => "getDeviceList",
            "mds"    => $token,
        ];

        $data = $this->httpGet(self::COMMAND_URL, $params);

        if (!isset($data["data"][0]["records"])) return null;

        foreach ($data["data"][0]["records"] as $record) {
            $imei = $record[11] ?? null;
            if ($imei === $macId) {
                return $record[12]; // device_id
            }
        }

        return null;
    }

    // =============================================================
    // 3. Récupérer le statut moteur + online/offline
    // =============================================================

    /**
     * Retourne le statut moteur et la disponibilité du GPS.
     *
     * PARAMÈTRE :
     *   - $macId (IMEI du GPS)
     *
     * RETOUR :
     *   [
     *     "engine_on"  => true/false
     *     "online"     => true/false
     *     "status_raw" => "10000010"
     *     "heartbeat"  => timestamp
     *     "server"     => timestamp
     *   ]
     */
    public function getEngineStatus(string $macId): array
    {
        // 0. Login
        $token = $this->loginGps();
        if (!$token)
            return ['success' => false, 'message' => 'Token GPS invalide'];

        // 1. Trouver le device_id
        $deviceId = $this->getDeviceIdFromMac($macId, $token);
        if (!$deviceId)
            return ['success' => false, 'message' => 'Device ID introuvable (IMEI inconnu)'];

        // 2. Appel API officiel
        $params = [
            "method"  => "getUserAndGpsInfoByIDsUtcNew",
            "user_id" => $deviceId,
            "mapType" => "BAIDU",
            "option"  => "cn",
            "mds"     => $token,
        ];

        $response = $this->httpGet(self::COMMAND_URL, $params);

        if (!isset($response["data"][0]["records"][0]))
            return ['success' => false, 'message' => 'Pas de data GPS'];

        $record = $response["data"][0]["records"][0];

        $status   = $record[9]; // Bits ex : 10000010
        $heartbeat = $record[7]; // heart_time (timestamp)
        $server    = $response["data"][0]["key"]["server_time"] ?? (time() * 1000);

        // Moteur = bit 1 du champ "status"
        $engineOn = ($status[0] === "1");

        // GPS online = dernier heartbeat < 25 minutes
        $online = (($server - $heartbeat) < 25 * 60 * 1000);

        return [
            "success"    => true,
            "macid"      => $macId,
            "device_id"  => $deviceId,
            "engine_on"  => $engineOn, // statut du moteur (allumé ou etient)
            "online"     => $online,  // statut du moteur (online ou offline)
            "status_raw" => $status,
            "heartbeat"  => $heartbeat,
            "server"     => $server,
        ];
    }

    // =============================================================
    // 4. Commandes moteur
    // =============================================================

    /**
     * Couper le moteur
     *  - SAFEON = couper moteur (armer le relais)
     */
    public function cutEngine(string $macId): array
    {
        return $this->sendGpsCommand($macId, "SAFEON");
    }

    /**
     * Allumer le moteur
     *  - SAFEOFF = désarmer / permettre le démarrage
     */
    public function startEngine(string $macId): array
    {
        return $this->sendGpsCommand($macId, "SAFEOFF");
    }

    // =============================================================
    // 5. Envoi de commandes GPS
    // =============================================================

    /**
     * Envoie une commande brute à un appareil GPS.
     *
     * PARAMÈTRES :
     *   - $macId  → IMEI
     *   - $command → "SAFEON" / "SAFEOFF" / etc.
     *   - $param → paramètre optionnel
     *   - $pwd → mot de passe du device
     *
     * RETOUR :
     *   - Réponse JSON de 18GPS
     */
    public function sendGpsCommand(string $macId, string $command, string $param = "", string $pwd = "proxym123"): ?array
    {
        $token = $this->loginGps();
        if (!$token) return null;

        $params = [
            "method"   => "SendCommands",
            "macid"    => $macId,
            "cmd"      => $command,
            "param"    => $param,
            "pwd"      => $pwd,
            "sendTime" => $this->nowIso(),
            "mds"      => $token,
        ];

        return $this->httpGet(self::COMMAND_URL, $params);
    }

    // =============================================================
    // 6. Réinitialisation Token
    // =============================================================
    public function resetGpsToken(): void
    {
        $this->gpsToken = null;
    }
}
