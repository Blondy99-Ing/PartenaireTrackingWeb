<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Partner\PartnerDriverService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Throwable;

class PartnerDriverController extends Controller
{
    public function __construct(
        private readonly PartnerDriverService $driverService
    ) {}

    /**
     * Page liste des chauffeurs du partenaire connecté.
     */
    public function index(Request $request)
    {
        try {
            $partner = $request->user();

            if (! $partner) {
                return redirect()
                    ->route('login')
                    ->withErrors([
                        'general' => 'Veuillez vous connecter pour accéder à vos chauffeurs.',
                    ]);
            }

            $users = $this->driverService->listDrivers($partner);

            return view('partner.drivers.index', compact('users'));
        } catch (Throwable $e) {
            Log::error('[PARTNER_DRIVER_INDEX_FAILED]', [
                'partner_id' => optional($request->user())->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'general' => app()->environment('local')
                    ? $e->getMessage()
                    : 'Impossible de récupérer la liste des chauffeurs.',
            ]);
        }
    }

    /**
     * Création atomique d’un chauffeur.
     */
    public function store(Request $request)
    {
        $partner = $request->user();

        if (! $partner) {
            return redirect()
                ->route('login')
                ->withErrors([
                    'general' => 'Veuillez vous connecter pour créer un chauffeur.',
                ]);
        }

        $data = $request->validate([
            'nom' => ['required', 'string', 'max:120'],
            'prenom' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:190'],
            'ville' => ['nullable', 'string', 'max:120'],
            'quartier' => ['nullable', 'string', 'max:120'],
            'photo' => ['nullable', 'image', 'max:4096'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        try {
            $this->driverService->createDriver($partner, $data);

            return redirect()
                ->route('partner.drivers.index')
                ->with('status', 'Chauffeur créé avec succès.');
        } catch (ConflictHttpException $e) {
            return back()
                ->withInput($request->except(['password', 'password_confirmation']))
                ->withErrors([
                    'general' => $e->getMessage(),
                ]);
        } catch (UniqueConstraintViolationException $e) {
            throw ValidationException::withMessages(
                $this->uniqueErrorToMessages($e)
            );
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('[PARTNER_DRIVER_CREATE_FAILED]', [
                'partner_id' => $partner->id,
                'payload' => $this->safePayload($request),
                'error' => $e->getMessage(),
            ]);

            return back()
                ->withInput($request->except(['password', 'password_confirmation']))
                ->withErrors([
                    'general' => app()->environment('local')
                        ? $e->getMessage()
                        : 'Création chauffeur impossible. Aucun chauffeur local n’a été conservé.',
                ]);
        }
    }

    /**
     * Affichage simple d’un chauffeur.
     * Utile si tu veux plus tard une page détail.
     */
    public function show(Request $request, int $id)
    {
        $partner = $request->user();

        if (! $partner) {
            return redirect()->route('login');
        }

        try {
            $driver = $this->findDriverOfPartner($partner, $id);

            return view('partner.drivers.show', compact('driver'));
        } catch (Throwable $e) {
            Log::error('[PARTNER_DRIVER_SHOW_FAILED]', [
                'partner_id' => $partner->id,
                'driver_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->route('partner.drivers.index')
                ->withErrors([
                    'general' => 'Chauffeur introuvable.',
                ]);
        }
    }

    /**
     * Mise à jour d’un chauffeur.
     */
    public function update(Request $request, int $id)
    {
        $partner = $request->user();

        if (! $partner) {
            return redirect()
                ->route('login')
                ->withErrors([
                    'general' => 'Veuillez vous connecter pour modifier un chauffeur.',
                ]);
        }

        $data = $request->validate([
            'nom' => ['required', 'string', 'max:120'],
            'prenom' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:190'],
            'ville' => ['nullable', 'string', 'max:120'],
            'quartier' => ['nullable', 'string', 'max:120'],
            'photo' => ['nullable', 'image', 'max:4096'],
            'password' => ['nullable', 'string', 'min:6', 'confirmed'],
        ]);

        try {
            $this->driverService->updateDriver($partner, $id, $data);

            return redirect()
                ->route('partner.drivers.index')
                ->with('status', 'Chauffeur mis à jour avec succès.');
        } catch (ConflictHttpException $e) {
            return back()
                ->withInput($request->except(['password', 'password_confirmation']))
                ->withErrors([
                    'general' => $e->getMessage(),
                ]);
        } catch (UniqueConstraintViolationException $e) {
            throw ValidationException::withMessages(
                $this->uniqueErrorToMessages($e)
            );
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('[PARTNER_DRIVER_UPDATE_FAILED]', [
                'partner_id' => $partner->id,
                'driver_id' => $id,
                'payload' => $this->safePayload($request),
                'error' => $e->getMessage(),
            ]);

            return back()
                ->withInput($request->except(['password', 'password_confirmation']))
                ->withErrors([
                    'general' => app()->environment('local')
                        ? $e->getMessage()
                        : 'Impossible de mettre à jour ce chauffeur.',
                ]);
        }
    }

    /**
     * Suppression locale du chauffeur.
     */
    public function destroy(Request $request, int $id)
    {
        $partner = $request->user();

        if (! $partner) {
            return redirect()
                ->route('login')
                ->withErrors([
                    'general' => 'Veuillez vous connecter pour supprimer un chauffeur.',
                ]);
        }

        try {
            $this->driverService->deleteDriver($partner, $id);

            return redirect()
                ->route('partner.drivers.index')
                ->with('status', 'Chauffeur supprimé avec succès.');
        } catch (Throwable $e) {
            Log::error('[PARTNER_DRIVER_DELETE_FAILED]', [
                'partner_id' => $partner->id,
                'driver_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'general' => app()->environment('local')
                    ? $e->getMessage()
                    : 'Impossible de supprimer ce chauffeur.',
            ]);
        }
    }

    private function findDriverOfPartner(User $partner, int $driverId): User
    {
        return User::query()
            ->with('role')
            ->where('id', $driverId)
            ->where('partner_id', $partner->id)
            ->whereHas('role', function ($query) {
                $query->where('slug', 'utilisateur_secondaire');
            })
            ->firstOrFail();
    }

    private function safePayload(Request $request): array
    {
        return $request->except([
            'password',
            'password_confirmation',
            'photo',
            '_token',
            '_method',
        ]);
    }

    private function uniqueErrorToMessages(UniqueConstraintViolationException $e): array
    {
        $message = mb_strtolower($e->getMessage());

        if (str_contains($message, 'users_phone_unique') || str_contains($message, 'phone')) {
            return [
                'phone' => ['Ce numéro existe déjà.'],
            ];
        }

        if (str_contains($message, 'users_email_unique') || str_contains($message, 'email')) {
            return [
                'email' => ['Cet email existe déjà.'],
            ];
        }

        if (str_contains($message, 'keycloak_id')) {
            return [
                'general' => ['Ce compte Keycloak est déjà lié à un utilisateur local.'],
            ];
        }

        return [
            'general' => ['Une contrainte d’unicité a été violée.'],
        ];
    }
}