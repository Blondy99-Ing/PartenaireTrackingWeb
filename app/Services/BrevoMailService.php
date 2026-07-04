<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BrevoMailService
{
    /**
     * Envoi OTP reset via Template Brevo.
     */
    public function sendResetOtp(string $toEmail, string $fullName, string $code): array
    {
        return $this->sendTemplate(
            toEmail: $toEmail,
            toName: $fullName,
            templateId: (int) config('services.brevo.template_reset_id', 2),
            params: [
                'FULLNAME'   => $fullName,
                'RESET_CODE' => $code,
            ]
        );
    }

    /**
     * Test rapide depuis Tinker :
     * app(\App\Services\BrevoMailService::class)->test('email@domaine.com');
     */
    public function test(string $toEmail): array
    {
        return $this->sendResetOtp($toEmail, 'Test Proxym', '123456');
    }

    /**
     * Méthode interne générique d'envoi via template Brevo.
     */
    private function sendTemplate(string $toEmail, string $toName, int $templateId, array $params): array
    {
        $apiKey = (string) config('services.brevo.key', '');
        $senderEmail = (string) config('services.brevo.sender_email', '');
        $senderName = (string) config('services.brevo.sender_name', 'FLEETRA BY PROXYM GROUP');

        if ($apiKey === '') {
            Log::error('Brevo config missing: API key is empty.');
            return [
                'ok' => false,
                'status' => null,
                'body' => 'BREVO_API_KEY manquante (services.brevo.key vide).',
            ];
        }

        if ($senderEmail === '') {
            Log::error('Brevo config missing: sender email is empty.');
            return [
                'ok' => false,
                'status' => null,
                'body' => 'BREVO_SENDER_EMAIL manquant (services.brevo.sender_email vide).',
            ];
        }

        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            Log::warning('Brevo send aborted: invalid recipient email.', [
                'to' => $toEmail,
            ]);

            return [
                'ok' => false,
                'status' => null,
                'body' => 'Adresse email destinataire invalide.',
            ];
        }

        if ($templateId <= 0) {
            Log::error('Brevo config invalid: template id must be > 0.', [
                'templateId' => $templateId,
            ]);

            return [
                'ok' => false,
                'status' => null,
                'body' => 'Template Brevo invalide.',
            ];
        }

        $payload = [
            'sender' => [
                'email' => $senderEmail,
                'name'  => $senderName,
            ],
            'to' => [
                [
                    'email' => $toEmail,
                    'name'  => $toName,
                ],
            ],
            'templateId' => $templateId,
            'params' => $params,
        ];

        try {
            $resp = Http::timeout(20)
                ->acceptJson()
                ->withHeaders([
                    'api-key' => $apiKey,
                    'content-type' => 'application/json',
                ])
                ->post('https://api.brevo.com/v3/smtp/email', $payload);

            if ($resp->failed()) {
                Log::error('Brevo send failed', [
                    'status' => $resp->status(),
                    'body' => $resp->body(),
                    'to' => $toEmail,
                    'templateId' => $templateId,
                ]);

                return [
                    'ok' => false,
                    'status' => $resp->status(),
                    'body' => $resp->body(),
                ];
            }

            Log::info('Brevo send success', [
                'status' => $resp->status(),
                'to' => $toEmail,
                'templateId' => $templateId,
            ]);

            return [
                'ok' => true,
                'status' => $resp->status(),
                'body' => $resp->body(),
            ];
        } catch (\Throwable $e) {
            Log::error('Brevo exception', [
                'message' => $e->getMessage(),
                'to' => $toEmail,
                'templateId' => $templateId,
            ]);

            return [
                'ok' => false,
                'status' => null,
                'body' => $e->getMessage(),
            ];
        }
    }
}