<?php

namespace App\Services\Gps;

use App\Models\SimGps;
use App\Services\GpsControlService;
use Illuminate\Support\Facades\Cache;

/**
 * Dispatcher GPS pour les commandes moteur.
 *
 * Objectif :
 * - envoyer la commande de coupure sur le bon compte (tracking / mobility)
 * - gérer quelques cas provider connus (queue saturée, mauvais compte)
 * - NE PAS conclure trop vite à un échec définitif
 *
 * Philosophie :
 * - SENT => le provider a clairement accepté la commande
 * - PENDING_VERIFICATION => réponse ambiguë / timeout / provider lent
 *   => il faut revérifier l'état réel du véhicule au prochain cron
 * - FAILED => rejet clair / erreur explicite côté provider
 */
class GpsCommandDispatcherService
{
    public function __construct(
        private readonly GpsControlService $gps
    ) {
    }

    /**
     * Envoie une commande de coupure moteur.
     *
     * Retourne toujours une structure normalisée :
     * - status : SENT | PENDING_VERIFICATION | FAILED
     * - success : bool
     * - should_verify : bool
     * - cmd_no : string|null
     * - message : string
     * - provider : array|null
     */
    public function dispatchCutByMacId(string $macId): array
    {
        $macId = trim($macId);

        if ($macId === '') {
            return [
                'status' => 'FAILED',
                'success' => false,
                'should_verify' => false,
                'cmd_no' => null,
                'message' => 'mac_id_gps vide',
                'provider' => null,
            ];
        }

        // 1) Se positionner si possible sur le bon compte connu
        $accDb = $this->getAccountFromDb($macId);
        if ($accDb) {
            $this->gps->setAccount($accDb);
        }

        // 2) Premier envoi
        $providerResp = $this->gps->cutEngine($macId);
        $parsed = $this->classifyProviderResponse($providerResp);

        // 3) Si la file provider est saturée, on clear puis on retry
        if (($parsed['return_msg'] ?? '') === 'CMD_EXCEEDLENGTH') {
            $this->gps->clearCmdList($macId);

            $providerResp = $this->gps->cutEngine($macId);
            $parsed = $this->classifyProviderResponse($providerResp);
        }

        // 4) Si mauvais compte probable, on bascule et on retry une fois
        if (
            ($parsed['status'] ?? 'FAILED') === 'FAILED'
            && $this->isWrongAccountMsg((string) ($parsed['return_msg'] ?? ''))
        ) {
            $current = $this->gps->getAccount();
            $other = ($current === 'tracking') ? 'mobility' : 'tracking';

            $this->upsertAccountForMac($macId, $other);

            $this->gps->setAccount($other);
            $this->gps->resetGpsToken();

            $providerResp = $this->gps->cutEngine($macId);
            $parsed = $this->classifyProviderResponse($providerResp);
        }

        // 5) Nettoyage des caches d'état moteur live
        Cache::forget("gps18gps:engine_status:tracking:{$macId}");
        Cache::forget("gps18gps:engine_status:mobility:{$macId}");

        return [
            'status' => $parsed['status'],
            'success' => $parsed['status'] !== 'FAILED',
            'should_verify' => in_array($parsed['status'], ['SENT', 'PENDING_VERIFICATION'], true),
            'cmd_no' => $parsed['cmd_no'],
            'message' => $parsed['message'],
            'return_msg' => $parsed['return_msg'],
            'provider' => $providerResp,
        ];
    }

    /**
     * Classe la réponse provider en 3 états :
     * - SENT
     * - PENDING_VERIFICATION
     * - FAILED
     */
    private function classifyProviderResponse(array $resp): array
    {
        $success = $resp['success'] ?? null;
        $errorCode = trim((string) ($resp['errorCode'] ?? ''));
        $returnMsg = trim((string) ($resp['returnMsg'] ?? ($resp['errorDescribe'] ?? '')));
        $cmdNo = trim((string) ($resp['cmdNo'] ?? ''));

        $providerAccepted = false;

        if ($success === true || $success === 1 || $success === '1') {
            $providerAccepted = true;
        } elseif (is_string($success) && strtolower(trim($success)) === 'true') {
            $providerAccepted = true;
        }

        if ($providerAccepted && in_array($errorCode, ['', '0', '200'], true)) {
            return [
                'status' => 'SENT',
                'cmd_no' => $cmdNo !== '' ? $cmdNo : null,
                'return_msg' => $returnMsg,
                'message' => $cmdNo !== '' ? 'Commande acceptée par le provider' : 'Commande acceptée',
            ];
        }

        /**
         * Cas ambiguës : le provider ne confirme pas proprement,
         * mais on ne veut PAS conclure tout de suite à un échec.
         *
         * Exemples :
         * - timeout HTTP
         * - erreur provider temporaire
         * - pas de cmdNo mais aucune preuve forte de rejet définitif
         */
        if ($this->isAmbiguousProviderSituation($errorCode, $returnMsg)) {
            return [
                'status' => 'PENDING_VERIFICATION',
                'cmd_no' => $cmdNo !== '' ? $cmdNo : null,
                'return_msg' => $returnMsg,
                'message' => $returnMsg !== '' ? $returnMsg : 'Réponse ambiguë provider, vérification différée requise',
            ];
        }

        return [
            'status' => 'FAILED',
            'cmd_no' => null,
            'return_msg' => $returnMsg,
            'message' => $returnMsg !== '' ? $returnMsg : 'Commande refusée par le provider',
        ];
    }

    /**
     * Détecte un cas ambigu où il faut re-vérifier l'état réel du véhicule
     * au lieu de marquer FAILED immédiatement.
     */
    private function isAmbiguousProviderSituation(string $errorCode, string $returnMsg): bool
    {
        $msg = strtolower(trim($returnMsg));

        if (in_array($errorCode, ['500', '502', '503', '504'], true)) {
            return true;
        }

        if ($msg === '') {
            return true;
        }

        return str_contains($msg, 'timeout')
            || str_contains($msg, 'timed out')
            || str_contains($msg, 'exception http')
            || str_contains($msg, 'tempor')
            || str_contains($msg, 'network')
            || str_contains($msg, 'retry')
            || str_contains($msg, 'later')
            || str_contains($msg, 'no response');
    }

    /**
     * Détecte un message de mauvais compte.
     */
    private function isWrongAccountMsg(string $returnMsg): bool
    {
        $msg = trim($returnMsg);

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
     * Lit le compte GPS connu en base.
     */
    private function getAccountFromDb(string $macId): ?string
    {
        $acc = SimGps::query()->where('mac_id', $macId)->value('account_name');
        $acc = strtolower(trim((string) $acc));

        return in_array($acc, ['tracking', 'mobility'], true) ? $acc : null;
    }

    /**
     * Met à jour le compte GPS connu en base si besoin.
     */
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

        Cache::forget("gps18gps:macid_account:" . $macId);
    }



    // rallumer la moto

    public function dispatchRestoreByMacId(string $macId): array
{
    $macId = trim($macId);

    if ($macId === '') {
        return [
            'status' => 'FAILED',
            'success' => false,
            'should_verify' => false,
            'cmd_no' => null,
            'message' => 'mac_id_gps vide',
            'provider' => null,
        ];
    }

    $accDb = $this->getAccountFromDb($macId);

    if ($accDb) {
        $this->gps->setAccount($accDb);
    }

    $providerResp = $this->gps->restoreEngine($macId);
    $parsed = $this->classifyProviderResponse($providerResp);

    if (($parsed['return_msg'] ?? '') === 'CMD_EXCEEDLENGTH') {
        $this->gps->clearCmdList($macId);

        $providerResp = $this->gps->restoreEngine($macId);
        $parsed = $this->classifyProviderResponse($providerResp);
    }

    if (
        ($parsed['status'] ?? 'FAILED') === 'FAILED'
        && $this->isWrongAccountMsg((string) ($parsed['return_msg'] ?? ''))
    ) {
        $current = $this->gps->getAccount();
        $other = ($current === 'tracking') ? 'mobility' : 'tracking';

        $this->upsertAccountForMac($macId, $other);

        $this->gps->setAccount($other);
        $this->gps->resetGpsToken();

        $providerResp = $this->gps->restoreEngine($macId);
        $parsed = $this->classifyProviderResponse($providerResp);
    }

    \Illuminate\Support\Facades\Cache::forget("gps18gps:engine_status:tracking:{$macId}");
    \Illuminate\Support\Facades\Cache::forget("gps18gps:engine_status:mobility:{$macId}");

    return [
        'status' => $parsed['status'],
        'success' => $parsed['status'] !== 'FAILED',
        'should_verify' => in_array($parsed['status'], ['SENT', 'PENDING_VERIFICATION'], true),
        'cmd_no' => $parsed['cmd_no'],
        'message' => $parsed['message'],
        'return_msg' => $parsed['return_msg'],
        'provider' => $providerResp,
    ];
}


}