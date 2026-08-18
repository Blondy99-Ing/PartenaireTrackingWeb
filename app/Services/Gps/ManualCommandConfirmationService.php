<?php

namespace App\Services\Gps;

use App\Models\Commande;
use App\Services\GpsControlService;
use Illuminate\Support\Facades\Log;

/**
 * Boucle de confirmation pour les commandes moteur MANUELLES (table
 * "commands"), sur le même principe que LeaseCutoffQueueProcessorService
 * pour les coupures automatiques : l'accusé de réception du provider
 * (SEND_OK / QUEUED_OFFLINE) ne prouve pas que le boîtier a réellement
 * exécuté la commande — seul l'état moteur réel le prouve.
 *
 * Fenêtre de vérification : ~20 minutes (DEFAULT_MAX_CHECKS * ~1
 * passage/minute), identique au système automatique. Au-delà, la commande
 * est marquée "UNCONFIRMED" — jamais "FAILED" : elle a bien été envoyée,
 * seule sa confirmation manque.
 */
class ManualCommandConfirmationService
{
    private const DEFAULT_MAX_CHECKS = 20;
    private const DEFAULT_DELAY_SECONDS = 60;

    /** Au-delà, on abandonne même les commandes qui n'ont jamais reçu de premier check (scheduler resté arrêté, etc.). */
    private const MAX_AGE_MINUTES = 30;

    public function __construct(private readonly GpsControlService $gps)
    {
    }

    /**
     * Premier contrôle, fait de façon synchrone juste après l'envoi de la
     * commande (ControlGpsController réalise déjà un appel live à ce
     * moment-là) — évite d'attendre le prochain passage planifié pour les
     * commandes qui confirment tout de suite.
     */
    public function recordInitialCheck(Commande $commande, array $liveEngineStatus): void
    {
        $engineState = (string) ($liveEngineStatus['decoded']['engineState'] ?? 'UNKNOWN');

        if ($this->matchesExpectedOutcome($commande->type_commande, $engineState)) {
            $commande->forceFill([
                'confirmation_status' => 'CONFIRMED',
                'confirmed_at' => now(),
                'next_check_at' => null,
            ])->save();

            return;
        }

        $delay = (int) env('MANUAL_COMMAND_CONFIRM_DELAY_SECONDS', self::DEFAULT_DELAY_SECONDS);

        $commande->forceFill([
            'retry_count' => 0,
            'next_check_at' => now()->addSeconds($delay),
        ])->save();
    }

    /**
     * Boucle planifiée (cron) : reprend les commandes encore non résolues.
     */
    public function process(): array
    {
        $maxChecks = (int) env('MANUAL_COMMAND_CONFIRM_MAX_CHECKS', self::DEFAULT_MAX_CHECKS);
        $delay = (int) env('MANUAL_COMMAND_CONFIRM_DELAY_SECONDS', self::DEFAULT_DELAY_SECONDS);

        $items = Commande::query()
            ->whereIn('status', ['SEND_OK', 'QUEUED_OFFLINE'])
            ->whereNull('confirmation_status')
            ->where('created_at', '>=', now()->subMinutes(self::MAX_AGE_MINUTES))
            ->where(function ($q) {
                $q->whereNull('next_check_at')->orWhere('next_check_at', '<=', now());
            })
            ->with('vehicule')
            ->get();

        $confirmed = 0;
        $waiting = 0;
        $unconfirmed = 0;

        foreach ($items as $commande) {
            $vehicle = $commande->vehicule;
            $macId = trim((string) ($vehicle->mac_id_gps ?? ''));

            if (! $vehicle || $macId === '') {
                $commande->forceFill(['confirmation_status' => 'UNCONFIRMED', 'next_check_at' => null])->save();
                $unconfirmed++;
                continue;
            }

            try {
                $liveStatus = $this->gps->getEngineStatusFromLastLocation($macId);
            } catch (\Throwable $e) {
                Log::warning('[MANUAL_COMMAND_CONFIRM] Lecture état véhicule impossible', [
                    'commande_id' => $commande->id,
                    'mac_id_gps' => $macId,
                    'error' => $e->getMessage(),
                ]);
                $this->markStillWaiting($commande, $delay);
                $waiting++;
                continue;
            }

            if (! ($liveStatus['success'] ?? false)) {
                $this->markStillWaiting($commande, $delay);
                $waiting++;
                continue;
            }

            $engineState = (string) ($liveStatus['decoded']['engineState'] ?? 'UNKNOWN');

            if ($this->matchesExpectedOutcome($commande->type_commande, $engineState)) {
                $commande->forceFill([
                    'confirmation_status' => 'CONFIRMED',
                    'confirmed_at' => now(),
                    'next_check_at' => null,
                ])->save();
                $confirmed++;
                continue;
            }

            if ($commande->retry_count >= $maxChecks) {
                $commande->forceFill(['confirmation_status' => 'UNCONFIRMED', 'next_check_at' => null])->save();
                $unconfirmed++;
                continue;
            }

            $this->markStillWaiting($commande, $delay);
            $waiting++;
        }

        return compact('confirmed', 'waiting', 'unconfirmed');
    }

    private function markStillWaiting(Commande $commande, int $delay): void
    {
        $commande->forceFill([
            'retry_count' => $commande->retry_count + 1,
            'next_check_at' => now()->addSeconds($delay),
        ])->save();
    }

    private function matchesExpectedOutcome(?string $typeCommande, string $engineState): bool
    {
        return $typeCommande === 'ALLUMAGE' ? $engineState !== 'CUT' : $engineState === 'CUT';
    }
}
