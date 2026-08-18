<?php

use App\Models\LeaseContractLink;
use App\Models\LeaseCutoffHistory;
use App\Services\Leases\LeaseCutoffPlannerService;
use Illuminate\Database\Migrations\Migration;

/**
 * LeaseCutoffPlannerService::buildTriggerContext() écrivait un texte de
 * motif exposant des identifiants internes bruts ("lease #10288 du
 * sous-contrat #182 rattaché au contrat principal #181", "Type #23") et une
 * heure de coupure planifiée NON convertie en heure locale (UTC brute,
 * "11:00" au lieu de "12:00" heure Douala). Corrigé pour toutes les
 * NOUVELLES planifications ; cette migration régénère le motif des lignes
 * encore en statut PENDING (le seul statut où ce texte d'origine subsiste —
 * tout autre statut l'a déjà remplacé par un message propre écrit par
 * LeaseCutoffQueueProcessorService).
 */
return new class extends Migration
{
    public function up(): void
    {
        LeaseCutoffHistory::query()
            ->where('status', 'PENDING')
            ->with(['vehicle', 'contractLink'])
            ->chunkById(200, function ($rows) {
                foreach ($rows as $history) {
                    $history->update(['reason' => $this->buildCleanReason($history)]);
                }
            });
    }

    public function down(): void
    {
        // Correction de texte : pas de retour en arrière pertinent (le texte
        // d'origine contenait des identifiants qu'on ne veut justement plus
        // afficher).
    }

    private function buildCleanReason(LeaseCutoffHistory $history): string
    {
        $isSub = $history->contract_kind === 'SUB';
        $triggerName = $isSub ? 'sous-contrat' : 'contrat principal';
        $parentText = $isSub && $history->parent_contract_id ? ' (rattaché à son contrat principal)' : '';

        $typeLabel = $history->contractLink
            ? $this->cleanContractTypeLabel($history->contractLink)
            : ($isSub ? 'Sous-contrat' : 'Contrat principal');

        $vehicleLabel = $history->vehicle->immatriculation ?? 'ce véhicule';

        $snapshot = is_array($history->payment_status_snapshot) ? $history->payment_status_snapshot : [];
        $reste = $snapshot['reste_a_payer'] ?? null;
        $amountText = $reste !== null && $reste !== '' ? ' avec un reste à payer de ' . $reste . ' FCFA' : '';

        $dueDate = optional($history->lease_date_echeance)->toDateString() ?? '—';

        /**
         * On relit la valeur BRUTE (non castée par Eloquent) et on l'ancre
         * explicitement sur UTC : le cast datetime standard étiquette la
         * chaîne avec la timezone ambiante de l'environnement PHP qui
         * exécute cette migration (APP_TIMEZONE), qui peut différer entre
         * environnements partageant la même base (.env de test réglé sur
         * Africa/Douala, contrairement à la prod en UTC) — ce qui rendrait
         * une simple ->setTimezone() sans effet si les deux valent la même
         * chose, ou faussée si elles diffèrent.
         */
        $scheduledForLocal = LeaseCutoffPlannerService::toLocalDisplayFromRaw(
            $history->getRawOriginal('scheduled_for')
        );

        return sprintf(
            'Le %s "%s" a causé la planification de la coupure de %s : ce contrat%s, échéance du %s, n’est pas payé%s. La règle de coupure associée est active. Coupure planifiée pour le %s.',
            $triggerName,
            $typeLabel,
            $vehicleLabel,
            $parentText,
            $dueDate,
            $amountText,
            $scheduledForLocal
        );
    }

    private function cleanContractTypeLabel(LeaseContractLink $contractLink): string
    {
        $candidates = [
            data_get($contractLink->last_snapshot, 'type_contrat_libelle'),
            data_get($contractLink->last_snapshot, 'type_contrat_label'),
            data_get($contractLink->last_snapshot, 'type_contrat.libelle'),
            data_get($contractLink->last_snapshot, 'type_contrat.label'),
            $contractLink->type_contrat_label,
        ];

        foreach ($candidates as $candidate) {
            $label = trim((string) $candidate);

            if ($label !== '' && ! preg_match('/^(type|contrat|sous-contrat)\s*#?\d+$/i', $label)) {
                return $label;
            }
        }

        return $contractLink->contract_kind === 'SUB' ? 'Sous-contrat' : 'Contrat principal';
    }
};
