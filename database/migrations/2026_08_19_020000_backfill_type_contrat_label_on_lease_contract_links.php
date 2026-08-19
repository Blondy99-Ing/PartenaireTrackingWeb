<?php

use App\Models\LeaseContractLink;
use Illuminate\Database\Migrations\Migration;

/**
 * LeaseContractLinkService::extractTypeContractLabel() ne vérifiait jamais
 * la clé plate "type_contrat_libelle" (orthographe française) renvoyée par
 * l'API Recouvrement — seule "type_contrat_label" (anglicisme, absente de
 * la réponse) l'était. Résultat : quasi 100% des lignes lease_contract_links
 * ont un type_contrat_label bloqué sur le replai technique "Type #N",
 * jamais le vrai libellé (Moto, Royal care, Phone...), alors même que
 * last_snapshot contient ce libellé depuis toujours. Corrigé pour toute
 * nouvelle synchronisation ; cette migration régénère le libellé des liens
 * existants à partir de leur dernier snapshot connu, sans nouvel appel API.
 */
return new class extends Migration
{
    public function up(): void
    {
        LeaseContractLink::query()
            ->where(function ($q) {
                $q->where('type_contrat_label', 'like', 'Type #%')
                    ->orWhereNull('type_contrat_label')
                    ->orWhere('type_contrat_label', '');
            })
            ->chunkById(200, function ($links) {
                foreach ($links as $link) {
                    $label = $this->resolveLabel($link);

                    if ($label !== null && $label !== $link->type_contrat_label) {
                        $link->update(['type_contrat_label' => $label]);
                    }
                }
            });
    }

    public function down(): void
    {
        // Correction de libellé : pas de retour en arrière pertinent (l'ancien
        // libellé n'était qu'un identifiant technique qu'on ne veut plus afficher).
    }

    private function resolveLabel(LeaseContractLink $link): ?string
    {
        $candidates = [
            data_get($link->last_snapshot, 'type_contrat_libelle'),
            data_get($link->last_snapshot, 'type_contrat_label'),
            data_get($link->last_snapshot, 'type_contrat.libelle'),
            data_get($link->last_snapshot, 'type_contrat.label'),
        ];

        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate !== '' && ! preg_match('/^(type|contrat|sous-contrat)\s*#?\d+$/i', $candidate)) {
                return $candidate;
            }
        }

        return null;
    }
};
