<?php

namespace App\Http\Controllers\Leases;

use App\Exceptions\LeaseApiException;
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
     * Cohérence métier :
     * - /leases/ est la source des échéances à payer ;
     * - /contrats/ permet de savoir si l'échéance concerne le contrat parent
     *   ou un sous-contrat et de connaître son type ;
     * - Tracking applique uniquement les règles spécifiques existantes sur
     *   les contrats/sous-contrats réels ;
     * - la vue affiche les attentes sécurité GPS avant le chrono théorique.
     */
    public function index(Request $request, PartnerLeaseApiService $leaseApiService): View
    {
        $contracts = [];
        $leaseData = [];
        $contractTypes = [];
        $pageWarnings = [];
        $pageError = null;

        try {
            Log::info('[LEASE_PAGE_INDEX_START]', [
                'user_id' => optional($request->user())->id,
                'partner_id' => optional($request->user())->partner_id,
                'query' => $request->query(),
            ]);

            $contractTypes = $leaseApiService->fetchContractTypes();
            $contracts = $leaseApiService->fetchContracts();

            /**
             * La page peut utiliser les filtres documentés par /leases/.
             * Les filtres UI restent aussi disponibles côté navigateur pour une
             * navigation rapide sans rechargement.
             */
            $leaseFilters = $request->only([
                'search',
                'statut',
                'statut__in',
                'date_echeance',
                'date_echeance_start',
                'date_echeance_end',
                'created_at',
                'start_date',
                'end_date',
                'page',
            ]);

            $leaseData = $leaseApiService->fetchLeases(null, $contracts, $leaseFilters);
            $cutoffHub = $leaseApiService->buildPaymentCutoffHub($leaseData);

            Log::info('[LEASE_PAGE_INDEX_DONE]', [
                'contracts_count' => count($contracts),
                'contract_types_count' => count($contractTypes),
                'leases_count' => count($leaseData),
                'hub' => $cutoffHub,
            ]);
        } catch (Throwable $e) {
            report($e);

            Log::error('[LEASE_PAGE_INDEX_FAILED]', [
                'user_id' => optional($request->user())->id,
                'exception_class' => get_class($e),
                'error' => $e->getMessage(),
                'lease_request_id' => $e instanceof LeaseApiException ? $e->requestId : null,
                'status' => $e instanceof LeaseApiException ? $e->status : null,
                'api_message' => $e instanceof LeaseApiException ? $e->apiMessage : null,
            ]);

            $cutoffHub = [
                'global_enabled' => false,
                'global_time' => null,
                'next_cutoff_time' => null,
                'upcoming_cutoff_times' => [],
                'rules_total' => 0,
                'rules_enabled' => 0,
                'rules_disabled' => 0,
                'active_rules_count' => 0,
                'active_type_rules_count' => 0,
                'eligible_unpaid_count' => 0,
                'waiting_queues_count' => 0,
                'waiting_queues' => [],
                'processed_today' => 0,
            ];

            $pageError = $e instanceof LeaseApiException
                ? $e->userMessage()
                : (app()->environment('local')
                    ? $e->getMessage()
                    : "Impossible de charger les paiements lease pour le moment.");
        }

        return view('leases.index', [
            'lease_data' => $leaseData,
            'leases' => $leaseData,
            'payments' => $leaseData,
            'paymentData' => $leaseData,
            'contracts' => $contracts,
            'contractTypes' => $contractTypes,
            'cutoffHub' => $cutoffHub,
            'pageError' => $pageError,
            'pageWarnings' => $pageWarnings,
            'connectedUserId' => optional($request->user())->id,
            'connectedUserName' => $this->connectedUserLabel($request->user()),
        ]);
    }

    /**
     * Activation/désactivation en masse des règles spécifiques existantes.
     *
     * Important : cette action ne crée aucune règle et ne doit jamais créer de
     * règle pour un sous-contrat non associé. Elle agit uniquement sur les
     * lignes déjà présentes dans lease_cutoff_contract_rules.
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
                'message' => "L'heure de coupure est obligatoire pour activer les règles spécifiques.",
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
                'message' => 'Règles spécifiques mises à jour en masse.',
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
                    : "Impossible d'enregistrer le paramétrage en masse des règles spécifiques.",
            ], 500);
        }
    }

    /**
     * Paiement cash d’un lease.
     *
     * Documentation recouvrement :
     * POST /paiements/
     * { "lease_id": 97, "montant": "100" }
     */
    public function payCash(Request $request, PartnerLeaseApiService $leaseApiService): JsonResponse
    {
        $data = $request->validate([
            'lease_id' => ['required', 'integer'],
            'montant' => ['required', 'numeric', 'min:1'],
        ], [
            'lease_id.required' => 'La ligne de lease à payer est introuvable.',
            'montant.required' => 'Le montant du paiement est obligatoire.',
            'montant.min' => 'Le montant du paiement doit être supérieur à zéro.',
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
                'montant' => (string) $data['montant'],
                'recorded_by' => optional($request->user())->id,
                'recorded_by_name' => $recordedByName,
            ]);

            $cancelledQueuesCount = $leaseApiService->cancelActiveCutoffQueuesAfterPayment(
                (int) $data['lease_id'],
                optional($request->user())->id,
                $recordedByName
            );

            Log::info('[LEASE_CASH_PAYMENT_DONE]', [
                'user_id' => optional($request->user())->id,
                'lease_id' => (int) $data['lease_id'],
                'response_shape' => is_array($result) ? array_keys($result) : null,
            ]);

            return response()->json([
                'ok' => true,
                'message' => 'Paiement cash enregistré avec succès.',
                'recorded_by_name' => $recordedByName,
                'cancelled_cutoff_queues_count' => $cancelledQueuesCount ?? 0,
                'data' => $result,
            ]);
        } catch (Throwable $e) {
            report($e);

            Log::error('[LEASE_CASH_PAYMENT_FAILED]', [
                'user_id' => optional($request->user())->id,
                'lease_id' => $data['lease_id'] ?? null,
                'montant' => $data['montant'] ?? null,
                'exception_class' => get_class($e),
                'message' => $e->getMessage(),
                'lease_request_id' => $e instanceof LeaseApiException ? $e->requestId : null,
                'status' => $e instanceof LeaseApiException ? $e->status : null,
                'api_message' => $e instanceof LeaseApiException ? $e->apiMessage : null,
            ]);

            return response()->json([
                'ok' => false,
                'message' => $e instanceof LeaseApiException
                    ? $e->userMessage()
                    : "Impossible d'enregistrer le paiement cash.",
            ], $e instanceof LeaseApiException && $e->status < 500 ? $e->status : 500);
        }
    }

    /**
     * Paiement mobile d’un ou plusieurs leases.
     */
    public function payMobile(Request $request, PartnerLeaseApiService $leaseApiService): JsonResponse
    {
        $data = $request->validate([
            'phone_number' => ['required', 'string', 'max:30'],
            'lignes' => ['required', 'array', 'min:1'],
            'lignes.*.lease_id' => ['required', 'integer'],
            'lignes.*.montant' => ['required', 'numeric', 'min:1'],
        ]);

        try {
            $result = $leaseApiService->initiateMobilePayment(
                lines: $data['lignes'],
                phoneNumber: $data['phone_number']
            );

            return response()->json([
                'ok' => true,
                'message' => 'Paiement mobile initié avec succès.',
                'data' => $result,
            ]);
        } catch (Throwable $e) {
            report($e);

            Log::error('[LEASE_MOBILE_PAYMENT_FAILED]', [
                'user_id' => optional($request->user())->id,
                'exception_class' => get_class($e),
                'message' => $e->getMessage(),
                'lease_request_id' => $e instanceof LeaseApiException ? $e->requestId : null,
                'status' => $e instanceof LeaseApiException ? $e->status : null,
                'api_message' => $e instanceof LeaseApiException ? $e->apiMessage : null,
            ]);

            return response()->json([
                'ok' => false,
                'message' => $e instanceof LeaseApiException
                    ? $e->userMessage()
                    : "Impossible d'initier le paiement mobile.",
            ], $e instanceof LeaseApiException && $e->status < 500 ? $e->status : 500);
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