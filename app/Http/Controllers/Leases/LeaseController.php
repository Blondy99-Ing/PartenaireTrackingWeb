<?php

namespace App\Http\Controllers\Leases;

use App\Http\Controllers\Controller;
use App\Services\Leases\LeaseForgivenessService;
use App\Services\Leases\PartnerLeaseApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class LeaseController extends Controller
{
    /**
     * Page paiements lease.
     *
     * Important :
     * - Les contrats enrichissent les leases.
     * - Les leases viennent bien de GET /leases/.
     * - La vue principale utilise $lease_data.
     */
    public function index(Request $request, PartnerLeaseApiService $leaseApiService): View
    {
        try {
            Log::info('[LEASE_PAGE_INDEX_START]', [
                'user_id' => optional($request->user())->id,
                'partner_id' => optional($request->user())->partner_id,
            ]);

            $contracts = $leaseApiService->fetchContracts();

            $leaseData = $leaseApiService->fetchLeases(null, $contracts);

            $cutoffHub = $leaseApiService->buildPaymentCutoffHub($leaseData);

            $pageError = null;

            Log::info('[LEASE_PAGE_INDEX_DONE]', [
                'contracts_count' => count($contracts),
                'leases_count' => count($leaseData),
                'hub' => $cutoffHub,
            ]);
        } catch (Throwable $e) {
            report($e);

            Log::error('[LEASE_PAGE_INDEX_FAILED]', [
                'user_id' => optional($request->user())->id,
                'error' => $e->getMessage(),
            ]);

            $contracts = [];

            $leaseData = [];

            $cutoffHub = [
                'global_enabled' => false,
                'global_time' => null,
                'next_cutoff_time' => null,
                'upcoming_cutoff_times' => [],
                'active_rules_count' => 0,
                'eligible_unpaid_count' => 0,
            ];

            $pageError = app()->environment('local')
                ? $e->getMessage()
                : "Impossible de charger les paiements lease pour le moment.";
        }

        return view('leases.index', [
            /**
             * Nom principal utilisé par ta vue actuelle.
             */
            'lease_data' => $leaseData,

            /**
             * Alias de sécurité.
             * Ça évite de casser une ancienne version de vue si elle utilise
             * $leases, $payments ou $paymentData.
             */
            'leases' => $leaseData,
            'payments' => $leaseData,
            'paymentData' => $leaseData,

            /**
             * Contrats disponibles si la vue en a besoin plus tard.
             */
            'contracts' => $contracts,

            /**
             * Hub de coupure automatique.
             */
            'cutoffHub' => $cutoffHub,

            /**
             * Message d’erreur éventuel.
             */
            'pageError' => $pageError,
             // Utilisateur connecté pour la vue
            'connectedUserId' => optional($request->user())->id,
            'connectedUserName' => $this->connectedUserLabel($request->user()),
        ]);
    }

    /**
     * Mise à jour de la règle globale de coupure automatique.
     */
    public function updateGlobalCutoff(Request $request, PartnerLeaseApiService $leaseApiService): JsonResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'cutoff_time' => ['nullable', 'date_format:H:i'],
        ]);

        if ($data['enabled'] && empty($data['cutoff_time'])) {
            return response()->json([
                'ok' => false,
                'message' => "L'heure de coupure est obligatoire lorsque la coupure auto est activée.",
            ], 422);
        }

        try {
            Log::info('[LEASE_GLOBAL_CUTOFF_UPDATE_START]', [
                'user_id' => optional($request->user())->id,
                'enabled' => (bool) $data['enabled'],
                'cutoff_time' => $data['cutoff_time'] ?? null,
            ]);

            $result = $leaseApiService->applyGlobalCutoffRule(
                enabled: (bool) $data['enabled'],
                cutoffTime: $data['cutoff_time'] ?? null,
            );

            Log::info('[LEASE_GLOBAL_CUTOFF_UPDATE_DONE]', [
                'user_id' => optional($request->user())->id,
                'hub' => $result['hub'] ?? null,
            ]);

            return response()->json([
                'ok' => true,
                'message' => 'Paramètres globaux de coupure mis à jour.',
                'hub' => $result['hub'],
            ]);
        } catch (Throwable $e) {
            report($e);

            Log::error('[LEASE_GLOBAL_CUTOFF_UPDATE_FAILED]', [
                'user_id' => optional($request->user())->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => app()->environment('local')
                    ? $e->getMessage()
                    : "Impossible d'enregistrer la configuration globale.",
            ], 500);
        }
    }

    /**
     * Paiement cash d’un lease.
     *
     * API recouvrement attend :
     * {
     *   "lease": 14,
     *   "montant": 2500
     * }
     */
    public function payCash(Request $request, PartnerLeaseApiService $leaseApiService): JsonResponse
    {
        $data = $request->validate([
            'lease_id' => ['required', 'integer'],
            'montant' => ['required', 'numeric', 'min:1'],
        ]);

        try {
            Log::info('[LEASE_CASH_PAYMENT_START]', [
                'user_id' => optional($request->user())->id,
                'lease_id' => (int) $data['lease_id'],
                'montant' => (float) $data['montant'],
            ]);

            $recordedByName = $this->connectedUserLabel($request->user());

            $result = $leaseApiService->registerCashPayment([
                'lease_id' => (int) $data['lease_id'],
                'montant' => (float) $data['montant'],

                // Utilisé pour logs/debug côté Tracking.
                // L’API recouvrement, elle, identifie normalement l’enregistreur via le token Keycloak.
                'recorded_by' => optional($request->user())->id,
                'recorded_by_name' => $recordedByName,
            ]);

            Log::info('[LEASE_CASH_PAYMENT_DONE]', [
                'user_id' => optional($request->user())->id,
                'lease_id' => (int) $data['lease_id'],
                'response' => $result,
            ]);

            return response()->json([
                'ok' => true,
                'message' => 'Paiement cash enregistré avec succès.',
                'recorded_by_name' => $recordedByName,
                'data' => $result,
            ]);
        } catch (Throwable $e) {
            report($e);

            Log::error('[LEASE_CASH_PAYMENT_FAILED]', [
                'user_id' => optional($request->user())->id,
                'lease_id' => $data['lease_id'] ?? null,
                'montant' => $data['montant'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => $e->getMessage() ?: "Impossible d'enregistrer le paiement cash.",
            ], 500);
        }
    }

    /**
     * Pardon intelligent d’un lease non payé.
     *
     * La logique métier reste dans LeaseForgivenessService :
     * - pardon avant coupure
     * - pardon après coupure
     * - demande de rallumage
     * - échec de rallumage
     */
public function forgive(
    int $leaseId,
    Request $request,
    LeaseForgivenessService $forgivenessService
): JsonResponse {
    $data = $request->validate([
        'reason' => ['nullable', 'string', 'max:255'],
    ]);

    try {
        $result = $forgivenessService->forgive(
            $request->user(),
            (int) $leaseId,
            trim((string) ($data['reason'] ?? '')) ?: null
        );

        return response()->json([
            'ok' => true,
            'message' => $result['message'] ?? 'Pardon enregistré.',
            'data' => $result,
        ]);
    } catch (\Throwable $e) {
        report($e);

        return response()->json([
            'ok' => false,
            'message' => $e->getMessage() ?: "Impossible d’enregistrer le pardon.",
        ], 500);
    }
}



//utilisateur connecté 
private function connectedUserLabel($user): string
{
    if (! $user) {
        return 'Utilisateur connecté';
    }

    $fullName = trim((string) (
        $user->full_name
        ?? $user->nom_complet
        ?? trim(($user->prenom ?? '') . ' ' . ($user->nom ?? ''))
    ));

    if ($fullName !== '') {
        return $fullName;
    }

    return (string) ($user->email ?? $user->phone ?? 'Utilisateur connecté');
}
}