<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Partner\PartnerDriverService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function __construct(private PartnerDriverService $service) {}

    public function index(Request $request)
    {
        /** @var User $partner */
        $partner = $request->user(); // auth:web

        $users = $this->service->listDrivers($partner);

        // Vue : resources/views/users/partner.blade.php
        return view('users.index', compact('users'));
    }

    public function store(Request $request)
    {
        /** @var User $partner */
        $partner = $request->user();

        $data = $request->validate([
            'nom'      => ['required', 'string', 'max:120'],
            'prenom'   => ['required', 'string', 'max:120'],
            'phone'    => ['required', 'string', 'max:40'],
            'email'    => ['nullable', 'email', 'max:190'],
            'ville'    => ['nullable', 'string', 'max:120'],
            'quartier' => ['nullable', 'string', 'max:120'],
            'photo'    => ['nullable', 'image', 'max:4096'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        // Normalisation téléphone (stockage propre)
        $data['phone'] = $this->normalizeCmPhone($data['phone']);

        // Contrôles doublons multi-formats (sans colonne DB)
        $this->guardDuplicatePhone($data['phone']);
        $this->guardDuplicateEmail($data['email'] ?? null);

        try {
            $this->service->createDriver($partner, $data);

            return redirect()
                ->route('users.index')
                ->with('status', 'Utilisateur créé avec succès.');
        } catch (UniqueConstraintViolationException $e) {
            throw ValidationException::withMessages($this->uniqueErrorToMessages($e));
        }
    }

    public function update(Request $request, $id)
    {
        /** @var User $partner */
        $partner = $request->user();

        $driverId = (int) $id;

        $data = $request->validate([
            'nom'      => ['required', 'string', 'max:120'],
            'prenom'   => ['required', 'string', 'max:120'],
            'phone'    => ['required', 'string', 'max:40'],
            'email'    => ['nullable', 'email', 'max:190'],
            'ville'    => ['nullable', 'string', 'max:120'],
            'quartier' => ['nullable', 'string', 'max:120'],
            'photo'    => ['nullable', 'image', 'max:4096'],
            // password optionnel en update
            'password' => ['nullable', 'string', 'min:6', 'confirmed'],
        ]);

        $data['phone'] = $this->normalizeCmPhone($data['phone']);

        // Contrôle doublon en excluant l’utilisateur actuel
        $this->guardDuplicatePhone($data['phone'], $driverId);
        $this->guardDuplicateEmail($data['email'] ?? null, $driverId);

        try {
            $this->service->updateDriver($partner, $driverId, $data);

            return redirect()
                ->route('users.index')
                ->with('status', 'Utilisateur mis à jour avec succès.');
        } catch (UniqueConstraintViolationException $e) {
            throw ValidationException::withMessages($this->uniqueErrorToMessages($e));
        }
    }

    public function destroy(Request $request, $id)
    {
        /** @var User $partner */
        $partner = $request->user();

        $this->service->deleteDriver($partner, (int) $id);

        return redirect()
            ->route('users.index')
            ->with('status', 'Utilisateur supprimé.');
    }

    /**
     * ========================
     * Normalisation CM robuste
     * ========================
     *
     * Supporte : +237690..., 237690..., 00237690..., 0690..., 690..., 096...
     * Stockage : +237 + 9 chiffres (ex: +237690111222)
     */
    private function normalizeCmPhone(string $input): string
    {
        $raw = trim($input);

        // garder uniquement chiffres + +
        $raw = preg_replace('/[^\d\+]/', '', $raw) ?? '';

        // 00xxxx => enlever 00
        if (str_starts_with($raw, '00')) {
            $raw = substr($raw, 2);
        }

        // +xxxx => enlever +
        if (str_starts_with($raw, '+')) {
            $raw = substr($raw, 1);
        }

        // si commence par 237 => enlever 237
        if (str_starts_with($raw, '237')) {
            $raw = substr($raw, 3);
        }

        // 0xxxxxxxxx (10 chiffres) => enlever 0
        if (strlen($raw) === 10 && str_starts_with($raw, '0')) {
            $raw = substr($raw, 1);
        }

        // à la fin : 9 chiffres
        if (!preg_match('/^\d{9}$/', $raw)) {
            throw ValidationException::withMessages([
                'phone' => ['Numéro invalide. Formats acceptés : 696..., 0696..., +237..., 237..., 00237...'],
            ]);
        }

        return '+237' . $raw;
    }

    /**
     * Variantes possibles en BD (sans colonne supplémentaire)
     */
    private function phoneCandidatesFromNormalized(string $normalizedPlus237): array
    {
        // normalizedPlus237 = +237XXXXXXXXX
        $digits9 = substr($normalizedPlus237, 4); // après +237

        return array_values(array_unique([
            '+237' . $digits9,
            '237' . $digits9,
            '00237' . $digits9,
            '0' . $digits9,
            $digits9,
        ]));
    }

    private function guardDuplicatePhone(string $normalizedPhone, ?int $ignoreUserId = null): void
    {
        $candidates = $this->phoneCandidatesFromNormalized($normalizedPhone);

        $q = User::query()->whereIn('phone', $candidates);
        if ($ignoreUserId) {
            $q->where('id', '!=', $ignoreUserId);
        }

        if ($q->exists()) {
            throw ValidationException::withMessages([
                'phone' => ['Ce numéro existe déjà (même si le format est différent).'],
            ]);
        }
    }

    private function guardDuplicateEmail(?string $email, ?int $ignoreUserId = null): void
    {
        if (!$email) return;

        $email = strtolower(trim($email));

        $q = User::query()->where('email', $email);
        if ($ignoreUserId) {
            $q->where('id', '!=', $ignoreUserId);
        }

        if ($q->exists()) {
            throw ValidationException::withMessages([
                'email' => ['Cet email existe déjà.'],
            ]);
        }
    }

    private function uniqueErrorToMessages(UniqueConstraintViolationException $e): array
    {
        $msg = $e->getMessage();

        if (str_contains($msg, 'users_phone_unique')) {
            return ['phone' => ['Ce numéro existe déjà.']];
        }

        if (str_contains($msg, 'users_email_unique')) {
            return ['email' => ['Cet email existe déjà.']];
        }

        return ['general' => ['Une contrainte d’unicité a été violée.']];
    }
}
