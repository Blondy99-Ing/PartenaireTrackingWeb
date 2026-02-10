<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TechsoftSmsService
{
    /**
     * SMS générique réutilisable partout
     */
    public function sendSms(string $recipientAnyFormat, string $message, array $options = []): array
    {
        $url = 'https://app.techsoft-sms.com/api/http/sms/send';

        $token = config('services.techsoft_sms.token');
        $senderId = $options['sender_id'] ?? config('services.techsoft_sms.sender_id', 'PROXYM');
        $timeout = (int) ($options['timeout'] ?? 20);

        // ✅ Toujours normaliser en 2376XXXXXXXX
        $recipient237 = $this->normalizeCameroonPhoneTo237($recipientAnyFormat);
        if (!$recipient237) {
            return ['ok' => false, 'status' => null, 'body' => "Numéro invalide: {$recipientAnyFormat}", 'json' => null];
        }

        if (!$token) {
            return ['ok' => false, 'status' => null, 'body' => "Token Techsoft manquant", 'json' => null];
        }

        // ✅ Le plus fiable: plain + message ASCII (pas d'accents)
        $type = $options['type'] ?? 'plain';

        if ($type === 'plain') {
            // enlève accents/char spéciaux (réinitialisation -> reinitialisation)
            $message = Str::ascii($message);
        }

        Log::info('Techsoft SMS request', [
            'to' => $recipient237,
            'from' => $senderId,
            'type' => $type,
            'len' => strlen($message),
        ]);

        try {
            $resp = Http::timeout($timeout)->post($url, [
                'api_token' => $token,
                'recipient' => $recipient237,
                'sender_id' => $senderId,
                'type'      => $type,
                'message'   => $message,
            ]);

            $json = null;
            try { $json = $resp->json(); } catch (\Throwable $e) {}

            Log::info('Techsoft SMS response', [
                'status' => $resp->status(),
                'body' => $resp->body(),
            ]);

            return [
                'ok' => $resp->successful(),
                'status' => $resp->status(),
                'body' => $resp->body(),
                'json' => $json,
            ];
        } catch (\Throwable $e) {
            Log::error('Techsoft SMS exception', ['message' => $e->getMessage(), 'to' => $recipient237]);

            return [
                'ok' => false,
                'status' => null,
                'body' => $e->getMessage(),
                'json' => null,
            ];
        }
    }

    /**
     * Helper OTP (mais basé sur sendSms générique)
     */
    public function sendOtp(string $recipientAnyFormat, string $otpCode, int $ttlMinutes = 10, string $context = 'reset'): array
    {
       

        // ✅ message volontairement ASCII
        $msg = "PROXYM TRACKING {$otpCode}. Valable {$ttlMinutes} min.";

        // on force plain (et sendSms enlève accents si besoin)
        return $this->sendSms($recipientAnyFormat, $msg, ['type' => 'plain']);
    }

    public function test(string $recipientAnyFormat): array
    {
        return $this->sendSms($recipientAnyFormat, 'TEST PROXYM TRACKING - 342678', ['type' => 'plain']);
    }

    /**
     * Accepte: 696..., 0696..., 237696..., +237696..., 00237696...
     * Retourne: 2376XXXXXXXX (digits-only)
     */
    private function normalizeCameroonPhoneTo237(string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if ($digits === '') return null;

        if (str_starts_with($digits, '00237')) $digits = substr($digits, 2); // 00237 -> 237
        if (strlen($digits) === 10 && str_starts_with($digits, '0')) $digits = substr($digits, 1); // 0696 -> 696
        if (strlen($digits) === 9 && str_starts_with($digits, '6')) $digits = '237' . $digits; // 696 -> 237696

        return preg_match('/^2376\d{8}$/', $digits) ? $digits : null;
    }
}
