<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PasswordOtpController extends Controller
{
    private int $otpTtlMinutes = 10;
    private int $resetTtlMinutes = 15;

    /**
     * 1) Demander / renvoyer un OTP (email ou phone)
     * Route: POST /password/otp/request
     * Body: { identifier: "email@..." } ou { identifier: "+2376..." }
     */
    public function requestOtp(Request $request, SmsService $smsService)
    {
        $identifier = $this->extractIdentifier($request);

        if (!$identifier) {
            return response()->json([
                'success' => false,
                'message' => "Veuillez fournir un email ou un numéro de téléphone."
            ], 422);
        }

        // =========================
        // CASE 1: EMAIL
        // =========================
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            $email = strtolower(trim($identifier));

            $user = User::where('email', $email)->first();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => "Cet email n'existe pas."
                ], 422);
            }

            $otp = (string) random_int(100000, 999999);

            $otpKey = "pwd_otp:email:" . $email;
            Cache::put($otpKey, [
                'user_id'   => $user->id,
                'otp_hash'  => Hash::make($otp),
            ], now()->addMinutes($this->otpTtlMinutes));

            try {
                Mail::raw(
                    "Votre code de réinitialisation est : {$otp}\nValable {$this->otpTtlMinutes} minutes.",
                    function ($m) use ($email) {
                        $m->to($email)->subject('Code de réinitialisation');
                    }
                );
            } catch (\Throwable $e) {
                Log::error('OTP email send failed', [
                    'email' => $email,
                    'error' => $e->getMessage(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => "Impossible d'envoyer l'email pour le moment."
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => "Code envoyé par email."
            ]);
        }

        // =========================
        // CASE 2: PHONE (CM) -> compare last 9 digits
        // =========================
        $last9 = $this->cmLast9($identifier);

        if (!$last9) {
            return response()->json([
                'success' => false,
                'message' => "Numéro invalide. Entrez un numéro camerounais (9 chiffres)."
            ], 422);
        }

        $user = $this->findUserByPhoneLast9($last9);
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => "Ce numéro n'existe pas."
            ], 422);
        }

        $otp = (string) random_int(100000, 999999);

        $otpKey = "pwd_otp:phone:" . $last9;
        Cache::put($otpKey, [
            'user_id'   => $user->id,
            'otp_hash'  => Hash::make($otp),
        ], now()->addMinutes($this->otpTtlMinutes));

        // Envoi SMS: "237" + last9 (sans "+")
        $recipient = $this->cmTo237($last9);

        $send = $smsService->send(
            $recipient,
            "Votre code de réinitialisation est : {$otp}. Valable {$this->otpTtlMinutes} min."
        );

        // LOG
        Log::info('OTP SMS send attempt', [
            'input_identifier' => $identifier,
            'last9'            => $last9,
            'recipient'        => $recipient,
            'sms_result'       => $send,
        ]);

        // Vérifier aussi le status du provider (car HTTP 200 peut contenir status=error)
        $providerStatus = is_array($send['data'] ?? null) ? ($send['data']['status'] ?? null) : null;
        $providerMsg    = is_array($send['data'] ?? null) ? ($send['data']['message'] ?? null) : null;

        if (($send['ok'] ?? false) === false || $providerStatus === 'error') {
            return response()->json([
                'success' => false,
                'message' => $providerMsg ?: "Échec d'envoi SMS. Veuillez réessayer."
            ], 422);
        }

        return response()->json([
        'success' => true,
        'message' => "Code envoyé par SMS.",
        ]);

    }

    /**
     * 2) Vérifier OTP -> retourne reset_token
     * Route: POST /password/otp/verify
     * Body: { identifier, otp }
     */
    public function verifyOtp(Request $request)
    {
        $identifier = $this->extractIdentifier($request);
        $otp = (string) ($request->input('otp') ?? '');

        if (!$identifier || $otp === '') {
            return response()->json([
                'success' => false,
                'message' => "Veuillez fournir identifier et otp."
            ], 422);
        }

        // EMAIL
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            $email = strtolower(trim($identifier));

            $otpKey = "pwd_otp:email:" . $email;
            $data = Cache::get($otpKey);

            if (!$data || !Hash::check($otp, $data['otp_hash'])) {
                return response()->json([
                    'success' => false,
                    'message' => "Code invalide ou expiré."
                ], 422);
            }

            Cache::forget($otpKey);

            $resetToken = Str::random(64);
            Cache::put("pwd_reset:" . $resetToken, [
                'user_id' => $data['user_id']
            ], now()->addMinutes($this->resetTtlMinutes));

            return response()->json([
                'success' => true,
                'reset_token' => $resetToken,
                'expires_in_minutes' => $this->resetTtlMinutes
            ]);
        }

        // PHONE
        $last9 = $this->cmLast9($identifier);
        if (!$last9) {
            return response()->json([
                'success' => false,
                'message' => "Numéro invalide."
            ], 422);
        }

        $otpKey = "pwd_otp:phone:" . $last9;
        $data = Cache::get($otpKey);

        if (!$data || !Hash::check($otp, $data['otp_hash'])) {
            return response()->json([
                'success' => false,
                'message' => "Code invalide ou expiré."
            ], 422);
        }

        Cache::forget($otpKey);

        $resetToken = Str::random(64);
        Cache::put("pwd_reset:" . $resetToken, [
            'user_id' => $data['user_id']
        ], now()->addMinutes($this->resetTtlMinutes));

        return response()->json([
            'success' => true,
            'reset_token' => $resetToken,
            'expires_in_minutes' => $this->resetTtlMinutes
        ]);
    }

    /**
     * 3) Reset password
     * Route: POST /password/otp/reset
     * Body: { reset_token, password, password_confirmation }
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'reset_token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $resetToken = $request->input('reset_token');
        $data = Cache::get("pwd_reset:" . $resetToken);

        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => "reset_token invalide ou expiré."
            ], 422);
        }

        $user = User::find($data['user_id']);
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => "Utilisateur introuvable."
            ], 404);
        }

        $user->password = Hash::make($request->input('password'));
        $user->setRememberToken(Str::random(60));
        $user->save();

        Cache::forget("pwd_reset:" . $resetToken);

        return response()->json([
            'success' => true,
            'message' => "Mot de passe réinitialisé."
        ]);
    }

    // =========================
    // Helpers
    // =========================

    private function extractIdentifier(Request $request): ?string
    {
        $identifier = $request->input('identifier')
            ?? $request->input('email')
            ?? $request->input('phone')
            ?? $request->input('emailOrPhone')
            ?? $request->input('login');

        $identifier = is_string($identifier) ? trim($identifier) : null;
        return $identifier ?: null;
    }

    /**
     * Retourne les 9 derniers chiffres (CM), en retirant +237/237 si présent.
     * "+237 696098576" => "696098576"
     * "237696098576"   => "696098576"
     * "696098576"      => "696098576"
     */
    private function cmLast9(string $input): ?string
    {
        $digits = preg_replace('/\D+/', '', $input) ?? '';
        if ($digits === '') return null;

        if (str_starts_with($digits, '237')) {
            $digits = substr($digits, 3);
        }

        if (strlen($digits) > 9) {
            $digits = substr($digits, -9);
        }

        return (strlen($digits) === 9) ? $digits : null;
    }

    private function cmTo237(string $last9): string
    {
        return '237' . $last9;
    }

    /**
     * Trouve un user si une de ses colonnes tel contient les mêmes 9 derniers chiffres
     * (même si DB stocke +237/237/espaces).
     */
    private function findUserByPhoneLast9(string $last9): ?User
    {
        // Ajoute ici ta vraie colonne si nécessaire
        $phoneColumns = ['phone', 'telephone', 'tel', 'mobile'];

        foreach ($phoneColumns as $col) {
            if (!Schema::hasColumn('users', $col)) continue;

            $candidates = User::where($col, 'like', '%' . $last9)->limit(50)->get();

            foreach ($candidates as $u) {
                $dbLast9 = $this->cmLast9((string) $u->{$col});
                if ($dbLast9 === $last9) {
                    return $u;
                }
            }
        }

        return null;
    }
}