<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BrevoMailService
{
    /**
     * Envoi OTP reset via Template Brevo (ID 2).
     */
    public function sendResetOtp(string $toEmail, string $fullName, string $code): array
    {
        return $this->sendTemplate(
            toEmail: $toEmail,
            toName: $fullName,
            templateId: (int) config('services.brevo.template_reset_id', 2),
            params: [
                'FULLNAME' => $fullName,
                'RESET_CODE' => $code,
            ]
        );
    }

    /**
     * TEST rapide depuis Tinker :
     * app(BrevoMailService::class)->test('email@domaine.com');
     */
    public function test(string $toEmail): array
    {
        return $this->sendResetOtp($toEmail, 'Test Proxym', '123456');
    }

    /**
     * Méthode interne générique d'envoi via template.
     */
    private function sendTemplate(string $toEmail, string $toName, int $templateId, array $params): array
    {
        $key = config('services.brevo.key');

        if (!$key) {
            return [
                'ok' => false,
                'status' => null,
                'body' => 'BREVO_API_KEY manquante (config services.brevo.key vide).',
            ];
        }

        try {
            $resp = Http::withHeaders([
                'accept' => 'application/json',
                'api-key' => $key,
                'content-type' => 'application/json',
            ])->post('https://api.brevo.com/v3/smtp/email', [
                'sender' => [
                    'email' => config('services.brevo.sender_email'),
                    'name'  => config('services.brevo.sender_name', 'PROXYM TRACKING'),
                ],
                'to' => [
                    ['email' => $toEmail, 'name' => $toName],
                ],
                'templateId' => $templateId,
                'params' => $params,
            ]);

            if ($resp->failed()) {
                Log::error('Brevo send failed', ['status' => $resp->status(), 'body' => $resp->body()]);
                return ['ok' => false, 'status' => $resp->status(), 'body' => $resp->body()];
            }

            return ['ok' => true, 'status' => $resp->status(), 'body' => $resp->body()];
        } catch (\Throwable $e) {
            Log::error('Brevo exception: ' . $e->getMessage());
            return ['ok' => false, 'status' => null, 'body' => $e->getMessage()];
        }
    }
}
