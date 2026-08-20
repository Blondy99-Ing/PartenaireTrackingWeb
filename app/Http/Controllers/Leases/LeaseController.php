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
        $leasePagination = [];
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

            $leaseResult     = $leaseApiService->fetchLeases(null, $contracts, $leaseFilters);
            $leaseData       = $leaseResult['data'];
            $leasePagination = [
                'count'        => $leaseResult['count'],
                'current_page' => $leaseResult['current_page'],
                'page_size'    => $leaseResult['page_size'],
                'total_pages'  => $leaseResult['total_pages'],
                'has_next'     => $leaseResult['has_next'],
                'has_previous' => $leaseResult['has_previous'],
            ];
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
            'leasePagination' => $leasePagination,
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
     * Mêmes données que index(), en JSON, pour actualiser le tableau après
     * une action (paiement, pardon, coupure) sans recharger toute la page —
     * ce qui perdait les filtres/tri/pagination du partenaire à chaque fois.
     */
    public function refreshData(Request $request, PartnerLeaseApiService $leaseApiService): JsonResponse
    {
        try {
            $contracts = $leaseApiService->fetchContracts();

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

            $leaseResult = $leaseApiService->fetchLeases(null, $contracts, $leaseFilters);
            $leaseData = $leaseResult['data'];

            return response()->json([
                'ok' => true,
                'lease_data' => $leaseData,
                'cutoff_hub' => $leaseApiService->buildPaymentCutoffHub($leaseData),
                'lease_pagination' => [
                    'count' => $leaseResult['count'],
                    'current_page' => $leaseResult['current_page'],
                    'page_size' => $leaseResult['page_size'],
                    'total_pages' => $leaseResult['total_pages'],
                    'has_next' => $leaseResult['has_next'],
                    'has_previous' => $leaseResult['has_previous'],
                ],
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'ok' => false,
                'message' => $e instanceof LeaseApiException
                    ? $e->userMessage()
                    : 'Impossible de rafraîchir les données pour le moment.',
            ], 500);
        }
    }

    /**
     * Activation/désactivation en masse des règles spécifiques existantes par contrat/sous-contrat.
     *
     * Important : cette action ne crée aucune règle et ne doit jamais créer de
     * règle pour un sous-contrat non associé. Elle agit uniquement sur les
     * lignes déjà présentes dans lease_cutoff_contract_rules.
     */
    public function bulkToggleContractCutoffRules(Request $request, PartnerLeaseApiService $leaseApiService): JsonResponse
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
            Log::info('[LEASE_CONTRACT_RULES_BULK_TOGGLE_START]', [
                'user_id' => optional($request->user())->id,
                'enabled' => (bool) $data['enabled'],
                'cutoff_time' => $data['cutoff_time'] ?? null,
            ]);

            $result = $leaseApiService->bulkToggleExistingContractCutoffRules(
                enabled: (bool) $data['enabled'],
                cutoffTime: $data['cutoff_time'] ?? null,
            );

            Log::info('[LEASE_CONTRACT_RULES_BULK_TOGGLE_DONE]', [
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

            Log::error('[LEASE_CONTRACT_RULES_BULK_TOGGLE_FAILED]', [
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
            'password' => ['required', 'string', 'current_password:web'],
        ], [
            'lease_id.required' => 'La ligne de lease à payer est introuvable.',
            'montant.required' => 'Le montant du paiement est obligatoire.',
            'montant.min' => 'Le montant du paiement doit être supérieur à zéro.',
            'password.required' => 'Veuillez saisir votre mot de passe pour confirmer.',
            'password.current_password' => 'Mot de passe incorrect.',
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
            'password' => ['required', 'string', 'current_password:web'],
        ], [
            'password.required' => 'Veuillez saisir votre mot de passe pour confirmer.',
            'password.current_password' => 'Mot de passe incorrect.',
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
        'cascade' => ['nullable', 'boolean'],
        'password' => ['required', 'string', 'current_password:web'],
    ], [
        'password.required' => 'Veuillez saisir votre mot de passe pour confirmer.',
        'password.current_password' => 'Mot de passe incorrect.',
    ]);

    try {
        $result = $forgivenessService->forgive(
            $request->user(),
            (int) $leaseId,
            trim((string) ($data['reason'] ?? '')) ?: null,
            filter_var($data['cascade'] ?? false, FILTER_VALIDATE_BOOLEAN)
        );

        return response()->json([
            'ok' => true,
            'message' => $result['message'] ?? 'Pardon enregistré.',
            'data' => $result,
        ]);
    } catch (\Throwable $e) {
        report($e);

        /**
         * Bug corrigé : une erreur inattendue (ex. colonne trop petite en
         * base) affichait sa trace SQL brute au partenaire, parce que
         * $e->getMessage() était renvoyé tel quel dans tous les cas. Seules
         * les RuntimeException, levées volontairement par
         * LeaseForgivenessService avec un message métier déjà propre
         * ("Lease introuvable côté recouvrement", etc.), sont sûres à
         * afficher. Toute autre exception reste générique côté partenaire ;
         * le détail technique part uniquement dans les logs via report($e).
         */
        $message = $e instanceof \RuntimeException
            ? ($e->getMessage() ?: "Impossible d’enregistrer le pardon.")
            : "Impossible d’enregistrer le pardon : une erreur technique est survenue. L’équipe technique a été notifiée.";

        return response()->json([
            'ok' => false,
            'message' => $message,
        ], 500);
    }
}

/**
 * Pardon en masse pour une sélection libre de leases (cases à cocher sur
 * le tableau) — chaque lease est traité indépendamment via le même
 * LeaseForgivenessService::forgive() que le pardon unitaire, pour ne
 * jamais dupliquer la logique métier (frères, cascade, etc.). Un échec
 * sur un lease n'interrompt pas les autres : on remonte un résumé par
 * lease pour que le partenaire voie précisément ce qui a marché.
 */
public function forgiveBulk(
    Request $request,
    LeaseForgivenessService $forgivenessService
): JsonResponse {
    $data = $request->validate([
        'lease_ids' => ['required', 'array', 'min:1', 'max:200'],
        'lease_ids.*' => ['integer', 'min:1'],
        'reason' => ['nullable', 'string', 'max:255'],
        'cascade' => ['nullable', 'boolean'],
        'password' => ['required', 'string', 'current_password:web'],
    ], [
        'password.required' => 'Veuillez saisir votre mot de passe pour confirmer.',
        'password.current_password' => 'Mot de passe incorrect.',
    ]);

    $leaseIds = collect($data['lease_ids'])->map(fn ($id) => (int) $id)->unique()->values();
    $reason = trim((string) ($data['reason'] ?? '')) ?: null;
    $cascade = filter_var($data['cascade'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $actor = $request->user();

    $results = [];
    $succeeded = 0;
    $failed = 0;

    foreach ($leaseIds as $leaseId) {
        try {
            $result = $forgivenessService->forgive($actor, $leaseId, $reason, $cascade);

            $results[] = [
                'lease_id' => $leaseId,
                'ok' => true,
                'message' => $result['message'] ?? 'Pardon enregistré.',
                'status' => $result['status'] ?? null,
            ];
            $succeeded++;
        } catch (\Throwable $e) {
            report($e);

            $message = $e instanceof \RuntimeException
                ? ($e->getMessage() ?: "Impossible d’enregistrer le pardon.")
                : "Erreur technique : l’équipe a été notifiée.";

            $results[] = [
                'lease_id' => $leaseId,
                'ok' => false,
                'message' => $message,
            ];
            $failed++;
        }
    }

    return response()->json([
        'ok' => $failed === 0,
        'message' => $failed === 0
            ? sprintf('%d lease(s) pardonné(s).', $succeeded)
            : sprintf('%d lease(s) pardonné(s), %d échec(s).', $succeeded, $failed),
        'succeeded' => $succeeded,
        'failed' => $failed,
        'results' => $results,
    ]);
}

/**
 * Sous-contrats réels du même véhicule qui bloqueraient encore un
 * rallumage, pour afficher "Pardonner tout" dès l'ouverture de la
 * modale (sans attendre un premier refus). Purement local : aucun
 * appel Recouvrement.
 */
public function blockingSiblingContracts(
    int $contractLinkId,
    Request $request,
    LeaseForgivenessService $forgivenessService
): JsonResponse {
    $user = $request->user();
    $partnerId = (int) ($user->partner_id ?: $user->id);

    /**
     * Échéance du lease qu'on s'apprête à pardonner, PAS la date du jour :
     * sans elle, le contrôle des frères bloquants se faisait par défaut sur
     * "aujourd'hui", ratant les frères d'un lease en retard pardonné
     * plusieurs jours après son échéance (bug corrigé le 19/08/2026).
     */
    $dueDate = trim((string) $request->query('date_echeance', ''));
    $dueDate = $dueDate !== '' ? $dueDate : null;

    $siblings = $forgivenessService->previewBlockingSiblings($partnerId, $contractLinkId, $dueDate);

    return response()->json([
        'ok' => true,
        'siblings' => $siblings,
    ]);
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