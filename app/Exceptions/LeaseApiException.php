<?php

namespace App\Exceptions;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Exception métier pour les erreurs API recouvrement.
 *
 * Objectif :
 * - ne jamais exposer au client le HTML brut Django/DRF ;
 * - garder un message clair dans les logs développeur ;
 * - donner au contrôleur un message client personnalisé et exploitable.
 */
class LeaseApiException extends RuntimeException
{
    public function __construct(
        public readonly string $method,
        public readonly string $endpoint,
        public readonly int $status,
        public readonly string $requestId,
        public readonly string $apiMessage,
        public readonly string $bodyPreview,
        ?Throwable $previous = null
    ) {
        parent::__construct(
            sprintf(
                'API recouvrement %s %s a échoué [%d] (%s) : %s',
                $method,
                $endpoint,
                $status,
                $requestId,
                $apiMessage !== '' ? $apiMessage : 'Erreur API sans message exploitable.'
            ),
            $status,
            $previous
        );
    }

    public static function fromResponse(string $method, string $endpoint, Response $response): self
    {
        $body = (string) $response->body();
        $message = self::extractApiMessage($response, $body);

        return new self(
            method: strtoupper($method),
            endpoint: $endpoint,
            status: $response->status(),
            requestId: (string) Str::uuid(),
            apiMessage: $message,
            bodyPreview: mb_substr($body, 0, 2000)
        );
    }

    public function userMessage(): string
    {
        if ($this->status === 401) {
            return 'Votre session de recouvrement a expiré. Reconnectez-vous puis réessayez.';
        }

        if ($this->status === 403) {
            if (str_contains(mb_strtolower($this->apiMessage, 'UTF-8'), 'hard-delete')) {
                return 'Ce contrat ne peut pas être supprimé définitivement car il est déjà lié à des échéances ou à des sous-contrats. Utilisez plutôt l’action “Clôturer”.';
            }

            return 'Action refusée par le recouvrement. Vérifiez vos droits ou l’état du contrat.';
        }

        if ($this->status === 404) {
            return 'La ressource demandée est introuvable dans recouvrement. Rechargez la page puis réessayez.';
        }

        if (in_array($this->status, [400, 422], true)) {
            $message = trim($this->apiMessage);

            return $message !== ''
                ? "Recouvrement a refusé les données envoyées : {$message}"
                : 'Recouvrement a refusé les données envoyées. Vérifiez les champs du formulaire.';
        }

        if ($this->status >= 500) {
            $lowerMessage = mb_strtolower($this->apiMessage, 'UTF-8');

            if (str_contains($lowerMessage, 'uniq_contrat_parent_actif_par_chauffeur')
                || str_contains($lowerMessage, 'duplicate key value')) {
                return 'Ce chauffeur possède déjà un contrat principal actif. Si vous modifiez un sous-contrat, gardez son contrat parent rattaché ; sinon créez ou modifiez le sous-contrat depuis la ligne du contrat principal.';
            }

            return 'Le service recouvrement est momentanément indisponible. Réessayez plus tard.';
        }

        return 'Une erreur est survenue pendant la communication avec recouvrement.';
    }

    private static function extractApiMessage(Response $response, string $body): string
    {
        $json = $response->json();

        if (is_array($json)) {
            foreach (['detail', 'message', 'error', 'non_field_errors'] as $key) {
                if (! empty($json[$key])) {
                    return self::stringify($json[$key]);
                }
            }

            $parts = [];

            foreach ($json as $key => $value) {
                if (is_string($key) && $key !== '') {
                    $parts[] = $key . ' : ' . self::stringify($value);
                } else {
                    $parts[] = self::stringify($value);
                }
            }

            return mb_substr(implode(' | ', array_filter($parts)), 0, 1200);
        }

        $plain = trim(strip_tags($body));
        $plain = preg_replace('/\s+/', ' ', $plain) ?: '';

        return mb_substr($plain, 0, 1200);
    }

    private static function stringify(mixed $value): string
    {
        if (is_array($value)) {
            return implode(', ', array_map(fn ($item) => self::stringify($item), $value));
        }

        if (is_bool($value)) {
            return $value ? 'oui' : 'non';
        }

        if ($value === null) {
            return '';
        }

        return (string) $value;
    }
}
