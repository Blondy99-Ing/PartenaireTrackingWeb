<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\BrevoMailService;
use App\Services\TechsoftSmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class VerifyLoginController extends Controller
{
    private string $prefix = 'partner';

    public function sendForgotOtp(Request $request, BrevoMailService $mail, TechsoftSmsService $sms)
    {
        $rid = (string) Str::uuid();

        Log::info("[OTP-PARTNER][$rid] sendForgotOtp:start", [
            'ip' => $request->ip(),
            'ua' => substr((string) $request->userAgent(), 0, 120),
        ]);

        $request->validate([
            'login' => ['required', 'string', 'min:3'],
        ]);

        $raw = trim((string) $request->input('login'));
        [$channel, $normalized] = $this->detectChannelAndNormalize($raw);

        Log::info("[OTP-PARTNER][$rid] detect", [
            'raw' => $raw,
            'channel' => $channel,
            'normalized' => $normalized,
        ]);

        $rlKey = $this->rlKey('send', $request->ip(), $channel, $normalized);
        if (RateLimiter::tooManyAttempts($rlKey, (int) env('OTP_SEND_MAX_PER_MIN', 5))) {
            Log::warning("[OTP-PARTNER][$rid] rate_limited_send", ['key' => $rlKey]);
            return back()
                ->with('show_forgot', true)
                ->withErrors(['login' => 'Trop de tentatives. Réessaie dans 1 minute.'])
                ->withInput();
        }
        RateLimiter::hit($rlKey, 60);

        $user = $this->findUserByLogin($channel, $normalized);

        Log::info("[OTP-PARTNER][$rid] user_lookup", [
            'found' => (bool) $user,
            'user_id' => $user?->id,
        ]);

        if (!$user) {
            return back()
                ->with('show_forgot', true)
                ->withErrors(['login' => 'Compte introuvable. Vérifie votre email ou numéro.'])
                ->withInput();
        }

        $code = (string) random_int(100000, 999999);
        $ttlMinutes = (int) env('OTP_TTL_MINUTES', 10);

        $otpKey = $this->otpCacheKey($channel, $normalized);

        Cache::put($otpKey, [
            'hash'       => Hash::make($code),
            'channel'    => $channel,
            'normalized' => $normalized,
            'user_id'    => $user->id,
            'attempts'   => 0,
            'resends'    => 0,
            'expires_at' => now()->addMinutes($ttlMinutes)->timestamp,
        ], now()->addMinutes($ttlMinutes));

        Log::info("[OTP-PARTNER][$rid] cache_written", [
            'otpKey' => $otpKey,
            'ttlMin' => $ttlMinutes,
            'expires_at' => now()->addMinutes($ttlMinutes)->toDateTimeString(),
        ]);

        // sessions -> affichage modales
        $request->session()->put('show_forgot', true);
        $request->session()->put($this->sessKey('pwd_reset'), [
            'channel'    => $channel,
            'normalized' => $normalized,
            'masked_to'  => $this->maskDestination($channel, $normalized),
        ]);
        $request->session()->put($this->sessKey('pwd_reset_modal'), true); // => partner_pwd_reset_modal

        $sendOk = false;
        $sendError = null;
        $sendResp = null;

        try {
            if ($channel === 'email') {
                $fullName = trim(($user->prenom ?? '') . ' ' . ($user->nom ?? '')) ?: 'Utilisateur';
                Log::info("[OTP-PARTNER][$rid] send_email", ['to' => $normalized]);
                $mail->sendResetOtp($normalized, $fullName, $code);
                $sendOk = true;
            } else {
                Log::info("[OTP-PARTNER][$rid] send_sms", ['to' => $normalized]);
                $sendResp = $sms->sendOtp($normalized, $code, $ttlMinutes, 'reset');
                $sendOk = (bool)($sendResp['ok'] ?? false);
                $sendError = $sendOk ? null : ($sendResp['body'] ?? 'SMS failed');
                Log::info("[OTP-PARTNER][$rid] send_sms_response", ['resp' => $sendResp]);
            }
        } catch (\Throwable $e) {
            $sendError = $e->getMessage();
            Log::error("[OTP-PARTNER][$rid] send_exception", ['error' => $sendError]);
        }

        if (!$sendOk) {
            $request->session()->put($this->sessKey('pwd_reset_modal'), true);
            return back()
                ->with('show_forgot', true)
                ->withErrors(['login' => "Impossible d’envoyer le code. {$sendError}"])
                ->withInput();
        }

        return back()
            ->with('show_forgot', true)
            ->with($this->sessKey('pwd_reset_modal'), true) // force ouverture OTP
            ->with('status', 'Code envoyé. Vérifiez vos messages.')
            ->withInput();
    }

    public function resendForgotOtp(Request $request, BrevoMailService $mail, TechsoftSmsService $sms)
    {
        $rid = (string) Str::uuid();
        Log::info("[OTP-PARTNER][$rid] resendForgotOtp:start", ['ip' => $request->ip()]);

        $sess = $request->session()->get($this->sessKey('pwd_reset'));

        Log::info("[OTP-PARTNER][$rid] session_pwd_reset", ['pwd_reset' => $sess]);

        if (!$sess || empty($sess['channel']) || empty($sess['normalized'])) {
            return back()
                ->with('show_forgot', true)
                ->withErrors(['login' => 'Veuillez saisir votre email/téléphone.']);
        }

        $channel = $sess['channel'];
        $normalized = $sess['normalized'];

        $rlKey = $this->rlKey('resend', $request->ip(), $channel, $normalized);
        if (RateLimiter::tooManyAttempts($rlKey, (int) env('OTP_RESEND_MAX_PER_MIN', 5))) {
            Log::warning("[OTP-PARTNER][$rid] rate_limited_resend", ['key' => $rlKey]);
            return back()
                ->with('show_forgot', true)
                ->with($this->sessKey('pwd_reset_modal'), true)
                ->withErrors(['otp_code' => 'Trop de renvois. Réessaie dans 1 minute.']);
        }
        RateLimiter::hit($rlKey, 60);

        $otpKey = $this->otpCacheKey($channel, $normalized);
        $data = Cache::get($otpKey);

        Log::info("[OTP-PARTNER][$rid] cache_read", [
            'otpKey' => $otpKey,
            'hasData' => (bool)$data,
            'expires_at' => $data['expires_at'] ?? null,
            'resends' => $data['resends'] ?? null,
            'attempts' => $data['attempts'] ?? null,
        ]);

        if (!$data || now()->timestamp > (int)($data['expires_at'] ?? 0)) {
            return back()
                ->with('show_forgot', true)
                ->with($this->sessKey('pwd_reset_modal'), true)
                ->withErrors(['otp_code' => 'Code expiré. Cliquez sur “Envoyer le code”.']);
        }

        $maxResends = (int) env('OTP_MAX_RESENDS', 3);
        if ((int)($data['resends'] ?? 0) >= $maxResends) {
            return back()
                ->with('show_forgot', true)
                ->with($this->sessKey('pwd_reset_modal'), true)
                ->withErrors(['otp_code' => "Limite de renvoi atteinte ({$maxResends})."]);
        }

        $user = !empty($data['user_id']) ? User::find($data['user_id']) : null;

        if (!$user) {
            return back()
                ->with('show_forgot', true)
                ->with($this->sessKey('pwd_reset_modal'), true)
                ->withErrors(['otp_code' => 'Session invalide. Recommencez.']);
        }

        $code = (string) random_int(100000, 999999);
        $ttlMinutes = (int) env('OTP_TTL_MINUTES', 10);

        $data['hash'] = Hash::make($code);
        $data['resends'] = ((int)($data['resends'] ?? 0)) + 1;
        $data['attempts'] = 0;
        $data['expires_at'] = now()->addMinutes($ttlMinutes)->timestamp;

        Cache::put($otpKey, $data, now()->addMinutes($ttlMinutes));

        $sendOk = false;
        $sendError = null;
        $sendResp = null;

        try {
            if ($channel === 'email') {
                $fullName = trim(($user->prenom ?? '') . ' ' . ($user->nom ?? '')) ?: 'Utilisateur';
                $mail->sendResetOtp($normalized, $fullName, $code);
                $sendOk = true;
            } else {
                $sendResp = $sms->sendOtp($normalized, $code, $ttlMinutes, 'reset');
                $sendOk = (bool)($sendResp['ok'] ?? false);
                $sendError = $sendOk ? null : ($sendResp['body'] ?? 'SMS failed');
                Log::info("[OTP-PARTNER][$rid] resend_sms_response", ['resp' => $sendResp]);
            }
        } catch (\Throwable $e) {
            $sendError = $e->getMessage();
        }

        if (!$sendOk) {
            return back()
                ->with('show_forgot', true)
                ->with($this->sessKey('pwd_reset_modal'), true)
                ->withErrors(['otp_code' => "Impossible de renvoyer le code. {$sendError}"]);
        }

        return back()
            ->with('show_forgot', true)
            ->with($this->sessKey('pwd_reset_modal'), true)
            ->with('status', 'Code renvoyé.');
    }

    public function verifyForgotOtp(Request $request)
    {
        $rid = (string) Str::uuid();
        Log::info("[OTP-PARTNER][$rid] verifyForgotOtp:start", ['ip' => $request->ip()]);

        $request->validate([
            'otp_code' => ['required', 'digits:6'],
        ]);

        $sess = $request->session()->get($this->sessKey('pwd_reset'));

        if (!$sess || empty($sess['channel']) || empty($sess['normalized'])) {
            return back()
                ->with('show_forgot', true)
                ->with($this->sessKey('pwd_reset_modal'), true)
                ->withErrors(['otp_code' => 'Session expirée. Recommencez.']);
        }

        $channel = $sess['channel'];
        $normalized = $sess['normalized'];

        $rlKey = $this->rlKey('verify', $request->ip(), $channel, $normalized);
        if (RateLimiter::tooManyAttempts($rlKey, (int) env('OTP_VERIFY_MAX_PER_MIN', 10))) {
            return back()
                ->with('show_forgot', true)
                ->with($this->sessKey('pwd_reset_modal'), true)
                ->withErrors(['otp_code' => 'Trop de tentatives. Réessaie dans 1 minute.']);
        }
        RateLimiter::hit($rlKey, 60);

        $otpKey = $this->otpCacheKey($channel, $normalized);
        $data = Cache::get($otpKey);

        if (!$data || now()->timestamp > (int)($data['expires_at'] ?? 0)) {
            return back()
                ->with('show_forgot', true)
                ->with($this->sessKey('pwd_reset_modal'), true)
                ->withErrors(['otp_code' => 'Code expiré. Cliquez sur “Renvoyer le code”.']);
        }

        $maxAttempts = (int) env('OTP_MAX_ATTEMPTS', 5);
        if ((int)($data['attempts'] ?? 0) >= $maxAttempts) {
            return back()
                ->with('show_forgot', true)
                ->with($this->sessKey('pwd_reset_modal'), true)
                ->withErrors(['otp_code' => "Trop d’essais ({$maxAttempts}). Renvoyez un nouveau code."]);
        }

        $code = (string) $request->input('otp_code');

        if (!Hash::check($code, (string)($data['hash'] ?? ''))) {
            $data['attempts'] = ((int)($data['attempts'] ?? 0)) + 1;
            Cache::put($otpKey, $data, now()->addMinutes((int) env('OTP_TTL_MINUTES', 10)));

            return back()
                ->with('show_forgot', true)
                ->with($this->sessKey('pwd_reset_modal'), true)
                ->withErrors(['otp_code' => 'Code invalide.']);
        }

        $userId = $data['user_id'] ?? null;
        if (!$userId) {
            return back()
                ->with('show_forgot', true)
                ->with($this->sessKey('pwd_reset_modal'), true)
                ->withErrors(['otp_code' => 'Code invalide ou expiré.']);
        }

        $resetToken = Str::random(64);
        $resetTtl = (int) env('RESET_TOKEN_TTL_MINUTES', 15);

        Cache::put($this->resetTokenCacheKey($resetToken), ['user_id' => $userId], now()->addMinutes($resetTtl));

        Cache::forget($otpKey);
        $request->session()->forget([$this->sessKey('pwd_reset_modal'), $this->sessKey('pwd_reset'), 'show_forgot']);

        return redirect()->route('partner.otp.password.reset', ['token' => $resetToken]);
    }

    public function showResetForm(string $token)
    {
        $data = Cache::get($this->resetTokenCacheKey($token));
        abort_if(!$data, 404);

        // ✅ vue partenaire
        return view('auth.otp-reset-password', ['token' => $token]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $token = (string) $request->input('token');
        $data = Cache::get($this->resetTokenCacheKey($token));

        if (!$data) {
            return redirect()
                ->route('login')
                ->with('status', 'Jeton expiré. Recommencez la procédure.');
        }

        $user = User::find($data['user_id'] ?? null);
        if (!$user) {
            return redirect()
                ->route('login')
                ->with('status', 'Compte introuvable.');
        }

        $user->password = $request->input('password');
        $user->save();

        Cache::forget($this->resetTokenCacheKey($token));

        return redirect()
            ->route('login')
            ->with('status', 'Mot de passe mis à jour. Vous pouvez vous connecter.');
    }

    /* ========================= Helpers ========================= */

    private function detectChannelAndNormalize(string $raw): array
    {
        if (filter_var($raw, FILTER_VALIDATE_EMAIL)) {
            return ['email', mb_strtolower($raw)];
        }

        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if (str_starts_with($digits, '00237')) {
            $digits = substr($digits, 2);
        }
        if (strlen($digits) === 10 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }
        if (strlen($digits) === 9 && str_starts_with($digits, '6')) {
            $digits = '237' . $digits;
        }

        return ['sms', $digits];
    }

    private function findUserByLogin(string $channel, string $normalized): ?User
    {
        if ($channel === 'email') {
            return User::where('email', $normalized)->first();
        }

        $digits = preg_replace('/\D+/', '', $normalized) ?? $normalized;

        $variants = [
            $digits,           // 237696...
            '+' . $digits,     // +237696...
        ];

        if (str_starts_with($digits, '237') && strlen($digits) === 12) {
            $variants[] = substr($digits, 3);       // 696...
            $variants[] = '0' . substr($digits, 3); // 0696...
        }

        return User::whereIn('phone', $variants)->first();
    }

    private function maskDestination(string $channel, string $normalized): string
    {
        if ($channel === 'email') {
            [$u, $d] = array_pad(explode('@', $normalized, 2), 2, '');
            $uMask = mb_substr($u, 0, 1) . '***';
            $dotPos = mb_strrpos($d, '.');
            $ext = $dotPos !== false ? mb_substr($d, $dotPos) : '';
            $dMask = mb_substr($d, 0, 1) . '***' . $ext;
            return $uMask . '@' . $dMask;
        }

        $digits = preg_replace('/\D+/', '', $normalized) ?? $normalized;
        return '+237 ***' . mb_substr($digits, -3);
    }

    private function otpCacheKey(string $channel, string $normalized): string
    {
        return "{$this->prefix}_pwd_reset_otp:" . sha1($channel . '|' . $normalized);
    }

    private function resetTokenCacheKey(string $token): string
    {
        return "{$this->prefix}_pwd_reset_token:" . $token;
    }

    private function rlKey(string $action, string $ip, string $channel, string $normalized): string
    {
        return "{$this->prefix}_pwdotp:{$action}:{$ip}:" . sha1($channel . '|' . $normalized);
    }

    private function sessKey(string $key): string
    {
        return "{$this->prefix}_{$key}";
    }
}
